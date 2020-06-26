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
use Nette\PhpGenerator\ClassType;

final class ClassAssertGenerator
{
    public static function generate(ClassMetadata $metadata, ClassType $class): void
    {
        if ($metadata->ref->isAbstract() || $metadata->ref->isInterface()) {
            $assert = $class->addMethod('assert')->setVisibility('private');
            $assert->setReturnType('void');
            $assert->addParameter('data')->setType('array');

            $assert->addBody('if (!isset($data[\'@class\'])) {');
            $assert->addBody('    throw \Koded\Serializer\SerializationError::forMissingField(\'@class\');');
            $assert->addBody('}');

            $assert->addBody('if ($data[\'@class\'] === $this->metadata->name) {');
            $assert->addBody('    throw \Koded\Serializer\SerializationError::forCircularReference(?);', [$metadata->name]);
            $assert->addBody('}');
        }
    }
}
