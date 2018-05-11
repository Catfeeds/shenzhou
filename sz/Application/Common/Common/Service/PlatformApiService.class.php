<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/21
 * Time: 15:46
 */

namespace Common\Common\Service;


use Common\Common\Logic\CryptLogic;
use Library\Crypt\Des;
use Library\Crypt\Rsa;

class PlatformApiService
{
    public static $config;
    public static $code;
    public static $key;
    public static $data;
    public static $platform_service;

    // 对接平台，账号类型的厂家能下的保内外单类型
    const FACTORY_WORKEKR_ORDER_TYPE_IN         = '1'; // 保内单
    const FACTORY_WORKEKR_ORDER_TYPE_OUT        = '2'; // 保外单
    const FACTORY_WORKEKR_ORDER_TYPE_ARR_VALUE = [
        self::FACTORY_WORKEKR_ORDER_TYPE_IN => OrderService::ORDER_TYPE_FACTORY_IN_INSURANCE,
        self::FACTORY_WORKEKR_ORDER_TYPE_OUT => OrderService::ORDER_TYPE_FACTORY_OUT_INSURANCE,
    ];

    const CONTACT_TYPE_PHONE = 1; // 手机号码
    const CONTACT_TYPE_TEL = 2; // 固话
    const CONTACT_TYPE_ALL = 3; // 自动识别

    public static function getPlatformService($config = [])
    {
        if (!static::$config) {
            static::$config = $config;
            $cp = '\\Common\\Common\\Service\\PlatformApiService\\'.static::$config['PLATFORM_SERVICE'].'Service';
            if (!class_exists($cp)) {
                throw new \Exception('该平台未对接', -411);
            }
            static::$platform_service = new $cp();
        }

        return static::$platform_service;
    }

    public static function base64Decode()
    {
        $des_key        = I('des_key', I('get.des_key'));
        $rsa_data       = I('rsa_data', I('get.rsa_data'));
        static::$key = base64_decode($des_key);
        static::$data = json_decode(base64_decode($rsa_data), true);
        if (!in_array(strlen(static::$key), [32, 8])) {
            throw new \Exception('验证失败', -409);
        }
        static::$data = json_decode(base64_decode($rsa_data), true);
        if (!is_array(static::$data)) {
            throw new \Exception('内容解析失败', -409);
        }
    }

    public static function base64Encode($data)
    {
        $key = Des::getDesKey();
        if (is_array($data)) {
            $data = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        return [
            'des_key' => base64_encode($key),
            'rsa_data' => $data,
        ];
    }

    public static function privateDecrypt()
    {
        $des_key        = I('des_key', I('get.des_key'));
        $rsa_data       = I('rsa_data', I('get.rsa_data'));
        $platform_code  = I('platform_code', I('get.platform_code', ''));
        if (!C('FIEXD_KEY_PLATFORM')[PlatformApiService::$code]) {
            static::$key = Rsa::privDecrypt($des_key, file_get_contents(static::$config['RSA_PRIVATE_KEY_PEM']));
        } elseif (PlatformApiService::$code == $platform_code) {
            static::$key = C('FIEXD_KEY_PLATFORM')[PlatformApiService::$code];
        }
        if (!in_array(strlen(static::$key), [32, 8])) {
            throw new \Exception('验证失败', -409);
        }
        $data = PlatformApiService::$config['CIPHER'] ? Des::decrypt($rsa_data, static::$key, PlatformApiService::$config['CIPHER']) : Des::decrypt($rsa_data, static::$key);
        static::$data = json_decode($data, true);
        if (!is_array(static::$data)) {
            throw new \Exception('内容解析失败', -409);
        }
    }

    public static function publicEncrypt($data)
    {
        $key = Des::getDesKey();
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $rsa_data = PlatformApiService::$config['CIPHER'] ? Des::encrypt($data, $key, PlatformApiService::$config['CIPHER']) : Des::encrypt($data, $key);
        $des_key = Rsa::publicEncrypt($key, file_get_contents(static::$config['RSA_PUBLIC_KEY_PEM']));
        return [
            'des_key' => $des_key,
            'rsa_data' => $rsa_data,
        ];
    }

    public static function getAuthType()
    {
        return static::$platform_service->getAuthType() ?? static::$config['AUTH_TYPE'];
    }

}
