<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Contract;

use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\SkillResource;

/**
 * Contract for loading the raw content of a skill resource file.
 *
 * Implementations are responsible for both security enforcement and I/O.
 * Two built-in implementations are provided:
 *   - {@see \PeterFox\Arcana\NativeResourceLoader}  — local PHP filesystem (default)
 *   - {@see \PeterFox\Arcana\Flysystem\FlysystemResourceLoader} — any Flysystem adapter
 */
interface SkillResourceLoaderInterface
{
    /**
     * Load the raw content of a resource file.
     *
     * @param SkillResource $resource The resource descriptor from the skill's frontmatter.
     * @param string $skillDirectory The root directory of the skill (used to resolve relative paths).
     *
     * @throws SecurityException When the resource path attempts to escape the skill directory.
     * @throws SkillParseException When the file cannot be found or read.
     */
    public function load(SkillResource $resource, string $skillDirectory): string;
}
