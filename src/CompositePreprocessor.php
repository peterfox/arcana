<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Contract\SkillPreprocessorInterface;

/**
 * Chains multiple preprocessors, passing each skill through in order.
 *
 * Useful for composing small, single-responsibility preprocessors:
 *
 *   $preprocessor = new CompositePreprocessor([
 *       new VariablePreprocessor(['env' => app()->environment()]),
 *       new MarkdownSanitizerPreprocessor(),
 *   ]);
 *
 *   $library = Arcana::create('/skills', preprocessor: $preprocessor);
 *
 * If the list is empty, the skill is returned unchanged.
 */
final class CompositePreprocessor implements SkillPreprocessorInterface
{
    /** @var array<SkillPreprocessorInterface> */
    private readonly array $preprocessors;

    /**
     * @param  array<SkillPreprocessorInterface>  $preprocessors  Ordered list of preprocessors to apply.
     */
    public function __construct(array $preprocessors)
    {
        $this->preprocessors = array_values($preprocessors);
    }

    public function process(Skill $skill): Skill
    {
        foreach ($this->preprocessors as $preprocessor) {
            $skill = $preprocessor->process($skill);
        }

        return $skill;
    }

    /**
     * Returns a new CompositePreprocessor with an additional preprocessor appended.
     */
    public function append(SkillPreprocessorInterface $preprocessor): self
    {
        return new self([...$this->preprocessors, $preprocessor]);
    }

    /**
     * Returns a new CompositePreprocessor with an additional preprocessor prepended.
     */
    public function prepend(SkillPreprocessorInterface $preprocessor): self
    {
        return new self([$preprocessor, ...$this->preprocessors]);
    }

    /**
     * Returns the number of chained preprocessors.
     */
    public function count(): int
    {
        return count($this->preprocessors);
    }
}
