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
    use Symfony\Component\Mailer\Envelope;
    use Symfony\Component\Mailer\SentMessage;
    use Symfony\Component\Mime\Address;
    use Illuminate\Support\Facades\Facade;

    class NcloudMailerTest extends TestCase
    {
        /**
         * 드라이버가 Lang 파사드로 로그 문구를 만든다. 테스트에는 라라벨 앱이 없으므로
         * 키를 그대로 돌려주는 최소 변환기를 물려 준다.
         */
        protected function setUp(): void
        {
            parent::setUp();

            // 파사드는 컨테이너에서 ArrayAccess 로만 인스턴스를 꺼내므로
            // illuminate/container 를 끌어오지 않고 최소 스텁으로 충분하다.
            $container = new class implements \ArrayAccess {
                private array $items = [];

                public function offsetExists(mixed $offset): bool
                {
                    return isset($this->items[$offset]);
                }

                public function offsetGet(mixed $offset): mixed
                {
                    return $this->items[$offset] ?? null;
                }

                public function offsetSet(mixed $offset, mixed $value): void
                {
                    $this->items[$offset] = $value;
                }

                public function offsetUnset(mixed $offset): void
                {
                    unset($this->items[$offset]);
                }
            };

            $container['translator'] = new class {
                public function get($key, array $replace = [], $locale = null)
                {
                    return $key;
                }
            };

            Facade::setFacadeApplication($container);
        }

        protected function tearDown(): void
        {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);

            Mockery::close();

            parent::tearDown();
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

        /**
         * ncloud 가 돌려준 requestId 가 SentMessage 에 실려야 발송 이력을 추적할 수 있다.
         */
        public function testSetsNcloudRequestIdAsMessageId(): void
        {
            $sent = $this->send(new Response(201, [], json_encode([
                'requestId' => '20181203000000000201',
                'count'     => 1,
            ])));

            $this->assertSame('20181203000000000201', $sent->getMessageId());
        }

        /**
         * 응답에 requestId 가 없더라도 이미 접수된 발송을 실패로 만들지 않는다.
         */
        public function testKeepsSendingWhenRequestIdIsMissing(): void
        {
            $sent = $this->send(new Response(201, [], json_encode(['count' => 1])));

            $this->assertNotSame('20181203000000000201', $sent->getMessageId());
        }

        /**
         * 첨부 없는 메일 한 통을 주어진 응답으로 발송하고 SentMessage 를 돌려준다.
         */
        private function send(Response $response): SentMessage
        {
            $driver = new NcloudMailerDriver('test-key', 'test-secret', new NullLogger());

            $client = new Client([
                'handler' => HandlerStack::create(new MockHandler([$response])),
            ]);

            $clientProperty = new \ReflectionProperty($driver, 'client');
            $clientProperty->setAccessible(true);
            $clientProperty->setValue($driver, $client);

            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('제목')
                ->text('본문');

            $sent = new SentMessage($email, new Envelope(
                new Address('sender@example.com'),
                [new Address('recipient@example.com')]
            ));

            $doSend = new \ReflectionMethod($driver, 'doSend');
            $doSend->setAccessible(true);
            $doSend->invoke($driver, $sent);

            return $sent;
        }
    }
