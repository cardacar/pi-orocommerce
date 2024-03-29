<?php

namespace Oro\Bundle\ChannelBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\AccountBundle\Entity\Account;
use Oro\Bundle\ChannelBundle\Entity\LifetimeValueHistory;
use Oro\Bundle\CurrencyBundle\Query\CurrencyQueryBuilderTransformerInterface;
use Oro\Bundle\SalesBundle\Entity\Customer;
use Oro\Bundle\SalesBundle\Entity\Manager\AccountCustomerManager;
use Oro\Bundle\SalesBundle\Entity\Opportunity;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Schedules accounts for deletion that has no customers assigned.
 */
class AccountLifetimeListener implements ServiceSubscriberInterface
{
    /** @var ContainerInterface */
    private $container;

    /** @var Account[] */
    private $accounts = [];

    /** @var array|null */
    private $customerTargetFields;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices()
    {
        return [
            'oro_currency.query.currency_transformer' => CurrencyQueryBuilderTransformerInterface::class,
            'oro_sales.manager.account_customer' => AccountCustomerManager::class
        ];
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($this->getChangedEntities($uow) as $entity) {
            if ($entity instanceof Opportunity) {
                $this->scheduleOpportunityAccount($entity, $uow);
            } elseif ($entity instanceof Customer) {
                $this->scheduleCustomerAccounts($entity, $uow);
            }
        }
    }

    /**
     * @param UnitOfWork $uow
     * @return \Generator
     */
    protected function getChangedEntities(UnitOfWork $uow)
    {
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            yield $entity;
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            yield $entity;
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            yield $entity;
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (!$this->accounts) {
            return;
        }

        $em = $args->getEntityManager();
        $lifetimeRepository = $em->getRepository(LifetimeValueHistory::class);
        $lifetimeAmountQb = $this->getLifetimeAmountQueryBuilder($em);

        $historyUpdates = [];
        foreach ($this->accounts as $account) {
            $lifetimeAmountQb->setParameter('account', $account->getId());

            $lifetimeAmount = (double)$lifetimeAmountQb->getQuery()->getSingleScalarResult();

            $history = new LifetimeValueHistory();
            $history->setAmount($lifetimeAmount);
            $history->setAccount($account);
            $em->persist($history);

            $historyUpdates[] = [$account, null, $history];
        }

        $this->accounts = [];
        $em->flush();
        $lifetimeRepository->massStatusUpdate($historyUpdates);
    }

    public function onClear(OnClearEventArgs $event)
    {
        $this->customerTargetFields = null;
    }

    /**
     * @param string $customerAlias
     *
     * @return string
     */
    private function createNoCustomerCondition($customerAlias)
    {
        return implode(
            ' AND ',
            array_map(
                function ($customerTargetField) use ($customerAlias) {
                    return sprintf('%s.%s IS NULL', $customerAlias, $customerTargetField);
                },
                $this->getCustomerTargetFields()
            )
        );
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function scheduleOpportunityAccount(Opportunity $entity, UnitOfWork $uow)
    {
        $customerAssociation = $entity->getCustomerAssociation();
        if (null === $customerAssociation) {
            return;
        }

        $account = $customerAssociation->getTarget();
        if (!$account instanceof Account) {
            return;
        }

        $changeSet = $uow->getEntityChangeSet($entity);
        if ($uow->isScheduledForDelete($entity) ||
            (array_intersect(['closeRevenueValue', 'status', 'customerAssociation'], array_keys($changeSet)) &&
                (($entity->getStatus() && $entity->getStatus()->getId() === Opportunity::STATUS_WON) ||
                    !empty($changeSet['status'][0]) && $changeSet['status'][0]->getId() === Opportunity::STATUS_WON))
        ) {
            if (isset($changeSet['customerAssociation'])) {
                [$oldCustomer] = $changeSet['customerAssociation'];
                if ($oldCustomer && $oldCustomer->getTarget() instanceof Account) {
                    $oldAccount = $oldCustomer->getTarget();
                    if ($oldAccount->getId() !== $account->getId()) {
                        $this->scheduleAccount($oldAccount, $uow);
                    }
                }
            }
            $this->scheduleAccount($account, $uow);
        }
    }

    private function scheduleCustomerAccounts(Customer $entity, UnitOfWork $uow)
    {
        $changeSet = $uow->getEntityChangeSet($entity);
        if (isset($changeSet['account'])) {
            [$oldAccount, $newAccount] = $changeSet['account'];
            if ($oldAccount) {
                $this->scheduleAccount($oldAccount, $uow);
            }
            if ($newAccount && (!$oldAccount || $oldAccount->getId() !== $newAccount->getId())) {
                $this->scheduleAccount($newAccount, $uow);
            }
        } elseif (array_intersect($this->getCustomerTargetFields(), array_keys($changeSet))) {
            $account = $entity->getAccount();
            $this->scheduleAccount($account, $uow);
        }
    }

    private function scheduleAccount(Account $account, UnitOfWork $uow)
    {
        if ($uow->isScheduledForDelete($account)) {
            return;
        }

        $this->accounts[spl_object_hash($account)] = $account;
    }

    /**
     * @param EntityManager $em
     *
     * @return QueryBuilder
     */
    private function getLifetimeAmountQueryBuilder(EntityManager $em)
    {
        /** @var CurrencyQueryBuilderTransformerInterface $transformer */
        $transformer = $this->container->get('oro_currency.query.currency_transformer');

        $qb = $em->getRepository(Opportunity::class)->createQueryBuilder('o');
        $qb
            ->select(sprintf('SUM(%s)', $transformer->getTransformSelectQuery('closeRevenue', $qb, 'o')))
            ->join('o.customerAssociation', 'c')
            ->andWhere('c.account = :account')
            ->andWhere('o.status = :status')
            ->setParameter('status', Opportunity::STATUS_WON);

        $noCustomerCondition = $this->createNoCustomerCondition('c');
        if ($noCustomerCondition) {
            $qb->andWhere($noCustomerCondition);
        }

        return $qb;
    }

    /**
     * @return array
     */
    private function getCustomerTargetFields()
    {
        if (null === $this->customerTargetFields) {
            /** @var AccountCustomerManager $accountCustomerManager */
            $accountCustomerManager = $this->container->get('oro_sales.manager.account_customer');
            $this->customerTargetFields = $accountCustomerManager->getCustomerTargetFields();
        }

        return $this->customerTargetFields;
    }
}
