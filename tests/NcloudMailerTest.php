<?php
    
    namespace Daworks\NcloudCloudOutboundMailer\Tests;
    
    use Daworks\NcloudCloudOutboundMailer\NcloudMailerDriver;
    use Daworks\NcloudCloudOutboundMailer\NcloudMailerException;
    use GuzzleHttp\Client;
    use GuzzleHttp\Handler\MockHandler;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Psr7\Response;
    use Mockery;
    use PHPUnit\Framework\TestCase;
    use Psr\Log\NullLogger;
    use Symfony\Component\Mime\Email;
    use Symfony\Component\Mime\Part\DataPart;
    use Symfony\Component\Mime\Part\File;
    
    class NcloudMailerTest extends TestCase
    {
        protected function tearDown(): void
        {
            Mockery::close();
        }
        
        public function testConstructorValidation(): void
        {
            $this->expectException(NcloudMailerException::class);
            new NcloudMailerDriver('', '');
        }
        
        public function testSuccessfulEmailSend(): void
        {
            // Mock successful API responses
            $mock = new MockHandler([
                new Response(200, [], json_encode(['requestId' => 'test-123'])),
            ]);
            
            $handlerStack = HandlerStack::create($mock);
            $client = new Client(['handler' => $handlerStack]);
            
            $driver = $this->getMockBuilder(NcloudMailerDriver::class)
                ->setConstructorArgs(['test-key', 'test-secret', new NullLogger()])
                ->onlyMethods(['getClient'])
                ->getMock();
            
            $driver->method('getClient')->willReturn($client);
            
            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('Test Email')
                ->text('Hello World');
            
            // This should not throw an exception
            $driver->send($email);
        }
        
        public function testEmailWithAttachment(): void
        {
            // Mock successful file upload and email send responses
            $mock = new MockHandler([
                new Response(200, [], json_encode(['fileId' => 'file-123'])),
                new Response(200, [], json_encode(['requestId' => 'test-123']))
            ]);
            
            $handlerStack = HandlerStack::create($mock);
            $client = new Client(['handler' => $handlerStack]);
            
            $driver = $this->getMockBuilder(NcloudMailerDriver::class)
                ->setConstructorArgs(['test-key', 'test-secret', new NullLogger()])
                ->onlyMethods(['getClient'])
                ->getMock();
            
            $driver->method('getClient')->willReturn($client);
            
            $attachment = new DataPart(new File(__DIR__ . '/fixtures/test.txt'));
            
            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('Test Email with Attachment')
                ->text('Hello World')
                ->addPart($attachment);
            
            // This should not throw an exception
            $driver->send($email);
        }
        
        public function testMultipleRecipients(): void
        {
            $mock = new MockHandler([
                new Response(200, [], json_encode(['requestId' => 'test-123'])),
            ]);
            
            $handlerStack = HandlerStack::create($mock);
            $client = new Client(['handler' => $handlerStack]);
            
            $driver = $this->getMockBuilder(NcloudMailerDriver::class)
                ->setConstructorArgs(['test-key', 'test-secret', new NullLogger()])
                ->onlyMethods(['getClient'])
                ->getMock();
            
            $driver->method('getClient')->willReturn($client);
            
            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient1@example.com', 'recipient2@example.com')
                ->cc('cc@example.com')
                ->bcc('bcc@example.com')
                ->subject('Test Email')
                ->text('Hello World');
            
            // This should not throw an exception
            $driver->send($email);
        }
        
        public function testFailedApiCall(): void
        {
            $this->expectException(NcloudMailerException::class);
            
            $mock = new MockHandler([
                new Response(400, [], json_encode(['message' => 'Invalid request'])),
            ]);
            
            $handlerStack = HandlerStack::create($mock);
            $client = new Client(['handler' => $handlerStack]);
            
            $driver = $this->getMockBuilder(NcloudMailerDriver::class)
                ->setConstructorArgs(['test-key', 'test-secret', new NullLogger()])
                ->onlyMethods(['getClient'])
                ->getMock();
            
            $driver->method('getClient')->willReturn($client);
            
            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('Test Email')
                ->text('Hello World');
            
            $driver->send($email);
        }
        
        public function testRetryLogic(): void
        {
            $mock = new MockHandler([
                new Response(500, [], 'Server Error'),
                new Response(500, [], 'Server Error'),
                new Response(200, [], json_encode(['requestId' => 'test-123'])),
            ]);
            
            $handlerStack = HandlerStack::create($mock);
            $client = new Client(['handler' => $handlerStack]);
            
            $logger = Mockery::mock(\Psr\Log\LoggerInterface::class);
            $logger->shouldReceive('warning')->times(2);
            $logger->shouldReceive('info')->atLeast(1);
            
            $driver = $this->getMockBuilder(NcloudMailerDriver::class)
                ->setConstructorArgs(['test-key', 'test-secret', $logger])
                ->onlyMethods(['getClient'])
                ->getMock();
            
            $driver->method('getClient')->willReturn($client);
            
            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('Test Email')
                ->text('Hello World');
            
            // This should succeed on the third try
            $driver->send($email);
        }
    }
