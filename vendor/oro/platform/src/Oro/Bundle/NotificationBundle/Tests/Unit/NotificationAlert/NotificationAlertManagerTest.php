<?php

namespace Oro\Bundle\NotificationBundle\Tests\Unit\NotificationAlert;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\NotificationBundle\Entity\NotificationAlert;
use Oro\Bundle\NotificationBundle\Exception\NotificationAlertFetchFailedException;
use Oro\Bundle\NotificationBundle\Exception\NotificationAlertUpdateFailedException;
use Oro\Bundle\NotificationBundle\NotificationAlert\NotificationAlertManager;
use Oro\Bundle\NotificationBundle\Tests\Unit\Fixtures\NotificationAlert\TestNotificationAlert;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessor;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class NotificationAlertManagerTest extends \PHPUnit\Framework\TestCase
{
    private const SOURCE_TYPE = 'test_integration';
    private const RESOURCE_TYPE = 'test_resource';

    /** @var EntityManager|\PHPUnit\Framework\MockObject\MockObject */
    private $em;

    /** @var Connection|\PHPUnit\Framework\MockObject\MockObject */
    private $connection;

    /** @var TokenAccessor|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenAccessor;

    /** @var NotificationAlertManager */
    private $notificationAlertManager;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManager::class);
        $this->connection = $this->createMock(Connection::class);
        $this->tokenAccessor = $this->createMock(TokenAccessor::class);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::any())
            ->method('getManagerForClass')
            ->with(NotificationAlert::class)
            ->willReturn($this->em);
        $this->em->expects(self::any())
            ->method('getConnection')
            ->willReturn($this->connection);
        $this->em->expects(self::any())
            ->method('getClassMetadata')
            ->with(NotificationAlert::class)
            ->willReturn($this->mockMetadata());
        $this->connection->expects(self::any())
            ->method('convertToPHPValue')
            ->willReturnCallback(function ($value, $type) {
                if (null === $value) {
                    return null;
                }
                if ('integer' === $type) {
                    return (int) $value;
                }
                if ('boolean' === $type) {
                    return (bool) $value;
                }
                if ('datetime' === $type) {
                    return $this->createDateTime($value);
                }

                return $value;
            });

        $this->notificationAlertManager = new NotificationAlertManager(
            self::SOURCE_TYPE,
            self::RESOURCE_TYPE,
            $doctrine,
            $this->tokenAccessor
        );
    }

    private function mockMetadata(): MockObject
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects(self::any())
            ->method('getTableName')
            ->willReturn('oro_notification_alert');
        $metadata->expects(self::any())
            ->method('getTypeOfField')
            ->willReturnMap([
                ['id', 'guid'],
                ['alertType', 'text'],
                ['sourceType', 'text'],
                ['resourceType', 'text'],
                ['createdAt', 'datetime'],
                ['updatedAt', 'datetime'],
                ['operation', 'text'],
                ['step', 'text'],
                ['itemId', 'integer'],
                ['externalId', 'text'],
                ['message', 'text'],
                ['resolved', 'boolean'],
            ]);
        $metadata->expects(self::any())
            ->method('getColumnName')
            ->willReturnMap([
                ['id', 'id'],
                ['alertType', 'alert_type'],
                ['sourceType', 'source_type'],
                ['resourceType', 'resource_type'],
                ['createdAt', 'created_at'],
                ['updatedAt', 'updated_at'],
                ['operation', 'operation'],
                ['step', 'step'],
                ['itemId', 'item_id'],
                ['externalId', 'external_id'],
                ['message', 'message'],
                ['resolved', 'is_resolved'],
            ]);
        $metadata->expects(self::any())
            ->method('getFieldName')
            ->willReturnMap([
                ['id', 'id'],
                ['alert_type', 'errorType'],
                ['source_type', 'sourceType'],
                ['resource_type', 'resourceType'],
                ['created_at', 'createdAt'],
                ['updated_at', 'updatedAt'],
                ['operation', 'operation'],
                ['step', 'step'],
                ['item_id', 'itemId'],
                ['external_id', 'externalId'],
                ['message', 'message'],
                ['is_resolved', 'resolved'],
            ]);
        $metadata->expects(self::any())
            ->method('getSingleAssociationJoinColumnName')
            ->willReturnMap([
                ['user', 'user_id'],
                ['organization', 'organization_id']
            ]);
        $metadata->expects(self::any())
            ->method('hasAssociation')
            ->willReturnCallback(function ($name) {
                return in_array($name, ['user', 'organization'], true);
            });

        return $metadata;
    }

    private function createDateTime(string $dateTime = 'now'): \DateTime
    {
        return new \DateTime($dateTime);
    }

    public function testResolveNotificationAlertByIdForCurrentUser(): void
    {
        $id = UUIDGenerator::v4();
        $userId = 14;
        $organizationId = 89;

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn($userId);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);
        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                self::isType('array'),
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'id'              => 'guid'
                ]
            )
            ->willReturnCallback(
                function (string $table, array $values, array $data) use ($id, $userId, $organizationId) {
                    self::assertSame(self::SOURCE_TYPE, $data['source_type']);
                    self::assertSame(self::RESOURCE_TYPE, $data['resource_type']);
                    self::assertSame($userId, $data['user_id']);
                    self::assertSame($organizationId, $data['organization_id']);
                    self::assertSame($id, $data['id']);
                }
            );

        $this->notificationAlertManager->resolveNotificationAlertByIdForCurrentUser($id);
    }

    public function testResolveNotificationAlertByIdForCurrentUserWhenRecordWasNotDeleted(): void
    {
        $id = UUIDGenerator::v4();

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn(78);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(76);

        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                self::isType('array'),
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'id'              => 'guid'
                ]
            );

        $this->notificationAlertManager->resolveNotificationAlertByIdForCurrentUser($id);
    }

    public function testTryToResolveNotificationAlertByIdForCurrentUserWhenExceptionWasThrown(): void
    {
        $exception = new \Exception('Error during deletion.', 404);

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn(85);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(49);

        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                self::isType('array'),
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'id'              => 'guid'
                ]
            )
            ->willThrowException($exception);

        try {
            $this->notificationAlertManager->resolveNotificationAlertByIdForCurrentUser(UUIDGenerator::v4());
            self::fail('An exception expected');
        } catch (NotificationAlertUpdateFailedException $e) {
            self::assertSame($exception, $e->getPrevious());
            self::assertSame($exception->getCode(), $e->getCode());
            self::assertEquals(
                'Failed to resolve a notification alert.',
                $e->getMessage()
            );
        }
    }

    public function testTryToAddNotificationAlertWhenIntegrationTypesInErrorObjectAndManagerIsNotEqual(): void
    {
        $error = new TestNotificationAlert('some_other_integration', []);

        try {
            $this->notificationAlertManager->addNotificationAlert($error);
            self::fail('An exception expected');
        } catch (\BadMethodCallException $e) {
            self::assertEquals(
                'Bad manager used to store notification alert.'
                . ' Expected "test_integration" notification alert, "some_other_integration" given.',
                $e->getMessage()
            );
        }
    }

    public function testAddNotificationAlert(): void
    {
        $dateTime = $this->createDateTime();
        $userId = 12;
        $organizationId = 37;

        $notificationAlert = new TestNotificationAlert(
            'test_integration',
            [
                'operation'    => 'import',
                'step'         => 'get',
                'createdAt'    => $dateTime,
                'updatedAt'    => $dateTime,
                'itemId'       => 456,
                'externalId'   => 'test_item_id',
                'resourceType' => 'test_entity',
                'alertType'    => 'sync',
            ]
        );

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn($userId);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->connection->expects(self::atMost(2))
            ->method('fetchOne')
            ->willReturn(null);
        $this->connection->expects(self::atMost(2))
            ->method('insert')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                [
                    'operation'       => 'text',
                    'step'            => 'text',
                    'created_at'      => 'datetime',
                    'updated_at'      => 'datetime',
                    'item_id'         => 'integer',
                    'external_id'     => 'text',
                    'resource_type'   => 'text',
                    'alert_type'      => 'text',
                    'source_type'     => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'id'              => 'guid',
                ]
            )
            ->willReturnCallback(function (string $table, array $data) use ($dateTime) {
                self::assertSame(self::SOURCE_TYPE, $data['source_type']);
                self::assertSame(self::RESOURCE_TYPE, $data['resource_type']);
                self::assertSame('import', $data['operation']);
                self::assertSame('get', $data['step']);
                self::assertSame('sync', $data['alert_type']);
                self::assertSame($dateTime, $data['created_at']);
                self::assertSame($dateTime, $data['updated_at']);
                self::assertSame(456, $data['item_id']);
                self::assertSame('test_item_id', $data['external_id']);
                self::assertSame(12, $data['user_id']);
                self::assertSame(37, $data['organization_id']);
                self::assertIsString($data['id']);

                return 1;
            });

        self::assertIsString($this->notificationAlertManager->addNotificationAlert($notificationAlert));
    }

    public function testHasNotificationAlerts(): void
    {
        $userId = 1;
        $organizationId = 1;

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn($userId);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                "SELECT COUNT(alert.id) as notificationAlertCount\n"
                . "                FROM oro_notification_alert AS alert\n"
                . "                WHERE\n"
                . "                 alert.source_type = :source_type\n"
                . "                 AND alert.resource_type = :resource_type\n"
                . "                 AND alert.user_id = :user_id\n"
                . "                 AND alert.organization_id = :organization_id\n"
                . "                 AND alert.is_resolved = :is_resolved",
                [
                    'source_type'     => 'test_integration',
                    'resource_type'   => 'test_resource',
                    'user_id'         => 1,
                    'organization_id' => 1,
                    'is_resolved'     => false,
                ],
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'is_resolved'     => 'boolean',
                ]
            )
            ->willReturn(2);

        self::assertTrue($this->notificationAlertManager->hasNotificationAlerts());
    }

    public function testHasNotificationAlertsWhenExceptionWasThrown()
    {
        $exception = new \Exception('Error during fetch', 510);

        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->willThrowException($exception);

        try {
            $this->notificationAlertManager->hasNotificationAlerts();
            self::fail('An exception expected');
        } catch (NotificationAlertFetchFailedException $e) {
            self::assertSame($exception, $e->getPrevious());
            self::assertSame($exception->getCode(), $e->getCode());
            self::assertEquals(
                'Failed to fetch a notification alert.',
                $e->getMessage()
            );
        }
    }

    public function testHasNotificationAlertsByType(): void
    {
        $userId = 1;
        $organizationId = 1;

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn($userId);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                <<<EOS
SELECT COUNT(alert.id) as notificationAlertCount
                FROM oro_notification_alert AS alert
                WHERE
                 alert.source_type = :source_type
                 AND alert.resource_type = :resource_type
                 AND alert.alert_type = :alert_type
                 AND alert.user_id = :user_id
                 AND alert.organization_id = :organization_id
                 AND alert.is_resolved = :is_resolved
EOS,
                [
                    'source_type'     => 'test_integration',
                    'resource_type'   => 'test_resource',
                    'alert_type'      => 'test_error_type',
                    'user_id'         => 1,
                    'organization_id' => 1,
                    'is_resolved'     => false,
                ],
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'alert_type'      => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'is_resolved'     => 'boolean',
                ]
            )
            ->willReturn(2);

        self::assertTrue(
            $this->notificationAlertManager->hasNotificationAlertsByType('test_error_type')
        );
    }

    public function testHasNotificationAlertsByTypeWhenExceptionWasThrown(): void
    {
        $exception = new \Exception('Error during fetch by error type.', 510);

        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->willThrowException($exception);

        try {
            $this->notificationAlertManager->hasNotificationAlertsByType('test_error_type');
            self::fail('An exception expected');
        } catch (NotificationAlertFetchFailedException $e) {
            self::assertSame($exception, $e->getPrevious());
            self::assertSame($exception->getCode(), $e->getCode());
            self::assertEquals(
                'Failed to fetch a notification alert.',
                $e->getMessage()
            );
        }
    }

    public function testAddNotificationAlertWithSyncAtParameter(): void
    {
        $syncAt = $this->createDateTime('now -1 day');
        $userId = 12;
        $organizationId = 37;
        $alert = new TestNotificationAlert(
            'test_integration',
            [
                'operation'    => 'import',
                'step'         => 'get',
                'createdAt'    => $syncAt,
                'itemId'       => 456,
                'externalId'   => 'test_item_id',
                'resourceType' => 'test_entity',
                'alertType'    => 'sync',
            ]
        );

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn($userId);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                'SELECT alert.id as similarNotificationAlert FROM oro_notification_alert AS alert '
                . 'WHERE alert.operation = :operation AND alert.step = :step AND alert.item_id = :item_id '
                . 'AND alert.external_id = :external_id AND alert.resource_type = :resource_type '
                . 'AND alert.alert_type = :alert_type AND alert.source_type = :source_type '
                . 'AND alert.user_id = :user_id AND alert.organization_id = :organization_id '
                . 'AND alert.is_resolved = :is_resolved ORDER BY alert.updated_at DESC, alert.created_at DESC',
                [
                    'operation'       => 'import',
                    'step'            => 'get',
                    'item_id'         => 456,
                    'external_id'     => 'test_item_id',
                    'resource_type'   => 'test_resource',
                    'alert_type'      => 'sync',
                    'source_type'     => 'test_integration',
                    'user_id'         => 12,
                    'organization_id' => 37,
                    'is_resolved'     => false
                ],
                [
                    'operation'       => 'text',
                    'step'            => 'text',
                    'item_id'         => 'integer',
                    'external_id'     => 'text',
                    'resource_type'   => 'text',
                    'alert_type'      => 'text',
                    'source_type'     => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'is_resolved'     => 'boolean',
                ]
            )
            ->willReturn(null);
        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                [
                    'operation'       => 'text',
                    'step'            => 'text',
                    'created_at'      => 'datetime',
                    'item_id'         => 'integer',
                    'external_id'     => 'text',
                    'resource_type'   => 'text',
                    'alert_type'      => 'text',
                    'source_type'     => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'id'              => 'guid',
                    'updated_at'      => 'datetime',
                ]
            )
            ->willReturnCallback(function (string $table, array $data) use ($syncAt) {
                self::assertSame(self::SOURCE_TYPE, $data['source_type']);
                self::assertSame(self::RESOURCE_TYPE, $data['resource_type']);
                self::assertSame('import', $data['operation']);
                self::assertSame('get', $data['step']);
                self::assertSame('sync', $data['alert_type']);
                self::assertSame($syncAt, $data['created_at']);
                self::assertSame(456, $data['item_id']);
                self::assertSame('test_item_id', $data['external_id']);
                self::assertSame(12, $data['user_id']);
                self::assertSame(37, $data['organization_id']);
                self::assertIsString($data['id']);

                return 1;
            });

        self::assertIsString($this->notificationAlertManager->addNotificationAlert($alert, $syncAt));
    }

    public function testAddNotificationAlertWithoutCreatedAt(): void
    {
        $error = new TestNotificationAlert(
            'test_integration',
            [
                'operation' => 'import',
                'step'      => 'get'
            ]
        );

        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn(null);
        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                [
                    'operation'       => 'text',
                    'step'            => 'text',
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'id'              => 'guid',
                    'created_at'      => 'datetime',
                    'updated_at'      => 'datetime',
                ]
            )
            ->willReturnCallback(function (string $table, array $data) {
                self::assertInstanceOf(\DateTime::class, $data['created_at']);

                return 1;
            });

        self::assertIsString($this->notificationAlertManager->addNotificationAlert($error));
    }

    public function testAddNotificationAlertWithIdFromErrorObject(): void
    {
        $error = new TestNotificationAlert(
            'test_integration',
            [
                'id'        => 'test_id',
                'operation' => 'import',
                'step'      => 'get'
            ]
        );
        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn(null);
        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                [
                    'operation'       => 'text',
                    'step'            => 'text',
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'id'              => 'guid',
                    'created_at'      => 'datetime',
                    'updated_at'      => 'datetime',
                ]
            )
            ->willReturnCallback(function (string $table, array $data) {
                self::assertEquals('test_id', $data['id']);

                return 1;
            });

        self::assertIsString($this->notificationAlertManager->addNotificationAlert($error));
    }

    public function testAddNotificationAlertWithExceptionDuringSave(): void
    {
        $exception = new \Exception('Error during insert.', 510);
        $error = new TestNotificationAlert(
            'test_integration',
            ['operation' => 'import']
        );

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn(23);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(66);

        $this->connection->expects(self::once())
            ->method('insert')
            ->willThrowException($exception);

        try {
            $this->notificationAlertManager->addNotificationAlert($error);
            self::fail('An exception expected');
        } catch (NotificationAlertUpdateFailedException $e) {
            self::assertSame($exception, $e->getPrevious());
            self::assertSame($exception->getCode(), $e->getCode());
            self::assertEquals(
                'Failed to insert a new notification alert.',
                $e->getMessage()
            );
        }
    }

    public function testResolveNotificationAlertsByErrorTypeForCurrentUser(): void
    {
        $alertType = 'testError';
        $userId = 18;
        $organizationId = 7;

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn($userId);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                self::isType('array'),
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'alert_type'      => 'text'
                ]
            )
            ->willReturnCallback(
                function (string $table, array $values, array $data) use ($alertType, $userId, $organizationId) {
                    self::assertSame(self::SOURCE_TYPE, $data['source_type']);
                    self::assertSame(self::RESOURCE_TYPE, $data['resource_type']);
                    self::assertSame($userId, $data['user_id']);
                    self::assertSame($organizationId, $data['organization_id']);
                    self::assertSame($alertType, $data['alert_type']);
                }
            );

        $this->notificationAlertManager->resolveNotificationAlertsByAlertTypeForCurrentUser($alertType);
    }

    public function testTryToResolveNotificationAlertsByErrorTypeForCurrentUserWhenExceptionWasThrown(): void
    {
        $exception = new \Exception('Error during deletion.', 404);

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn(85);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(49);

        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                self::isType('array'),
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'alert_type'      => 'text'
                ]
            )
            ->willThrowException($exception);

        try {
            $this->notificationAlertManager->resolveNotificationAlertsByAlertTypeForCurrentUser('testType');
            self::fail('An exception expected');
        } catch (NotificationAlertUpdateFailedException $e) {
            self::assertSame($exception, $e->getPrevious());
            self::assertSame($exception->getCode(), $e->getCode());
            self::assertEquals(
                'Failed to resolve a notification alert.',
                $e->getMessage()
            );
        }
    }

    public function testResolveNotificationAlertsByErrorTypeAndStepForCurrentUser(): void
    {
        $alertType = 'testError';
        $step = 'testStep';
        $userId = 24;
        $organizationId = 6;

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn($userId);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                self::isType('array'),
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'alert_type'      => 'text',
                    'step'            => 'text'
                ]
            )
            ->willReturnCallback(
                function (string $table, array $values, array $data) use ($alertType, $step, $userId, $organizationId) {
                    self::assertSame(self::SOURCE_TYPE, $data['source_type']);
                    self::assertSame(self::RESOURCE_TYPE, $data['resource_type']);
                    self::assertSame($userId, $data['user_id']);
                    self::assertSame($organizationId, $data['organization_id']);
                    self::assertSame($alertType, $data['alert_type']);
                    self::assertSame($step, $data['step']);
                }
            );

        $this->notificationAlertManager->resolveNotificationAlertsByAlertTypeAndStepForCurrentUser($alertType, $step);
    }

    public function testTryToResolveNotificationAlertsByAlertTypeAndStepForCurrentUserWhenExceptionWasThrown(): void
    {
        $exception = new \Exception('Error during deletion.', 404);

        $this->tokenAccessor->expects(self::once())
            ->method('getUserId')
            ->willReturn(79);
        $this->tokenAccessor->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(85);

        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'oro_notification_alert',
                self::isType('array'),
                self::isType('array'),
                [
                    'source_type'     => 'text',
                    'resource_type'   => 'text',
                    'user_id'         => 'integer',
                    'organization_id' => 'integer',
                    'alert_type'      => 'text',
                    'step'            => 'text'
                ]
            )
            ->willThrowException($exception);

        try {
            $this->notificationAlertManager->resolveNotificationAlertsByAlertTypeAndStepForCurrentUser(
                'testType',
                'testStep'
            );
            self::fail('An exception expected');
        } catch (NotificationAlertUpdateFailedException $e) {
            self::assertSame($exception, $e->getPrevious());
            self::assertSame($exception->getCode(), $e->getCode());
            self::assertEquals(
                'Failed to resolve a notification alert.',
                $e->getMessage()
            );
        }
    }
}
