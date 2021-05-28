<?php

declare (strict_types=1);
namespace Rector\NodeNameResolver;

use RectorPrefix20210528\Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use Rector\CodingStyle\Naming\ClassNaming;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\NodeNameResolver\Contract\NodeNameResolverInterface;
use Rector\NodeNameResolver\Error\InvalidNameNodeReporter;
use Rector\NodeNameResolver\Regex\RegexPatternDetector;
final class NodeNameResolver
{
    /**
     * @var \Rector\NodeNameResolver\Regex\RegexPatternDetector
     */
    private $regexPatternDetector;
    /**
     * @var \Rector\CodingStyle\Naming\ClassNaming
     */
    private $classNaming;
    /**
     * @var \Rector\NodeNameResolver\Error\InvalidNameNodeReporter
     */
    private $invalidNameNodeReporter;
    /**
     * @var mixed[]
     */
    private $nodeNameResolvers = [];
    /**
     * @param NodeNameResolverInterface[] $nodeNameResolvers
     */
    public function __construct(\Rector\NodeNameResolver\Regex\RegexPatternDetector $regexPatternDetector, \Rector\CodingStyle\Naming\ClassNaming $classNaming, \Rector\NodeNameResolver\Error\InvalidNameNodeReporter $invalidNameNodeReporter, array $nodeNameResolvers = [])
    {
        $this->regexPatternDetector = $regexPatternDetector;
        $this->classNaming = $classNaming;
        $this->invalidNameNodeReporter = $invalidNameNodeReporter;
        $this->nodeNameResolvers = $nodeNameResolvers;
    }
    /**
     * @param string[] $names
     */
    public function isNames(\PhpParser\Node $node, array $names) : bool
    {
        foreach ($names as $name) {
            if ($this->isName($node, $name)) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * @param Node|Node[] $node
     */
    public function isName($node, string $name) : bool
    {
        if ($node instanceof \PhpParser\Node\Expr\MethodCall) {
            return \false;
        }
        if ($node instanceof \PhpParser\Node\Expr\StaticCall) {
            return \false;
        }
        $nodes = \is_array($node) ? $node : [$node];
        foreach ($nodes as $node) {
            if ($this->isSingleName($node, $name)) {
                return \true;
            }
        }
        return \false;
    }
    public function getName(\PhpParser\Node $node) : ?string
    {
        if ($node instanceof \PhpParser\Node\Expr\MethodCall || $node instanceof \PhpParser\Node\Expr\StaticCall) {
            if ($this->isCallOrIdentifier($node->name)) {
                return null;
            }
            $this->invalidNameNodeReporter->reportInvalidNodeForName($node);
        }
        foreach ($this->nodeNameResolvers as $nodeNameResolver) {
            if (!\is_a($node, $nodeNameResolver->getNode(), \true)) {
                continue;
            }
            return $nodeNameResolver->resolve($node);
        }
        // more complex
        if (!\property_exists($node, 'name')) {
            return null;
        }
        // unable to resolve
        if ($node->name instanceof \PhpParser\Node\Expr) {
            return null;
        }
        return (string) $node->name;
    }
    public function areNamesEqual(\PhpParser\Node $firstNode, \PhpParser\Node $secondNode) : bool
    {
        $secondResolvedName = $this->getName($secondNode);
        if ($secondResolvedName === null) {
            return \false;
        }
        return $this->isName($firstNode, $secondResolvedName);
    }
    /**
     * @param Name[]|Node[] $nodes
     * @return string[]
     */
    public function getNames(array $nodes) : array
    {
        $names = [];
        foreach ($nodes as $node) {
            $name = $this->getName($node);
            if (!\is_string($name)) {
                throw new \Rector\Core\Exception\ShouldNotHappenException();
            }
            $names[] = $name;
        }
        return $names;
    }
    public function isLocalPropertyFetchNamed(\PhpParser\Node $node, string $name) : bool
    {
        if (!$node instanceof \PhpParser\Node\Expr\PropertyFetch) {
            return \false;
        }
        if ($node->var instanceof \PhpParser\Node\Expr\MethodCall) {
            return \false;
        }
        if (!$this->isName($node->var, 'this')) {
            return \false;
        }
        if ($node->name instanceof \PhpParser\Node\Expr) {
            return \false;
        }
        return $this->isName($node->name, $name);
    }
    /**
     * Ends with ucname
     * Starts with adjective, e.g. (Post $firstPost, Post $secondPost)
     */
    public function endsWith(string $currentName, string $expectedName) : bool
    {
        $suffixNamePattern = '#\\w+' . \ucfirst($expectedName) . '#';
        return (bool) \RectorPrefix20210528\Nette\Utils\Strings::match($currentName, $suffixNamePattern);
    }
    /**
     * @param string|Name|Identifier|ClassLike $name
     */
    public function getShortName($name) : string
    {
        return $this->classNaming->getShortName($name);
    }
    /**
     * @param array<string, string> $renameMap
     */
    public function matchNameFromMap(\PhpParser\Node $node, array $renameMap) : ?string
    {
        $name = $this->getName($node);
        return $renameMap[$name] ?? null;
    }
    private function isCallOrIdentifier(\PhpParser\Node $node) : bool
    {
        if ($node instanceof \PhpParser\Node\Expr\MethodCall) {
            return \true;
        }
        if ($node instanceof \PhpParser\Node\Expr\StaticCall) {
            return \true;
        }
        return $node instanceof \PhpParser\Node\Identifier;
    }
    private function isSingleName(\PhpParser\Node $node, string $name) : bool
    {
        if ($node instanceof \PhpParser\Node\Expr\MethodCall) {
            // method call cannot have a name, only the variable or method name
            return \false;
        }
        $resolvedName = $this->getName($node);
        if ($resolvedName === null) {
            return \false;
        }
        if ($name === '') {
            return \false;
        }
        // is probably regex pattern
        if ($this->regexPatternDetector->isRegexPattern($name)) {
            return (bool) \RectorPrefix20210528\Nette\Utils\Strings::match($resolvedName, $name);
        }
        // is probably fnmatch
        if (\RectorPrefix20210528\Nette\Utils\Strings::contains($name, '*')) {
            return \fnmatch($name, $resolvedName, \FNM_NOESCAPE);
        }
        // special case
        if ($name === 'Object') {
            return $name === $resolvedName;
        }
        return \strtolower($resolvedName) === \strtolower($name);
    }
}
