<?php declare(strict_types=1);

namespace Gos\Bundle\WebSocketBundle\Server\Type;

use Gos\Bundle\WebSocketBundle\Event\ServerLaunchedEvent;
use Gos\Bundle\WebSocketBundle\GosWebSocketEvents;
use Gos\Bundle\WebSocketBundle\Server\App\ServerBuilderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Ratchet\Server\IoServer;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Johann Saunier <johann_27@hotmail.fr>
 */
final class WebSocketServer implements ServerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ServerBuilderInterface $serverBuilder;
    private LoopInterface $loop;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ServerBuilderInterface $serverBuilder,
        LoopInterface $loop,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->serverBuilder = $serverBuilder;
        $this->loop = $loop;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function launch(string $host, int $port, bool $profile): void
    {
        if (null !== $this->logger) {
            $this->logger->info('Starting web socket');
        }

        $server = new Server("$host:$port", $this->loop);

        $app = new IoServer(
            $this->serverBuilder->buildMessageStack(),
            $server,
            $this->loop
        );

        // Server Event Loop to add other services in the same loop.
        $this->eventDispatcher->dispatch(new ServerLaunchedEvent($this->loop, $server, $profile), GosWebSocketEvents::SERVER_LAUNCHED);

        if (null !== $this->logger) {
            $this->logger->info(
                sprintf(
                    'Launching %s on %s PID: %s',
                    $this->getName(),
                    $host.':'.$port,
                    getmypid()
                )
            );
        }

        $app->run();
    }

    public function getName(): string
    {
        return 'Ratchet';
    }
}
