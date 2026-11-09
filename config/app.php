<?php

declare(strict_types=1);

return [

    'openai_api_key' => env('OPENAI_API_KEY', null),

    /*
     * Key of the citoyens personal data columns, see EncryptedPersonalDataCast. The intranet
     * reads and writes the same columns: both applications must use the same key.
     */
    'citoyen_data_key' => env('CITOYEN_DATA_KEY'),
];
