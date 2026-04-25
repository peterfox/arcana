<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Contract\SkillResourceLoaderInterface;
use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;

/**
 * Loads skill resource files using native PHP filesystem calls.
 *
 * This is the default resource loader. It enforces three security guards:
 *
 *   1. Reject absolute paths before any filesystem access.
 *   2. Reject path traversal sequences ('..').
 *   3. After realpath() resolution, assert the resolved path is still within
 *      the skill's own directory (guards against symlink escapes).
 */
final class NativeResourceLoader implements SkillResourceLoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(SkillResource $resource, string $skillDirectory): string
    {
        $rawRelative = $resource->path;

        // Guard 1 — reject absolute paths before any filesystem access.
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

        $resolvedBase = realpath($skillDirectory);

        if ($resolvedBase === false) {
            throw new SecurityException(
                "Cannot resolve skill directory: {$skillDirectory}",
            );
        }

        $rawPath = $resolvedBase . DIRECTORY_SEPARATOR . $rawRelative;
        $resolvedPath = realpath($rawPath);

        if ($resolvedPath === false) {
            throw new SkillParseException(
                message: 'Resource file not found.',
                filePath: $rawPath,
            );
        }

        // Guard 3 — final check after symlink resolution: the resolved path
        // must still be within the skill directory.
        if (!str_starts_with($resolvedPath . DIRECTORY_SEPARATOR, $resolvedBase . DIRECTORY_SEPARATOR)) {
            throw new SecurityException(
                "Path traversal detected: resource '{$resource->name}' resolved to '{$resolvedPath}', "
                . "which is outside the skill directory '{$resolvedBase}'.",
            );
        }

        $content = file_get_contents($resolvedPath);

        if ($content === false) {
            throw new SkillParseException(
                message: 'Resource file is not readable (check permissions).',
                filePath: $resolvedPath,
            );
        }

        return $content;
    }
}
