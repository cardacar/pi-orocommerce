<?php

namespace Oro\Bundle\SecurityBundle\Tests\Functional\Acl;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Tests\Functional\AclQuery\AclTestCase;
use Oro\Bundle\SecurityBundle\Tests\Functional\DataFixtures\LoadRolesData;
use Oro\Bundle\TestFrameworkBundle\Entity\TestEntityWithUserOwnership as TestEntity;
use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class FieldAclTest extends AclTestCase
{
    /** @var TestEntity */
    protected $testEntity;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadFixtures([
            '@OroSecurityBundle/Tests/Functional/DataFixtures/load_test_data.yml',
            LoadRolesData::class
        ]);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine')->getManagerForClass('OroUserBundle:User');
        $this->testEntity = $em->getRepository(TestEntity::class)
            ->createQueryBuilder('e')
            ->orderBy('e.id')
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
        if (null === $this->testEntity) {
            $this->testEntity = new TestEntity();
            $this->testEntity
                ->setName('test')
                ->setOrganization($this->getReference('organization'))
                ->setOwner($em->getRepository('OroUserBundle:User')->findOneBy(['email' => self::AUTH_USER]));
            $em->persist($this->testEntity);
            $em->flush();
        }
    }

    protected function getAuthorizationChecker(): AuthorizationCheckerInterface
    {
        return self::getContainer()->get('security.authorization_checker');
    }

    public function testUserShouldHaveAccessToFieldsWithDefauldData(): void
    {
        $this->authenticateUser('user_has_all_bu');

        self::assertTrue(
            $this->getAuthorizationChecker()->isGranted('VIEW', new FieldVote($this->testEntity, 'name'))
        );
    }

    public function testUserShouldHaveAccessToFieldsWhenOneRoleHaveNoAccess(): void
    {
        $this->updateRolePermissionsForField(
            'ROLE_SECOND',
            TestEntity::class,
            'name',
            [
                'VIEW' => AccessLevel::NONE_LEVEL,
                'EDIT' => AccessLevel::NONE_LEVEL
            ]
        );

        $this->authenticateUser('user_has_all_bu');

        self::assertTrue(
            $this->getAuthorizationChecker()->isGranted('VIEW', new FieldVote($this->testEntity, 'name'))
        );
    }

    public function testUserShouldNotHaveAccessToFieldsWhenBothRolesHaveNoAccess(): void
    {
        $this->updateRolePermissionsForField(
            'ROLE_SECOND',
            TestEntity::class,
            'name',
            [
                'VIEW' => AccessLevel::NONE_LEVEL,
                'EDIT' => AccessLevel::NONE_LEVEL
            ]
        );
        $this->updateRolePermissionsForField(
            'ROLE_THIRD',
            TestEntity::class,
            'name',
            [
                'VIEW' => AccessLevel::NONE_LEVEL,
                'EDIT' => AccessLevel::NONE_LEVEL
            ]
        );

        $this->authenticateUser('user_has_all_bu');

        self::assertFalse(
            $this->getAuthorizationChecker()->isGranted('VIEW', new FieldVote($this->testEntity, 'name'))
        );
    }
}
