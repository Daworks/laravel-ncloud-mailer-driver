# NCloud Outbound Mailer Driver for Laravel

이 패키지는 Laravel 프레임워크에서 NCloud Cloud Outbound Mailer를 사용할 수 있게 해주는 메일러 드라이버입니다.

## 요구사항

- PHP 8.2 이상
- Laravel 9.0 이상
- NCloud Cloud Outbound Mailer 서비스 계정

## 설치

Composer를 통해 패키지를 설치할 수 있습니다:

```bash
composer require daworks/ncloud-outbound-mailer-driver
```

## 설정

1. 설정 파일을 발행합니다:

```bash
php artisan vendor:publish --provider="Daworks\NcloudCloudOutboundMailer\NcloudCloudOutboundMailerServiceProvider"
```

2. `.env` 파일에 NCloud 인증 정보를 추가합니다:

```env
NCLOUD_AUTH_KEY=your-auth-key
NCLOUD_SERVICE_SECRET=your-service-secret
NCLOUD_MAIL_TIMEOUT=30
NCLOUD_MAIL_RETRIES=3
NCLOUD_MAIL_DEBUG=false
```

3. `config/mail.php`에 NCloud 메일러를 추가합니다:

```php
'mailers' => [
    'ncloud' => [
        'transport' => 'ncloud',
    ],
],
```

## 사용법

### 기본 사용법

```php
Mail::to('recipient@example.com')
    ->send(new MyMailable());
```

### 첨부 파일 사용

```php
Mail::to('recipient@example.com')
    ->send(new MyMailable($attachment));
```

### 다중 수신자

```php
Mail::to(['recipient1@example.com', 'recipient2@example.com'])
    ->cc('cc@example.com')
    ->bcc('bcc@example.com')
    ->send(new MyMailable());
```

## 고급 설정

### 타임아웃 설정

API 요청 타임아웃을 설정할 수 있습니다:

```php
'timeout' => env('NCLOUD_MAIL_TIMEOUT', 30),
```

### 재시도 설정

실패한 요청에 대한 재시도 횟수를 설정할 수 있습니다:

```php
'retries' => env('NCLOUD_MAIL_RETRIES', 3),
```

## 문제 해결

### 로깅

디버그 모드를 활성화하여 상세한 로그를 확인할 수 있습니다:

```env
NCLOUD_MAIL_DEBUG=true
```

### 일반적인 문제

1. 인증 오류
    - AUTH_KEY와 SERVICE_SECRET이 올바르게 설정되었는지 확인하세요.

2. 첨부 파일 업로드 실패
    - 파일 크기 제한을 확인하세요.
    - 지원되는 파일 형식인지 확인하세요.

3. API 타임아웃
    - NCLOUD_MAIL_TIMEOUT 값을 조정해보세요.

## 테스트

패키지의 테스트를 실행하려면:

```bash
composer test
```

## 라이선스

MIT 라이선스 하에 배포됩니다. 자세한 내용은 [LICENSE](LICENSE) 파일을 참조하세요.


## 라라벨 8버전 이하는 아래 링크를 참고하세요.

[https://github.com/Daworks/ncloud-mailer-for-laravel6to8](https://github.com/Daworks/ncloud-mailer-for-laravel6to8)

