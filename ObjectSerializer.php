<?php declare(strict_types=1);
/*
 * This file is part of the Koded package.
 *
 * (c) Mihail Binev <mihail@kodeart.com>
 *
 * Please view the LICENSE distributed with this source code
 * for the full copyright and license information.
 */

namespace Koded\Serializer;

use Koded\Exceptions\SerializerException;
use Koded\Serializer\Metadata\XmlLoader;
use Koded\Serializer\Normalizer\{CollectionNormalizer, DateTimeNormalizer, JsonNormalizer, ObjectNormalizer};
use Koded\Stdlib\Serializer\JsonSerializer;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final class ObjectSerializer implements Serializer
{
    /** @var \Koded\Stdlib\Serializer */
    private $encoder;

    /** @var Normalizer[] | Denormalizer[] */
    private $normalizers = [];

    /** @var ConfigurationLoader */
    private $configLoader;

    /** @var CacheInterface */
    private $cache;

    public function __construct(ConfigurationLoader $conf = null)
    {
        $this->configLoader = $conf ?? new XmlLoader;
        $this->encoder = new JsonSerializer(0, false);
        $this->cache = new NoCache;

        if ($this->configLoader->getParameter('@normalizers', true)) {
            $this->normalizers[CollectionNormalizer::class] = new CollectionNormalizer;
            $this->normalizers[ObjectNormalizer::class] = new ObjectNormalizer;
            $this->normalizers[JsonNormalizer::class] = new JsonNormalizer;
            $this->normalizers[DateTimeNormalizer::class] = new DateTimeNormalizer;
        }

        try {
            foreach ($this->configLoader->getParameter('normalizer', []) as $normalizer) {
                $args = array_column($normalizer['argument'] ?? [], '#');
                $this->addNormalizer(new $normalizer['@class'](...$args));
            }
        } catch (Throwable $e) {
            throw SerializationError::forInvalidConfigurationParameter(get_class($this), 'normalizer');
        }
    }

    public function serialize($value): string
    {
        $value = $this->normalize($value);
        if (null === $value || is_scalar($value)) {
            return $value;
        }
        return $this->encoder->serialize($value);
    }

    public function unserialize($serialized, string $type, object $target = null)
    {
        $normalized = $this->encoder->unserialize($serialized) ?: $serialized;
        return $this->denormalize($normalized, $type, $target);
    }

    public function normalize($value)
    {
        $type = is_object($value) ? get_class($value) : gettype($value);
        if (null === $normalizer = $this->findNormalizer($value, $type)) {
            throw SerializationError::forUnknownNormalizerType($type);
        }
        return $normalizer->normalize($this, $value);
    }

    public function denormalize($normalized, string $type, object $target = null)
    {
        if (null === $normalizer = $this->findDenormalizer($type)) {
            $t = is_object($target) ? get_class($target) : gettype($target);
            throw SerializationError::forUnknownDenormalizerType($type, $t);
        }
        return $normalizer->denormalize($this, $normalized, $type, $target);
    }

    public function addNormalizer(Normalizer $normalizer): Serializer
    {
        // append the instance to the stack
        $this->normalizers = array_merge([get_class($normalizer) => $normalizer], $this->normalizers);
        return $this;
    }

    /**
     * @codeCoverageIgnore
     * @inheritDoc
     */
    public function withCache(CacheInterface $cache): Serializer
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @codeCoverageIgnore
     * @inheritDoc
     */
    public function withConfigurationLoader(ConfigurationLoader $loader): Serializer
    {
        $this->configLoader = $loader;
        return $this;
    }

    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    public function getConfigurationLoader(): ConfigurationLoader
    {
        return $this->configLoader;
    }

    private function findNormalizer($value, string $type): ?Normalizer
    {
        if (isset($this->normalizers[$type])) {
            return $this->normalizers[$type];
        }

        foreach ($this->normalizers as $instance) {
            if ($instance->canNormalize($value)) {
                $this->normalizers[$type] = $instance;
                return $instance;
            }
        }
        return null;
    }

    private function findDenormalizer(string $type): ?Denormalizer
    {
        if (isset($this->normalizers[$type])) {
            return $this->normalizers[$type];
        }

        foreach ($this->normalizers as $instance) {
            if ($instance->canDenormalize($type)) {
                $this->normalizers[$type] = $instance;
                return $instance;
            }
        }
        return null;
    }
}



class SerializationError extends SerializerException
{
    protected $messages = [
        0 => ':message',
        1 => 'Unavailable normalizer for type ":type"',
        2 => 'Unavailable de-normalizer for ":class" and type ":type"',
        3 => 'No mapping was found in class ":class". Did you configure the correct namespaces and paths?',
        4 => 'Circular reference detected for class ":class"',
        5 => 'Invalid class provided: ":class":',
        6 => 'Invalid loading strategy ":runtime". Supports [production, dev, debug]',
        7 => 'Required field ":name" is missing',
        8 => 'Invalid configuration parameter ":param" for ":class"',
    ];

    public function __construct(int $code, array $arguments = [], Throwable $previous = null)
    {
        parent::__construct($code, $arguments, $previous);
        $this->code = \Koded\Stdlib\Serializer::E_INVALID_SERIALIZER;
    }

    public static function forUnknownNormalizerType(string $type)
    {
        return new self(1, [':type' => $type]);
    }

    public static function forUnknownDenormalizerType(string $class, string $type)
    {
        return new self(2, [':class' => $class,':type' =>  $type]);
    }

    public static function forMissingMapping(string $class)
    {
        return new self(3, [':class' => $class]);
    }

    public static function forCircularReference(string $class)
    {
        return new self(4, [':class' => $class]);
    }

    public static function forInvalidClass(string $class)
    {
        return new self(5, [':class' => $class]);
    }

    public static function forInvalidLoadingStrategy(string $runtime)
    {
        return new self(6, [':runtime' => $runtime]);
    }

    public static function forMissingField(string $name)
    {
        return new self(7, [':name' => $name]);
    }

    public static function forInvalidConfigurationParameter(string $class, string $parameter)
    {
        return new self(8, [':param' => $parameter, ':class' => $class]);
    }
}
