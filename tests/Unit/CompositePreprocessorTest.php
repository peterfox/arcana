<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PeterFox\Arcana\CompositePreprocessor;
use PeterFox\Arcana\Contract\SkillPreprocessorInterface;
use PeterFox\Arcana\NullPreprocessor;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillMetadata;

#[CoversClass(CompositePreprocessor::class)]
#[CoversClass(NullPreprocessor::class)]
final class CompositePreprocessorTest extends TestCase
{
    private Skill $skill;

    protected function setUp(): void
    {
        $this->skill = $this->makeSkill('Original body content.');
    }

    #[Test]
    public function null_preprocessor_returns_skill_unchanged(): void
    {
        $preprocessor = new NullPreprocessor();
        $result = $preprocessor->process($this->skill);

        self::assertSame($this->skill, $result);
    }

    #[Test]
    public function empty_composite_returns_skill_unchanged(): void
    {
        $composite = new CompositePreprocessor([]);
        $result = $composite->process($this->skill);

        self::assertSame($this->skill, $result);
    }

    #[Test]
    public function it_applies_each_preprocessor_in_order(): void
    {
        $log = [];

        $first = $this->makeTrackingPreprocessor('FIRST', $log);
        $second = $this->makeTrackingPreprocessor('SECOND', $log);
        $third = $this->makeTrackingPreprocessor('THIRD', $log);

        $composite = new CompositePreprocessor([$first, $second, $third]);
        $composite->process($this->skill);

        self::assertSame(['FIRST', 'SECOND', 'THIRD'], $log);
    }

    #[Test]
    public function it_passes_transformed_skill_through_the_chain(): void
    {
        $appendA = $this->makeAppendPreprocessor(' A');
        $appendB = $this->makeAppendPreprocessor(' B');

        $composite = new CompositePreprocessor([$appendA, $appendB]);
        $result = $composite->process($this->skill);

        self::assertSame('Original body content. A B', $result->body);
    }

    #[Test]
    public function it_counts_chained_preprocessors(): void
    {
        $composite = new CompositePreprocessor([
            new NullPreprocessor(),
            new NullPreprocessor(),
            new NullPreprocessor(),
        ]);

        self::assertSame(3, $composite->count());
    }

    #[Test]
    public function append_returns_new_instance_with_extra_step(): void
    {
        $composite = new CompositePreprocessor([new NullPreprocessor()]);
        $extended = $composite->append(new NullPreprocessor());

        self::assertSame(1, $composite->count());
        self::assertSame(2, $extended->count());
        self::assertNotSame($composite, $extended);
    }

    #[Test]
    public function prepend_returns_new_instance_with_step_at_front(): void
    {
        $log = [];
        $first = $this->makeTrackingPreprocessor('FIRST', $log);
        $second = $this->makeTrackingPreprocessor('SECOND', $log);

        $composite = new CompositePreprocessor([$second]);
        $extended = $composite->prepend($first);
        $extended->process($this->skill);

        self::assertSame(['FIRST', 'SECOND'], $log);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSkill(string $body): Skill
    {
        $metadata = new SkillMetadata(
            name: 'test-skill',
            description: 'A test skill',
            version: '1.0.0',
            author: null,
            tags: [],
            triggers: [],
            resources: [],
            scripts: [],
            references: [],
            filePath: '/tmp/test/SKILL.md',
        );

        return new Skill(metadata: $metadata, body: $body);
    }

    /**
     * @param  list<string>  $log
     */
    private function makeTrackingPreprocessor(string $label, array &$log): SkillPreprocessorInterface
    {
        return new class ($label, $log) implements SkillPreprocessorInterface {
            /** @param list<string> $log */
            public function __construct(
                private readonly string $label,
                private array &$log,
            ) {}

            public function process(Skill $skill): Skill
            {
                $this->log[] = $this->label;

                return $skill;
            }
        };
    }

    private function makeAppendPreprocessor(string $suffix): SkillPreprocessorInterface
    {
        return new class ($suffix) implements SkillPreprocessorInterface {
            public function __construct(private readonly string $suffix) {}

            public function process(Skill $skill): Skill
            {
                return new Skill(
                    metadata: $skill->metadata,
                    body: $skill->body . $this->suffix,
                );
            }
        };
    }
}
