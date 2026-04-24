<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Contract;

use PeterFox\Arcana\Skill;

/**
 * Contract for skill preprocessors that transform skill content before it
 * is returned to the caller.
 *
 * Preprocessors run after a skill is parsed and before it is cached.
 * Common uses include:
 *   - Variable interpolation (replace {{VAR}} placeholders)
 *   - Safe script execution to inject dynamic context
 *   - Content filtering or sanitisation
 *
 * @example Chaining preprocessors:
 *   $processor = new CompositePreprocessor([
 *       new VariableInterpolationPreprocessor(['env' => 'production']),
 *       new NullPreprocessor(),
 *   ]);
 */
interface SkillPreprocessorInterface
{
    /**
     * Process a skill and return the (possibly transformed) result.
     *
     * Implementations MUST return a Skill instance. They MAY return
     * the same instance unchanged (identity transform) or a new instance
     * with modified body content.
     */
    public function process(Skill $skill): Skill;
}
