<?php

namespace Oro\Bundle\PlatformBundle\Tests\Unit\Maintenance;

use Lexik\Bundle\MaintenanceBundle\Drivers\DatabaseDriver;
use Oro\Bundle\PlatformBundle\Maintenance\Events;
use Oro\Bundle\PlatformBundle\Maintenance\Mode;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ModeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Mode
     */
    protected $mode;

    /**
     * @var DatabaseDriver
     */
    protected $driver;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    protected function setUp(): void
    {
        $factory = $this->getMockBuilder('Lexik\Bundle\MaintenanceBundle\Drivers\DriverFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $this->driver = $this->getMockBuilder('Lexik\Bundle\MaintenanceBundle\Drivers\DatabaseDriver')
            ->disableOriginalConstructor()
            ->getMock();

        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')
            ->setMethods(array('dispatch'))
            ->getMockForAbstractClass();

        $factory
            ->expects($this->any())
            ->method('getDriver')
            ->will($this->returnValue($this->driver));

        $this->driver
            ->expects($this->any())
            ->method('lock')
            ->will($this->returnValue(true));

        $this->driver
            ->expects($this->any())
            ->method('unlock')
            ->will($this->returnValue(true));

        $this->mode = new Mode($factory, $this->dispatcher);
    }

    public function testModeIsOn()
    {
        $this->driver
            ->expects($this->once())
            ->method('decide')
            ->will($this->returnValue(true));

        $this->assertTrue($this->mode->isOn());
    }

    public function testModeOn()
    {
        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(Events::MAINTENANCE_ON));

        $this->assertTrue($this->mode->on());
    }

    public function testModeOff()
    {
        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(Events::MAINTENANCE_OFF));

        $this->assertTrue($this->mode->off());
    }

    public function testActivate()
    {
        // can't check activation of maintenance, because it's turning off inside register_shutdown_function callback
        // it should be tested with Selenium tests
    }
}
