<?php

namespace Oro\Bundle\CampaignBundle\Model;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\CampaignBundle\Entity\EmailCampaign;
use Oro\Bundle\CampaignBundle\Provider\EmailTransportProvider;
use Oro\Bundle\CampaignBundle\Transport\TransportInterface;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\MarketingListBundle\Provider\ContactInformationFieldsProvider;
use Oro\Bundle\MarketingListBundle\Provider\MarketingListProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Sends email campaign for each entity from marketing list.
 */
class EmailCampaignSender
{
    /**
     * @var MarketingListProvider
     */
    protected $marketingListProvider;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var EmailCampaignStatisticsConnector
     */
    protected $statisticsConnector;

    /**
     * @var ContactInformationFieldsProvider
     */
    protected $contactInformationFieldsProvider;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var EmailTransportProvider
     */
    protected $emailTransportProvider;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var TransportInterface
     */
    protected $transport;

    /**
     * @var EmailCampaign
     */
    protected $emailCampaign;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(
        MarketingListProvider $marketingListProvider,
        ConfigManager $configManager,
        EmailCampaignStatisticsConnector $statisticsConnector,
        ContactInformationFieldsProvider $contactInformationFieldsProvider,
        ManagerRegistry $registry,
        EmailTransportProvider $emailTransportProvider
    ) {
        $this->marketingListProvider            = $marketingListProvider;
        $this->configManager                    = $configManager;
        $this->statisticsConnector              = $statisticsConnector;
        $this->contactInformationFieldsProvider = $contactInformationFieldsProvider;
        $this->registry                         = $registry;
        $this->emailTransportProvider           = $emailTransportProvider;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setEmailCampaign(EmailCampaign $emailCampaign)
    {
        $this->emailCampaign = $emailCampaign;

        $this->transport = $this->emailTransportProvider
            ->getTransportByName($emailCampaign->getTransport());
    }

    public function send()
    {
        if (!$this->assertTransport()) {
            return;
        }

        $marketingList = $this->emailCampaign->getMarketingList();
        if (is_null($marketingList)) {
            return;
        }

        $iterator = $this->getIterator();
        /** @var EntityManager $manager */
        $manager = $this->registry->getManager();
        $emailFields = $this->contactInformationFieldsProvider
            ->getMarketingListTypedFields(
                $marketingList,
                ContactInformationFieldsProvider::CONTACT_INFORMATION_SCOPE_EMAIL
            );
        foreach ($iterator as $item) {
            $entity = array_shift($item);

            $toFromFields = $this->contactInformationFieldsProvider->getTypedFieldsValues($emailFields, $item);
            try {
                $toFromEntity = $this->contactInformationFieldsProvider->getTypedFieldsValues($emailFields, $entity);
            } catch (NoSuchPropertyException $e) {
                $toFromEntity = [];
            }

            /**
             * Filter empty values (Contact information could be empty for some rows)
             */
            $to = array_filter(array_unique(array_merge($toFromFields, $toFromEntity)));

            /**
             * Check if row has not empty contact information. If Contact information is empty - skip
             */
            if (!$to) {
                $this->logger->info('Email sending skipped. Reason: Recipients Contact information is blank.');

                continue;
            }

            try {
                $manager->beginTransaction();
                // Do actual send
                $this->transport->send(
                    $this->emailCampaign,
                    $entity,
                    [$this->getSenderEmail() => $this->getSenderName()],
                    $to
                );

                $statisticsRecord = $this->statisticsConnector->getStatisticsRecord($this->emailCampaign, $entity);
                // Mark marketing list item as contacted
                $statisticsRecord->getMarketingListItem()->contact();

                $manager->flush($statisticsRecord);
                $manager->commit();
            } catch (\Exception $e) {
                $manager->rollback();

                if ($this->logger) {
                    $this->logger->error(
                        sprintf('Email sending to "%s" failed.', implode(', ', $to)),
                        ['exception' => $e]
                    );
                }
            }
        }
    }

    /**
     * Assert that transport is present.
     *
     * @return bool
     * @throws \RuntimeException
     */
    protected function assertTransport()
    {
        if (!$this->transport) {
            throw new \RuntimeException('Transport is required to perform send');
        }

        $transportSettings = $this->emailCampaign->getTransportSettings();
        if ($transportSettings) {
            $errors = $this->validator->validate($transportSettings);

            if (count($errors) > 0) {
                $this->logger->error('Email sending failed. Transport settings are not valid.');

                return false;
            }
        }

        return true;
    }

    /**
     * @return string
     */
    protected function getSenderEmail()
    {
        if ($senderEmail = $this->emailCampaign->getSenderEmail()) {
            return $senderEmail;
        }

        return $this->configManager->get('oro_campaign.campaign_sender_email');
    }

    /**
     * @return string
     */
    protected function getSenderName()
    {
        if ($senderName = $this->emailCampaign->getSenderName()) {
            return $senderName;
        }

        return $this->configManager->get('oro_campaign.campaign_sender_name');
    }

    /**
     * @return \Iterator
     */
    protected function getIterator()
    {
        return $this->marketingListProvider->getEntitiesIterator(
            $this->emailCampaign->getMarketingList()
        );
    }

    /**
     * @param ValidatorInterface $validator
     * @return EmailCampaignSender
     */
    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;

        return $this;
    }
}
