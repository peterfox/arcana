<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Contract\SkillPreprocessorInterface;

/**
 * Identity (no-op) preprocessor — returns the skill unchanged.
 *
 * Used as a safe default when no transformation is required.
 * Also useful as a placeholder in test environments.
 */
final class NullPreprocessor implements SkillPreprocessorInterface
{
    public function process(Skill $skill): Skill
    {
        return $skill;
    }
}
