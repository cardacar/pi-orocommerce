<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Entity;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowRestriction;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowRestrictionIdentity;
use Symfony\Component\PropertyAccess\PropertyAccess;

class WorkflowRestrictionIdentityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var WorkflowRestrictionIdentity
     */
    protected $identity;

    protected function setUp(): void
    {
        $this->identity = new WorkflowRestrictionIdentity();
    }

    protected function tearDown(): void
    {
        unset($this->identity);
    }

    public function testGetId()
    {
        $this->assertNull($this->identity->getId());

        $value = 1;
        $this->setEntityId($value);
        $this->assertEquals($value, $this->identity->getId());
    }

    /**
     * @dataProvider propertiesDataProvider
     *
     * @param string $property
     * @param mixed  $value
     */
    public function testSettersAndGetters($property, $value)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $accessor->setValue($this->identity, $property, $value);
        $this->assertEquals($value, $accessor->getValue($this->identity, $property));
    }

    public function propertiesDataProvider()
    {
        return [
            ['restriction', new WorkflowRestriction()],
            ['entityId', 123],
            ['workflowItem', new WorkflowItem()],
        ];
    }

    /**
     * @param int $value
     */
    protected function setEntityId($value)
    {
        $idReflection = new \ReflectionProperty('Oro\Bundle\WorkflowBundle\Entity\WorkflowRestrictionIdentity', 'id');
        $idReflection->setAccessible(true);
        $idReflection->setValue($this->identity, $value);
    }
}
