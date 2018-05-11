<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/7
 * Time: 18:39
 */

namespace Common\Common\Service;


use Common\Common\Model\BaseModel;
use EasyWeChat\Payment\Order;
use Library\Crypt\Des;
use Library\Crypt\Rsa;

class XinYingYngService extends PlatformApiService
{
//    private static $rsa_key;
//    private static $des_data;
    public static $key;
    public static $data;
    public static $factory;

    const PLATFORM_TYPE_CODE = AuthService::ROLE_FACTORY;
    const PLATFORM_CODE = 'factory:xinyingyan';
    const OBJECT_PLATFORM_CODE = 'shenzhoulianbao';

    const SKIP_TYPE_WORKER_ORDER_DETAIL = '1'; // 工单详情
    const SKIP_TYPE_ARR = [
        self::SKIP_TYPE_WORKER_ORDER_DETAIL,
    ];

    const RETURN_RESULT_CREATE_ORDER_FAIL = '0'; // 失败
    const RETURN_RESULT_CREATE_ORDER_SUCCESS = '1'; // 成功

    const WORKEKR_ORDER_TYPE_IN         = '1'; // 保内单
    const WORKEKR_ORDER_TYPE_OUT        = '2'; // 保外单
    const WORKEKR_ORDER_TYPE_ARR_VALUE = [
        self::WORKEKR_ORDER_TYPE_IN => OrderService::ORDER_TYPE_FACTORY_IN_INSURANCE,
        self::WORKEKR_ORDER_TYPE_OUT => OrderService::ORDER_TYPE_FACTORY_OUT_INSURANCE,
    ];

    const RETURN_CODE_SUCCESS = '200';                    // 成功
    const RETURN_CODE_FACTORY_ACCOUNT_NOT_EXPIRE = '501'; // 厂家账号已过期
    const RETURN_CODE_PRODUCT_NOT_INSTALLATION = '502';   // 下单产品暂不支持安装服务，请核对后再提交
    const RETURN_CODE_FACTORY_NOT_ENOUTH = '503';         // 下单余额不足，请及时充值或调整下单数量
    const RETURN_CODE_OTHER_ERROR = '504'; // 其他错误

    const ORDER_RETURN_CODE_DATA_IMPERFECT = '201';       // 	数据不完善
    const ORDER_RETURN_CODE_HAS_PLATFORM_SN = '202';      // 	重复下单
    const ORDER_RETURN_CODE_OTHER = '203';                // 	其他错误

    // 返回数据的订单状态
    const ORDER_STATUS_NEED_WORKER_SERVICE = '1';         // 待技工上门
    const ORDER_STATUS_WORKER_IN_SERVICE = '2';           // 技工服务中
    const ORDER_STATUS_WORKER_FINISH_SERVICE = '3';       // 服务已完成
    const ORDER_STATUS_WORKER_CANCEL_ORDER = '4';         // 工单取消
    const ORDER_STATUS_KEY_VALUE = [
        OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE     => self::ORDER_STATUS_NEED_WORKER_SERVICE,
        OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK        => self::ORDER_STATUS_NEED_WORKER_SERVICE,
        OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE   => self::ORDER_STATUS_NEED_WORKER_SERVICE,
        OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE => self::ORDER_STATUS_NEED_WORKER_SERVICE,
        OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL    => self::ORDER_STATUS_NEED_WORKER_SERVICE,
        OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT => self::ORDER_STATUS_NEED_WORKER_SERVICE,
        OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE       => self::ORDER_STATUS_NEED_WORKER_SERVICE,
        OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE => self::ORDER_STATUS_WORKER_IN_SERVICE,
        OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE => self::ORDER_STATUS_WORKER_FINISH_SERVICE,
    ];
    // 取消状态集合
    const CANCEL_ORDER_STATUS_ARR = [
        OrderService::CANCEL_TYPE_WX_USER,
        OrderService::CANCEL_TYPE_WX_DEALER,
        OrderService::CANCEL_TYPE_FACTORY,
        OrderService::CANCEL_TYPE_CS,
        OrderService::CANCEL_TYPE_FACTORY_ADMIN,
    ];

    // 配件订单状态配置
    const RETURN_TAG_NEEK_CHECKER_CHECK_OR_NOT_AUDIT = '0';       // 无标识(待客服审核、客服审核不通过)
    const RETURN_TAG_WAIT_FOR_FACTORY_CHECK_OR_SEEND_ACCE = '1';  // 红配(待厂家审核、待厂家发件)
    const RETURN_TAG_WAIT_FOR_WORKER_GET_GETED_OR_FACTORY_NOT_CHECK_END_OR_ADMIN_END = '2'; // 绿配(待技工签收、技工已签收、厂家审核不通过、客服终止、厂家终止)
    const RETURN_TAG_WAIT_FOR_WORKER_SEND_ACCE = '3';             // 红返(待技工返件)
    const RETURN_TAG_WAIT_FOR_FACTORY_GET_ACCE = '4';             // 绿返(待厂家确认收件)
    const RETURN_TAG_KEY_VALUE = [
        AccessoryService::STATUS_WORKER_APPLY_ACCESSORY => self::RETURN_TAG_NEEK_CHECKER_CHECK_OR_NOT_AUDIT,
        AccessoryService::STATUS_ADMIN_FORBIDDEN => self::RETURN_TAG_NEEK_CHECKER_CHECK_OR_NOT_AUDIT,
        AccessoryService::STATUS_ADMIN_CHECKED => self::RETURN_TAG_WAIT_FOR_FACTORY_CHECK_OR_SEEND_ACCE,
        AccessoryService::STATUS_FACTORY_CHECKED => self::RETURN_TAG_WAIT_FOR_FACTORY_CHECK_OR_SEEND_ACCE,
        AccessoryService::STATUS_FACTORY_SENT => self::RETURN_TAG_WAIT_FOR_WORKER_GET_GETED_OR_FACTORY_NOT_CHECK_END_OR_ADMIN_END,
        AccessoryService::STATUS_WORKER_TAKE => self::RETURN_TAG_WAIT_FOR_WORKER_SEND_ACCE,
        AccessoryService::STATUS_FACTORY_FORBIDDEN => self::RETURN_TAG_WAIT_FOR_WORKER_GET_GETED_OR_FACTORY_NOT_CHECK_END_OR_ADMIN_END,
        AccessoryService::STATUS_WORKER_SEND_BACK => self::RETURN_TAG_WAIT_FOR_FACTORY_GET_ACCE,
    ];
    // 取消状态配置
    const RETURN_TAG_CANCELL_STATUS = [
        AccessoryService::CANCEL_STATUS_ADMIN_STOP => self::RETURN_TAG_WAIT_FOR_WORKER_GET_GETED_OR_FACTORY_NOT_CHECK_END_OR_ADMIN_END,
        AccessoryService::CANCEL_STATUS_FACTORY_STOP => self::RETURN_TAG_WAIT_FOR_WORKER_GET_GETED_OR_FACTORY_NOT_CHECK_END_OR_ADMIN_END,
    ];

    public static function decrypt($des_data, $des_key)
    {
//        static::$rsa_key = $des_key;
//        static::$des_data = $data;
        $private_key_content = file_get_contents(C('PEM_URL.XINYINGYAN_RSA_PRIVATE_KEY_PEM'));
//        static::$type = self::PLATFORM_TYPE_CODE;
        static::$key = Rsa::privDecrypt($des_key, $private_key_content);
        static::$data = json_decode(Des::decrypt($des_data, static::$key), true);
    }

    public static function login()
    {
//        throw new \Exception("厂家账号已过期", self::RETURN_CODE_FACTORY_ACCOUNT_NOT_EXPIRE);
        $telphone = static::$data['account'];
        $data = BaseModel::getInstance('factory')->getOne([
            'field' => '*',
            'where' => [
                'linkphone' => $telphone,
            ],
        ]);
        if (!$data) {
            throw new \Exception("账号不存在", self::RETURN_CODE_OTHER_ERROR);
        } elseif ($data['datefrom'] > NOW_TIME || NOW_TIME > $data['dateto']) {
            throw new \Exception("厂家账号已过期", self::RETURN_CODE_FACTORY_ACCOUNT_NOT_EXPIRE);
        }
        static::$factory = $data;
    }

}
