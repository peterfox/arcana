<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Flysystem;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use PeterFox\Arcana\Cache\NullCache;
use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\Contract\SkillPreprocessorInterface;
use PeterFox\Arcana\Exception\ArcanaException;
use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\Exception\ValidationException;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillMetadata;
use PeterFox\Arcana\SkillParser;
use Psr\SimpleCache\CacheInterface;

/**
 * A SkillLibrary backed by a Flysystem filesystem.
 *
 * Discovers and serves AI agent skills from any filesystem supported by
 * League\Flysystem (local, S3, SFTP, in-memory, etc.).
 *
 * Usage:
 *
 *   $library = new FlysystemSkillLibrary(
 *       new Filesystem(new LocalFilesystemAdapter('/path/to/skills')),
 *   );
 */
final class FlysystemSkillLibrary implements SkillLibraryInterface
{
    /** @var array<string, SkillMetadata>|null In-memory metadata index: name → metadata. */
    private ?array $metadataIndex = null;

    /**
     * @param FilesystemOperator $filesystem Flysystem filesystem to read skills from.
     * @param CacheInterface $cache PSR-16 cache. Defaults to NullCache (no caching).
     * @param SkillPreprocessorInterface|null $preprocessor Optional preprocessor applied before caching.
     * @param int $cacheTtl Cache TTL in seconds (default: 1 hour).
     * @param string $cachePrefix Prefix for all cache keys.
     * @param SkillParser $parser Parser instance (injectable for testing).
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly CacheInterface $cache = new NullCache(),
        private readonly ?SkillPreprocessorInterface $preprocessor = null,
        private readonly int $cacheTtl = 3600,
        private readonly string $cachePrefix = 'arcana.',
        private readonly SkillParser $parser = new SkillParser(),
    ) {}

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

        try {
            $content = $this->filesystem->read($filePath);
        } catch (FilesystemException $e) {
            throw new SkillParseException(
                message: 'Failed to read skill file via Flysystem.',
                filePath: $filePath,
                previous: $e,
            );
        }

        $parsed = $this->parser->parse($content, $filePath);
        $skill = new Skill(
            metadata: $parsed->metadata,
            body: $parsed->body,
            resourceLoader: new FlysystemResourceLoader($this->filesystem),
        );

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

        return isset($this->buildMetadataIndex()[$name]);
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
     * @return array<string, SkillMetadata>
     */
    private function buildMetadataIndex(): array
    {
        if ($this->metadataIndex !== null) {
            return $this->metadataIndex;
        }

        $this->metadataIndex = [];

        foreach ($this->discoverSkillFiles() as $path) {
            try {
                $content = $this->filesystem->read($path);
                $metadata = $this->parser->parseMetadataOnlyFromContent($content, $path);

                // Later files do NOT override earlier ones; first wins.
                if (!isset($this->metadataIndex[$metadata->name])) {
                    $this->metadataIndex[$metadata->name] = $metadata;
                }
            } catch (ArcanaException) {
                // Skip malformed SKILL.md files; they should not prevent
                // valid skills from being discovered.
            } catch (FilesystemException) {
                // Skip unreadable files.
            }
        }

        return $this->metadataIndex;
    }

    /**
     * Discover all SKILL.md files in the filesystem (recursive).
     *
     * @return array<string> Flysystem paths.
     */
    private function discoverSkillFiles(): array
    {
        $paths = [];

        try {
            $listing = $this->filesystem->listContents('', true);

            /** @var StorageAttributes $item */
            foreach ($listing as $item) {
                if ($item->isFile() && basename($item->path()) === 'SKILL.md') {
                    $paths[] = $item->path();
                }
            }
        } catch (FilesystemException) {
            // Return empty on listing errors.
        }

        return $paths;
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

    private function skillCacheKey(string $name): string
    {
        return $this->cachePrefix . 'skill.' . $name;
    }
}
