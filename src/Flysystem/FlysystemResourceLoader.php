<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Flysystem;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use PeterFox\Arcana\Contract\SkillResourceLoaderInterface;
use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\SkillResource;

/**
 * Loads skill resource files via a Flysystem filesystem.
 *
 * Suitable for any adapter supported by League\Flysystem — local, S3, SFTP,
 * in-memory, etc. Path traversal is blocked via string-level guards before
 * any filesystem access; Flysystem's own path normalization provides an
 * additional layer.
 *
 * Note: Guard 3 (symlink escape via realpath) is not applied here because
 * Flysystem virtualises paths and does not expose symlink semantics.
 */
final class FlysystemResourceLoader implements SkillResourceLoaderInterface
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function load(SkillResource $resource, string $skillDirectory): string
    {
        $rawRelative = $resource->path;

        // Guard 1 — reject absolute paths.
        if ($rawRelative !== '' && ($rawRelative[0] === '/' || $rawRelative[0] === '\\')) {
            throw new SecurityException(
                'Absolute resource paths are not permitted. '
                . "Resource '{$resource->name}' declared path: '{$rawRelative}'.",
            );
        }

        // Guard 2 — reject explicit traversal sequences.
        if (str_contains($rawRelative, '..')) {
            throw new SecurityException(
                "Path traversal sequences ('..') are not permitted in resource paths. "
                . "Resource '{$resource->name}' declared path: '{$rawRelative}'.",
            );
        }

        $path = rtrim($skillDirectory, '/') . '/' . $rawRelative;

        try {
            return $this->filesystem->read($path);
        } catch (FilesystemException $e) {
            throw new SkillParseException(
                message: 'Resource file is not readable.',
                filePath: $path,
                previous: $e,
            );
        }
    }
}
