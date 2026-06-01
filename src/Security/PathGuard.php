<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Security;

use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;

/**
 * Static helpers that enforce the three path containment guards used by
 * resource loaders and script runners.
 *
 * Guard 1 — reject absolute paths (Unix, Windows UNC, Windows drive-letter).
 * Guard 2 — reject explicit traversal sequences ('..').
 * Guard 3 — after realpath() resolution, assert the path stays inside the
 *            skill directory (protects against symlink escapes).
 *
 * Callers that need all three guards on native filesystems can use
 * {@see self::resolveContained()}, which applies all guards and returns the
 * verified absolute path.
 */
final class PathGuard
{
    /**
     * Guard 1 — throw if $rawRelative is an absolute path.
     *
     * Detects: Unix absolute (/foo), Windows backslash (\foo),
     * Windows drive-letter (C:\foo, c:/foo).
     *
     * @throws SecurityException
     */
    public static function assertNotAbsolute(string $type, string $name, string $rawRelative): void
    {
        if ($rawRelative === '') {
            return;
        }

        if (
            $rawRelative[0] === '/'
            || $rawRelative[0] === '\\'
            || preg_match('/^[A-Za-z]:/', $rawRelative) === 1
        ) {
            throw SecurityException::absolutePathRejected($type, $name, $rawRelative);
        }
    }

    /**
     * Guard 2 — throw if $rawRelative contains a traversal sequence ('..').
     *
     * @throws SecurityException
     */
    public static function assertNoTraversal(string $type, string $name, string $rawRelative): void
    {
        if (str_contains($rawRelative, '..')) {
            throw SecurityException::traversalSequenceRejected($type, $name, $rawRelative);
        }
    }

    /**
     * Guards 1 + 2 + 3 for native (non-virtualised) filesystems.
     *
     * Applies Guards 1 and 2, resolves the path with realpath(), then
     * asserts (Guard 3) that the resolved path is still within $skillDirectory.
     *
     *
     * @throws SecurityException
     * @throws SkillParseException When the file does not exist or the skill
     *                             directory itself cannot be resolved.
     *
     * @return string The verified, resolved absolute path.
     */
    public static function resolveContained(
        string $type,
        string $name,
        string $rawRelative,
        string $skillDirectory,
        string $notFoundMessage,
    ): string {
        self::assertNotAbsolute($type, $name, $rawRelative);
        self::assertNoTraversal($type, $name, $rawRelative);

        $resolvedBase = realpath($skillDirectory);

        if ($resolvedBase === false) {
            throw SecurityException::skillDirectoryUnresolvable($skillDirectory);
        }

        $rawPath = $resolvedBase . DIRECTORY_SEPARATOR . $rawRelative;
        $resolvedPath = realpath($rawPath);

        if ($resolvedPath === false) {
            throw new SkillParseException(
                message: $notFoundMessage,
                filePath: $rawPath,
            );
        }

        if (!str_starts_with($resolvedPath . DIRECTORY_SEPARATOR, $resolvedBase . DIRECTORY_SEPARATOR)) {
            throw SecurityException::directoryEscapeDetected($type, $name, $resolvedPath, $resolvedBase);
        }

        return $resolvedPath;
    }
}
