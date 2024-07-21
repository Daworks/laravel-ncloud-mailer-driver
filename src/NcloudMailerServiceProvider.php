<?php
	
	namespace YourVendor\NcloudMailer;
	
	use Illuminate\Support\ServiceProvider;
	use Illuminate\Support\Facades\Mail;
	
	class NcloudMailerServiceProvider extends ServiceProvider
	{
		public function boot()
		{
			$this->publishes([
				__DIR__.'/config/ncloud-mailer.php' => config_path('ncloud-mailer.php'),
			]);
			
			Mail::extend('ncloud', function ($app) {
				$config = $app['config']['ncloud-mailer'];
				return new NcloudMailerDriver(
					$config['auth_key'],
					$config['service_secret']
				);
			});
		}
		
		public function register()
		{
			$this->mergeConfigFrom(
				__DIR__.'/config/ncloud-mailer.php', 'ncloud-mailer'
			);
		}
	}
