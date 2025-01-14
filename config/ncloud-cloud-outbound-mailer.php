<?php
    
    return [
        /*
        |--------------------------------------------------------------------------
        | NCloud Auth Key
        |--------------------------------------------------------------------------
        |
        | Your NCloud API authentication key
        |
        */
        'auth_key' => env('NCLOUD_AUTH_KEY'),
        
        /*
        |--------------------------------------------------------------------------
        | NCloud Service Secret
        |--------------------------------------------------------------------------
        |
        | Your NCloud API service secret key
        |
        */
        'service_secret' => env('NCLOUD_SERVICE_SECRET'),
        
        /*
        |--------------------------------------------------------------------------
        | API Timeout
        |--------------------------------------------------------------------------
        |
        | The timeout for API requests in seconds
        |
        */
        'timeout' => env('NCLOUD_MAIL_TIMEOUT', 30),
        
        /*
        |--------------------------------------------------------------------------
        | Retry Attempts
        |--------------------------------------------------------------------------
        |
        | Number of times to retry failed API requests
        |
        */
        'retries' => env('NCLOUD_MAIL_RETRIES', 3),
        
        /*
        |--------------------------------------------------------------------------
        | Debug Mode
        |--------------------------------------------------------------------------
        |
        | When enabled, detailed logging will be performed
        |
        */
        'debug' => env('NCLOUD_MAIL_DEBUG', false),
    ];
