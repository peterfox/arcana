<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Contract\SkillResourceLoaderInterface;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\Security\PathGuard;

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
    #[\Override]
    public function load(SkillResource $resource, string $skillDirectory): string
    {
        $resolvedPath = PathGuard::resolveContained(
            type: 'resource',
            name: $resource->name,
            rawRelative: $resource->path,
            skillDirectory: $skillDirectory,
            notFoundMessage: 'Resource file not found.',
        );

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
