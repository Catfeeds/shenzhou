<?php
/**
 * File: AccessoryService.class.php
 * User: sakura
 * Date: 2017/11/9
 */

namespace Common\Common\Service;


use Common\Common\Model\BaseModel;

class AccessoryService
{

    // 只要配件单技工有返件（status>=8），并且返件费支付类型是现付的（worker_return_pay_method = 1），就算配件单被取消了也要算进worker_order_fee(即可通过平台财务客服审核)
    //配件单状态
    const STATUS_WORKER_APPLY_ACCESSORY = 1; // 申请配件
    const STATUS_ADMIN_FORBIDDEN        = 2; // 客服审核不通过
    const STATUS_ADMIN_CHECKED          = 3; // 客服审核
    const STATUS_FACTORY_FORBIDDEN      = 4; // 厂家审核不通过
    const STATUS_FACTORY_CHECKED        = 5; // 厂家审核
    const STATUS_FACTORY_SENT           = 6; // 厂家发件
    const STATUS_WORKER_TAKE            = 7; // 技工签收
    const STATUS_WORKER_SEND_BACK       = 8; // 技工返件
    const STATUS_COMPLETE               = 9; // 完结

    // 返件费支付方式（技工选择）
    const PAY_METHOD_NOW_PAY    = 1;           // 现付
    const PAY_METHOD_ARRIVE_PAY = 2;        // 到付
    // 返件费支付方式（技工选择） 集合
    const PAY_METHOD_ARR = [
        self::PAY_METHOD_NOW_PAY,
        self::PAY_METHOD_ARRIVE_PAY,
    ];

    //取消状态
    const CANCEL_STATUS_NORMAL       = 0;
    const CANCEL_STATUS_ADMIN_STOP   = 1;
    const CANCEL_STATUS_FACTORY_STOP = 2;
    const CANCEL_STATUS_WORKER_STOP  = 3;

    //是否返件
    const RETURN_ACCESSORY_PASS      = 0; // 需要返件
    const RETURN_ACCESSORY_FORBIDDEN = 1; // 不需要返件
    const RETURN_ACCESSORY_GIVE_UP   = 2; // 中途放弃返件

    // 收件地址类型 0未知 1-技工地址 2-用户地址
    const RECEIVE_ADDRESS_TYPE_default = '0';
    const RECEIVE_ADDRESS_TYPE_WORKER = '1';
    const RECEIVE_ADDRESS_TYPE_USER = '2';

    const RECEIVE_ADDRESS_TYPE_LIST = [
        self::RECEIVE_ADDRESS_TYPE_WORKER,
        self::RECEIVE_ADDRESS_TYPE_USER,
    ];

    // 厂家需要支付反件费的是否需要反件状态
    const FACTORY_NEED_PAY_RETURN_STATUS
        = [
            self::RETURN_ACCESSORY_PASS,
            self::RETURN_ACCESSORY_GIVE_UP,
        ];
    const RETURN_ACCESSORY_ALL_LIST = [
        self::RETURN_ACCESSORY_PASS,
        self::RETURN_ACCESSORY_FORBIDDEN,
        self::RETURN_ACCESSORY_GIVE_UP,
    ];

    const STATUS_IS_NOT_RETURN_ONGOING
        = [
            self::STATUS_WORKER_APPLY_ACCESSORY,
            self::STATUS_ADMIN_CHECKED,
            self::STATUS_FACTORY_CHECKED,
            self::STATUS_FACTORY_SENT,
        ];

    // 进行中的状态
    const STATUS_IS_ONGOING
        = [
            self::STATUS_WORKER_APPLY_ACCESSORY,
            self::STATUS_ADMIN_CHECKED,
            self::STATUS_FACTORY_CHECKED,
            self::STATUS_FACTORY_SENT,
            self::STATUS_WORKER_TAKE,
        ];

    const STATUS_IS_OVER
        = [

        ];

    /*
     * 配件单取消或已完成的时候，检查工单状态
     * @return (int)：为0时无未返件或未完结配件单
     */
    public static function checkWorkerOrderWhenUpdateAccessoryStatus($order_id, $accessory_status = self::STATUS_COMPLETE)
    {
        $product_model = BaseModel::getInstance('worker_order_product');
        $worker_order_model = BaseModel::getInstance('worker_order');
        $num = $product_model->getNum([
            'worker_order_id' => $order_id,
        ]);
        $complete_num = $product_model->getNum([
            'worker_order_id' => $order_id,
            'is_complete'     => ['in', '1,2'],
        ]);
        if ($num != $complete_num) {
            return 0;
        }
        //检查是否有未返件或未完结配件单
        $un_complete = BaseModel::getInstance('worker_order_apply_accessory')->getNum([
            'worker_order_id'  => $order_id,
            'cancel_status'    => 0,
            'accessory_status' => ['in', implode(',', self::STATUS_IS_ONGOING)]
        ]);
        return $un_complete;
    }

    /*
     * 生成配件单号
     */
    public static function genArNo(){

        //获取毫秒数（时间戳）
        list($t1, $t2) = explode(' ', microtime());

        $microtime =  (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);

        $microStr   = substr($microtime,7,6);

        $timeStr = date('ymd',time());

        $arno = $timeStr.$microStr;

        $id = BaseModel::getInstance('worker_order_apply_accessory')->getFieldVal([
            'accessory_number' => $arno
        ], 'id');

        if(!empty($id)){
            return self::genArNo();
        } else {
            return $arno;
        }
    }

}