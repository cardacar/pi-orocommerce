<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\League\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OAuth2ServerBundle\Entity\Client;
use Oro\Bundle\OAuth2ServerBundle\Entity\Manager\ClientManager;
use Oro\Bundle\OAuth2ServerBundle\League\Entity\ClientEntity;
use Oro\Bundle\OAuth2ServerBundle\League\Repository\ClientRepository;
use Oro\Bundle\OAuth2ServerBundle\Security\ApiFeatureChecker;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ClientRepositoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @deprecated
     * @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject
     */
    private $doctrine;

    /** @var ClientManager|\PHPUnit\Framework\MockObject\MockObject */
    private $clientManager;

    /** @var EncoderFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $encoderFactory;

    /** @var ApiFeatureChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $featureChecker;

    /** @var ClientRepository */
    private $clientRepository;

    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->clientManager = $this->createMock(ClientManager::class);
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->featureChecker = $this->createMock(ApiFeatureChecker::class);

        $this->clientRepository = new ClientRepository(
            $this->doctrine,
            $this->encoderFactory,
            $this->featureChecker
        );
        $this->clientRepository->setClientManager($this->clientManager);
    }

    private function getOrganization(bool $enabled = true): Organization
    {
        $organization = new Organization();
        $organization->setEnabled($enabled);

        return $organization;
    }

    public function testGetClientEntityWhenClientNotFound()
    {
        $clientIdentifier = 'test_client';

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn(null);

        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testGetClientEntityWhenNotActiveClient()
    {
        $clientIdentifier = 'test_client';
        $client = new Client();
        $client->setActive(false);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testGetClientEntityWhenGrantSupported()
    {
        $clientIdentifier = 'test_client';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGetClientEntityWhenGrantNotSupported()
    {
        $clientIdentifier = 'test_client';
        $clientGrants = ['another_grant'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');

        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testGetClientEntityWhenPasswordGrantShouldSupportRefreshTokenGrant()
    {
        $clientIdentifier = 'test_client';
        $clientGrants = ['password'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGetClientEntityWhenAuthorizationCodeGrantShouldSupportRefreshTokenGrant()
    {
        $clientIdentifier = 'test_client';
        $clientGrants = ['authorization_code'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertInstanceOf(ClientEntity::class, $clientEntity);
        self::assertEquals($clientIdentifier, $clientEntity->getIdentifier());
        self::assertEquals([], $clientEntity->getRedirectUri());
        self::assertFalse($clientEntity->isFrontend());
    }

    public function testGetClientEntityWhenOrganizationIsDisabled()
    {
        $clientIdentifier = 'test_client';
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setOrganization($this->getOrganization(false));

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');

        /** @var ClientEntity $clientEntity */
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);
        self::assertNull($clientEntity);
    }

    public function testValidateClientWhenClientNotFound()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn(null);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenNotActiveClient()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $client = new Client();
        $client->setActive(false);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenGrantSupported()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenGrantNotSupported()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenPasswordGrantShouldSupportRefreshTokenGrant()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'refresh_token';
        $clientGrants = ['password'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenAuthorizationCodeGrantShouldSupportRefreshTokenGrant()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'refresh_token';
        $clientGrants = ['authorization_code'];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenOrganizationIsDisabled()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setOrganization($this->getOrganization(false));

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->encoderFactory->expects(self::never())
            ->method('getEncoder');

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenInvalidSecret()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $client->setGrants($clientGrants);
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(false);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertFalse($result);
    }

    public function testValidateClientWhenValidSecret()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $clientRedirectUris = ['test_url'];
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris($clientRedirectUris);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }

    public function testValidateClientWhenValidSecretForFrontend()
    {
        $clientIdentifier = 'test_client';
        $clientSecret = 'test_secret';
        $grantType = 'test_grant';
        $clientGrants = ['another_grant', $grantType];
        $clientEncodedSecret = 'client_encoded_secret';
        $clientSecretSalt = 'client_secret_salt';
        $client = new Client();
        $client->setIdentifier($clientIdentifier);
        $client->setRedirectUris([]);
        $client->setGrants($clientGrants);
        $client->setSecret($clientEncodedSecret, $clientSecretSalt);
        $client->setOrganization($this->getOrganization());
        $client->setFrontend(true);
        $secretEncoder = $this->createMock(PasswordEncoderInterface::class);

        $this->clientManager->expects(self::once())
            ->method('getClient')
            ->with($clientIdentifier)
            ->willReturn($client);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);
        $this->encoderFactory->expects(self::once())
            ->method('getEncoder')
            ->with(self::identicalTo($client))
            ->willReturn($secretEncoder);
        $secretEncoder->expects(self::once())
            ->method('isPasswordValid')
            ->with($clientEncodedSecret, $clientSecret, $clientSecretSalt)
            ->willReturn(true);
        $this->featureChecker->expects(self::once())
            ->method('isEnabledByClient')
            ->with($client)
            ->willReturn(true);

        $result = $this->clientRepository->validateClient(
            $clientIdentifier,
            $clientSecret,
            $grantType
        );
        self::assertTrue($result);
    }
}
