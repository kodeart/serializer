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

use Koded\Serializer\{MetadataAware, MetadataAwareTrait, ObjectGenerator, SerializerAware, SerializerAwareTrait};
use Koded\Serializer\Metadata\ClassMetadata;
use Nette\PhpGenerator\{PhpFile, PsrPrinter};

final class CodeCompiler
{
    public static function compile(GeneratorBuilder $generator, ClassMetadata $metadata): string
    {
        $php = (new PhpFile)->addComment("--- DO NOT EDIT THIS FILE ---\nAuto-generated by Koded Serializer")
            ->setStrictTypes();

        $class = $php->addNamespace($generator->getNamespace($metadata))->addClass($generator->getClassName($metadata))
            ->setFinal(true)->setImplements([ObjectGenerator::class, MetadataAware::class, SerializerAware::class])
            ->setTraits([MetadataAwareTrait::class, SerializerAwareTrait::class]);

        CreateCodeGenerator::generate($metadata, $class);
        ExportCodeGenerator::generate($metadata, $class);
        ImportCodeGenerator::generate($metadata, $class);
        ClassAssertGenerator::generate($metadata, $class);

        return (new PsrPrinter)->printFile($php);
    }
}
