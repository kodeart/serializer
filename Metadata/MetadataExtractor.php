<?php declare(strict_types=1);
/*
 * This file is part of the Koded package.
 *
 * (c) Mihail Binev <mihail@kodeart.com>
 *
 * Please view the LICENSE distributed with this source code
 * for the full copyright and license information.
 */

namespace Koded\Serializer\Metadata;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;
use Throwable;

final class MetadataExtractor
{
    private $getterPrefixes = ['get', 'has', 'is', 'can'];
    private $setterPrefixes = ['set', 'add', 'with', 'remove', 'delete'];

    public function extract(ClassMetadata $metadata)
    {
        foreach ($metadata->getProperties() as $property) {
            $this->findAccessorMethod($property);
            $this->findMutatorMethod($property);

            if ($this->findTypeFromMutator($property)) {
                continue;
            }

            if ($this->findTypeFromAccessor($property)) {
                continue;
            }

            if ($this->findTypeFromConstructor($metadata->ref, $property)) {
                continue;
            }

            $this->findTypeFromDefaultValue($property);
        }
    }


    private function findAccessorMethod(PropertyMetadata $property): void
    {
        $name = ucfirst($property->name);
        foreach ($this->getterPrefixes as $prefix) {
            try {
                $method = new ReflectionMethod($property->class, $prefix . $name);
                if ($method->isStatic()) {
                    continue;
                }

                if (0 === $method->getNumberOfRequiredParameters()) {
                    $property->get = $method;
                    break;
                }
            } catch (ReflectionException $e) {
                // NOOP; check next prefix
            }
        }
    }


    private function findMutatorMethod(PropertyMetadata $property): void
    {
        $name = ucfirst($property->name);
        foreach ($this->setterPrefixes as $prefix) {
            try {
                $method = new ReflectionMethod($property->class, $prefix . $name);
                if ($method->isStatic()) {
                    continue;
                }

                if ($method->getNumberOfRequiredParameters() > 0) {
                    $property->set = $method;
                    break;
                }
            } catch (ReflectionException $e) {
                // NOOP; try the next prefix
            }
        }
    }


    private function findTypeFromAccessor(PropertyMetadata $property): bool
    {
        if ($property->type) {
            return true;
        }

        if (!$property->get) {
            return false;
        }

        if ($type = $property->get->getReturnType()) {
            $property->null = $type->allowsNull();
            $property->type = $type->getName();
            return true;
        }

        $prefix = str_replace(ucfirst($property->name), '', $property->get->name);
        if (in_array($prefix, ['is', 'has', 'can'])) {
            $property->type = 'bool';
            return true;
        }

        return $this->extractFromDocComment($property, $property->ref);
    }


    private function findTypeFromMutator(PropertyMetadata $property): bool
    {
        if ($property->type) {
            return true;
        }

        if (!$property->set) {
            return false;
        }

        try {
            $parameter = $property->set->getParameters()[0];
            if ($type = $parameter->getType()) {
                $property->null = $type->allowsNull();
                $property->type = $type->getName();
                return true;
            }

            return $this->extractFromDocComment($property, $parameter);

        } catch (Throwable $e) {
            $property->type = '';
            return false;
        }
    }


    private function findTypeFromConstructor(ReflectionClass $class, PropertyMetadata $property): bool
    {
        if (false === $property->ctor) {
            return false;
        }

        if (!$constructor = $class->getConstructor()) {
            return false;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->name !== $property->name) {
                continue;
            }

            if ($type = $parameter->getType()) {
                $property->type = $type->getName();
                $property->null = $parameter->allowsNull();
                return true;
            }
        }

        if ($parent = $class->getParentClass()) {
            return $this->findTypeFromConstructor($parent, $property);
        }

        return $this->extractFromDocComment($property, $constructor);
    }


    private function findTypeFromDefaultValue(PropertyMetadata $property): bool
    {
        $class = $property->ref->getDeclaringClass();

        if (!$p = array_filter($class->getProperties(), function(ReflectionProperty $p) use ($property) {
            return $p->name === $property->name;
        })) {
            return false;
        }

        $p = current($p);

        try {
            // PHP 7.4+ has \ReflectionType $p->getType()
            if (null !== $type = $p->getType()) {
                $property->type = $type->getName();
                $property->null = $type->allowsNull();
                return true;
            }
        } catch (\Error $e) {
            // PHP < v7.4
            return $this->extractFromDocComment($property, $p);
        }
    }

    /**
     * @param PropertyMetadata                    $parent   Declaring property metadata class
     * @param ReflectionMethod | ReflectionParameter $property Reflection method or parameter
     *
     * @return bool
     */
    private function extractFromDocComment(PropertyMetadata $parent, ?Reflector $property): bool
    {
        if (!$property) {
            return false;
        }

        $regex = '~@(return|var)\s+([^\s]+)~u';

        if ($property instanceof ReflectionParameter) {
            $property = $property->getDeclaringFunction();
            $regex = '~@(param)\s+([^\s]+)~u';
        }

        if (!$docComment = $property->getDocComment()) {
            return false;
        }

        if (!preg_match($regex, $docComment, $type)) {
            return false;
        }

        [, , $type] = $type;
        $types = array_map('trim', explode('|', $type));

        if ($parent->null = in_array('null', $types)) {
            unset($types[array_search('null', $types)]);
        }

        if (1 !== count($types)) {
            // skip if multiple types are set; type may be any of those
            $parent->type = 'mixed';
            return false;
        }

        $type = $types[0];

        if (($pos = strrpos($type, '[]')) && $c = $this->findFQCN(substr($type, 0, $pos), $property->getDeclaringClass())) {
            $parent->type = $c . '[]';
        } else {
            $parent->type = $this->getReturnType($property, $type);
        }

        return true;
    }


    private function findFQCN(string $class, ReflectionClass $parent): string
    {
        if (class_exists($class, false)) {
            return $class;
        }

        $parser = new ClassParser(file_get_contents($parent->getFileName()) ?: '');
        if ($c = $parser->getFQCN($class, $parent->getNamespaceName())) {
            return $c;
        }

        return $class;
    }

    // TODO
    private function getReturnType(Reflector $property, string $type): string
    {
        if ('self' === $t = strtolower($type)) {
            return $property->getDeclaringClass()->name;
        }

        // FIXME this needs refactoring
        if ('mixed' === $t) {
            return '[]';
        }

        // TODO check for type?
        if (false !== strrpos($type, '[]')) {
//            return 'array';
        }

        if ('parent' === $t && $parent = $property->getDeclaringClass()->getParentClass()) {
            return $parent->name;
        }

        return $type;
    }
}
