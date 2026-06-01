<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Flysystem;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use PeterFox\Arcana\Contract\SkillScriptRunnerInterface;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\Security\PathGuard;
use PeterFox\Arcana\SkillScript;

/**
 * Reads skill script files via a Flysystem filesystem and returns their contents.
 *
 * Suitable for any adapter supported by League\Flysystem — local, S3, SFTP,
 * in-memory, etc. Path traversal is blocked via string-level guards before
 * any filesystem access; Flysystem's own path normalization provides an
 * additional layer.
 *
 * Note: Guard 3 (symlink escape via realpath) is not applied here because
 * Flysystem virtualises paths and does not expose symlink semantics.
 *
 * This runner returns the raw script content as a string. Consumers are
 * responsible for executing it in the appropriate runtime for the script's
 * declared language.
 */
final class FlysystemScriptRunner implements SkillScriptRunnerInterface
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
    ) {}

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function run(SkillScript $script, string $skillDirectory): string
    {
        PathGuard::assertNotAbsolute('script', $script->name, $script->path);
        PathGuard::assertNoTraversal('script', $script->name, $script->path);

        $path = rtrim($skillDirectory, '/') . '/' . $script->path;

        try {
            return $this->filesystem->read($path);
        } catch (FilesystemException $e) {
            throw new SkillParseException(
                message: 'Script file is not readable.',
                filePath: $path,
                previous: $e,
            );
        }
    }
}
