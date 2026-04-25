<?php

declare(strict_types=1);

namespace PeterFox\Arcana\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Throw_>
 */
final class DisallowRuntimeExceptionThrowRule implements Rule
{
    #[\Override]
    public function getNodeType(): string
    {
        return Throw_::class;
    }

    #[\Override]
    public function processNode(Node $node, Scope $scope): array
    {
        // Test helpers may throw \RuntimeException for infrastructure failures — skip them
        if (str_starts_with($scope->getNamespace() ?? '', 'PeterFox\\Arcana\\Tests')) {
            return [];
        }

        $type = $scope->getType($node->expr);

        if ($type->getObjectClassNames() !== ['RuntimeException']) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Use PeterFox\Arcana\Exception\ArcanaException or a subclass instead of \RuntimeException directly.'
            )
                ->identifier('arcana.runtimeException.throw')
                ->build(),
        ];
    }
}
