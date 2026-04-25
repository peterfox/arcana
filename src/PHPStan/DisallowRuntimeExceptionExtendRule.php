<?php

declare(strict_types=1);

namespace PeterFox\Arcana\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final class DisallowRuntimeExceptionExtendRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    #[\Override]
    public function getNodeType(): string
    {
        return Class_::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->extends === null) {
            return [];
        }

        // ArcanaException itself is the permitted base for \RuntimeException
        if ($node->namespacedName?->toString() === 'PeterFox\\Arcana\\Exception\\ArcanaException') {
            return [];
        }

        $extendsName = $node->extends->toString();

        if (! $this->reflectionProvider->hasClass($extendsName)) {
            return [];
        }

        if ($this->reflectionProvider->getClass($extendsName)->getName() !== 'RuntimeException') {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Extend PeterFox\Arcana\Exception\ArcanaException instead of \RuntimeException directly.'
            )
                ->identifier('arcana.runtimeException.extend')
                ->build(),
        ];
    }
}
