<?php

declare(strict_types=1);

return [

    'openai_api_key' => env('OPENAI_API_KEY', null),

    /*
     * Key of the citoyens personal data columns, see EncryptedPersonalDataCast. The intranet
     * reads and writes the same columns: both applications must use the same key.
     */
    'citoyen_data_key' => env('CITOYEN_DATA_KEY'),

    /*
     * Inforius SMS gateway, the same account as the intranet.
     */
    'sms' => [
        'host' => env('SMS_HOST', 'https://ecom.inforius.be/Api/'),
        'user' => env('SMS_USER'),
        'password' => env('SMS_PASSWORD'),
        'sender' => env('SMS_SENDER'),
    ],
];
