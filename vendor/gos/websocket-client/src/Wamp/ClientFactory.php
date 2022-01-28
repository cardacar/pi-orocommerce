<?php

namespace Gos\Component\WebSocketClient\Wamp;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

trigger_deprecation('gos/websocket-client', '1.2', 'The package is deprecated, use "ratchet/pawl" instead.');

/**
 * @deprecated the package is deprecated, use "ratchet/pawl" instead
 */
final class ClientFactory implements ClientFactoryInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $this->resolveConfig($config);
    }

    public function createConnection(): ClientInterface
    {
        $client = new Client(
            $this->config['host'],
            $this->config['port'],
            $this->config['ssl'],
            $this->config['origin']
        );

        if (null !== $this->logger) {
            $client->setLogger($this->logger);
        }

        return $client;
    }

    private function resolveConfig(array $config): array
    {
        $resolver = new OptionsResolver();

        $resolver->setRequired(
            [
                'host',
                'port',
            ]
        );

        $resolver->setDefaults(
            [
                'ssl' => false,
                'origin' => null,
            ]
        );

        $resolver->setAllowedTypes('host', 'string');
        $resolver->setAllowedTypes('port', ['string', 'integer']);
        $resolver->setAllowedTypes('ssl', 'boolean');
        $resolver->setAllowedTypes('origin', ['string', 'null']);

        return $resolver->resolve($config);
    }
}
