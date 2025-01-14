# Ncloud Mailer for Laravel

이 패키지는 Laravel 버전 10 이상에서 Ncloud Cloud Outbound Mailer를 사용할 수 있게 해주는 메일러 드라이버입니다.


### 요구사항

- PHP 8.1 이상
- Laravel 9.x 이상


### 설치

Composer를 통해 패키지를 설치하세요:

````
composer require daworks/ncloud-cloud-outbound-mailer
````


### 설정

1. `.env` 파일에 Ncloud 인증 정보를 추가하세요:

```
NCLOUD_AUTH_KEY=your_auth_key_here
NCLOUD_SERVICE_SECRET=your_service_secret_here
```


2. 설정 파일 퍼블리싱

```
php artisan vendor:publish --provider="Daworks\NcloudCloudOutboundMailer\NcloudCloudOutboundMailerServiceProvider" --tag=config
```


3. `config/mail.php`에서 새 메일러를 설정하세요:

```php
'mailers' => [
    'ncloud' => [
        'transport' => 'ncloud',
    ],
],

'default' => 'ncloud',
```

### 사용

일반적인 Laravel 메일 기능을 그대로 사용하면 됩니다. 예:

```php
Mail::to($request->user())->send(new OrderShipped($order));
```


### 기타

laravel 6.x ~ 8.x는 아래와 같이 설치해서 사용하세요.

```
composer require daworks/ncloud-mailer-for-laravel6to8
```

참고 : https://github.com/Daworks/ncloud-mailer-for-laravel6to8
