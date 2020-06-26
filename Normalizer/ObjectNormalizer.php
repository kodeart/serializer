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

final class ObjectNormalizer implements Normalizer, Denormalizer
{
    use LoaderTrait;

    public function canNormalize($value): bool
    {
        return is_object($value)
            && !is_iterable($value)
            && !$value instanceof \stdClass
            && !$value instanceof \DateTimeInterface;
    }

    public function canDenormalize(string $type): bool
    {
        return (class_exists($type, true) || interface_exists($type, true))
            && !is_a($type, \DateTimeInterface::class, true);
    }

    public function normalize(Serializer $serializer, $value): array
    {
        return $this->load(get_class($value), $serializer)->export($value);
    }

    public function denormalize(Serializer $serializer, $normalized, string $type, object $target = null)
    {
        $normalized = (array)$normalized;
        $generator = $this->load($type, $serializer);

        if (null === $target) {
            $target = $generator->create($normalized);
        }

        return $generator->import($normalized, $target);
    }
}
