<?php

/**
 * This file is a part of Woketo package.
 *
 * (c) Nekland <dev@nekland.fr>
 *
 * For the full license, take a look to the LICENSE file
 * on the root directory of this project
 */

namespace Nekland\Woketo\Server;

use Nekland\Woketo\Exception\RuntimeException;
use Nekland\Woketo\Message\MessageHandlerInterface;
use Nekland\Woketo\Rfc6455\FrameFactory;
use Nekland\Woketo\Rfc6455\MessageFactory;
use Nekland\Woketo\Rfc6455\MessageHandler\CloseFrameHandler;
use Nekland\Woketo\Rfc6455\MessageHandler\RsvCheckFrameHandler;
use Nekland\Woketo\Rfc6455\MessageHandler\WrongOpcodeHandler;
use Nekland\Woketo\Rfc6455\MessageHandler\PingFrameHandler;
use Nekland\Woketo\Rfc6455\MessageProcessor;
use Nekland\Woketo\Rfc6455\ServerHandshake;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;

class WebSocketServer
{
    /**
     * @var int Store the port for debug purpose.
     */
    private $port;

    /**
     * @var string
     */
    private $address;

    /**
     * @var ServerHandshake
     */
    private $handshake;

    /**
     * @var MessageHandlerInterface
     */
    private $messageHandler;

    /**
     * @var array
     */
    private $connections;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var MessageProcessor
     */
    private $messageProcessor;

    /**
     * @var array
     */
    private $config;

    /**
     * Websocket constructor.
     *
     * @param int    $port    The number of the port to bind
     * @param string $address The address to listen on (by default 127.0.0.1)
     * @param array  $config
     */
    public function __construct($port, $address = '127.0.0.1', $config = [])
    {
        $this->setConfig($config);
        $this->address = $address;
        $this->port = $port;
        $this->handshake = new ServerHandshake();
        $this->connections = [];
        $this->buildMessageProcessor();

        // Some optimization
        \gc_enable();       // As the process never stops, the garbage collector will be usefull, you may need to call it manually sometimes for performance purpose
        \set_time_limit(0); // It's by default on most server for cli apps but better be sure of that fact
    }

    public function setMessageHandler($messageHandler)
    {
        if (!$messageHandler instanceof MessageHandlerInterface &&  !\is_string($messageHandler)) {
            throw new \InvalidArgumentException('The message handler must be an instance of MessageHandlerInterface or a string.');
        }
        if (\is_string($messageHandler)) {
            try {
                $reflection = new \ReflectionClass($messageHandler);
                if(!$reflection->implementsInterface('Nekland\Woketo\Message\MessageHandlerInterface')) {
                    throw new \InvalidArgumentException('The messageHandler must implement MessageHandlerInterface');
                }
            } catch (\ReflectionException $e) {
                throw new \InvalidArgumentException('The messageHandler must be a string representing a class.');
            }
        }
        $this->messageHandler = $messageHandler;
    }

    public function start()
    {
        if ($this->config['prod'] && \extension_loaded('xdebug')) {
            throw new \Exception('xdebug is enabled, it\'s a performance issue. Disable that extension or specify "prod" option to false.');
        }
        
        $this->loop = \React\EventLoop\Factory::create();

        $socket = new \React\Socket\Server($this->loop);
        $socket->on('connection', function ($socketStream) {
            $this->onNewConnection($socketStream);
        });
        $socket->listen($this->port);

        $this->loop->run();
    }

    /**
     * @param ConnectionInterface $socketStream
     */
    private function onNewConnection(ConnectionInterface $socketStream)
    {
        $messageHandler = $this->messageHandler;
        if (\is_string($messageHandler)) {
            $messageHandler = new $messageHandler;
        }

        $this->connections[] = new Connection($socketStream, $messageHandler, $this->loop, $this->messageProcessor);
    }

    /**
     * Build the message processor with configuration
     */
    private function buildMessageProcessor()
    {
        $this->messageProcessor = new MessageProcessor(
            new FrameFactory($this->config['frame']),
            new MessageFactory($this->config['message'])
        );
        $this->messageProcessor->addHandler(new PingFrameHandler());
        $this->messageProcessor->addHandler(new CloseFrameHandler());
        $this->messageProcessor->addHandler(new WrongOpcodeHandler());
        $this->messageProcessor->addHandler(new RsvCheckFrameHandler());

        foreach ($this->config['messageHandlers'] as $handler) {
            if (!$handler instanceof MessageHandlerInterface) {
                throw new RuntimeException(sprintf('%s is not an instance of MessageHandlerInterface but must be !', get_class($handler)));
            }
        }
    }

    /**
     * Sets the configuration
     *
     * @param array $config
     */
    private function setConfig(array $config)
    {
        $this->config = \array_merge([
            'frame' => [],
            'message' => [],
            'messageHandlers' => [],
            'prod' => true
        ], $config);
    }
}