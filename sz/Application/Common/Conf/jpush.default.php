<?php

return
    [
        'easyWeChat' => [
            /**
             * Debug 模式，bool 值：true/false
             *
             * 当值为 false 时，所有的日志都不会记录
             */
            'debug'  => true,

            /**
             * 账号基本信息，请从微信公众平台/开放平台获取
             */
            'app_id'  => 'wx0d957e6985b1bd17',                     // AppID
            'secret'  => '3a319d46b615934c256a2836f14eca68',       // AppSecret
            'token'   => 'shenzhoulianbaoAppC',                    // Token
            'aes_key' => 'ZyucmGBgaqjKiBZxou7Dwk5WYubP0RqLGTOSVVKPTFT',   // EncodingAESKey

            'platform_service_fee' => '0.2',       // 保外单平台服务费比例

            /**
             * 日志配置
             *
             * level: 日志级别, 可选为：
             *         debug/info/notice/warning/error/critical/alert/emergency
             * file：日志文件位置(绝对路径!!!)，要求可写权限
             */
            'log' => [
                'level' => 'debug',
                'file'  => LOG_PATH . 'easyWeChat/' . date('Ym') . 'easywechat.log',
            ],

            /**
             * OAuth 配置
             *
             * scopes：公众平台（snsapi_userinfo / snsapi_base），开放平台：snsapi_login
             * callback：OAuth授权完成后的回调页地址
             */
            'oauth' => [
                'scopes'   => ['snsapi_userinfo'],
                'callback' => '/examples/oauth_callback.php',
            ],

            /**
             * 微信支付
             */
            'payment' => [
                'merchant_id'        => '1301074801',
                'key'                => 'e0682A4278265085Dbf5f77042bcb300',
                'cert_path'          => 'path/to/your/cert.pem', // XXX: 绝对路径！！！！
                'key_path'           => 'path/to/your/key',      // XXX: 绝对路径！！！！
                // 'device_info'     => '013467007045764',
                // 'sub_app_id'      => '',
                // 'sub_merchant_id' => '',
                // ...
            ],

            /**
             * Guzzle 全局设置
             *
             * 更多请参考： http://docs.guzzlephp.org/en/latest/request-options.html
             */
            'guzzle' => [
                'timeout' => 3.0, // 超时时间（秒）
                //'verify' => false, // 关掉 SSL 认证（强烈不建议！！！）
            ],
        ]
    ];


