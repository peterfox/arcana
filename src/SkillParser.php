<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Exception\SkillParseException;
use Symfony\Component\Yaml\Exception\ParseException as YamlParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses SKILL.md files into typed value objects.
 *
 * A SKILL.md file consists of a YAML frontmatter block (delimited by ---)
 * followed by a Markdown body:
 *
 *   ---
 *   name: my-skill
 *   description: What this skill does
 *   version: 1.0.0
 *   ---
 *
 *   # Skill Title
 *   ...body content...
 *
 * The parser handles two modes:
 *   - Full parse:     {@see self::parse()}          → returns a Skill (metadata + body)
 *   - Metadata-only:  {@see self::parseMetadataOnly()} → returns SkillMetadata (no body I/O)
 */
final class SkillParser
{
    /**
     * Regex capturing the YAML block (group 1) and the body (group 2).
     * Handles both LF and CRLF line endings.
     */
    private const FRONTMATTER_PATTERN = '/^---[ \t]*\r?\n(.*?)\r?\n---[ \t]*\r?\n?(.*)/s';

    /** Default maximum SKILL.md file size: 1 MiB. */
    public const DEFAULT_MAX_FILE_SIZE = 1_048_576;

    /**
     * @param int $maxFileSizeBytes Maximum permitted SKILL.md file size in bytes.
     *                              Files exceeding this limit are rejected before parsing
     *                              to prevent memory exhaustion from maliciously large inputs.
     */
    public function __construct(
        private readonly int $maxFileSizeBytes = self::DEFAULT_MAX_FILE_SIZE,
    ) {}

    /**
     * Parse the full contents of a SKILL.md file.
     *
     * @param string $content Raw file contents.
     * @param string $filePath Absolute path to the source file (used for error context).
     *
     * @throws SkillParseException
     */
    public function parse(string $content, string $filePath): Skill
    {
        [$data, $body] = $this->splitContent($content, $filePath);
        $metadata = $this->buildMetadata($data, $filePath);

        return new Skill(
            metadata: $metadata,
            body: trim($body),
        );
    }

    /**
     * Parse only the YAML frontmatter from a file path (fast, low-memory).
     *
     * The body is never read into memory, making this suitable for building
     * a skill index over hundreds of files.
     *
     * @throws SkillParseException
     */
    public function parseMetadataOnly(string $filePath): SkillMetadata
    {
        return $this->parseMetadataOnlyFromContent($this->readFile($filePath), $filePath);
    }

    /**
     * Parse only the YAML frontmatter from already-loaded content.
     *
     * Use this when the content has already been fetched externally (e.g. via
     * Flysystem). Unlike {@see self::parseMetadataOnly()}, this method does not
     * perform any filesystem access.
     *
     * @throws SkillParseException
     */
    public function parseMetadataOnlyFromContent(string $content, string $filePath): SkillMetadata
    {
        [$data] = $this->splitContent($content, $filePath);

        return $this->buildMetadata($data, $filePath);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws SkillParseException
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitContent(string $content, string $filePath): array
    {
        if (!preg_match(self::FRONTMATTER_PATTERN, $content, $matches)) {
            throw new SkillParseException(
                message: 'Missing or invalid YAML frontmatter. Ensure the file starts with --- on its own line.',
                filePath: $filePath,
            );
        }

        try {
            $data = Yaml::parse($matches[1]);
        } catch (YamlParseException $e) {
            throw new SkillParseException(
                message: "Invalid YAML frontmatter: {$e->getMessage()}",
                filePath: $filePath,
                previous: $e,
            );
        }

        if (!is_array($data)) {
            throw new SkillParseException(
                message: 'YAML frontmatter must be a mapping (key: value pairs), not a scalar or sequence.',
                filePath: $filePath,
            );
        }

        return [$data, $matches[2]];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws SkillParseException
     */
    private function buildMetadata(array $data, string $filePath): SkillMetadata
    {
        $name = $this->requireString($data, 'name', $filePath);
        $description = $this->requireString($data, 'description', $filePath);

        $this->validateSkillName($name, $filePath);

        return new SkillMetadata(
            name: $name,
            description: $description,
            version: isset($data['version']) ? (string) $data['version'] : '1.0.0',
            author: isset($data['author']) ? (string) $data['author'] : null,
            tags: $this->parseStringArray($data, 'tags', $filePath),
            triggers: $this->parseStringArray($data, 'triggers', $filePath),
            resources: $this->parseResources($data['resources'] ?? [], $filePath),
            scripts: $this->parseScripts($data['scripts'] ?? [], $filePath),
            references: $this->parseReferences($data['references'] ?? [], $filePath),
            filePath: $filePath,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws SkillParseException
     */
    private function requireString(array $data, string $key, string $filePath): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            throw new SkillParseException(
                message: "Required field '{$key}' is missing or empty.",
                filePath: $filePath,
            );
        }

        return trim($data[$key]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws SkillParseException
     *
     * @return array<string>
     */
    private function parseStringArray(array $data, string $key, string $filePath): array
    {
        if (!isset($data[$key])) {
            return [];
        }

        if (!is_array($data[$key])) {
            throw new SkillParseException(
                message: "Field '{$key}' must be a YAML sequence (list), not a scalar.",
                filePath: $filePath,
            );
        }

        return array_values(
            array_filter(
                array_map(fn(mixed $v) => is_scalar($v) ? (string) $v : '', $data[$key]),
                fn(string $v) => $v !== '',
            ),
        );
    }

    /**
     * @param mixed $raw
     *
     * @throws SkillParseException
     *
     * @return array<SkillResource>
     */
    private function parseResources(mixed $raw, string $filePath): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $resources = [];

        foreach ($raw as $i => $item) {
            if (!is_array($item)) {
                continue;
            }

            $resources[] = new SkillResource(
                name: isset($item['name']) ? (string) $item['name'] : "resource-{$i}",
                description: isset($item['description']) ? (string) $item['description'] : '',
                path: isset($item['path']) ? (string) $item['path'] : '',
            );
        }

        return $resources;
    }

    /**
     * @param mixed $raw
     *
     * @throws SkillParseException
     *
     * @return array<SkillScript>
     */
    private function parseScripts(mixed $raw, string $filePath): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $scripts = [];

        foreach ($raw as $i => $item) {
            if (!is_array($item)) {
                continue;
            }

            $scripts[] = new SkillScript(
                name: isset($item['name']) ? (string) $item['name'] : "script-{$i}",
                description: isset($item['description']) ? (string) $item['description'] : '',
                path: isset($item['path']) ? (string) $item['path'] : '',
                language: isset($item['language']) ? strtolower((string) $item['language']) : 'php',
            );
        }

        return $scripts;
    }

    /**
     * @param mixed $raw
     *
     * @return array<SkillReference>
     */
    private function parseReferences(mixed $raw, string $filePath): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $references = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = isset($item['url']) ? (string) $item['url'] : '';

            // Only allow http and https schemes. Rejecting other schemes
            // (javascript:, data:, ftp:, file:, etc.) prevents XSS if a
            // consumer renders reference URLs in HTML without escaping.
            if ($url !== '' && !preg_match('/^https?:\/\//i', $url)) {
                throw new SkillParseException(
                    message: "Reference URL must use http or https scheme. Got: '{$url}'.",
                    filePath: $filePath,
                );
            }

            $references[] = new SkillReference(
                title: isset($item['title']) ? (string) $item['title'] : '',
                url: $url,
            );
        }

        return $references;
    }

    /**
     * @throws SkillParseException
     */
    private function validateSkillName(string $name, string $filePath): void
    {
        if ($name === '' || strlen($name) > 64) {
            throw new SkillParseException(
                message: 'Skill name must be 1–64 characters, got ' . strlen($name) . '.',
                filePath: $filePath,
            );
        }

        if (!preg_match('/^[a-z][a-z0-9\-]*$/', $name)) {
            throw new SkillParseException(
                message: "Invalid skill name '{$name}'. Names must start with a lowercase letter "
                         . 'and contain only lowercase letters (a–z), digits (0–9), and hyphens (-).',
                filePath: $filePath,
            );
        }
    }

    /**
     * @throws SkillParseException
     */
    private function readFile(string $filePath): string
    {
        if (!is_file($filePath)) {
            throw new SkillParseException(
                message: 'File not found.',
                filePath: $filePath,
            );
        }

        $size = filesize($filePath);

        if ($size !== false && $size > $this->maxFileSizeBytes) {
            throw new SkillParseException(
                message: sprintf(
                    'SKILL.md file is too large (%s bytes). Maximum permitted size is %s bytes. '
                    . 'This limit exists to prevent memory exhaustion from oversized skill files.',
                    number_format($size),
                    number_format($this->maxFileSizeBytes),
                ),
                filePath: $filePath,
            );
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new SkillParseException(
                message: 'File is not readable (check permissions).',
                filePath: $filePath,
            );
        }

        return $content;
    }
}
