<?php

namespace Oro\Bundle\MarketingListBundle\Tests\Unit\Entity;

use Oro\Bundle\MarketingListBundle\Entity\MarketingListUnsubscribedItem;
use Symfony\Component\PropertyAccess\PropertyAccess;

class MarketingListUnsubscribedItemTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MarketingListUnsubscribedItem
     */
    protected $entity;

    protected function setUp(): void
    {
        $this->entity = new MarketingListUnsubscribedItem();
    }

    protected function tearDown(): void
    {
        unset($this->entity);
    }

    public function testGetId()
    {
        $this->assertNull($this->entity->getId());

        $value = 42;
        $idReflection = new \ReflectionProperty(get_class($this->entity), 'id');
        $idReflection->setAccessible(true);
        $idReflection->setValue($this->entity, $value);
        $this->assertEquals($value, $this->entity->getId());
    }

    /**
     * @dataProvider propertiesDataProvider
     * @param string $property
     * @param mixed $value
     */
    public function testSettersAndGetters($property, $value)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $accessor->setValue($this->entity, $property, $value);
        $this->assertEquals($value, $accessor->getValue($this->entity, $property));
    }

    public function propertiesDataProvider()
    {
        return array(
            array('entityId', 2),
            array('marketingList', $this->createMock('Oro\Bundle\MarketingListBundle\Entity\MarketingList')),
            array('createdAt', new \DateTime()),
        );
    }

    public function testBeforeSave()
    {
        $this->assertNull($this->entity->getCreatedAt());
        $this->entity->beforeSave();
        $this->assertInstanceOf('\DateTime', $this->entity->getCreatedAt());
    }
}
