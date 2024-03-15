<?php
namespace PersistentIdentifiers\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class PIDItemRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'pid' => $this->resource->getPID(),
            'o:item' => $this->resource->getItem(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:PIDItem';
    }

    public function item()
    {
        return $this->getAdapter('items')
            ->getRepresentation($this->resource->getItem());
    }
    
    public function getPID()
    {
        return $this->resource->getPID();
    }
}
