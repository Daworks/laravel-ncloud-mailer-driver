<?php
    
    namespace Daworks\NcloudCloudOutboundMailer;
    
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\Lang;
    use Symfony\Component\Mailer\SentMessage;
    use Symfony\Component\Mailer\Transport\AbstractTransport;
    use Symfony\Component\Mime\Email;
    use Symfony\Component\Mime\MessageConverter;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\GuzzleException;
    use Psr\Log\LoggerInterface;
    use Psr\Log\NullLogger;
    
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
        
        // Ncloud API 에러 코드 정의
        protected const ERROR_MESSAGES = [
            77101 => 'Login information error',
            77102 => 'Bad request',
            77103 => 'Requested resource does not exist',
            77201 => 'No permission for the requested resource',
            77202 => 'Email service not subscribed',
            77001 => 'Method not allowed',
            77002 => 'Unsupported media type',
            77301 => 'Default project does not exist',
            77302 => 'External system API integration error',
            77303 => 'Internal server error'
        ];
        
        public function __construct(
            string $authKey,
            string $serviceSecret,
            LoggerInterface $logger = null,
            int $timeout = 30,
            int $retries = 3
        ) {
            parent::__construct();
            
            if (empty($authKey) || empty($serviceSecret)) {
                throw new \Exception('Auth key and service secret are required');
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
                $this->logger->info(Lang::get('ncloud-mailer.messages.sending'), [
                    'subject' => $email->getSubject(),
                    'from'    => $email->getFrom()[0]->getAddress(),
                    'to'      => array_map(fn($to) => $to->getAddress(), $email->getTo())
                ]);
                
                $attachments = $this->uploadAttachments($email);
                
                $timestamp = $this->getTimestamp();
                $signature = $this->makeSignature($timestamp, '/api/v1/mails');
                
                $emailData = $this->formatEmailData($email, $attachments);
                
                $attempts = 0;
                do {
                    try {
                        $response = $this->client->post($this->apiEndpoint, [
                            'headers' => [
                                'Content-Type'             => 'application/json',
                                'x-ncp-apigw-timestamp'    => $timestamp,
                                'x-ncp-iam-access-key'     => $this->authKey,
                                'x-ncp-apigw-signature-v2' => $signature,
                            ],
                            'json'    => $emailData,
                        ]);
                        
                        $statusCode = $response->getStatusCode();
                        $responseData = json_decode($response->getBody()->getContents(), true);
                        
                        if ($statusCode === 201) {
                            $this->logger->info(Lang::get('ncloud-mailer.messages.sent_success'), [
                                'requestId' => $responseData['requestId'] ?? null,
                                'count'     => $responseData['count'] ?? 1
                            ]);
                            break;
                        }
                        
                        // 에러 응답 처리
                        $errorCode = $responseData['code'] ?? null;
                        $statusMessage = Lang::get('ncloud-mailer.status.'.$statusCode);
                        $errorMessage = $this->getErrorMessage($errorCode);
                        
                        $fullErrorMessage = "{$statusMessage}: {$errorMessage}";
                        throw new \Exception($fullErrorMessage);
                        
                    } catch (GuzzleException $e) {
                        $attempts++;
                        if ($attempts >= $this->retries) {
                            $this->logger->error(Lang::get('ncloud-mailer.messages.max_retries_exceeded', [
                                'max_retries' => $this->retries
                            ]), [
                                'error'    => $e->getMessage(),
                                'attempts' => $attempts
                            ]);
                            throw new \Exception($e->getMessage());
                        }
                        
                        $this->logger->warning(Lang::get('ncloud-mailer.messages.retry_attempt', [
                            'attempt' => $attempts
                        ]), [
                            'error' => $e->getMessage()
                        ]);
                        sleep(1);
                    }
                } while ($attempts < $this->retries);
                
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), [
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
                    'name'    => $address->getName() ?: '',
                    'type'    => 'R'
                ];
            }
            
            // Add CC recipients if any
            foreach ($email->getCc() as $address) {
                $recipients[] = [
                    'address' => $address->getAddress(),
                    'name'    => $address->getName() ?: '',
                    'type'    => 'C'
                ];
            }
            
            // Add BCC recipients if any
            foreach ($email->getBcc() as $address) {
                $recipients[] = [
                    'address' => $address->getAddress(),
                    'name'    => $address->getName() ?: '',
                    'type'    => 'B'
                ];
            }
            
            $data = [
                'senderAddress' => $email->getFrom()[0]->getAddress(),
                'senderName'    => $email->getFrom()[0]->getName() ?: '',
                'title'         => $email->getSubject(),
                'body'          => $email->getHtmlBody() ?? $email->getTextBody(),
                'recipients'    => $recipients,
                'individual'    => false,
                'advertising'   => false,
            ];
            
            if (!empty($attachments)) {
                $data['attachFileIds'] = $attachments; // 변경: attachFiles -> attachFileIds
            }
            
            return $data;
        }
        
        protected function uploadAttachments(Email $email): array
        {
            $attachments = [];
            $totalSize = 0;
            
            foreach ($email->getAttachments() as $attachment) {
                $filename = $attachment->getFilename();
                $fileContent = $attachment->getBody(); // toString() 제거
                $fileSize = strlen($fileContent);
                
                // 파일 크기 검증
                if ($fileSize > 10 * 1024 * 1024) {
                    throw new \Exception("File size exceeds 10MB limit: ".$filename);
                }
                
                $totalSize += $fileSize;
                if ($totalSize > 20 * 1024 * 1024) {
                    throw new \Exception("Total attachment size exceeds 20MB limit");
                }
                
                $timestamp = $this->getTimestamp();
                $signature = $this->makeSignature($timestamp, '/api/v1/files');
                
                try {
                    // multipart/form-data 형식으로 파일 업로드
                    $response = $this->client->post($this->fileApiEndpoint, [
                        'headers'   => [
                            'x-ncp-apigw-timestamp'    => $timestamp,
                            'x-ncp-iam-access-key'     => $this->authKey,
                            'x-ncp-apigw-signature-v2' => $signature,
                        ],
                        'multipart' => [
                            [
                                'name'     => 'fileList',
                                'contents' => $fileContent,
                                'filename' => $filename
                            ]
                        ]
                    ]);
                    
                    $responseData = json_decode($response->getBody()->getContents(), true);
                    
                    if ($response->getStatusCode() === 201 && isset($responseData['files'][0]['fileId'])) {
                        $attachments[] = $responseData['files'][0]['fileId'];
                        $this->logger->info('File uploaded successfully', [
                            'filename' => $filename,
                            'fileId'   => $responseData['files'][0]['fileId']
                        ]);
                    } else {
                        throw new \Exception("File upload failed: Invalid response");
                    }
                    
                } catch (\Exception $e) {
                    $this->logger->error("File upload failed", [
                        'filename' => $filename,
                        'error'    => $e->getMessage()
                    ]);
                    throw new \Exception("첨부파일 업로드에 실패했습니다: ".$filename);
                }
            }
            
            return $attachments;
        }

// 파일 업로드용 서명 생성 메서드 추가
        protected function makeSignature(int $timestamp, string $uri): string
        {
            $space = " ";
            $newLine = "\n";
            $method = "POST";
            
            $hmac = $method.$space.$uri.$newLine.$timestamp.$newLine.$this->authKey;
            
            return base64_encode(hash_hmac('sha256', $hmac, $this->serviceSecret, true));
        }
        
        protected function getTimestamp(): int
        {
            return (int) round(microtime(true) * 1000);
        }
        
        public function __toString(): string
        {
            return 'ncloud';
        }
        
        protected function getErrorMessage(?int $code): string
        {
            if ($code === null) {
                return Lang::get('ncloud-mailer.messages.unknown_error');
            }
            
            return Lang::get('ncloud-mailer.errors.'.$code) ??
                Lang::get('ncloud-mailer.messages.unknown_error_code', ['code' => $code]);
        }
    }
