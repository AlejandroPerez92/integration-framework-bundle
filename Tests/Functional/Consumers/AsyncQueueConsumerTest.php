<?php

namespace Smartbox\Integration\FrameworkBundle\Tests\Unit\Consumers;

use JMS\Serializer\SerializerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartbox\Integration\FrameworkBundle\Components\Queues\AsyncQueueConsumer;
use Smartbox\Integration\FrameworkBundle\Components\Queues\Drivers\AsyncQueueDriverInterface;
use Smartbox\Integration\FrameworkBundle\Components\Queues\QueueMessage;
use Smartbox\Integration\FrameworkBundle\Components\Queues\QueueProtocol;
use Smartbox\Integration\FrameworkBundle\Core\Endpoints\EndpointInterface;
use Smartbox\Integration\FrameworkBundle\Tools\Helper\SmartesbHelper;

/**
 * Class AsyncQueueConsumerTest
 * @group async-queue-consumer
 */
class AsyncQueueConsumerTest extends TestCase
{
    /**
     * Assert that the consume function passes the correct objects to the driver
     */
    public function testConsume()
    {
        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->expects($this->any())
            ->method('getOptions')
            ->willReturn(
                [
                    QueueProtocol::OPTION_QUEUE_DRIVER => 'ya-know-what?',
                    'prefix' => 'doughnuts-',
                    'queue' => 'should-be-plain'
                ]
            );

        $driver = $this->createMock(AsyncQueueDriverInterface::class);
        $driver->expects($this->once())
            ->method('consume')
            ->with(
                $this->isType('string'),
                $this->equalTo('doughnuts-should-be-plain'),
                $this->isInstanceOf(\Closure::class)
            );

        // Consumer is extended with an anon class to fake the consumption of a message
        $consumer = new class extends AsyncQueueConsumer {
            public function waitNoBlock(EndpointInterface $endpoint)
            {
                --$this->expirationCount;
            }

        };
        $consumer->setSmartesbHelper($this->getHelper($driver));
        $consumer->setExpirationCount(1);

        $consumer->consume($endpoint, function () {});
    }

    /**
     * Callback should call the exception handler when it fails to deserialize a message. By default, the
     * ReThrowExceptionHandler is used which simply throws the exception again.
     */
    public function testUsesExceptionHandlerOnSerializationErrors()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willThrowException(new \RuntimeException('I cöuld nót dese�rialize that JSON strin��������'));

        $driver = $this->createMock(AsyncQueueDriverInterface::class);
        $driver->expects($this->once())
            ->method('getFormat')
            ->willReturn('json');

        $messageHeaders = new AMQPTable([
            'ttl' => 86400,
            'expiration' => 86400000,
            'expires' => 1584634937000,
            'destination' => 'api-test',
            'priority' => 4
        ]);

        $message = $this->getMockBuilder(AMQPMessage::class)
            ->setMethods(['setBody'])
            ->getMock();
        $message->method('setBody')->with('an amqp message');
        $message->set('application_headers', $messageHeaders);
        $message->delivery_info = ['delivery_tag' => 1];

        $consumer = new AsyncQueueConsumer();
        $consumer->setSmartesbHelper($this->getHelper($driver));
        $consumer->setSerializer($serializer);
        $callback = $consumer->callback($this->createMock(EndpointInterface::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('I cöuld nót dese�rialize that JSON strin��������');

        $callback($message);
    }

    /**
     * Test that the message id is set when deserializing it, so it can be correctly acked later.
     */
    public function testConsumerSetsMessageID()
    {
        $messageID = 42;
        $queueMessage = new QueueMessage();
        $queueMessage->setMessageId($messageID);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($queueMessage);

        $driver = $this->createMock(AsyncQueueDriverInterface::class);
        $driver->expects($this->once())
            ->method('ack')
            ->with($queueMessage);

        $consumer = $this->getMockBuilder(AsyncQueueConsumer::class)
            // Prevent the parent class from processing the message, otherwise it would require mocking buncha stuff
            ->setMethods(['process'])
            ->getMock();
        $consumer->setSerializer($serializer);
        $consumer->setSmartesbHelper($this->getHelper($driver));
        $callback = $consumer->callback($this->createMock(EndpointInterface::class));

        $message = new AMQPMessage('an amqp message');
        $message->delivery_info['delivery_tag'] = $messageID;

        $callback($message);
    }

    /**
     * Returns a SmartESBHelper mock that returns the passed driver on getQueueDriver().
     *
     * @param AsyncQueueDriverInterface $driver
     *
     * @return MockObject
     */
    protected function getHelper(AsyncQueueDriverInterface $driver)
    {
        $helper = $this->createMock(SmartesbHelper::class);
        $helper->method('getQueueDriver')
            ->willReturn($driver);

        return $helper;
    }
}
