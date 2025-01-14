<?php
	
	namespace Daworks\NcloudCloudOutboundMailer;
	
	use Illuminate\Support\Facades\Http;
    use Symfony\Component\Mailer\SentMessage;
	use Symfony\Component\Mailer\Transport\AbstractTransport;
	use Symfony\Component\Mime\Email;
	use Symfony\Component\Mime\MessageConverter;
	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\GuzzleException;
	
	class NcloudMailerDriver extends AbstractTransport
	{
		protected $apiEndpoint = 'https://mail.apigw.ntruss.com/api/v1/mails';
		protected $fileApiEndpoint = 'https://mail.apigw.ntruss.com/api/v1/files';
		protected $authKey;
		protected $serviceSecret;
		protected $client;
		
		public function __construct(string $authKey, string $serviceSecret)
		{
			parent::__construct();
			$this->authKey = $authKey;
			$this->serviceSecret = $serviceSecret;
			$this->client = new Client();
		}
		
		protected function doSend(SentMessage $message): void
		{
			$email = MessageConverter::toEmail($message->getOriginalMessage());
			
			try {
				$attachments = $this->uploadAttachments($email);
				
				$timestamp = $this->getTimestamp();
				$signature = $this->makeSignature($timestamp);
				
				$response = $this->client->post($this->apiEndpoint, [
					'headers' => [
						'Content-Type' => 'application/json',
						'x-ncp-apigw-timestamp' => $timestamp,
						'x-ncp-iam-access-key' => $this->authKey,
						'x-ncp-apigw-signature-v2' => $signature,
					],
					'json' => $this->formatEmailData($email, $attachments),
				]);
				
				if ($response->getStatusCode() !== 200) {
					throw new \Exception('Failed to send email: ' . $response->getBody());
				}
			} catch (GuzzleException $e) {
				throw new \Exception('HTTP request failed: ' . $e->getMessage());
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
		
		protected function makeSignature($timestamp)
		{
			$space = " ";
			$newLine = "\n";
			$method = "POST";
			$uri= "/api/v1/mails";
			$accessKey = $this->authKey;
			$secretKey = $this->serviceSecret;
			
			$hmac = $method.$space.$uri.$newLine.$timestamp.$newLine.$accessKey;
			
			return base64_encode(hash_hmac('sha256', $hmac, $secretKey, true));
		}
		
		protected function getTimestamp()
		{
			return round(microtime(true) * 1000);
		}
		
		public function __toString(): string
		{
			return 'ncloud';
		}
	}
