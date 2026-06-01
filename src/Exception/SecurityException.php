<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Exception;

/**
 * Thrown when a security violation is detected in a skill's resource or script path.
 *
 * This exception is a safety feature. Arcana enforces strict path containment so
 * that a malicious or misconfigured SKILL.md cannot access files outside its own
 * skill directory. When this exception is thrown, the skill or script has NOT been
 * loaded — the violation was caught before any filesystem access occurred.
 *
 * Use the named constructors to produce consistent, descriptive messages:
 *   - {@see self::absolutePathRejected()}   — path started with / or \
 *   - {@see self::traversalSequenceRejected()} — path contained ..
 *   - {@see self::directoryEscapeDetected()} — realpath() resolved outside the skill directory
 *   - {@see self::skillDirectoryUnresolvable()} — skill directory itself could not be resolved
 */
final class SecurityException extends ArcanaException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    /**
     * The path began with a directory separator, indicating an absolute path.
     * Absolute paths are always rejected because they can reference any location
     * on the filesystem, not just the skill's own directory.
     */
    public static function absolutePathRejected(string $type, string $name, string $path): self
    {
        return new self(
            "Absolute paths are not permitted in skill {$type} declarations. "
            . "This is a safety restriction — {$type}s must be relative to their skill directory. "
            . ucfirst($type) . " '{$name}' declared path: '{$path}'.",
        );
    }

    /**
     * The path contained a '..' traversal sequence.
     * These sequences are rejected before any filesystem access because they
     * could be used to escape the skill's directory and read arbitrary files.
     */
    public static function traversalSequenceRejected(string $type, string $name, string $path): self
    {
        return new self(
            "Path traversal sequences ('..') are not permitted in skill {$type} declarations. "
            . "This is a safety restriction — {$type}s must be contained within their skill directory. "
            . ucfirst($type) . " '{$name}' declared path: '{$path}'.",
        );
    }

    /**
     * After resolving symlinks via realpath(), the path escaped the skill directory.
     * This guard catches traversal attempts that use symlinks rather than '..' sequences.
     */
    public static function directoryEscapeDetected(string $type, string $name, string $resolvedPath, string $skillDirectory): self
    {
        return new self(
            "Path traversal detected: {$type} '{$name}' resolved to '{$resolvedPath}', "
            . "which is outside the skill directory '{$skillDirectory}'. "
            . "This is a safety restriction — {$type}s must be contained within their skill directory.",
        );
    }

    /**
     * The skill's own directory path could not be resolved by realpath().
     * The skill directory must exist and be accessible before any path containment
     * check can be performed.
     */
    public static function skillDirectoryUnresolvable(string $skillDirectory): self
    {
        return new self(
            "Cannot resolve skill directory '{$skillDirectory}'. "
            . 'The directory must exist and be readable before skill resources or scripts can be loaded.',
        );
    }
}
