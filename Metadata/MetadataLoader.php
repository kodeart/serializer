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

use Generator;
use Koded\Serializer\{ConfigurationLoader, SerializationError};
use Psr\SimpleCache\{CacheInterface, InvalidArgumentException as SimpleCacheException};
use ReflectionClass;
use ReflectionException;

final class MetadataLoader
{
    private $configuration;

    public function __construct(ConfigurationLoader $loader)
    {
        $this->configuration = $loader;
    }

    public function loadMetadata(ReflectionClass $class, CacheInterface $cache): ?ClassMetadata
    {
        try {
            /** @var ClassMetadata $metadata */
            $this->ensureClassValidity($class);

            // load from cache
            $key = $this->createCacheKey($class->name);
            if ($metadata = $cache->get($key)) {
                return $metadata;
            }

            // load from source code
            $metadata = new ClassMetadata($class);
            foreach ($this->getClassHierarchy($class) as $parent) {
                $this->extractProperties($parent, $metadata);
            }
            (new MetadataExtractor)->extract($metadata);

            // override metadata from configuration definitions (if any)
            $this->configuration->loadConfiguration();
            $this->configuration->loadMetadata($metadata);

            $cache->set($key, $metadata);

        } catch (ReflectionException $e) {
            return null;
        } catch (SimpleCacheException $e) {
            error_log('Serializer Error : ' . $e->getMessage());
        }

        return $metadata;
    }


    private function extractProperties(ReflectionClass $class, ClassMetadata $metadata): void
    {
        foreach ($class->getProperties() as $property) {
            if (false === $property->isStatic()) {
                $metadata->addProperty(new PropertyMetadata($class->name, $property->name));
            }
        }
    }

    /**
     * @param ReflectionClass $class
     *
     * @return ReflectionClass[]
     */
    private function getClassHierarchy(ReflectionClass $class): Generator
    {
        /** @var ReflectionClass[] $classes */
        $classes = [];
        $interfaces = [];

        do {
            $classes[] = $class;
            $class = $class->getParentClass();
        } while ($class);

        foreach (array_reverse($classes, false) as $class) {
            foreach ($class->getInterfaces() as $interface) {
                if (isset($interfaces[$interface->getName()])) {
                    continue;
                }
                $interfaces[$interface->getName()] = true;
                yield $interface;
            }
            yield $class;
        }
    }


    private function ensureClassValidity(ReflectionClass &$class): void
    {
        if ($class->isAnonymous()) {
            if (false === $parent = get_parent_class($class->name)) {
                throw SerializationError::forInvalidClass($class->name);
            }
            try {
                $class = new ReflectionClass($parent);
                $this->ensureClassValidity($class);
            } catch (ReflectionException $e) {
                error_log('Serializer Error : ' . $e->getMessage());
            }
        }
    }

    /**
     * @param string $class
     *
     * @return string PSR-16 key
     */
    private function createCacheKey(string $class): string
    {
        return str_replace(['\\', '@', '/', '{', '}', '(', ')'], '-', $class);
    }
}
