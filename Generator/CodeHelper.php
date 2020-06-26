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

use Koded\Serializer\Metadata\PropertyMetadata;

final class CodeHelper
{
    public static function typecast(PropertyMetadata $p): string
    {
        return in_array($p->type, ['array', 'string', 'int', 'bool', 'float']) ? "($p->type)" : '';
    }


    public static function getValueWithAccessorMethod(PropertyMetadata $p): string
    {
        $nullable = ($nullable = $p->get->getReturnType()) ? $nullable->allowsNull() : false;

        if ($nullable === false && $p->null === true) {
            // the accessor return type is overridden by configuration;
            // we'll not rely on the original code, but use the reflection
            return self::getValuePropertyWithReflection($p) . ';';
        }

        if ($nullable === true && $p->null === false) {
            // the accessor return type allows NULL,
            // but the property nullability is overridden in the configuration
            return self::typecast($p) . '$object->' . $p->get->name . '()';
        }

        return '$object->' . $p->get->name . '()';
    }


    public static function getValueFromPublicProperty(PropertyMetadata $p): string
    {
        return sprintf('%s$object->%s%s', self::typecast($p), $p->name, $p->null ? ' ?? null' : '');
    }


    public static function setValueToPublicProperty(PropertyMetadata $p): string
    {
        $value = (class_exists($p->type, false))
            ? '$this->serializer->denormalize($data[\'' . $p->alias . '\'], \'' .$p->type.'\')'
            : '$data[\'' . $p->alias . '\']';

        if ($p->null) {
            return sprintf('($data[\'' . $p->alias . '\'] ?? null) ? %s' . $value . ' : null', self::typecast($p));
        }

        return sprintf('%s' . $value, self::typecast($p));
    }


    public static function getValuePropertyWithReflection(PropertyMetadata $p): string
    {
        $accessor = self::getValueFromAccessor($p);

        if (class_exists($p->type, false)) {
            return $p->null
                ?'(null !== $value = ' . $accessor . ') ? $this->serializer->normalize($value) : null'
                : sprintf('%s' . $accessor, self::typecast($p));
        }

        return $p->null
            ? sprintf('(null !== $value = ' . $accessor . ') ? %s$value : null', self::typecast($p))
            : sprintf('%s' . $accessor, self::typecast($p));
    }


    public static function getValueFromAccessor(PropertyMetadata $p): string
    {
        if ($p->get) {
            return '$object->' . $p->get->name . '()';
        }

        if ($p->ref->isPublic()) {
            return '$object->' . $p->name;
        }

        return '$this->metadata->property[\'' . $p->name . '\']->ref->getValue($object)';
    }
}
