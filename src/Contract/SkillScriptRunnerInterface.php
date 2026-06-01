<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Contract;

use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\SkillScript;

/**
 * Contract for executing a skill script and returning its output.
 *
 * Implementations are responsible for both security enforcement and execution.
 * Two built-in implementations are provided:
 *   - {@see \PeterFox\Arcana\NativeScriptRunner}                     — local PHP filesystem (default)
 *   - {@see \PeterFox\Arcana\Flysystem\FlysystemScriptRunner}        — any Flysystem adapter
 *
 * All implementations MUST enforce the same three path containment guards as
 * {@see SkillResourceLoaderInterface} before executing any script. This ensures
 * that a malicious SKILL.md cannot cause execution of files outside the skill's
 * own directory.
 */
interface SkillScriptRunnerInterface
{
    /**
     * Execute a script and return its output as a string.
     *
     * @param SkillScript $script The script descriptor from the skill's frontmatter.
     * @param string $skillDirectory The root directory of the skill (used to resolve relative paths).
     *
     * @throws SecurityException When the script path attempts to escape the skill directory.
     * @throws SkillParseException When the script file cannot be found or read.
     */
    public function run(SkillScript $script, string $skillDirectory): string;
}
