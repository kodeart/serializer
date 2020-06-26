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

use ReflectionMethod;
use ReflectionProperty;
use Serializable;

final class PropertyMetadata implements Serializable
{
    /** @var string */
    public $class = '';

    /** @var string */
    public $name = '';

    /** @var string */
    public $alias = '';

    /** @var string */
    public $type = '';

    /** @var bool */
    public $hide = false;

    /** @var bool */
    public $null = false;

    /** @var bool */
    public $ctor = false;

    /** @var ReflectionProperty */
    public $ref;

    /** @var ReflectionMethod */
    public $get;

    /** @var ReflectionMethod */
    public $set;


    public function __construct(string $class, string $name)
    {
        $this->class = $class;
        $this->name = $name;
        $this->alias = $name;

        $this->ref = new ReflectionProperty($class, $name);
        $this->ref->setAccessible(true);
    }

    public function getValue(object $object)
    {
        return $this->ref->getValue($object);
    }

    public function setValue(object $object, $value): void
    {
        $this->ref->setValue($object, $value);
    }

    /**
     * @internal
     * @inheritDoc
     */
    public function serialize(): string
    {
        return serialize([
            $this->class,
            $this->name,
            $this->type,
            $this->alias,
            $this->hide,
            $this->null,
            $this->ctor,
            $this->get ? [$this->get->class, $this->get->name] : null,
            $this->set ? [$this->set->class, $this->set->name] : null,
        ]);
    }

    /**
     * @internal
     * @inheritDoc
     */
    public function unserialize($serialized): void
    {
        [
            $this->class,
            $this->name,
            $this->type,
            $this->alias,
            $this->hide,
            $this->null,
            $this->ctor,
            $get,
            $set,
        ] = unserialize($serialized);

        if ($get) {
            $this->get = new ReflectionMethod(...$get);
        }

        if ($set) {
            $this->set = new ReflectionMethod(...$set);
        }

        $this->ref = new ReflectionProperty($this->class, $this->name);
        $this->ref->setAccessible(true);
    }
}
