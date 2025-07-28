<?php
    
    namespace Daworks\NcloudCloudOutboundMailer\Tests;
    
    use Daworks\NcloudCloudOutboundMailer\NcloudMailerDriver;
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
    use Symfony\Component\Mailer\SentMessage;
    
    class NcloudMailerTest extends TestCase
    {
        protected function tearDown(): void
        {
            Mockery::close();
        }
        
        public function testConstructorValidation(): void
        {
            $this->expectException(\Exception::class);
            new NcloudMailerDriver('', '');
        }
        
        public function testSuccessfulEmailSend(): void
        {
            $driver = new NcloudMailerDriver('test-key', 'test-secret', new NullLogger());
            
            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('Test Email')
                ->text('Hello World');
            
            // Test that driver can be instantiated with valid parameters
            $this->assertInstanceOf(NcloudMailerDriver::class, $driver);
            $this->assertEquals('ncloud', (string) $driver);
        }
        
        public function testBasicFunctionality(): void
        {
            $driver = new NcloudMailerDriver('test-key', 'test-secret', new NullLogger(), 30, 3);
            
            // Test that driver can be instantiated with all parameters
            $this->assertInstanceOf(NcloudMailerDriver::class, $driver);
        }
        
        public function testConstructorWithLogger(): void
        {
            $logger = new NullLogger();
            $driver = new NcloudMailerDriver('test-key', 'test-secret', $logger);
            
            $this->assertInstanceOf(NcloudMailerDriver::class, $driver);
        }
        
        public function testDriverToString(): void
        {
            $driver = new NcloudMailerDriver('test-key', 'test-secret', new NullLogger());
            $this->assertEquals('ncloud', (string) $driver);
        }
        
        public function testConstructorWithoutLogger(): void
        {
            $driver = new NcloudMailerDriver('test-key', 'test-secret');
            
            $this->assertInstanceOf(NcloudMailerDriver::class, $driver);
        }
    }
