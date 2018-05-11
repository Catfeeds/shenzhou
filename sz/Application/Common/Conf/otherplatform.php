<?php
return array(
    'FIEXD_KEY_PLATFORM' => [
//        'factory:xinfei' => 'Mc2sxExLflYppp4hiM4qle1g7OBpIIVc',
    ],
    'OUT_PLATFORM_FOR_FACTORY_ID' => [
        1 => 927,
        2 => 1900,
    ],
    'PLATFORM_TYPE_TO_SERVICE_KEY_VALUE' => [
        1 => 'factory:xinyingyan',
        2 => 'factory:xinfei',
    ],
    'PLATFORM_CONFIG' => [
        'factory:xinfei' => [
            'OUT_PLATFORM' => 2,
            'PLATFORM_SERVICE' => 'XinFei',
            'AUTH_TYPE' => 'factory',
            'PLATFORM_ID'   => 1900,
            'RSA_PRIVATE_KEY_PEM'   => './Pem/XinFei/rsa_private_key.pem',
            'RSA_PUBLIC_KEY_PEM'    => './Pem/XinFei/rsa_public_key.pem',
//            'CIPHER' => 'des',
        ],
        'factory:xinyingyan' => [
            'OUT_PLATFORM' => 1,
            'PLATFORM_SERVICE' => 'XinYingYan',
            'AUTH_TYPE' => 'factory',
            'PLATFORM_ID'   => 927,
            'RSA_PRIVATE_KEY_PEM'   => './Pem/XinYingYan/rsa_private_key.pem',
            'RSA_PUBLIC_KEY_PEM'    => './Pem/XinYingYan/rsa_public_key.pem',
        ],
    ],
    'PEM_URL' => [
        'XINYINGYAN_RSA_PRIVATE_KEY_PEM' => './Pem/XinYingYan/rsa_private_key.pem',
        'XINYINGYAN_RSA_PUBLIC_KEY_PEM' => './Pem/XinYingYan/rsa_public_key.pem',
    ],
    'REDIS_KEY' => [
        'XINYINGYAN_PUSH_WORKER_ORDER_STATUS' => 'syncData:workerOrderStatus',
    ],
);
