# Ncloud Mailer for Laravel

이 패키지는 Laravel 버전 10 이상에서 Ncloud Cloud Outbound Mailer를 사용할 수 있게 해주는 메일러 드라이버입니다.

라라벨 6,7,8,9버전의 경우 :
https://github.com/Daworks/ncloud-cloud-outbound-mailer/tree/laravel6to8 를 사용하세요.

## 설치

Composer를 통해 패키지를 설치하세요:

````bash
composer require daworks/ncloud-mailer
````


## 설정

1. `.env` 파일에 Ncloud 인증 정보를 추가하세요:

```
NCLOUD_AUTH_KEY=your_auth_key_here
NCLOUD_SERVICE_SECRET=your_service_secret_here
```



2. `config/mail.php`에서 새 메일러를 설정하세요:

```php
'mailers' => [
    'ncloud' => [
        'transport' => 'ncloud',
    ],
],

'default' => 'ncloud',
```

## 사용

일반적인 Laravel 메일 기능을 그대로 사용하면 됩니다. 예:

```php
Mail::to($request->user())->send(new OrderShipped($order));
```

