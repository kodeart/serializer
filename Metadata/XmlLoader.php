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

use Koded\Serializer\{ConfigurationLoader, SerializationError};
use Koded\Stdlib\Serializer;

final class XmlLoader extends Serializer\XmlSerializer implements ConfigurationLoader
{
    private $config = [];

    public function __construct()
    {
        parent::__construct('serializer');
    }


    public function loadMetadata(ClassMetadata $metadata): void
    {
        $classes = $this->config['class'] ?? [];

        if ($classes && false === isset($classes[0])) {
            $classes = [$classes];
        }

        foreach ($classes as $class) {
            $this->extractProperties($metadata, $class);
        }
    }


    public function loadConfiguration(): void
    {
        if (false === empty($this->config)) {
            return;
        }

        if (file_exists($conf = ($dir = getcwd()) . '/serializer.xml')) {
            $this->config = $this->unserialize(file_get_contents($conf));
        }

        $this->config['@runtime'] = $this->config['@runtime'] ?? 'production';
        $this->config['@directory'] = $this->config['@directory'] ?? $dir . '/build/generators';
        $this->config['@namespace'] = rtrim($this->config['@namespace'] ?? 'App', '\\');
        $this->config['@normalizers'] = filter_var($this->config['@normalizers'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }


    public function getParameter(string $name, $default = null)
    {
        $this->loadConfiguration();
        return $this->config[$name] ?? $default;
    }


    private function extractProperties(ClassMetadata $metadata, array $classDefinitions): void
    {
        if (!$className = $classDefinitions['@name'] ?? '') {
            throw SerializationError::forInvalidConfigurationParameter($metadata->name, 'class@name');
        }

        if ($className !== $metadata->name) {
            return;
        }

        if (!isset($classDefinitions['property'][0])) {
            $classDefinitions['property'] = [$classDefinitions['property']];
        }

        $this->overwriteProperties($metadata, $classDefinitions, $className);
    }


    private function overwriteProperties(ClassMetadata $metadata, array $classDefinitions, string $className): void
    {
        foreach ($classDefinitions as $property) {
            if (!$name = $property['@name'] ?? '') {
                throw SerializationError::forInvalidConfigurationParameter($className, 'property@name');
            }

            if (false === isset($metadata->property[$name])) {
                continue;
            }

            $value = rtrim($property['#'] ?? '', '[]');
            if ($metadata->name === $value) {
                throw SerializationError::forCircularReference($className);
            }

            $metadata->property[$name]->alias = $property['@alias'] ?? $name;
            $metadata->property[$name]->hide = filter_var($property['@hidden'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $metadata->property[$name]->null = filter_var($property['@nullable'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $type = $property['@type'] ?? '' ?: $metadata->property[$name]->type;
            $metadata->property[$name]->type = ('array' === $type) ? $value . '[]' : $type;
        }
    }
}
