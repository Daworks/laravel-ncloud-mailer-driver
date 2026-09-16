<?php

    namespace Daworks\NcloudCloudOutboundMailer;

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
            ?LoggerInterface $logger = null,
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
                            // ncloud 는 발송 요청마다 requestId 를 돌려준다. 이 값이 있어야
                            // 콘솔의 발송 내역과 애플리케이션 기록을 대조할 수 있다. 그대로
                            // 두면 SentMessage 에는 심포니가 만든 로컬 Message-ID 가 남는데,
                            // 그 값은 API 요청 본문에 실리지 않아 어디서도 조회되지 않는다.
                            //
                            // 응답 형태가 달라지더라도 이미 접수된 발송을 실패로 만들지는 않는다.
                            if (! empty($responseData['requestId'])) {
                                $message->setMessageId((string) $responseData['requestId']);
                            }

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
                $data['attachFileIds'] = $attachments;
            }

            return $data;
        }

        protected function uploadAttachments(Email $email): array
        {
            $attachments = [];
            $totalSize = 0;

            foreach ($email->getAttachments() as $attachment) {
                $filename = $attachment->getFilename();
                $fileContent = $attachment->getBody();
                $fileSize = strlen($fileContent);

                if ($fileSize > 10 * 1024 * 1024) {
                    throw new \Exception("File size exceeds 10MB limit: ".$filename);
                }

                $totalSize += $fileSize;
                if ($totalSize > 20 * 1024 * 1024) {
                    throw new \Exception("Total attachment size exceeds 20MB limit");
                }

                $attachments[] = $this->uploadAttachment($filename, $fileContent);
            }

            return $attachments;
        }

        /**
         * 첨부파일 하나를 ncloud 파일 API 에 올리고 fileId 를 돌려준다.
         *
         * ncloud 파일 API 는 간헐적으로 응답 없이 멈춘다(정상은 1~2초, 멈추면 타임아웃까지
         * 0바이트). 본문 발송과 같은 횟수만큼 재시도하고, 서명은 매 시도마다 새 타임스탬프로
         * 다시 만든다. 실패 사유는 로그와 예외 메시지에 그대로 남겨 원인을 추적할 수 있게 한다.
         */
        protected function uploadAttachment(string $filename, string $fileContent): string
        {
            $attempts = 0;

            do {
                $attempts++;
                $timestamp = $this->getTimestamp();
                $signature = $this->makeSignature($timestamp, '/api/v1/files');

                try {
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
                        return $responseData['files'][0]['fileId'];
                    }

                    $reason = sprintf('HTTP %d, code=%s', $response->getStatusCode(), $responseData['code'] ?? '-');
                } catch (GuzzleException $e) {
                    $reason = $e->getMessage();
                }

                if ($attempts >= $this->retries) {
                    $this->logger->error(Lang::get('ncloud-mailer.messages.attachment_upload_failed', [
                        'filename' => $filename
                    ]), [
                        'error'    => $reason,
                        'attempts' => $attempts
                    ]);
                    throw new \Exception(Lang::get('ncloud-mailer.messages.attachment_upload_failed', [
                        'filename' => $filename
                    ]).' ('.$reason.')');
                }

                $this->logger->warning(Lang::get('ncloud-mailer.messages.attachment_upload_retry', [
                    'filename' => $filename,
                    'attempt'  => $attempts
                ]), ['error' => $reason]);
                sleep(1);
            } while (true);
        }

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
