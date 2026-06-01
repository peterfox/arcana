<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Contract\SkillScriptRunnerInterface;
use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;

/**
 * Executes skill scripts using native PHP filesystem calls.
 *
 * This is the default script runner. It enforces three security guards before
 * any execution occurs:
 *
 *   1. Reject absolute paths before any filesystem access.
 *   2. Reject path traversal sequences ('..').
 *   3. After realpath() resolution, assert the resolved path is still within
 *      the skill's own directory (guards against symlink escapes).
 *
 * These guards mirror those in {@see NativeResourceLoader}. Scripts that pass
 * all three guards are verified to reside within the skill directory before
 * execution is delegated to the implementation.
 *
 * Concrete subclasses or decorators handle the actual execution strategy
 * (e.g. PHP include, shell exec) after the path has been verified safe.
 */
abstract class NativeScriptRunner implements SkillScriptRunnerInterface
{
    /**
     * {@inheritdoc}
     */
    #[\Override]
    final public function run(SkillScript $script, string $skillDirectory): string
    {
        $rawRelative = $script->path;

        // Guard 1 — reject absolute paths before any filesystem access.
        if ($rawRelative !== '' && ($rawRelative[0] === '/' || $rawRelative[0] === '\\')) {
            throw SecurityException::absolutePathRejected('script', $script->name, $rawRelative);
        }

        // Guard 2 — reject explicit traversal sequences.
        if (str_contains($rawRelative, '..')) {
            throw SecurityException::traversalSequenceRejected('script', $script->name, $rawRelative);
        }

        $resolvedBase = realpath($skillDirectory);

        if ($resolvedBase === false) {
            throw SecurityException::skillDirectoryUnresolvable($skillDirectory);
        }

        $rawPath = $resolvedBase . DIRECTORY_SEPARATOR . $rawRelative;
        $resolvedPath = realpath($rawPath);

        if ($resolvedPath === false) {
            throw new SkillParseException(
                message: 'Script file not found.',
                filePath: $rawPath,
            );
        }

        // Guard 3 — final check after symlink resolution: the resolved path
        // must still be within the skill directory.
        if (!str_starts_with($resolvedPath . DIRECTORY_SEPARATOR, $resolvedBase . DIRECTORY_SEPARATOR)) {
            throw SecurityException::directoryEscapeDetected('script', $script->name, $resolvedPath, $resolvedBase);
        }

        return $this->execute($script, $resolvedPath);
    }

    /**
     * Execute the script at the given verified absolute path and return its output.
     *
     * This method is only called after all three path containment guards have
     * passed. The $resolvedPath is guaranteed to be within the skill directory.
     *
     * @param SkillScript $script       The script descriptor (includes language, name, description).
     * @param string      $resolvedPath Verified absolute path to the script file.
     *
     * @throws SkillParseException When the script cannot be executed or produces no output.
     */
    abstract protected function execute(SkillScript $script, string $resolvedPath): string;
}
