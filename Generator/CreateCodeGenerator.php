<?php declare(strict_types=1);
/*
 * This file is part of the Koded package.
 *
 * (c) Mihail Binev <mihail@kodeart.com>
 *
 * Please view the LICENSE distributed with this source code
 * for the full copyright and license information.
 */

namespace Koded\Serializer\Generator;

use Koded\Serializer\LoaderTrait;
use Koded\Serializer\Metadata\{ClassMetadata, PropertyMetadata};
use Nette\PhpGenerator\{ClassType, Literal, Method};

final class CreateCodeGenerator
{
    public static function generate(ClassMetadata $metadata, ClassType $class): void
    {
        $method = $class->addMethod('create')->setReturnType('object');
        $method->addParameter('data')->setType('array');
        $method->addComment('@param array $data');
        $method->addComment('@return \\' . $metadata->ref->name);

        if ($metadata->ref->isAbstract() || $metadata->ref->isInterface()) {
            self::createWithLoader($class, $method);
            return;
        }

        if ($metadata->canInstantiateWithConstructor()) {
            self::instantiateWithConstructor($method, $metadata);
            return;
        }

        $method->addBody('return $this->metadata->ref->newInstance();');
    }


    private static function instantiateWithConstructor(Method $method, ClassMetadata $metadata): void
    {
        $args = [];
        $constructor = $metadata->ref->getConstructor();
        $method->addBody('$args = ?;' . PHP_EOL, [array_fill(0, $constructor->getNumberOfParameters(), null)]);

        /** @var PropertyMetadata[] $properties */
        $properties = array_filter($metadata->property, function(PropertyMetadata $property) {
            return $property->ctor;
        });

        foreach ($metadata->ctor as $name => $position) {
            $args[$position] = $properties[$name] ?? null;
            $alias = $metadata->property[$name]->alias;

            $method->addBody('if (array_key_exists(?, $data)) {', [$alias]);
            $method->addBody('    $args[?] = ?$data[?];', [
                $position,
                new Literal(CodeHelper::typecast($args[$position])),
                $alias,
            ]);
            $method->addBody('}');
        }
        $method->addBody('return $this->metadata->ref->newInstanceArgs($args);');
    }


    private static function createWithLoader(ClassType $class, Method $method): void
    {
        $class->addTrait(LoaderTrait::class);

        $method->addBody('$this->assert($data);');
        $method->addBody('$generator = $this->load($data[\'@class\'], $this->serializer);');
        $method->addBody('return $generator->create($data);');
    }
}
