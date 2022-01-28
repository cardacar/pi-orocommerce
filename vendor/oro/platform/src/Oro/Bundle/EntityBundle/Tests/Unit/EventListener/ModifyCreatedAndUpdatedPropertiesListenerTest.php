<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareInterface;
use Oro\Bundle\EntityBundle\EntityProperty\UpdatedByAwareInterface;
use Oro\Bundle\EntityBundle\EventListener\ModifyCreatedAndUpdatedPropertiesListener;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ModifyCreatedAndUpdatedPropertiesListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var TokenStorageInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenStorage;

    /** @var ConfigManager|\PHPUnit\Framework\MockObject\MockObject */
    private $configManager;

    /** @var ModifyCreatedAndUpdatedPropertiesListener */
    protected $listener;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->configManager = $this->createMock(ConfigManager::class);

        $this->listener = new ModifyCreatedAndUpdatedPropertiesListener($this->tokenStorage);
        $this->listener->setEntityConfigManager($this->configManager);
    }

    /**
     * @dataProvider userDataProvider
     *
     * @param object $user
     * @param boolean $expectedCallSetUpdatedBy
     */
    public function testModifyCreatedAndUpdatedPropertiesForNewEntity($user, $expectedCallSetUpdatedBy)
    {
        $datesAwareEntity = $this->createMock(DatesAwareInterface::class);

        $datesAwareEntity->expects(static::once())->method('getCreatedAt');
        $datesAwareEntity->expects(static::once())
            ->method('setCreatedAt')
            ->with(static::equalToWithDelta(new \DateTime(), 1.0));
        $datesAwareEntity->expects(static::once())->method('isUpdatedAtSet')->willReturn(false);
        $datesAwareEntity->expects(static::once())
            ->method('setUpdatedAt')
            ->with(static::equalToWithDelta(new \DateTime(), 1.0));

        $alreadyUpdatedDatesAwareEntity = $this->createMock(DatesAwareInterface::class);
        $alreadyUpdatedDatesAwareEntity->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime());
        $alreadyUpdatedDatesAwareEntity->expects($this->never())
            ->method('setCreatedAt');
        $alreadyUpdatedDatesAwareEntity->expects($this->once())
            ->method('isUpdatedAtSet')
            ->willReturn(true);
        $alreadyUpdatedDatesAwareEntity->expects($this->never())
            ->method('setUpdatedAt');

        $currentToken = $this->createMock(TokenInterface::class);
        $currentToken->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($currentToken);

        $updatedByAwareEntity = $this->createMock(UpdatedByAwareInterface::class);
        $updatedByAwareEntity->expects($this->once())
            ->method('isUpdatedBySet')
            ->willReturn(false);
        if ($expectedCallSetUpdatedBy) {
            $updatedByAwareEntity->expects($this->once())
                ->method('setUpdatedBy')
                ->with($user);
        } else {
            $updatedByAwareEntity->expects($this->never())
                ->method('setUpdatedBy');
        }
        $alreadyUpdatedUpdatedByAwareEntity = $this->createMock(UpdatedByAwareInterface::class);
        $alreadyUpdatedUpdatedByAwareEntity->expects($this->once())
            ->method('isUpdatedBySet')
            ->willReturn(true);
        $alreadyUpdatedUpdatedByAwareEntity->expects($this->never())
            ->method('setUpdatedBy');

        $scheduled = [
            $datesAwareEntity,
            $updatedByAwareEntity,
            $alreadyUpdatedDatesAwareEntity,
            $alreadyUpdatedUpdatedByAwareEntity
        ];
        $countOfRecompute = $expectedCallSetUpdatedBy ? 2 : 1;
        $args = $this->createArgsMock($countOfRecompute, $scheduled, []);

        $this->listener->onFlush($args);
    }

    /**
     * @dataProvider userDataProvider
     *
     * @param object $user
     * @param boolean $expectedCallSetUpdatedBy
     */
    public function testModifyCreatedAndUpdatedPropertiesForExistingEntity($user, $expectedCallSetUpdatedBy)
    {
        $datesAwareEntity = $this->createMock(DatesAwareInterface::class);

        $datesAwareEntity->expects(static::once())->method('isUpdatedAtSet')->willReturn(false);
        $datesAwareEntity->expects(static::once())
            ->method('setUpdatedAt')
            ->with(static::equalToWithDelta(new \DateTime(), 1.0));

        $alreadyUpdatedDatesAwareEntity = $this->createMock(DatesAwareInterface::class);
        $alreadyUpdatedDatesAwareEntity->expects(static::once())->method('isUpdatedAtSet')->willReturn(true);
        $alreadyUpdatedDatesAwareEntity->expects(static::never())->method('setUpdatedAt');

        $currentToken = $this->createMock(TokenInterface::class);
        $currentToken->expects(static::once())->method('getUser')->willReturn($user);
        $this->tokenStorage->expects(static::once())->method('getToken')->willReturn($currentToken);

        $updatedByAwareEntity = $this->createMock(UpdatedByAwareInterface::class);
        $updatedByAwareEntity->expects(static::once())->method('isUpdatedBySet')->willReturn(false);
        $updatedByAwareEntity->expects(static::exactly((int)$expectedCallSetUpdatedBy))
            ->method('setUpdatedBy')
            ->with($user);

        $alreadyUpdatedUpdatedByAwareEntity = $this->createMock(UpdatedByAwareInterface::class);
        $alreadyUpdatedUpdatedByAwareEntity->expects(static::once())->method('isUpdatedBySet')->willReturn(true);
        $alreadyUpdatedUpdatedByAwareEntity->expects(static::never())->method('setUpdatedBy');

        $scheduled = [
            $datesAwareEntity,
            $updatedByAwareEntity,
            $alreadyUpdatedDatesAwareEntity,
            $alreadyUpdatedUpdatedByAwareEntity
        ];
        $countOfRecompute = $expectedCallSetUpdatedBy ? 2 : 1;
        $args = $this->createArgsMock($countOfRecompute, [], $scheduled);

        $this->listener->onFlush($args);
    }

    /**
     * @return array
     */
    public function userDataProvider()
    {
        return [
            'realUser' => [
                'user' => $this->createMock(User::class),
                'expectedCallSetUpdatedBy' => true
            ],
            'anotherUser' => [
                'user' => new \stdClass(),
                'expectedCallSetUpdatedBy' => false
            ],
        ];
    }

    public function testModifyCreatedAndUpdatedPropertiesForOwningEntity()
    {
        $datesAwareEntity = $this->createMock(DatesAwareInterface::class);
        $datesAwareEntity->expects($this->once())
            ->method('isUpdatedAtSet')
            ->willReturn(false);
        $datesAwareEntity->expects($this->once())
            ->method('setUpdatedAt')
            ->with(self::equalToWithDelta(new \DateTime(), 1.0));
        $collectionItem = $this->createMock(User::class);

        $fieldConfig = $this->createMock(ConfigInterface::class);
        $fieldConfig->expects($this->once())
            ->method('get')
            ->with('actualize_owning_side_on_change')
            ->willReturn(true);
        $this->configManager->expects($this->once())
            ->method('hasConfig')
            ->with(User::class)
            ->willReturn(true);
        $this->configManager->expects($this->once())
            ->method('getFieldConfig')
            ->with('entity', User::class, 'children')
            ->willReturn($fieldConfig);

        $entityManager = $this->createMock(EntityManager::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('getAssociationMappings')
            ->willReturn([
                [
                    'type' => ClassMetadata::MANY_TO_ONE,
                    'inversedBy' => 'children',
                    'fieldName' => 'owner',
                    'targetEntity' => User::class
                ]
            ]);
        $metadata->expects($this->once())
            ->method('getFieldValue')
            ->with($collectionItem, 'owner')
            ->willReturn($datesAwareEntity);

        $entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->willReturn($metadata);
        $entityManager->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $unitOfWork->expects($this->once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);
        $unitOfWork->expects($this->once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$collectionItem]);
        $unitOfWork->expects($this->once())
            ->method('recomputeSingleEntityChangeSet');

        $args = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($args);
    }

    /**
     * @param int $countOfRecompute
     * @param object[] $scheduledForInsert
     * @param object[] $scheduledForUpdate
     *
     * @return OnFlushEventArgs
     */
    protected function createArgsMock($countOfRecompute, array $scheduledForInsert, array $scheduledForUpdate)
    {
        $entityManager = $this->createMock(EntityManager::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $metadataStub = $this->createMock(ClassMetadata::class);
        $metadataStub->expects($this->any())
            ->method('getAssociationMappings')
            ->willReturn([]);

        $entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->willReturn($metadataStub);
        $entityManager->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $unitOfWork->expects($this->once())
            ->method('getScheduledEntityInsertions')
            ->willReturn($scheduledForInsert);
        $unitOfWork->expects($this->once())
            ->method('getScheduledEntityUpdates')
            ->willReturn($scheduledForUpdate);
        $unitOfWork->expects($this->exactly($countOfRecompute))
            ->method('recomputeSingleEntityChangeSet');

        return new OnFlushEventArgs($entityManager);
    }
}
