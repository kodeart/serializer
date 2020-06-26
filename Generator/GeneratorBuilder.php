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

use Koded\Serializer\{MetadataAware, ObjectGenerator, Serializer, SerializerAware};
use Koded\Serializer\Metadata\ClassMetadata;

final class GeneratorBuilder
{
    private $directory = '';
    private $namespace = '';

    public function __construct(string $directory, string $namespace)
    {
        $this->namespace = $namespace;
        $this->directory = $directory;
    }

    public function new(ClassMetadata $metadata, Serializer $serializer): ObjectGenerator
    {
        /** @var ObjectGenerator $generator */
        $generator = (new \ReflectionClass($this->getNamespaceName($metadata)))
            ->newInstanceWithoutConstructor();

        if ($generator instanceof SerializerAware) {
            $generator->withSerializer($serializer);
        }

        if ($generator instanceof MetadataAware) {
            $generator->withMetadata($metadata);
        }

        return $generator;
    }

    public function getNamespaceName(ClassMetadata $metadata): string
    {
        return $this->getNamespace($metadata) . '\\' . $this->getClassName($metadata);
    }

    public function getNamespace(ClassMetadata $metadata): string
    {
        return rtrim($this->namespace . '\\' . $metadata->ref->getNamespaceName(), '\\');
    }

    public function getClassName(ClassMetadata $metadata): string
    {
        return $metadata->ref->getShortName() . 'Generator';
    }

    public function getClassFileName(ClassMetadata $metadata): string
    {
        return $this->directory . '/' . str_replace('\\', '', $metadata->ref->name) . '.php';
    }
}
