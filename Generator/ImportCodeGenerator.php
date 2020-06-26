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

use Koded\Serializer\Metadata\{ClassMetadata, PropertyMetadata};
use Nette\PhpGenerator\{ClassType, Literal, Method};

final class ImportCodeGenerator
{
    public static function generate(ClassMetadata $metadata, ClassType $class): void
    {
        $className = '\\' .  $metadata->ref->name;

        $method = $class->addMethod('import')->setReturnType('object');
        $method->addParameter('data')->setType('array');
        $method->addParameter('object')->setType('object');
        $method->addComment('@param array $data');
        $method->addComment('@param ' . $className . ' $object');
        $method->addComment('@return ' . $className);

        if (\stdClass::class === $metadata->name) {
            self::populateStdClass($method);
        }

        if ($metadata->ref->isAbstract() || $metadata->ref->isInterface()) {
            self::importWithLoader($method);
            return;
        }

        if (0 === count($metadata->property)) {
            $method->addBody('return $object;');
            return;
        }

        $properties = array_filter($metadata->property, function(PropertyMetadata $property) use ($metadata) {
            return !$property->ctor || !$metadata->canInstantiateWithConstructor();
        });

        /** @var PropertyMetadata $p */
        foreach ($properties as $p) {
            $method->addBody(PHP_EOL . '// property ?', [$p->name]);

            if ('mixed' === $p->type || '[]' === $p->type) {
                 // mixed collection

                continue;

            } elseif ($pos = strrpos($p->type, '[]')) {
                $class = substr($p->type, 0, $pos);

                $method->addBody('if (null !== $value = ($data[?] \?\? null)) {', [$p->alias]);
                $method->addBody('    foreach ($value as $k => $v) {');
                $method->addBody('        $value[$k] = $this->serializer->denormalize($v, ?);', [$class]);
                $method->addBody('    }');
                $method->addBody('    $this->metadata->property[?]->ref->setValue($object, $value);', [$p->name]);
                $method->addBody('}');

            } else {
                $method->addBody('if (array_key_exists(?, $data)) {', [$p->alias]);

                if ($p->null) {
                    $method->addBody('    if (null !== $value = $data[?]) {', [$p->alias]);
                }

                if (class_exists($p->type, false)) {
                    if ($p->null) {
                        $method->addBody('        $value = $this->serializer->denormalize($value, ?);', [$p->type]);
                        $method->addBody('    }');
                    } else {
                        $method->addBody('    $value = $this->serializer->denormalize($data[?], ?);', [$p->alias, $p->type]);
                    }
                } else {
                    if ($p->null) {
                        $method->addBody('        $value = ?$value;', [new Literal(CodeHelper::typecast($p))]);
                        $method->addBody('    }');
                    } else {
                        $method->addBody('    $value = ?$data[?];', [new Literal(CodeHelper::typecast($p)), $p->alias]);
                    }
                }

                $method->addBody('    ' . self::getValueFromMutator($p) . ';', [new Literal('')]);
                $method->addBody('}');
            }
        }
        $method->addBody('return $object;');
    }


    private static function populateStdClass(Method $method): void
    {
        $method->addBody('foreach ($data as $k => $v) {');
        $method->addBody('    $object->$k = $v;');
        $method->addBody('}');
    }


    private static function importWithLoader(Method $method): void
    {
        $method->addBody('$this->assert($data);');
        $method->addBody('$generator = $this->load($data[\'@class\'], $this->serializer);');
        $method->addBody('$generator->import($data, $object);');
        $method->addBody('return $object;');
    }


    private static function getValueFromMutator(PropertyMetadata $p)
    {
        if ($p->set) {
            return '$object->' . $p->set->name . '(?$value)';
        }

        if ($p->ref->isPublic()) {
            return '?$object->' . $p->name;
        }

        return '$this->metadata->property[\'' . $p->name . '\']->ref->setValue($object, ?$value)';
    }
}
