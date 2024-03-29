<?php

namespace Oro\Bundle\UserBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectRepository;
use Oro\Bundle\SecurityBundle\Acl\Persistence\AclSidManager;
use Oro\Bundle\UserBundle\Entity\AbstractRole;
use Oro\Component\DependencyInjection\ServiceLink;
use Symfony\Component\Security\Core\Role\Role;

class RoleListener
{
    /**
     * @var ServiceLink
     */
    protected $aclSidManagerLink;

    public function __construct(ServiceLink $aclSidManagerLink)
    {
        $this->aclSidManagerLink = $aclSidManagerLink;
    }

    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        if ($eventArgs->getEntity() instanceof RoleInterface && $eventArgs->hasChangedField('role')) {
            $oldRoleName = $eventArgs->getOldValue('role');
            $newRoleName = $eventArgs->getNewValue('role');
            /** @var $aclSidManager AclSidManager */
            $aclSidManager = $this->aclSidManagerLink->getService();
            $aclSidManager->updateSid($aclSidManager->getSid($newRoleName), $oldRoleName);
        }
    }

    /**
     * Pre persist event listener
     *
     * @throws \LogicException
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof AbstractRole) {
            /**
             * @var integer $count
             * count of attempts to set unique role, maximum 10 else exception
             */
            $count = 1;
            $repository = $args->getEntityManager()->getRepository('OroUserBundle:Role');
            do {
                $updateRequired = !$this->updateRole($entity, $repository) && $count < 10;
                $count++;
            } while ($updateRequired);

            if ($count > 10) {
                throw new \LogicException('10 attempts to generate unique role are failed.');
            }
        }
    }

    /**
     * Update role field.
     *
     * @param AbstractRole $role
     * @param ObjectRepository $repository
     * @return bool
     */
    protected function updateRole(AbstractRole $role, ObjectRepository $repository)
    {
        if ($role->getRole()) {
            return true;
        }

        $roleValue = $role->generateUniqueRole();
        if ($repository->findOneBy(['role' => $roleValue])) {
            return false;
        }

        $role->setRole($roleValue, false);

        return true;
    }
}
