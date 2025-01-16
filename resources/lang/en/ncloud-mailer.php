<?php
    
    return [
        /*
        |--------------------------------------------------------------------------
        | Ncloud Cloud Outbound Mailer Language File
        |--------------------------------------------------------------------------
        |
        | English translation file for Ncloud Mailer API responses.
        |
        */
        
        'status' => [
            // HTTP Status Codes
            '400' => 'Bad Request',
            '403' => 'Forbidden',
            '405' => 'Method Not Allowed',
            '415' => 'Unsupported Media Type',
            '500' => 'Server Error',
        ],
        
        'errors' => [
            // API Error Codes
            '77101' => 'Login information error',
            '77102' => 'Bad request error',
            '77103' => 'Requested resource does not exist',
            '77201' => 'No permission for the requested resource',
            '77202' => 'Email service subscription required',
            '77001' => 'Method not allowed',
            '77002' => 'Unsupported media type',
            '77301' => 'Default project does not exist',
            '77302' => 'External system API integration error',
            '77303' => 'Internal server error'
        ],
        
        'messages' => [
            // General Messages
            'sending' => 'Sending email',
            'sent_success' => 'Email sent successfully',
            'retry_attempt' => 'Retrying email sending (attempt :attempt)',
            'max_retries_exceeded' => 'Maximum retry attempts (:max_retries) exceeded',
            
            // Configuration Messages
            'config_missing' => 'Configuration file is missing. Please run vendor:publish command',
            'auth_key_required' => 'The auth_key configuration is required. Please check your .env file',
            'service_secret_required' => 'The service_secret configuration is required. Please check your .env file',
            
            // Attachment Messages
            'attachment_upload_start' => 'Uploading attachment: :filename',
            'attachment_upload_success' => 'Attachment uploaded successfully: :filename',
            'attachment_upload_failed' => 'Failed to upload attachment: :filename',
            
            // Other Error Messages
            'unknown_error' => 'An unknown error occurred',
            'unknown_error_code' => 'Unknown error code: :code',
        ],
    ];
