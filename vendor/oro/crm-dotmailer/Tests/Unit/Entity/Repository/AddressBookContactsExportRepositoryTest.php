<?php

namespace Oro\Bundle\DotmailerBundle\Tests\Unit\Entity\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\DotmailerBundle\Entity\AddressBookContactsExport;
use Oro\Bundle\DotmailerBundle\Entity\Repository\AddressBookContactsExportRepository;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Component\Testing\Unit\EntityTrait;

class AddressBookContactsExportRepositoryTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /**
     * @var AddressBookContactsExportRepository
     */
    private $repository;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = $this->createMock(ClassMetadata::class);
        $this->repository = new AddressBookContactsExportRepository($em, $metadata);
    }

    /**
     * @dataProvider errorStatusDataProvider
     */
    public function testIsErrorStatus(AbstractEnumValue $status, bool $expected)
    {
        $this->assertSame($expected, $this->repository->isErrorStatus($status));
    }

    public function errorStatusDataProvider()
    {
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_REJECTED_BY_WATCHDOG), true];
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_INVALID_FILE_FORMAT), true];
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_FAILED), true];
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_EXCEEDS_ALLOWED_CONTACT_LIMIT), true];
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_NOT_AVAILABLE_IN_THIS_VERSION), true];
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_NOT_FINISHED), false];
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_FINISH), false];
        yield [$this->getEnumStatus(AddressBookContactsExport::STATUS_UNKNOWN), false];
    }

    private function getEnumStatus(string $statusValue)
    {
        $status = $this->createMock(AbstractEnumValue::class);
        $status->expects($this->any())
            ->method('getId')
            ->willReturn($statusValue);

        return $status;
    }
}
