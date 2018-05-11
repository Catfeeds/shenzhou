<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/25
 * Time: 12:22
 */

namespace Common\Common\Service;

use Common\Common\ErrorCode;
use Common\Common\Model\BaseModel;
use Api\Repositories\Events\DealerActivatedEvent;

class AppMessageService
{

    // ====================================== 消息类型 ======================================
    /**
     *
     *  消息分类:message_type
     *  1 业务通告、活动消息的数据
     *  2 交易通知
     *  3 工单消息
     *  4 配件消息
     *  5 费用单消息
     *  6 接单必读
     *  7 反馈回复
     *
     */
    // ====================================== 消息分类 ======================================

    const MESSAGE_TYPE_BACKSTAGE_MESSAGE    = 1;
    const MESSAGE_TYPE_TRANSACTION_MESSAGE  = 2;
    const MESSAGE_TYPE_WORKER_ORDER_MESSAGE = 3;
    const MESSAGE_TYPE_ACCESSORY_MESSAGE    = 4;
    const MESSAGE_TYPE_COST_MESSAGE         = 5;
    const MESSAGE_TYPE_ORDER_MESSAGE        = 6;
    const MESSAGE_TYPE_FEEDBACK_MESSAGE     = 7;
    const MESSAGE_TYPE_COMPLAINT            = 8;

    // ====================================== 消息类型 ======================================

    const TYPE_ALL_MASSAGE = 0;

    /**
     * *  一 业务通告、活动消息的数据
     *      0 全部
     *      101 业务通告
     *      102 活动消息
     *
     */
    const TYPE_BUSINESS_MESSAGE = 101;
    const TYPE_ACTIVITY_MESSAGE = 102;

    /**
     * *  二 交易通知
     *      0 全部
     *      201 结算消息
     *      202 提现消息
     *      203 其他
     *      204 提现中
     *      205 提现成功
     *      206 提现失败
     *      207 钱包余额调整
     *      208 质保金调整
     */
    const TYPE_BALANCE_MASSAGE         = 201;
    const TYPE_CASH_MASSAGE            = 202;
    const TYPE_BACKSTAGE_OTHER_MASSAGE = 203;
    const TYPE_CASHING                 = 204;
    const TYPE_CASH_SUCCESS            = 205;
    const TYPE_CASH_FAIL               = 206;
    const TYPE_MONEY_ADJUST_SET        = 207;
    const TYPE_QUALITY_MONEY_SET       = 208;

    /**
     * *  三 工单消息
     *      0 全部
     *      301 新工单
     *      302 明天需上门
     *      303 回访通过
     *      304 其他
     *      305 安装预发件工单已签收提醒
     *      306 回访不通过
     */
    const TYPE_NEW_WORKER_ORDER_MASSAGE   = 301;
    const TYPE_APPOINT_MASSAGE            = 302;
    const TYPE_VISIT_PASS_MASSAGE         = 303;
    const TYPE_WORKER_ORDER_OTHER_MASSAGE = 304;
    const TYPE_SIGN_IN_REMIND             = 305;
    const TYPE_VISIT_NOT_PASS             = 306;
    const TYPE_ORIGIN_ORDER_REWORK        = 307;
    const TYPE_NEW_REWORK_ORDER_MESSAGE   = 308;

    /**
     * *  四 配件消息
     *      0 全部
     *      401 待发件
     *      402 已发件
     *      403 待返件
     *      404 其他
     *      405 待厂家审核
     *      406 客服/厂家审核不通过
     *      407 厂家延时发件
     *      408 厂家放弃旧配件返还
     *      409 客服/厂家终止配件单
     *      410 客服申请配件单并发件 zjz
     */
    const TYPE_WAIT_ACCESSORY_MASSAGE   = 401;
    const TYPE_SEND_ACCESSORY_MASSAGE   = 402;
    const TYPE_RETURN_ACCESSORY_MASSAGE = 403;
    const TYPE_OTHER_ACCESSORY_MESSAGE  = 404;
    const TYPE_WAIT_FACTORY_CHECK       = 405;
    const TYPE_ACCESSORY_CHECK_NOT_PASS = 406;
    const TYPE_FACTORY_DELAY_SEND       = 407;
    const TYPE_FACTORY_ABANDON_RETURN   = 408;
    const TYPE_ACCESSORY_END            = 409;
    const TYPE_CS_SEND_ACCESSORY_MASSAGE = 410;

    /**
     * *  五 费用单消息
     *      0 全部
     *      501 待厂家审核
     *      502 审核通过
     *      503 审核不通过
     */
    const TYPE_WAIT_CHECK_MASSAGE     = 501;
    const TYPE_CHECK_PASS_MASSAGE     = 502;
    const TYPE_CHECK_NOT_PASS_MASSAGE = 503;

    /**
     * *  六 接单必读
     *      0 全部
     *      601 接单必读
     */
    const TYPE_ORDERS_MASSAGE = 601;

    /**
     * *  七 反馈回复
     *      701 反馈回复
     */
    const TYPE_FEEDBACK_MASSAGE = 701;


    /**
     * 八 投诉消息
     */
    const TYPE_COMPLAINT_CREATE_MESSAGE = 801;


    const OPERATION_TYPE_CONTENT
        = [
            self::TYPE_ALL_MASSAGE => '全部消息',
        ];

    /*
     * 记录消息
     * $data_id:跳转数据id
     * $describe:描述
     */
    public static function create($worker_id, $data_id, $type, $title, $content, $describe = '')
    {
        $message = [
            'worker_id'   => $worker_id,
            'data_id'     => $data_id,
            'type'        => $type,
            'title'       => $title,
            'content'     => $content,
            'is_read'     => 0,
            'create_time' => NOW_TIME,
            'describe'    => $describe,
        ];
        $id = BaseModel::getInstance('worker_notification')->insert($message);

        return $id;
    }

    /*
     * 记录系统消息
     */
    public static function createAll($type, $title, $content)
    {
        $worker_list = BaseModel::getInstance('worker')->getList([
            'where' => [
                'is_complete_info' => 1,
            ],
            'field' => 'worker_id',
        ]);
        foreach ($worker_list as $v) {
            $message[] = [
                'worker_id'   => $v['worker_id'],
                'data_id'     => 0,
                'type'        => $type,
                'title'       => $title,
                'content'     => $content,
                'is_read'     => 0,
                'create_time' => NOW_TIME,
                'describe'    => '',
            ];
        }
        $id = BaseModel::getInstance('worker_notification')
            ->insertAll($message);

        return $id;
    }

    /*
     * 获取某一分类全部类型的消息条件
     */
    public static function getStr($type)
    {
        switch ($type) {
            case self::MESSAGE_TYPE_BACKSTAGE_MESSAGE :
                $str = (string)self::TYPE_BUSINESS_MESSAGE . ',' . self::TYPE_ACTIVITY_MESSAGE;
                break;
            case self::MESSAGE_TYPE_TRANSACTION_MESSAGE :
                $str = (string)self::TYPE_BALANCE_MASSAGE . ',' . self::TYPE_CASH_MASSAGE . ',' . self::TYPE_BACKSTAGE_OTHER_MASSAGE . ',' . self::TYPE_CASHING . ',' . self::TYPE_CASH_SUCCESS . ',' . self::TYPE_CASH_FAIL . ',' . self::TYPE_MONEY_ADJUST_SET . ',' . self::TYPE_QUALITY_MONEY_SET;
                break;
            case self::MESSAGE_TYPE_WORKER_ORDER_MESSAGE :
                $str = (string)self::TYPE_NEW_WORKER_ORDER_MASSAGE . ',' . self::TYPE_APPOINT_MASSAGE . ',' . self::TYPE_VISIT_PASS_MASSAGE . ',' . self::TYPE_SIGN_IN_REMIND . ',' . self::TYPE_VISIT_NOT_PASS . ',' . self::TYPE_ORIGIN_ORDER_REWORK . ',' . self::TYPE_NEW_REWORK_ORDER_MESSAGE;
                break;
            case self::MESSAGE_TYPE_ACCESSORY_MESSAGE :
                $str = (string)self::TYPE_WAIT_ACCESSORY_MASSAGE . ',' . self::TYPE_SEND_ACCESSORY_MASSAGE . ',' . self::TYPE_RETURN_ACCESSORY_MASSAGE . ',' . self::TYPE_WAIT_FACTORY_CHECK . ',' . self::TYPE_ACCESSORY_CHECK_NOT_PASS . ',' . self::TYPE_FACTORY_DELAY_SEND . ',' . self::TYPE_FACTORY_ABANDON_RETURN . ',' . self::TYPE_ACCESSORY_END. ',' . self::TYPE_CS_SEND_ACCESSORY_MASSAGE;
                break;
            case self::MESSAGE_TYPE_COST_MESSAGE :
                $str = (string)self::TYPE_WAIT_CHECK_MASSAGE . ',' . self::TYPE_CHECK_PASS_MASSAGE . ',' . self::TYPE_CHECK_NOT_PASS_MASSAGE;
                break;
            case self::MESSAGE_TYPE_ORDER_MESSAGE :
                $str = (string)self::TYPE_ORDERS_MASSAGE;
                break;
            case self::MESSAGE_TYPE_COMPLAINT:
                $str = (string)self::TYPE_COMPLAINT_CREATE_MESSAGE;
                break;
        }
        if (!empty($str)) {
            return $str;
        }
    }

    /*
     * 获取配件消息中其他消息类型
     */
    public static function getAccessoryOtherStr()
    {
        return (string)self::TYPE_WAIT_FACTORY_CHECK . ',' . self::TYPE_ACCESSORY_CHECK_NOT_PASS . ',' . self::TYPE_FACTORY_DELAY_SEND . ',' . self::TYPE_FACTORY_ABANDON_RETURN . ',' . self::TYPE_ACCESSORY_END;
    }

    /*
     * 获取工单消息中其他消息类型
     */
    public static function getWorkerOrderOtherStr()
    {
        return (string)self::TYPE_SIGN_IN_REMIND . ',' . self::TYPE_VISIT_NOT_PASS . ',' . self::TYPE_ORIGIN_ORDER_REWORK . ',' . self::TYPE_NEW_REWORK_ORDER_MESSAGE;
    }

    /*
     * 获取交易消息中其他消息类型
     */
    public static function getTransactionOtherStr()
    {
        return (string)self::TYPE_MONEY_ADJUST_SET . ',' . self::TYPE_QUALITY_MONEY_SET;
    }

    /*
     * 获取交易消息中提现消息类型
     */
    public static function getCashMessageStr()
    {
        return (string)self::TYPE_CASHING . ',' . self::TYPE_CASH_SUCCESS . ',' . self::TYPE_CASH_FAIL;
    }

    /*
     * 获取系统消息分类
     */
    //public static function systemAnnouncementStr($type)
    //{
    //    switch ($type) {
    //        case self::MESSAGE_TYPE_BACKSTAGE_MESSAGE :
    //            $str = '1,3';
    //            break;
    //        case self::MESSAGE_TYPE_ORDER_MESSAGE :
    //            $str = '2';
    //    }
    //    if (!empty($str)) {
    //        return $str;
    //    }
    //}

    public static function systemAnnouncementStr($type)
    {
        switch ($type) {
            case self::MESSAGE_TYPE_BACKSTAGE_MESSAGE :
                return [
                    WorkerAnnouncementService::TYPE_BUSINESS_NOTICE,
                    WorkerAnnouncementService::TYPE_ACTIVITY_NOTICE
                ];
                break;
            case self::MESSAGE_TYPE_ORDER_MESSAGE :
                return [
                    WorkerAnnouncementService::TYPE_RECEIVE_ORDER_READ_REQUIRED
                ];
        }

        return [
            -1
        ];
    }

    /*
     * 极光推送test
     */
    public static function jpush($type, $id, $title, $content, $data_id, $registration_id = '', $is_radio = '')
    {
        $app_key = C('jpush.app_key');
        $master_secret = C('jpush.master_secret');
        $jpush = new \JPush\Client($app_key, $master_secret);
        $params = [
            'extras' => [
                'type'        => (string)$type,
                'id'          => $id,
                'title'       => $title,
                'content'     => $content,
                'create_time' => (string)NOW_TIME,
                'data_id'     => $data_id,
                'url'         => C('BACKEND_B_SITE') . C('qy_base_path') . '/app/manual-content/' . $type . '/' . $id,
            ],
        ];
        $push = $jpush->push()
            ->setPlatform(['Android', 'IOS']);
        if ($registration_id) {
            $push->addRegistrationId($registration_id);
        }
        if ($is_radio) {
            $push->setAudience('all');
        }
        $push->setNotificationAlert($params['extras']['title'])
            ->iosNotification($params['extras']['content'], [
                'title'  => $params['extras']['title'],
                'extras' => $params,
            ])
            ->androidNotification($params['extras']['content'], [
                'title'  => $params['extras']['title'],
                'extras' => $params,
            ])
            ->options([
                'apns_production' => true,
            ])->send();
    }


}