<?php

return [

    'locales' => [ 'cs', 'de', 'en' ],

    'ownerMail' => env('CTVERO_OWNER_MAIL'),

    'diaryUrlToProcessor' => [
        'http://drive.cbdx.cz/xdenik' => 'CbdxCz',
        'https://drive.cbdx.cz/xdenik' => 'CbdxCz',
        'http://www.cbpmr.cz/deniky/' => 'CbpmrCz',
        'https://www.cbpmr.cz/deniky/' => 'CbpmrCz',
        'http://www.cbpmr.info/share/' => 'CbpmrInfo',
        'https://www.cbpmr.info/share/' => 'CbpmrInfo',
    ],

    'cbpmrInfoApiUrl' => env('CTVERO_CBPMR_INFO_API_URL'),
    'cbpmrInfoApiAuthUsername' => env('CTVERO_CBPMR_INFO_API_AUTH_USERNAME'),
    'cbpmrInfoApiAuthPassword' => env('CTVERO_CBPMR_INFO_API_AUTH_PASSWORD'),

    'recaptchaSiteKey' => env('CTVERO_RECAPTCHA_SITE_KEY'),
    'recaptchaSecret' => env('CTVERO_RECAPTCHA_SECRET'),
    'recaptchaScoreThreshold' => env('CTVERO_RECAPTCHA_SCORE_THRESHOLD', 0.5),
    'recaptchaExpectedAction' => env('CTVERO_RECAPTCHA_EXPECTED_ACTION', 'submit'),
    'recaptchaEnterpriseProjectId' => env('CTVERO_RECAPTCHA_ENTERPRISE_PROJECT_ID'),
    'recaptchaEnterpriseApiKey' => env('CTVERO_RECAPTCHA_ENTERPRISE_API_KEY'),

    'repositoryUrl' => env('CTVERO_REPOSITORY_URL'),
    'issuesReportUrl' => env('CTVERO_ISSUES_REPORT_URL'),

    // This secret key has to be at least 32 chars long!
    'apiAdminSecret' => env('CTVERO_API_ADMIN_SECRET'),

    'mapboxAccessToken' => env('CTVERO_MAPBOX_ACCESS_TOKEN'),
];
