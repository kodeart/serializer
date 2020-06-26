<?php
/*
 * This file is part of the Koded package.
 *
 * (c) Mihail Binev <mihail@kodeart.com>
 *
 * Please view the LICENSE distributed with this source code
 * for the full copyright and license information.
 */

namespace Koded\Serializer;

use Koded\Serializer\Generator\{CodeCompiler, GeneratorBuilder};
use Koded\Serializer\Metadata\{ClassMetadata, MetadataLoader};

trait LoaderTrait
{
    private $generators = [];

    private function load(string $type, Serializer $serializer): ObjectGenerator
    {
        if (isset($this->generators[$type])) {
            return $this->generators[$type];
        }

        // load class metadata
        $config = $serializer->getConfigurationLoader();
        $metadata = (new MetadataLoader($config))
            ->loadMetadata(new \ReflectionClass($type), $serializer->getCache());

        if (null === $metadata) {
            throw SerializationError::forMissingMapping($type);
        }

        $generator = new GeneratorBuilder($config->getParameter('@directory'),
            $config->getParameter('@namespace', '\\'));

        if (false === class_exists($generator->getNamespaceName($metadata), true)) {
            $this->process($generator, $metadata, $config->getParameter('@runtime', 'production'));
        }

        return $this->generators[$type] = $generator->new($metadata, $serializer);
    }

    private function process(GeneratorBuilder $generator, ClassMetadata $metadata, string $runtime): void
    {
        $file = $generator->getClassFileName($metadata);

        switch ($runtime) {
            case 'production':
                break;
            case 'dev':
                if (false === file_exists($file)) {
                    file_put_contents($file, CodeCompiler::compile($generator, $metadata));
                }
                break;
            case 'debug':
                file_put_contents($file, CodeCompiler::compile($generator, $metadata));
                break;
            default:
                throw SerializationError::forInvalidLoadingStrategy($runtime);
        }

        try {
            /** @noinspection PhpIncludeInspection */
            include_once $file;
        } catch (\Throwable $e) {
            $this->process($generator, $metadata, 'debug');
        }
    }
}
