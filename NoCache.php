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

use Psr\SimpleCache\CacheInterface;

final class NoCache implements CacheInterface
{
    public function get($key, $default = null)
    {
        return null;
    }

    public function set($key, $value, $ttl = null)
    {
        return true;
    }

    public function delete($key)
    {
        return true;
    }

    public function clear()
    {
        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        return [];
    }

    public function setMultiple($values, $ttl = null)
    {
        return true;
    }

    public function deleteMultiple($keys)
    {
        return true;
    }

    public function has($key)
    {
        return false;
    }
}
