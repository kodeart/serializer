<?php

namespace Koded\Serializer;

trait SerializerAwareTrait
{
    /** @var Serializer */
    private $serializer;

    public function withSerializer(Serializer $serializer): void
    {
        $this->serializer = $serializer;
    }
}
