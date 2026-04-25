<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Cache\NullCache;
use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\Contract\SkillPreprocessorInterface;
use PeterFox\Arcana\Exception\ArcanaException;
use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\Exception\ValidationException;
use Psr\SimpleCache\CacheInterface;

/**
 * Discovers and serves AI agent skills from one or more directories.
 *
 * Skills are defined as SKILL.md files inside subdirectories:
 *
 *   skills/
 *     web-search/
 *       SKILL.md
 *       resources/
 *         overview.md
 *     code-review/
 *       SKILL.md
 *
 * Progressive disclosure is supported via two distinct operations:
 *   - listSkills()  returns lightweight metadata (no body content)
 *   - loadSkill()   loads full content on demand
 *
 * Both individual skills and the discovery index are cached via
 * the injected PSR-16 cache. An in-memory metadata index is also
 * maintained per-instance to avoid redundant parsing within a
 * single request.
 */
final class SkillLibrary implements SkillLibraryInterface
{
    /** @var array<string> Normalised, real filesystem paths. */
    private readonly array $directories;

    /** @var array<string, SkillMetadata>|null In-memory metadata index: name → metadata. */
    private ?array $metadataIndex = null;

    /**
     * @param string|array<string> $directories One or more directories to scan for SKILL.md files.
     * @param CacheInterface $cache PSR-16 cache. Defaults to NullCache (no caching).
     * @param SkillPreprocessorInterface|null $preprocessor Optional preprocessor applied before caching.
     * @param int $cacheTtl Cache TTL in seconds (default: 1 hour).
     * @param string $cachePrefix Prefix for all cache keys.
     * @param SkillParser $parser Parser instance (injectable for testing).
     *
     * @throws ValidationException When a supplied directory does not exist.
     */
    public function __construct(
        string|array $directories,
        private readonly CacheInterface $cache = new NullCache(),
        private readonly ?SkillPreprocessorInterface $preprocessor = null,
        private readonly int $cacheTtl = 3600,
        private readonly string $cachePrefix = 'arcana.',
        private readonly SkillParser $parser = new SkillParser(),
    ) {
        $dirs = is_array($directories) ? $directories : [$directories];

        $this->directories = array_values(
            array_map(fn(string $d) => $this->resolveDirectory($d), $dirs),
        );
    }

    // -------------------------------------------------------------------------
    // SkillLibraryInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function listSkills(?string $filter = null): array
    {
        $index = $this->buildMetadataIndex();
        $skills = array_values($index);

        if ($filter === null || $filter === '') {
            return $skills;
        }

        return $this->filterSkills($skills, strtolower($filter));
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function loadSkill(string $name): Skill
    {
        $this->assertValidName($name);

        $cacheKey = $this->skillCacheKey($name);
        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof Skill) {
            return $cached;
        }

        $index = $this->buildMetadataIndex();

        if (!isset($index[$name])) {
            throw new SkillNotFoundException($name);
        }

        $filePath = $index[$name]->filePath;
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new SkillParseException(
                message: 'File is not readable (check permissions).',
                filePath: $filePath,
            );
        }

        $skill = $this->parser->parse($content, $filePath);

        if ($this->preprocessor !== null) {
            $skill = $this->preprocessor->process($skill);
        }

        $this->cache->set($cacheKey, $skill, $this->cacheTtl);

        return $skill;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function hasSkill(string $name): bool
    {
        try {
            $this->assertValidName($name);
        } catch (ValidationException) {
            return false;
        }

        $index = $this->buildMetadataIndex();

        return isset($index[$name]);
    }

    // -------------------------------------------------------------------------
    // Public utilities
    // -------------------------------------------------------------------------

    /**
     * Flush all cached entries managed by this library instance.
     */
    public function flush(): void
    {
        $this->metadataIndex = null;
        $this->cache->clear();
    }

    /**
     * Returns the number of discovered skills without loading any body content.
     */
    public function count(): int
    {
        return count($this->buildMetadataIndex());
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build (or return the cached) in-memory metadata index.
     *
     * Scanning the filesystem is done only once per instance lifetime.
     * Individual metadata parsing results are NOT stored in the PSR-16 cache
     * because SkillMetadata objects contain a filePath that may be environment-
     * specific (e.g. Docker vs host paths).
     *
     * @return array<string, SkillMetadata>
     */
    private function buildMetadataIndex(): array
    {
        if ($this->metadataIndex !== null) {
            return $this->metadataIndex;
        }

        $this->metadataIndex = [];

        foreach ($this->directories as $dir) {
            foreach ($this->discoverSkillFiles($dir) as $filePath) {
                try {
                    $metadata = $this->parser->parseMetadataOnly($filePath);

                    // Later directories do NOT override earlier ones; first wins.
                    if (!isset($this->metadataIndex[$metadata->name])) {
                        $this->metadataIndex[$metadata->name] = $metadata;
                    }
                } catch (ArcanaException) {
                    // Skip malformed SKILL.md files; they should not prevent
                    // valid skills from being discovered.
                }
            }
        }

        return $this->metadataIndex;
    }

    /**
     * Discover all SKILL.md files under a given directory (recursive).
     *
     * @throws ValidationException
     *
     * @return array<string> Absolute file paths.
     */
    private function discoverSkillFiles(string $dir): array
    {
        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $dir,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS,
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getFilename() === 'SKILL.md' && $file->isReadable()) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\UnexpectedValueException $e) {
            throw new ValidationException(
                "Cannot scan directory '{$dir}': {$e->getMessage()}",
                previous: $e,
            );
        }

        return $files;
    }

    /**
     * @param array<SkillMetadata> $skills
     *
     * @return array<SkillMetadata>
     */
    private function filterSkills(array $skills, string $filter): array
    {
        return array_values(
            array_filter($skills, function (SkillMetadata $m) use ($filter): bool {
                if (str_contains(strtolower($m->name), $filter)) {
                    return true;
                }

                if (str_contains(strtolower($m->description), $filter)) {
                    return true;
                }

                foreach ($m->tags as $tag) {
                    if (str_contains(strtolower($tag), $filter)) {
                        return true;
                    }
                }

                foreach ($m->triggers as $trigger) {
                    if (str_contains(strtolower($trigger), $filter)) {
                        return true;
                    }
                }

                return false;
            }),
        );
    }

    /**
     * @throws ValidationException
     */
    private function assertValidName(string $name): void
    {
        if ($name === '' || strlen($name) > 64) {
            throw new ValidationException(
                'Skill name must be 1–64 characters, got ' . strlen($name) . '.',
            );
        }

        if (!preg_match('/^[a-z][a-z0-9\-]*$/', $name)) {
            throw new ValidationException(
                "Invalid skill name '{$name}'. Names must start with a lowercase letter "
                . 'and contain only lowercase letters (a–z), digits (0–9), and hyphens (-).',
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private function resolveDirectory(string $dir): string
    {
        $real = realpath($dir);

        if ($real === false || !is_dir($real)) {
            throw new ValidationException(
                "Skill directory not found or is not a directory: '{$dir}'.",
            );
        }

        return $real;
    }

    private function skillCacheKey(string $name): string
    {
        return $this->cachePrefix . 'skill.' . $name;
    }
}
