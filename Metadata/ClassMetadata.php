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

use ReflectionClass;
use Serializable;

final class ClassMetadata implements Serializable
{
    /** @var string */
    public $name;

    /** @var ReflectionClass */
    public $ref;

    /** @var array */
    public $ctor = [];

    /** @var PropertyMetadata[] */
    public $property = [];

    /**
     * ClassMetadata constructor.
     *
     * @param ReflectionClass|string $class
     *
     * @throws \ReflectionException
     */
    public function __construct($class)
    {
        $this->ref = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
        $this->name = $this->ref->name;

        if ($constructor = $this->ref->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                $this->ctor[$parameter->name] = $parameter->getPosition();
            }
        }
    }

    public function addProperty(PropertyMetadata $property): void
    {
        if (isset($this->ctor[$property->name])) {
            $property->ctor = true;
        }
        $this->property[$property->name] = $property;
    }

    /**
     * @return PropertyMetadata[]
     */
    public function getProperties(): array
    {
        return $this->property;
    }

    /**
     * @inheritDoc
     */
    public function serialize(): string
    {
        return serialize([
            $this->name,
            $this->ctor,
            $this->property,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized): void
    {
        [
            $this->name,
            $this->ctor,
            $this->property,
        ] = unserialize($serialized);

        $this->ref = new ReflectionClass($this->name);
    }

    public function canInstantiateWithConstructor(): bool
    {
        if (null === $constructor = $this->ref->getConstructor()) {
            return false;
        }

        if ($constructor->isPrivate() || $constructor->isProtected() || $constructor->isStatic()) {
            return false;
        }

        if (($count = $constructor->getNumberOfRequiredParameters()) > 0) {
            return count($this->ctor) >= $count;
        }

        return true;
    }
}
