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

final class CollectionNormalizer implements Normalizer, Denormalizer
{
    public function canNormalize($value): bool
    {
        return is_iterable($value);
    }

    public function canDenormalize(string $type): bool
    {
        return strrpos($type, '[]') > 0;
    }

    public function normalize(Serializer $serializer, $value): array
    {
        $collection = [];
        foreach ($value as $k => $v) {
            $collection[$k] = is_scalar($v) ? $v : $serializer->normalize($v);
        }
        return $collection;
    }

    public function denormalize(Serializer $serializer, $normalized, string $type, object $target = null)
    {
        // TODO: Implement denormalize() method.

        $type = substr($type, 0, strrpos($type, '[]'));

        $collection = [];
        foreach ($normalized as $k => $v) {
            $collection[$k] = $serializer->denormalize($v, $type, $target);
        }
        return $collection;
    }

    private function typecastValues(string $type, &$data): void
    {
        $types = [
            'int'     => true,
            'integer' => true,
            'string'  => true,
            'bool'    => true,
            'boolean' => true,
            'float'   => true,
            'double'  => true,
            'mixed'   => true,
        ];

        if (isset($types[$type])) {
            foreach ($data as $key => $val) {
                if (null === $val) {
                    continue;
                }

                switch ($type) {
                    case 'string':
                        $data[$key] = (string)$val;
                        break;
                    case 'int':
                    case 'integer':
                        $data[$key] = (int)$val;
                        break;
                    case 'bool':
                    case 'boolean':
                        $data[$key] = (bool)$val;
                        break;
                    case 'double':
                    case 'float':
                        $data[$key] = (float)$val;
                        break;
                    case 'mixed':
                    default:
                        $data[$key] = $val;
                }
            }
        }
    }
}
