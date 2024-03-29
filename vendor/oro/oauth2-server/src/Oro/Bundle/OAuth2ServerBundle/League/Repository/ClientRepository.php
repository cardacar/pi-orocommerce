<?php

namespace Oro\Bundle\OAuth2ServerBundle\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * The implementation of the client entity repository for "league/oauth2-server" library.
 */
class ClientRepository implements ClientRepositoryInterface
{
    /** @var EncoderFactoryInterface */
    private $encoderFactory;

    /** @var ApiFeatureChecker */
    private $featureChecker;

    /**
     * @var ManagerRegistry
     * @deprecated
     */
    private $doctrine;

    /** @var ClientManager */
    private $clientManager;

    public function __construct(
        ManagerRegistry $doctrine,
        EncoderFactoryInterface $encoderFactory,
        ApiFeatureChecker $featureChecker
    ) {
        $this->doctrine = $doctrine;
        $this->encoderFactory = $encoderFactory;
        $this->featureChecker = $featureChecker;
    }

    /**
     * @deprecated
     */
    public function setClientManager(ClientManager $clientManager): void
    {
        $this->clientManager = $clientManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        /** @var Client $client */
        $client = $this->getClient($clientIdentifier);
        if (null === $client) {
            return null;
        }

        if (!$this->isClientEnabled($client)) {
            return null;
        }

        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier($client->getIdentifier());
        $clientEntity->setRedirectUri(\array_map('strval', $client->getRedirectUris()));
        $clientEntity->setFrontend($client->isFrontend());
        $clientEntity->setConfidential($client->isConfidential());
        $clientEntity->setPlainTextPkceAllowed($client->isPlainTextPkceAllowed());

        return $clientEntity;
    }

    /**
     * {@inheritdoc}
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        $client = $this->getClient($clientIdentifier);
        if (null === $client) {
            return false;
        }

        if (!$this->isClientEnabled($client)) {
            return false;
        }

        if (!$this->isGrantSupported($client, $grantType)) {
            return false;
        }

        if (!$this->isPasswordValid($client, $clientSecret)) {
            return false;
        }

        return true;
    }

    private function getClient(string $clientId): ?Client
    {
        return $this->clientManager->getClient($clientId);
    }

    private function isClientEnabled(Client $client): bool
    {
        return
            $client->isActive()
            && $this->featureChecker->isEnabledByClient($client)
            && $client->getOrganization()->isEnabled();
    }

    private function isGrantSupported(Client $client, ?string $grant): bool
    {
        if (null === $grant) {
            return true;
        }

        $grants = $client->getGrants();
        if (empty($grants)) {
            return true;
        }

        // "refresh_token" grant should be supported for clients that support
        // "password" and "authorization_code" grants
        if ('refresh_token' === $grant
            && (
                \in_array('password', $grants, true)
                || \in_array('authorization_code', $grants, true)
            )
        ) {
            return true;
        }

        return \in_array($grant, $grants, true);
    }

    private function isPasswordValid(Client $client, ?string $clientSecret): bool
    {
        return $this->encoderFactory
            ->getEncoder($client)
            ->isPasswordValid($client->getSecret(), (string)$clientSecret, $client->getSalt());
    }
}
