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

use Koded\Serializer\Metadata\ClassMetadata;
use Psr\SimpleCache\CacheInterface;

interface Serializer
{
    public function serialize($value): string;

    public function unserialize($value, string $type, object $target = null);

    public function normalize($value);

    public function denormalize($normalized, string $type, object $target = null);

    public function addNormalizer(Normalizer $normalizer): Serializer;

    /**
     * @param CacheInterface $cache
     *
     * @return Serializer
     */
    public function withCache(CacheInterface $cache): Serializer;

    /**
     * @param ConfigurationLoader $loader
     *
     * @return Serializer
     */
    public function withConfigurationLoader(ConfigurationLoader $loader): Serializer;

    /**
     * @return CacheInterface
     */
    public function getCache(): CacheInterface;

    /**
     * @return ConfigurationLoader
     */
    public function getConfigurationLoader(): ConfigurationLoader;
}


interface Normalizer
{
    public function canNormalize($value): bool;

    public function normalize(Serializer $serializer, $value);
}


interface Denormalizer
{
    public function canDenormalize(string $type): bool;

    public function denormalize(Serializer $serializer, $normalized, string $type, object $target = null);
}


interface ObjectGenerator
{
    public function create(array $data): object;

    public function export(object $object): array;

    public function import(array $data, object $object): object;
}


interface SerializerAware
{
    public function withSerializer(Serializer $serializer): void;
}


interface MetadataAware
{
    public function withMetadata(ClassMetadata $metadata): void;
}

/**
 * Metadata configuration loader.
 *
 * @package Koded\Serializer
 */
interface ConfigurationLoader
{
    public function loadMetadata(ClassMetadata $metadata): void;

    public function loadConfiguration(): void;

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getParameter(string $name, $default = null);
}
