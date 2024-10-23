<?php
namespace LocalContexts\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 */
class LCEntity extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @Column(type="integer")
     */
    protected $entity_id;

    /**
     * API resource type (not neccesarily a Resource class)
     * @Column(type="string")
     */
    protected $resource_type;

    public function getId()
    {
        return $this->id;
    }
}
