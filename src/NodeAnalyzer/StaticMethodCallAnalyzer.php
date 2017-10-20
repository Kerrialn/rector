<?php declare(strict_types=1);

namespace Rector\NodeAnalyzer;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Node\Attribute;

final class StaticMethodCallAnalyzer
{
    /**
     * Checks "SpecificType::anyMethod()"
     */
    private function isStaticMethodCallType(Node $node, string $type): bool
    {
        if (! $node instanceof StaticCall) {
            return false;
        }

        return $this->resolveNodeType($node) === $type;
    }

    /**
     * Checks "SpecificType::specificMethod()"
     */
    public function isStaticMethodCallTypeAndMethod(Node $node, string $type, string $method): bool
    {
        if (! $this->isStaticMethodCallType($node, $type)) {
            return false;
        }

        /** @var StaticCall $node */
        return (string) $node->name === $method;
    }

    /**
     * Checks "SpecificType::oneOfSpecificMethods()"
     *
     * @param string[] $methodNames
     */
    public function isStaticMethodCallTypeAndMethods(Node $node, string $type, array $methodNames): bool
    {
        if (! $this->isStaticMethodCallType($node, $type)) {
            return false;
        }

        /** @var StaticCall $node */
        $currentMethodName = (string) $node->name;

        foreach ($methodNames as $methodName) {
            if ($currentMethodName === $methodName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $types
     */
    public function matchStaticMethodCallTypes(Node $node, array $types): ?string
    {
        if (! $node instanceof StaticCall) {
            return null;
        }

        if (! $node->name instanceof Identifier) {
            return null;
        }

        if (! $node->class instanceof Name) {
            return null;
        }

        $nodeType = $node->class->toString();

        return in_array($nodeType, $types, true) ? $nodeType : null;
    }

    private function resolveNodeType(StaticCall $staticCallNode): ?string
    {
        if ($staticCallNode->class instanceof Name) {
            return $staticCallNode->class->toString();
        }

        if ($staticCallNode->class instanceof Variable) {
            return $staticCallNode->class->getAttribute(Attribute::CLASS_NAME);
        }

        return null;
    }
}
