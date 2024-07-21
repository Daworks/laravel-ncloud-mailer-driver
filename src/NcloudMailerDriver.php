<?php
	
	namespace Daworks\NcloudMailer;
	
	use DateTime;
	use Symfony\Component\Mailer\SentMessage;
	use Symfony\Component\Mailer\Transport\AbstractTransport;
	use Symfony\Component\Mime\MessageConverter;
	use Symfony\Component\Mime\Email;
	use Illuminate\Support\Facades\Http;
	
	class NcloudMailerDriver extends AbstractTransport
	{
		protected $apiEndpoint = 'https://mail.apigw.ntruss.com/api/v1/mails';
		protected $fileApiEndpoint = 'https://mail.apigw.ntruss.com/api/v1/files';
		protected $authKey;
		protected $serviceSecret;
		
		public function __construct(string $authKey, string $serviceSecret)
		{
			$this->authKey = $authKey;
			$this->serviceSecret = $serviceSecret;
			parent::__construct();
		}
		
		protected function makeSignature($timestamp)
		{
			$space = " ";
			$newLine = "\n";
			$method = "POST";
			$uri= "/api/v1/mails";
			$accessKey = $this->authKey;
			$secretKey = $this->serviceSecret;
			
			$hmac = $method.$space.$uri.$newLine.$timestamp.$newLine.$accessKey;
			
			return base64_encode(hash_hmac('sha256', $hmac, $secretKey,true));
		}
		
		protected function doSend(SentMessage $message): void
		{
			$email = MessageConverter::toEmail($message->getOriginalMessage());
			
			$attachments = $this->uploadAttachments($email);
			
			$timestamp = (new DateTime())->format('Uv');
			$signature = $this->makeSignature($timestamp);
			
			$response = Http::withHeaders([
				'Content-Type' => 'application/json',
				'x-ncp-apigw-timestamp' => $timestamp,
				'x-ncp-iam-access-key' => $this->authKey,
				'x-ncp-apigw-signature-v2' => $signature,
				'x-ncp-lang' => 'ko-KR'
			])->post($this->apiEndpoint, $this->formatEmailData($email, $attachments));
			
			if (!$response->successful()) {
				throw new \Exception('Failed to send email: ' . $response->body());
			}
		}
		
		protected function formatEmailData(Email $email, array $attachments): array
		{
			$data = [
				'senderAddress' => $email->getFrom()[0]->getAddress(),
				'senderName' => $email->getFrom()[0]->getName(),
				'title' => $email->getSubject(),
				'body' => $email->getHtmlBody() ?? $email->getTextBody(),
				'recipients' => [
					[
						'address' => $email->getTo()[0]->getAddress(),
						'name' => $email->getTo()[0]->getName(),
						'type' => 'R',
					]
				],
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
				} else {
					throw new \Exception('Failed to upload attachment: ' . $response->body());
				}
			}
			
			return $attachments;
		}
		
		public function __toString(): string
		{
			return 'ncloud-mailer';
		}
	}
