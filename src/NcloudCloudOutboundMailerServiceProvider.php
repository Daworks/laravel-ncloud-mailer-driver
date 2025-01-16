<?php
    
    namespace Daworks\NcloudCloudOutboundMailer;
    
    use Illuminate\Support\ServiceProvider;
    use Illuminate\Support\Facades\Mail;
    use Illuminate\Support\Facades\Log;
    use Symfony\Component\Console\Output\ConsoleOutput;
    use Illuminate\Support\Facades\Config;
    use Psr\Log\LoggerInterface;
    
    class NcloudCloudOutboundMailerServiceProvider extends ServiceProvider
    {
        protected $output;
        
        public function __construct($app)
        {
            parent::__construct($app);
            $this->output = new ConsoleOutput();
        }
        
        public function boot()
        {
            // 설정 파일 퍼블리시
            $this->publishes([
                __DIR__.'/../config/ncloud-cloud-outbound-mailer.php' => $this->app->configPath('ncloud-cloud-outbound-mailer.php'),
            ], 'ncloud-mailer-config');
            
            // 언어 파일 퍼블리시
            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->resourcePath('lang'),
            ], 'ncloud-mailer-lang');
            
            // 모든 파일을 한번에 퍼블리시할 수 있는 그룹 태그
            $this->publishes([
                __DIR__.'/../config/ncloud-cloud-outbound-mailer.php' => $this->app->configPath('ncloud-cloud-outbound-mailer.php'),
                __DIR__.'/../resources/lang' => $this->app->resourcePath('lang'),
            ], 'ncloud-mailer');
            
            // 언어 파일 로드
            $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ncloud-mailer');
            
            if ($this->isConfigValid()) {
                Mail::extend('ncloud', function ($config) {
                    $authKey = $config['auth_key'] ?? Config::get('ncloud-cloud-outbound-mailer.auth_key');
                    $serviceSecret = $config['service_secret'] ?? Config::get('ncloud-cloud-outbound-mailer.service_secret');
                    $timeout = $config['timeout'] ?? Config::get('ncloud-cloud-outbound-mailer.timeout', 30);
                    $retries = $config['retries'] ?? Config::get('ncloud-cloud-outbound-mailer.retries', 3);
                    
                    return new NcloudMailerDriver(
                        $authKey,
                        $serviceSecret,
                        $this->app->make(LoggerInterface::class),
                        $timeout,
                        $retries
                    );
                });
            }
        }
        
        public function register()
        {
            $this->mergeConfigFrom(
                __DIR__.'/../config/ncloud-cloud-outbound-mailer.php',
                'ncloud-cloud-outbound-mailer'
            );
        }
        
        protected function isConfigValid(): bool
        {
            $config = Config::get('ncloud-cloud-outbound-mailer');
            
            if (empty($config)) {
                $this->outputMessage('Configuration is missing. Please run vendor:publish', 'warning');
                return false;
            }
            
            if (empty($config['auth_key'])) {
                $this->outputMessage('The auth_key configuration is required. Please check your .env file.', 'error');
                return false;
            }
            
            if (empty($config['service_secret'])) {
                $this->outputMessage('The service_secret configuration is required. Please check your .env file.', 'error');
                return false;
            }
            
            return true;
        }
        
        protected function publishConfiguration(): void
        {
            $configPath = config_path('ncloud-cloud-outbound-mailer.php');
            $sourcePath = __DIR__ . '/../config/ncloud-cloud-outbound-mailer.php';
            
            // 설정 파일이 없을 때만 퍼블리시
            if (!file_exists($configPath)) {
                $this->publishes([
                    $sourcePath => $configPath,
                ], 'config');
                
                if ($this->app->runningInConsole()) {
                    if (copy($sourcePath, $configPath)) {
                        $this->outputMessage('NCloud Mailer: Configuration file published successfully.');
                    } else {
                        $this->outputMessage('NCloud Mailer: Could not publish configuration file.', 'error');
                    }
                }
            }
        }
        
        protected function outputMessage(string $message, string $level = 'info'): void
        {
            // 로그에 기록
            Log::$level($message);
            
            // 커맨드 라인에서 실행 중일 때만 콘솔 출력
            if ($this->app->runningInConsole()) {
                switch ($level) {
                    case 'error':
                        $this->output->writeln("<error>$message</error>");
                        break;
                    case 'warning':
                        $this->output->writeln("<comment>$message</comment>");
                        break;
                    case 'info':
                    default:
                        $this->output->writeln("<info>$message</info>");
                        break;
                }
            }
        }
    }
