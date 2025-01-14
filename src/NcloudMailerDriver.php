<?php
    
    namespace Daworks\NcloudCloudOutboundMailer;
    
    use Illuminate\Support\Facades\Http;
    use Symfony\Component\Mailer\SentMessage;
    use Symfony\Component\Mailer\Transport\AbstractTransport;
    use Symfony\Component\Mime\Email;
    use Symfony\Component\Mime\MessageConverter;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\GuzzleException;
    use Psr\Log\LoggerInterface;
    use Psr\Log\NullLogger;
    
    class NcloudMailerException extends \Exception {}
    class NcloudAttachmentException extends NcloudMailerException {}
    class NcloudApiException extends NcloudMailerException {}
    
    class NcloudMailerDriver extends AbstractTransport
    {
        protected string $apiEndpoint = 'https://mail.apigw.ntruss.com/api/v1/mails';
        protected string $fileApiEndpoint = 'https://mail.apigw.ntruss.com/api/v1/files';
        protected string $authKey;
        protected string $serviceSecret;
        protected Client $client;
        protected LoggerInterface $logger;
        protected int $timeout;
        protected int $retries;
        
        public function __construct(
            string $authKey,
            string $serviceSecret,
            LoggerInterface $logger = null,
            int $timeout = 30,
            int $retries = 3
        ) {
            parent::__construct();
            
            if (empty($authKey) || empty($serviceSecret)) {
                throw new NcloudMailerException('Auth key and service secret are required');
            }
            
            $this->authKey = $authKey;
            $this->serviceSecret = $serviceSecret;
            $this->client = new Client(['timeout' => $timeout]);
            $this->logger = $logger ?? new NullLogger();
            $this->timeout = $timeout;
            $this->retries = $retries;
        }
        
        protected function doSend(SentMessage $message): void
        {
            $email = MessageConverter::toEmail($message->getOriginalMessage());
            
            try {
                $this->logger->info('Starting to send email', [
                    'subject' => $email->getSubject(),
                    'from' => $email->getFrom()[0]->getAddress(),
                    'to' => array_map(fn($to) => $to->getAddress(), $email->getTo())
                ]);
                
                $attachments = $this->uploadAttachments($email);
                
                $timestamp = $this->getTimestamp();
                $signature = $this->makeSignature($timestamp);
                
                $emailData = $this->formatEmailData($email, $attachments);
                
                $attempts = 0;
                do {
                    try {
                        $response = $this->client->post($this->apiEndpoint, [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'x-ncp-apigw-timestamp' => $timestamp,
                                'x-ncp-iam-access-key' => $this->authKey,
                                'x-ncp-apigw-signature-v2' => $signature,
                            ],
                            'json' => $emailData,
                        ]);
                        
                        $responseData = json_decode($response->getBody()->getContents(), true);
                        
                        if ($response->getStatusCode() !== 200) {
                            throw new NcloudApiException(
                                sprintf('API error: %s', $responseData['message'] ?? 'Unknown error')
                            );
                        }
                        
                        $this->logger->info('Email sent successfully', [
                            'requestId' => $responseData['requestId'] ?? null,
                            'subject' => $email->getSubject()
                        ]);
                        
                        break;
                    } catch (GuzzleException $e) {
                        $attempts++;
                        if ($attempts >= $this->retries) {
                            $this->logger->error('Failed to send email after max retries', [
                                'error' => $e->getMessage(),
                                'attempts' => $attempts
                            ]);
                            throw new NcloudMailerException(
                                'Failed to send email after ' . $this->retries . ' attempts: ' . $e->getMessage()
                            );
                        }
                        $this->logger->warning('Retry sending email', [
                            'attempt' => $attempts,
                            'error' => $e->getMessage()
                        ]);
                        sleep(1); // Wait before retry
                    }
                } while ($attempts < $this->retries);
                
            } catch (\Exception $e) {
                $this->logger->error('Email sending failed', [
                    'error' => $e->getMessage(),
                    'subject' => $email->getSubject()
                ]);
                throw $e;
            }
        }
        
        protected function formatEmailData(Email $email, array $attachments): array
        {
            $recipients = [];
            foreach ($email->getTo() as $address) {
                $recipients[] = [
                    'address' => $address->getAddress(),
                    'name' => $address->getName() ?: '',
                    'type' => 'R'
                ];
            }
            
            // Add CC recipients if any
            foreach ($email->getCc() as $address) {
                $recipients[] = [
                    'address' => $address->getAddress(),
                    'name' => $address->getName() ?: '',
                    'type' => 'C'
                ];
            }
            
            // Add BCC recipients if any
            foreach ($email->getBcc() as $address) {
                $recipients[] = [
                    'address' => $address->getAddress(),
                    'name' => $address->getName() ?: '',
                    'type' => 'B'
                ];
            }
            
            $data = [
                'senderAddress' => $email->getFrom()[0]->getAddress(),
                'senderName' => $email->getFrom()[0]->getName() ?: '',
                'title' => $email->getSubject(),
                'body' => $email->getHtmlBody() ?? $email->getTextBody(),
                'recipients' => $recipients,
                'individual' => false,
                'advertising' => false,
            ];
            
            if (!empty($attachments)) {
                $data['attachFiles'] = $attachments;
            }
            
            return $data;
        }
        
        protected function uploadAttachments(Email $email): array
        {
            $attachments = [];
            
            foreach ($email->getAttachments() as $attachment) {
                $this->logger->debug('Uploading attachment', [
                    'filename' => $attachment->getFilename()
                ]);
                
                try {
                    $response = Http::attach(
                        'fileBody',
                        $attachment->getBody()->getContents(),
                        $attachment->getFilename()
                    )->withHeaders([
                        'Content-Type' => 'multipart/form-data',
                        'x-ncp-auth-key' => $this->authKey,
                        'x-ncp-service-secret' => $this->serviceSecret,
                    ])->post($this->fileApiEndpoint);
                    
                    if ($response->successful()) {
                        $fileData = $response->json();
                        $attachments[] = [
                            'fileId' => $fileData['fileId'],
                            'fileName' => $attachment->getFilename(),
                        ];
                        
                        $this->logger->debug('Attachment uploaded successfully', [
                            'filename' => $attachment->getFilename(),
                            'fileId' => $fileData['fileId']
                        ]);
                    } else {
                        throw new NcloudAttachmentException(
                            'Failed to upload attachment: ' . $response->body()
                        );
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Attachment upload failed', [
                        'filename' => $attachment->getFilename(),
                        'error' => $e->getMessage()
                    ]);
                    throw new NcloudAttachmentException(
                        'Failed to upload attachment: ' . $e->getMessage()
                    );
                }
            }
            
            return $attachments;
        }
        
        protected function makeSignature(int $timestamp): string
        {
            $space = " ";
            $newLine = "\n";
            $method = "POST";
            $uri = "/api/v1/mails";
            
            $hmac = $method.$space.$uri.$newLine.$timestamp.$newLine.$this->authKey;
            
            return base64_encode(hash_hmac('sha256', $hmac, $this->serviceSecret, true));
        }
        
        protected function getTimestamp(): int
        {
            return (int)round(microtime(true) * 1000);
        }
        
        public function __toString(): string
        {
            return 'ncloud';
        }
    }
