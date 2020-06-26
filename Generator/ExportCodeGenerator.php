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

use Koded\Serializer\Metadata\ClassMetadata;
use Nette\PhpGenerator\{ClassType, Literal, Method};

final class ExportCodeGenerator
{
    public static function generate(ClassMetadata $metadata, ClassType $class): void
    {
        $method = $class->addMethod('export')->setReturnType('array');
        $method->addParameter('object')->setType('object');
        $method->addComment('@param \\' . $metadata->ref->name . ' $object');
        $method->addComment('@return array');

        if (\stdClass::class === $metadata->name) {
            self::exportStdClassMethod($method);
            return;
        }

        if (!$metadata->property) {
            $method->addBody('return [];');
            return;
        }

        $method->addBody('$data = [];');

        foreach ($metadata->getProperties() as $p) {
            if ($p->hide) {
                continue;
            }

            $method->addBody('// property ?', [$p->name]);

            if ('mixed' === $p->type || '[]' === $p->type) {
                // TODO mixed collection

                $method->addBody('$data[?] = (array)$value;', [$p->alias,]);

            } elseif (strrpos($p->type, '[]')) {
                $method->addBody('if (null !== $value = ?) {', [new Literal(CodeHelper::getValueFromAccessor($p))]);
                $method->addBody('    $iterable = clone $value;');
                $method->addBody('    foreach ($iterable as $k => $v) {');
                $method->addBody('        $data[?][$k] = $this->serializer->normalize($v);', [$p->name]);
                $method->addBody('    }');
                $method->addBody('}');

            } elseif ($p->get) {
                $method->addBody('$data[?] = ?;', [
                    $p->alias,
                    new Literal(CodeHelper::getValueWithAccessorMethod($p))
                ]);

            } elseif ($p->ref->isPublic()) {
                $method->addBody('$data[?] = ?;', [
                    $p->alias,
                    new Literal(CodeHelper::getValueFromPublicProperty($p))
                ]);

            } else {
                $method->addBody('$data[?] = ?;', [
                    $p->alias,
                    new Literal(CodeHelper::getValuePropertyWithReflection($p)),
                ]);
            }
        }
        $method->addBody('return $data;');
    }


    private static function exportStdClassMethod(Method $method): void
    {
//        $method->addBody('return (array)\json_decode(\json_encode($object, \JSON_NUMERIC_CHECK | \JSON_UNESCAPED_SLASHES));');
        $method->addBody('return (array)\Koded\Stdlib\json_unserialize(\Koded\Stdlib\json_serialize($object));');
    }
}
