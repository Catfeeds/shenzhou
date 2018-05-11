<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/21
 * Time: 16:58
 */
namespace Common\Common\Service\PlatformApiService;

use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\PlatformApiService;
use EasyWeChat\Payment\Order;
use Library\Crypt\Des;
use Library\Crypt\Rsa;

class XinFeiService extends PlatformApiService
{
    const AUTH_TYPE = AuthService::ROLE_FACTORY;

    const TEST_URL = 'http://61.54.97.20:888/forapp';
    const MASTER_URL = 'http://61.54.97.20:809/forapp';
    const POST_URL_PUST_FACTORY_ORDER_RECORD = [
        'path' => '/RepairTask.ashx?action=addProcess',
        'method' => 'POST',
        'params' => [
            'rpId' => 0,
            'Note' => '',
            'comid' => '',
            'handler' => '',
        ],
    ];
    const  POST_URL_FACTORY_ORDER_APPOINT_RECORD = [
        'path' => '/RepairTask.ashx?action=promise',
        'method' => 'POST',
        'params' => [
            'rpId' => 0,
            'centerPromiseDate' => '', // 预约时间
            'centerPromiseTimespan' => '', // 为空 时段(01:上午,02:下午,03：晚上)
            'promiseNote' => '', // 预约备注
            'handler' => '', // 预约人姓名或为空白字符
            'comid' => '', // 值可为空白字符
        ],
    ];

    public static function getAuthType()
    {
        return self::AUTH_TYPE;
    }
}
