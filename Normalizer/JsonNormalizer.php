<?php declare(strict_types=1);
/*
 * This file is part of the Koded package.
 *
 * (c) Mihail Binev <mihail@kodeart.com>
 *
 * Please view the LICENSE distributed with this source code
 * for the full copyright and license information.
 */

namespace Koded\Serializer\Normalizer;

use Koded\Serializer\{Denormalizer, LoaderTrait, Normalizer, Serializer};

final class JsonNormalizer implements Normalizer, Denormalizer
{
    use LoaderTrait;

    /**
     * @param \JsonSerializable $value Supports JsonSerializable object
     *
     * @return bool
     */
    public function canNormalize($value): bool
    {
        return $value instanceof \JsonSerializable || $value instanceof \stdClass;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function canDenormalize(string $type): bool
    {
        return 'json' === $type;
    }

    /**
     * @param Serializer        $serializer
     * @param \JsonSerializable $value JsonSerializable object
     *
     * @return array
     */
    public function normalize(Serializer $serializer, $value): array
    {
        return $this->load(\stdClass::class, $serializer)->export($value);
    }

    /**
     * @param Serializer  $serializer
     * @param array       $normalized
     * @param string      $type
     * @param object|null $target
     *
     * @return object
     */
    public function denormalize(Serializer $serializer, $normalized, string $type, object $target = null): object
    {
        $normalized = (array)$normalized;
        $generator = $this->load(\stdClass::class, $serializer);

        if (null === $target) {
            $target = $generator->create($normalized);
        }

        return $generator->import($normalized, $target);
    }
}
