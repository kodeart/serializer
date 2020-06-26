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

use Koded\Serializer\{Denormalizer, Normalizer, Serializer};

final class DateTimeNormalizer implements Normalizer, Denormalizer
{
    private $format = 'Y-m-d H:i:s';

    public function __construct(string $format = 'Y-m-d H:i:s')
    {
        $this->format = $format;
    }

    public function canNormalize($object): bool
    {
        return $object instanceof \DateTimeInterface;
    }

    /**
     * @param Serializer         $serializer
     * @param \DateTimeInterface $object
     *
     * @return string Formatted DateTimeInterface object
     */
    public function normalize(Serializer $serializer, $object): string
    {
        return $object->format($this->format);
    }

    public function canDenormalize(string $type): bool
    {
        return is_a($type, \DateTimeInterface::class, true);
    }

    /**
     * @param Serializer  $serializer
     * @param string      $normalized
     * @param string      $type
     * @param object|null $target
     *
     * @return \DateTimeInterface
     * @throws \Throwable
     */
    public function denormalize(
        Serializer $serializer,
        $normalized,
        string $type,
        object $target = null
    ): \DateTimeInterface {
        try {
            return date_create_immutable($normalized);// ?: null;
        } catch (\Throwable $e) {
            error_log(sprintf("[DateTime Error] for value %s\nand type '%s'\nwith errors:\n%s",
                    var_export($normalized, true), $type, join(PHP_EOL, \DateTime::getLastErrors()['errors'])));
            throw $e;
        }
    }
}
