<?php

namespace Koded\Serializer;

use Koded\Serializer\Metadata\ClassMetadata;

trait MetadataAwareTrait
{
    /** @var ClassMetadata */
    private $metadata;

    public function withMetadata(ClassMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }
}
