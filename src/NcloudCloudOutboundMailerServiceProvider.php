<?php
    
    namespace Daworks\NcloudCloudOutboundMailer;
    
    use Illuminate\Support\ServiceProvider;
    use Illuminate\Support\Facades\Mail;
    use Psr\Log\LoggerInterface;
    
    class NcloudCloudOutboundMailerServiceProvider extends ServiceProvider
    {
        public function boot()
        {
            $this->publishes([
                __DIR__.'/../config/ncloud-cloud-outbound-mailer.php' => config_path('ncloud-cloud-outbound-mailer.php'),
            ], 'config');
            
            $this->validateConfig();
            
            Mail::extend('ncloud', function ($config) {
                $authKey = $config['auth_key'] ?? config('ncloud-cloud-outbound-mailer.auth_key');
                $serviceSecret = $config['service_secret'] ?? config('ncloud-cloud-outbound-mailer.service_secret');
                $timeout = $config['timeout'] ?? config('ncloud-cloud-outbound-mailer.timeout', 30);
                $retries = $config['retries'] ?? config('ncloud-cloud-outbound-mailer.retries', 3);
                
                return new NcloudMailerDriver(
                    $authKey,
                    $serviceSecret,
                    $this->app->make(LoggerInterface::class),
                    $timeout,
                    $retries
                );
            });
        }
        
        public function register()
        {
            $this->mergeConfigFrom(
                __DIR__.'/../config/ncloud-cloud-outbound-mailer.php',
                'ncloud-cloud-outbound-mailer'
            );
        }
        
        protected function validateConfig(): void
        {
            $config = config('ncloud-cloud-outbound-mailer');
            
            if (empty($config)) {
                throw new NcloudMailerException(
                    'The ncloud-cloud-outbound-mailer config file is missing. Please publish it using: php artisan vendor:publish'
                );
            }
            
            if (empty($config['auth_key'])) {
                throw new NcloudMailerException('The auth_key configuration is required');
            }
            
            if (empty($config['service_secret'])) {
                throw new NcloudMailerException('The service_secret configuration is required');
            }
        }
    }
