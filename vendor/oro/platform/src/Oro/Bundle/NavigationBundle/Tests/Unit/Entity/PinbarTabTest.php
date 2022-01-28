<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Entity;

use Oro\Bundle\NavigationBundle\Entity\PinbarTab;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;

class PinbarTabTest extends \PHPUnit\Framework\TestCase
{
    use EntityTestCaseTrait;

    public function testSetMaximizedNotEmpty()
    {
        $item = $this->createMock('Oro\Bundle\NavigationBundle\Entity\NavigationItem');

        $pinbarTab = new PinbarTab();
        $pinbarTab->setItem($item);
        $pinbarTab->setMaximized('2022-02-02 22:22:22');

        $this->assertInstanceOf('DateTime', $pinbarTab->getMaximized());
    }

    public function testSetMaximizedEmpty()
    {
        $item = $this->createMock('Oro\Bundle\NavigationBundle\Entity\NavigationItem');

        $pinbarTab = new PinbarTab();
        $pinbarTab->setItem($item);
        $pinbarTab->setMaximized('');

        $this->assertNull($pinbarTab->getMaximized());
    }

    public function testSetGet()
    {
        $item = $this->createMock('Oro\Bundle\NavigationBundle\Entity\NavigationItem');

        $this->assertPropertyAccessors(
            new PinbarTab(),
            [
                ['item', $item, null],
                ['title', 'sample-title', null],
                ['titleShort', 'sample-title-short', null],
            ]
        );
    }

    public function testDoPrePersist()
    {
        $item = $this->createMock('Oro\Bundle\NavigationBundle\Entity\NavigationItem');

        $pinbarTab = new PinbarTab();
        $pinbarTab->setItem($item);
        $pinbarTab->doPrePersist();

        $this->assertNull($pinbarTab->getMaximized());
    }

    public function testSetValues()
    {
        $values = array('maximized' => '2022-02-02 22:22:22', 'url' => '/');
        $item = $this->createMock('Oro\Bundle\NavigationBundle\Entity\NavigationItem');
        $item->expects($this->once())
            ->method('setValues')
            ->with($values);
        $pinbarTab = new PinbarTab();
        $pinbarTab->setItem($item);
        $pinbarTab->setValues($values);
        $this->assertInstanceOf('DateTime', $pinbarTab->getMaximized());
    }

    public function testGetUserNoItem()
    {
        $pinbarTab = new PinbarTab();
        $this->assertNull($pinbarTab->getUser());
    }

    public function testGetUser()
    {
        $user = $this->createMock('stdClass');
        $item = $this->createMock('Oro\Bundle\NavigationBundle\Entity\NavigationItem');
        $item->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($user));
        $pinbarTab = new PinbarTab();
        $pinbarTab->setItem($item);
        $this->assertSame($user, $pinbarTab->getUser());
    }
}