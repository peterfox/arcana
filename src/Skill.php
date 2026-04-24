<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillNotFoundException;

/**
 * Immutable value object representing a fully-loaded AI agent skill.
 *
 * Returned by {@see Contract\SkillLibraryInterface::loadSkill()}.
 * Contains both the lightweight metadata (frontmatter) and the full
 * Markdown body content.
 *
 * Resources bundled with the skill can be loaded on demand via
 * {@see self::loadResource()}, which enforces path-traversal protection.
 */
final class Skill
{
    public function __construct(
        /** Parsed frontmatter metadata. */
        public readonly SkillMetadata $metadata,
        /** Full Markdown body of the skill (everything after the closing ---). */
        public readonly string $body,
    ) {}

    /**
     * Returns the full skill content, optionally appending all resource files.
     *
     * When $includeResources is true and the skill declares resources,
     * each resource's content is appended with a heading separator.
     * Unreadable resources are skipped silently to avoid breaking
     * the agent loop.
     */
    public function fullContent(bool $includeResources = true): string
    {
        if (!$includeResources || $this->metadata->resources === []) {
            return $this->body;
        }

        $content = $this->body;

        foreach ($this->metadata->resources as $resource) {
            try {
                $resourceContent = $this->loadResource($resource->name);
                $content .= "\n\n---\n\n### Resource: {$resource->name}";

                if ($resource->description !== '') {
                    $content .= "\n\n_{$resource->description}_";
                }

                $content .= "\n\n{$resourceContent}";
            } catch (\Throwable) {
                // Non-fatal: skip unloadable resources
            }
        }

        return $content;
    }

    /**
     * Load the raw content of a named resource file bundled with this skill.
     *
     * Path traversal is strictly enforced: resource files must reside
     * within the skill's own directory.
     *
     * @throws \InvalidArgumentException When the resource name is unknown.
     * @throws SecurityException         When the resolved path escapes the skill directory.
     * @throws \RuntimeException         When the file cannot be read.
     */
    public function loadResource(string $name): string
    {
        $resource = null;

        foreach ($this->metadata->resources as $r) {
            if ($r->name === $name) {
                $resource = $r;
                break;
            }
        }

        if ($resource === null) {
            throw new \InvalidArgumentException(
                "Resource '{$name}' is not declared in skill '{$this->metadata->name}'."
            );
        }

        $rawRelative = $resource->path;

        // Guard 1 — reject absolute paths before any filesystem access.
        if ($rawRelative !== '' && ($rawRelative[0] === '/' || $rawRelative[0] === '\\')) {
            throw new SecurityException(
                "Absolute resource paths are not permitted. " .
                "Resource '{$name}' declared path: '{$rawRelative}'."
            );
        }

        // Guard 2 — reject explicit traversal sequences.
        if (str_contains($rawRelative, '..')) {
            throw new SecurityException(
                "Path traversal sequences ('..') are not permitted in resource paths. " .
                "Resource '{$name}' declared path: '{$rawRelative}'."
            );
        }

        $skillDir = $this->metadata->directory();
        $resolvedBase = realpath($skillDir);

        if ($resolvedBase === false) {
            throw new SecurityException(
                "Cannot resolve skill directory: {$skillDir}"
            );
        }

        $rawPath = $resolvedBase . DIRECTORY_SEPARATOR . $rawRelative;
        $resolvedPath = realpath($rawPath);

        if ($resolvedPath === false) {
            throw new \RuntimeException(
                "Resource file not found: {$rawPath}"
            );
        }

        // Guard 3 — final check after symlink resolution: the resolved path
        // must still be within the skill directory.
        if (!str_starts_with($resolvedPath . DIRECTORY_SEPARATOR, $resolvedBase . DIRECTORY_SEPARATOR)) {
            throw new SecurityException(
                "Path traversal detected: resource '{$name}' resolved to '{$resolvedPath}', " .
                "which is outside the skill directory '{$resolvedBase}'."
            );
        }

        $content = file_get_contents($resolvedPath);

        if ($content === false) {
            throw new \RuntimeException("Cannot read resource file: {$resolvedPath}");
        }

        return $content;
    }

    /**
     * Convenience accessor for the skill name.
     */
    public function name(): string
    {
        return $this->metadata->name;
    }
}
