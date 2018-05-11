<?php
/**
 * File: SystemMessageService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/28
 */

namespace Common\Common\Service;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;

class SystemMessageService
{

    const UNREAD  = 0;
    const IS_READ = 1;

    const USER_TYPE_UNKNOWN       = 0;
    const USER_TYPE_ADMIN         = 1; // 客服
    const USER_TYPE_FACTORY       = 2; // 厂家
    const USER_TYPE_FACTORY_ADMIN = 3; // 厂家子账号
    const USER_TYPE_WORKER        = 4; // 技工
    const USER_TYPE_WX_USER       = 5; // 微信用户

    const CATEGORY_TYPE_UNKNOWN       = 0; // 异常
    const CATEGORY_TYPE_COST          = 1; // 费用单
    const CATEGORY_TYPE_ACCESSORY     = 2; // 配件单
    const CATEGORY_TYPE_YIMA          = 3; // 易码
    const CATEGORY_TYPE_ALLOWANCE     = 4; // 补贴单
    const CATEGORY_TYPE_COMPLAINT     = 5; // 投诉单
    const CATEGORY_TYPE_LEAVE_MESSAGE = 6; // 留言单
    const CATEGORY_TYPE_RECHARGE      = 7; // 充值
    const CATEGORY_TYPE_ORDER         = 8; // 工单消息
    const CATEGORY_TYPE_RECRUIT       = 9; // 开点单
    const CATEGORY_TYPE_MASTER_CODE   = 10; // 师傅码

    //费用单
    //维修商申请费用
    const MSG_TYPE_ADMIN_COST_WORKER_APPLY = 100;
    //厂家审核配件单(通过)
    const MSG_TYPE_ADMIN_COST_ADMIN_APPLY_PASS = 101;
    //厂家审核配件单(不通过)
    const MSG_TYPE_ADMIN_COST_ADMIN_APPLY_FORBIDDEN = 102;
    //客服审核通过费用单
    const MSG_TYPE_FACTORY_COST_ADMIN_APPLY_PASS = 103;

    //配件单
    //维修商申请配件
    const MSG_TYPE_ADMIN_ACCESSORY_WORKER_APPLY = 200;
    //厂家审核配件单(通过)
    const MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_PASS = 201;
    //厂家审核配件单(不通过)
    const MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_FORBIDDEN = 202;
    //已到厂家预估发货时间，但厂家还未发件
    const MSG_TYPE_ADMIN_ACCESSORY_FACTORY_DELAY = 203;
    //厂家确认发件
    const MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_SEND = 204;
    //维修商确认收件
    const MSG_TYPE_ADMIN_ACCESSORY_WORKER_TAKE = 205;
    //厂家确认收件
    const MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_TAKE = 206;
    //维修商返还配件
    const MSG_TYPE_ADMIN_ACCESSORY_WORKER_SENT = 207;
    //厂家放弃配件
    const MSG_TYPE_ADMIN_ACCESSORY_FACTORY_GIVE_UP_RETURN = 208;
    //厂家终止配件单
    const MSG_TYPE_ADMIN_ACCESSORY_FACTORY_STOP = 209;
    //工单完结7天后，配件还未返还
    const MSG_TYPE_ADMIN_ACCESSORY_SYSTEM_FOUND_NOT_BACK = 210;
    //客服审核通过配件单
    const MSG_TYPE_FACTORY_ACCESSORY_ADMIN_APPLY_PASS = 211;
    //已到预估发件时间，但还未发件
    const MSG_TYPE_FACTORY_ACCESSORY_SYSTEM_FOUND_NOT_SEND = 212;
    //维修商已返件
    const MSG_TYPE_FACTORY_ACCESSORY_WORKER_SENT_BACK = 213;
    //客服终止配件单
    const MSG_TYPE_FACTORY_ACCESSORY_ADMIN_STOP = 214;

    //易码
    //易码申请
    const MSG_TYPE_ADMIN_YIMA_FACTORY_APPLY = 300;
    //厂家申请易码后，自行取消
    const MSG_TYPE_ADMIN_YIMA_SYSTEM_APPLY_FORBIDDEN = 301;
    //易码审核结果(已印刷)
    const MSG_TYPE_FACTORY_YIMA_SYSTEM_APPLY_PASS = 302;
    //易码审核结果(自行取消)
    const MSG_TYPE_FACTORY_YIMA_SYSTEM_APPLY_FORBIDDEN = 303;
    //申请成为厂家经销商
    const MSG_TYPE_FACTORY_YIMA_USER_APPLY_JOIN = 304;
    //易码下单
    const MSG_TYPE_FACTORY_YIMA_USER_ORDER = 305;

    //补贴单
    //补贴审核(通过)
    const MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_PASS = 401;
    //补贴审核(不通过)
    const MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_FORBIDDEN = 402;

    //投诉
    //厂家投诉（工单的当前处理工单客服）
    const MSG_TYPE_ADMIN_COMPLAINT_APPLY = 500;
    //客服回复投诉（提交投诉的账号）
    const MSG_TYPE_FACTORY_COMPLAINT_RESPONSE = 501;

    //留言
    //厂家有留言发送/回复（工单的当前工单客服）
    const MSG_TYPE_FACTORY_LEAVE_MESSAGE_NEW_MESSAGE = 600;
    //客服有留言发送/回复
    const MSG_TYPE_ADMIN_LEAVE_MESSAGE_NEW_MESSAGE = 601;

    //充值
    //自主充值/平台财务手工调整
    const MSG_TYPE_FACTORY_RECHARGE_SUCCESS = 700;
    //余额不足
    const MSG_TYPE_FACTORY_RECHARGE_REMIND = 701;

    //工单
    //师傅退单
    const MSG_TYPE_ADMIN_ORDER_WORKER_CHARGE_BACK = 800;
    //放到抢单池的，师傅接单
    const MSG_TYPE_ADMIN_ORDER_WORKER_GET_ORDER = 801;
    //放到抢单池，12小时都没有师傅接单
    const MSG_TYPE_ADMIN_ORDER_ORDER_NOT_GET = 802;
    //厂家取消工单
    const MSG_TYPE_ADMIN_ORDER_FACTORY_STOP = 803;
    //预发件工单的物流，快递100返回已签收
    const MSG_TYPE_ADMIN_ORDER_WORKER_TAKE = 804;
    //上传预约
    const MSG_TYPE_ADMIN_ORDER_UPLOAD_APPOINT = 805;
    //到了预约的时间4小时，师傅还未提交服务报告/签到记录
    const MSG_TYPE_ADMIN_ORDER_SYSTEM_FOUND_NOT_SIGN_IN = 806;
    //神州财务退回给客服处理
    const MSG_TYPE_ADMIN_ORDER_AUDITOR_CHARGE_BACK = 807;
    //师傅上传完成服务/不能完成服务的报告
    const MSG_TYPE_ADMIN_ORDER_WORKER_UPLOAD_REPORT = 808;
    //厂家财务审核不通过
    const MSG_TYPE_FACTORY_ORDER_FACTORY_AUDITOR_FORBIDDEN = 809;
    //客服取消工单
    const MSG_TYPE_FACTORY_ORDER_ADMIN_STOP = 810;
    //神州财务提交给厂家财务审核
    const MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS = 811;
    //神州回访客服退回给技工
    const MSG_TYPE_ADMIN_ORDER_RETURNEE_RETURN_BACK = 812;
    // 保外单 神州财务提交给厂家财务审核
    const MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS_AND_FACTORY_AOTU_PASS = 813;

    //开点单
    //渠道客服选择跟进中
    const MSG_TYPE_ADMIN_RECRUIT_ADMIN_FOLLOW = 900;
    //渠道客服选择开点失败
    const MSG_TYPE_ADMIN_RECRUIT_ADMIN_FAIL = 901;
    //渠道客服选择开点成功
    const MSG_TYPE_ADMIN_RECRUIT_ADMIN_SUCCESS = 902;

    //师傅码
    const MSG_TYPE_ADMIN_MASTER_CODE_WORKER_APPLY = 1000;

    static $msg_title
        = [
            self::MSG_TYPE_ADMIN_COST_WORKER_APPLY          => '费用申请',
            self::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_PASS      => '费用申请通过',
            self::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_FORBIDDEN => '费用申请不通过',
            self::MSG_TYPE_FACTORY_COST_ADMIN_APPLY_PASS    => '费用申请',

            self::MSG_TYPE_ADMIN_ACCESSORY_WORKER_APPLY            => '配件申请',
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_PASS      => '配件申请通过',
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_FORBIDDEN => '配件申请不通过',
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_DELAY           => '配件发件延误',
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_SEND    => '已发件',
            self::MSG_TYPE_ADMIN_ACCESSORY_WORKER_TAKE             => '已签收',
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_TAKE    => '已返件',
            self::MSG_TYPE_ADMIN_ACCESSORY_WORKER_SENT             => '已返件',
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_GIVE_UP_RETURN  => '放弃配件',
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_STOP            => '配件终止',
            self::MSG_TYPE_ADMIN_ACCESSORY_SYSTEM_FOUND_NOT_BACK   => '未返件',
            self::MSG_TYPE_FACTORY_ACCESSORY_ADMIN_APPLY_PASS      => '配件申请',
            self::MSG_TYPE_FACTORY_ACCESSORY_SYSTEM_FOUND_NOT_SEND => '请发件',
            self::MSG_TYPE_FACTORY_ACCESSORY_WORKER_SENT_BACK      => '已返件',
            self::MSG_TYPE_FACTORY_ACCESSORY_ADMIN_STOP            => '配件终止',

            self::MSG_TYPE_ADMIN_YIMA_FACTORY_APPLY            => '易码申请',
            self::MSG_TYPE_ADMIN_YIMA_SYSTEM_APPLY_FORBIDDEN   => '易码取消',
            self::MSG_TYPE_FACTORY_YIMA_SYSTEM_APPLY_PASS      => '易码已印刷',
            self::MSG_TYPE_FACTORY_YIMA_SYSTEM_APPLY_FORBIDDEN => '易码系统取消',
            self::MSG_TYPE_FACTORY_YIMA_USER_APPLY_JOIN        => '申请经销商',
            self::MSG_TYPE_FACTORY_YIMA_USER_ORDER             => '用户下单',

            self::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_PASS      => '补贴审核通过',
            self::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_FORBIDDEN => '补贴审核不通过',

            self::MSG_TYPE_ADMIN_COMPLAINT_APPLY      => '投诉单',
            self::MSG_TYPE_FACTORY_COMPLAINT_RESPONSE => '投诉回复',

            self::MSG_TYPE_ADMIN_LEAVE_MESSAGE_NEW_MESSAGE   => '有留言',
            self::MSG_TYPE_FACTORY_LEAVE_MESSAGE_NEW_MESSAGE => '有留言',

            self::MSG_TYPE_FACTORY_RECHARGE_SUCCESS => '充值消息',
            self::MSG_TYPE_FACTORY_RECHARGE_REMIND  => '余额不足',

            self::MSG_TYPE_ADMIN_ORDER_WORKER_CHARGE_BACK          => '师傅退单',
            self::MSG_TYPE_ADMIN_ORDER_WORKER_GET_ORDER            => '师傅接单',
            self::MSG_TYPE_ADMIN_ORDER_ORDER_NOT_GET               => '无接单',
            self::MSG_TYPE_ADMIN_ORDER_FACTORY_STOP                => '厂家取消',
            self::MSG_TYPE_ADMIN_ORDER_WORKER_TAKE                 => '已签收',
            self::MSG_TYPE_ADMIN_ORDER_UPLOAD_APPOINT              => '上传预约',
            self::MSG_TYPE_ADMIN_ORDER_SYSTEM_FOUND_NOT_SIGN_IN    => '未上门',
            self::MSG_TYPE_ADMIN_ORDER_AUDITOR_CHARGE_BACK         => '财务退回',
            self::MSG_TYPE_ADMIN_ORDER_WORKER_UPLOAD_REPORT        => '完成维修',
            self::MSG_TYPE_FACTORY_ORDER_FACTORY_AUDITOR_FORBIDDEN => '厂家审核不通过',
            self::MSG_TYPE_FACTORY_ORDER_ADMIN_STOP                => '工单取消',
            self::MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS     => '工单审核',
            self::MSG_TYPE_ADMIN_ORDER_RETURNEE_RETURN_BACK        => '回访不通过',
            self::MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS_AND_FACTORY_AOTU_PASS => '保外单已完成',

            self::MSG_TYPE_ADMIN_RECRUIT_ADMIN_FOLLOW  => '开点跟进中',
            self::MSG_TYPE_ADMIN_RECRUIT_ADMIN_FAIL    => '开点失败',
            self::MSG_TYPE_ADMIN_RECRUIT_ADMIN_SUCCESS => '开点成功',

            self::MSG_TYPE_ADMIN_MASTER_CODE_WORKER_APPLY => '申领师傅码',
        ];


    public static function getCostType()
    {
        return [self::MSG_TYPE_ADMIN_COST_WORKER_APPLY, self::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_PASS, self::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_FORBIDDEN, self::MSG_TYPE_FACTORY_COST_ADMIN_APPLY_PASS,];
    }

    public static function getAccessoryType()
    {
        return [self::MSG_TYPE_ADMIN_ACCESSORY_WORKER_APPLY,
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_PASS,
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_FORBIDDEN,
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_DELAY,
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_SEND,
            self::MSG_TYPE_ADMIN_ACCESSORY_WORKER_TAKE,
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_TAKE,
            self::MSG_TYPE_ADMIN_ACCESSORY_WORKER_SENT,
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_GIVE_UP_RETURN,
            self::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_STOP,
            self::MSG_TYPE_ADMIN_ACCESSORY_SYSTEM_FOUND_NOT_BACK,
            self::MSG_TYPE_FACTORY_ACCESSORY_ADMIN_APPLY_PASS,
            self::MSG_TYPE_FACTORY_ACCESSORY_SYSTEM_FOUND_NOT_SEND,
            self::MSG_TYPE_FACTORY_ACCESSORY_WORKER_SENT_BACK,
            self::MSG_TYPE_FACTORY_ACCESSORY_ADMIN_STOP,];
    }

    public static function getYimaType()
    {
        return [self::MSG_TYPE_ADMIN_YIMA_FACTORY_APPLY, self::MSG_TYPE_ADMIN_YIMA_SYSTEM_APPLY_FORBIDDEN, self::MSG_TYPE_FACTORY_YIMA_SYSTEM_APPLY_PASS, self::MSG_TYPE_FACTORY_YIMA_SYSTEM_APPLY_FORBIDDEN, self::MSG_TYPE_FACTORY_YIMA_USER_APPLY_JOIN, self::MSG_TYPE_FACTORY_YIMA_USER_ORDER,];
    }

    public static function getAllowanceType()
    {
        return [self::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_PASS, self::MSG_TYPE_FACTORY_ALLOWANCE_ADMIN_APPLY_FORBIDDEN,];
    }

    public static function getComplaintType()
    {
        return [self::MSG_TYPE_ADMIN_COMPLAINT_APPLY, self::MSG_TYPE_FACTORY_COMPLAINT_RESPONSE,];
    }

    public static function getLeaveMessageType()
    {
        return [self::MSG_TYPE_ADMIN_LEAVE_MESSAGE_NEW_MESSAGE,
            self::MSG_TYPE_FACTORY_LEAVE_MESSAGE_NEW_MESSAGE,];
    }

    public static function getRechargeType()
    {
        return [self::MSG_TYPE_FACTORY_RECHARGE_SUCCESS, self::MSG_TYPE_FACTORY_RECHARGE_REMIND];
    }

    public static function getOrderType()
    {
        return [
            self::MSG_TYPE_ADMIN_ORDER_WORKER_CHARGE_BACK,
            self::MSG_TYPE_ADMIN_ORDER_WORKER_GET_ORDER,
            self::MSG_TYPE_ADMIN_ORDER_ORDER_NOT_GET,
            self::MSG_TYPE_ADMIN_ORDER_FACTORY_STOP,
            self::MSG_TYPE_ADMIN_ORDER_WORKER_TAKE,
            self::MSG_TYPE_ADMIN_ORDER_UPLOAD_APPOINT,
            self::MSG_TYPE_ADMIN_ORDER_SYSTEM_FOUND_NOT_SIGN_IN,
            self::MSG_TYPE_ADMIN_ORDER_AUDITOR_CHARGE_BACK,
            self::MSG_TYPE_ADMIN_ORDER_WORKER_UPLOAD_REPORT,
            self::MSG_TYPE_FACTORY_ORDER_FACTORY_AUDITOR_FORBIDDEN,
            self::MSG_TYPE_FACTORY_ORDER_ADMIN_STOP,
            self::MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS,
            self::MSG_TYPE_ADMIN_ORDER_RETURNEE_RETURN_BACK,
            self::MSG_TYPE_FACTORY_ORDER_PLATFORM_AUDITOR_PASS_AND_FACTORY_AOTU_PASS,
        ];
    }

    public static function getRecruitType()
    {
        return [self::MSG_TYPE_ADMIN_RECRUIT_ADMIN_FAIL, self::MSG_TYPE_ADMIN_RECRUIT_ADMIN_FOLLOW, self::MSG_TYPE_ADMIN_RECRUIT_ADMIN_SUCCESS];
    }


    public static function getMasterCodeType()
    {
        return [self::MSG_TYPE_ADMIN_MASTER_CODE_WORKER_APPLY];
    }

    /**
     * 添加消息到后台
     *
     * @param int    $receiver_type 接收者类型 取自常量USER_TYPE_*
     * @param int    $receiver_id   接收者用户ID
     * @param string $content       推送内容
     * @param int    $data_id       详情ID,如配件单ID,费用单ID
     * @param int    $msg_type      消息类型 取自常量MSG_TYPE_*
     *
     * @throws \Exception
     */
    public static function create($receiver_type, $receiver_id, $content, $data_id, $msg_type)
    {
        $valid_receiver_type = [self::USER_TYPE_ADMIN, self::USER_TYPE_FACTORY, self::USER_TYPE_FACTORY_ADMIN, self::USER_TYPE_WORKER, self::USER_TYPE_WX_USER];
        if (!in_array($receiver_type, $valid_receiver_type)) {
            throw new \Exception('用户类型异常', ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        $category_type = self::getCategoryType($msg_type);
        if (self::CATEGORY_TYPE_UNKNOWN == $category_type) {
            throw new \Exception('消息种类异常', ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        $title = self::$msg_title[$msg_type] ? '【' . self::$msg_title[$msg_type] . '】' : '';
        $content = $title . $content;

        $insert_data = [
            'user_type'     => $receiver_type,
            'user_id'       => $receiver_id,
            'msg_type'      => $msg_type,
            'data_id'       => $data_id,
            'category_type' => $category_type,
            'msg_content'   => $content,
            'create_time'   => NOW_TIME,
        ];

        $model = BaseModel::getInstance('worker_order_system_message');
        $model->insert($insert_data);
    }

    public static function createMany($receiver_type, $msg_type, $opts)
    {
        if (empty($opts)) {
            return false;
        }

        $insert_data = [];
        foreach ($opts as $opt) {
            $receiver_id = $opt['receiver_id'];
            $content = $opt['content'];
            $data_id = $opt['data_id'];

            $category_type = self::getCategoryType($msg_type);
            $title = self::$msg_title[$msg_type] ? '【' . self::$msg_title[$msg_type] . '】' : '';
            $content = $title . $content;

            $insert_data[] = [
                'user_type'     => $receiver_type,
                'user_id'       => $receiver_id,
                'msg_type'      => $msg_type,
                'data_id'       => $data_id,
                'category_type' => $category_type,
                'msg_content'   => $content,
                'create_time'   => NOW_TIME,
            ];
        }

        $model = BaseModel::getInstance('worker_order_system_message');
        $model->insertAll($insert_data);

        return true;
    }

    protected static function getCategoryType($msg_type)
    {
        if (in_array($msg_type, self::getAccessoryType())) {
            //配件单
            return self::CATEGORY_TYPE_ACCESSORY;
        } elseif (in_array($msg_type, self::getCostType())) {
            //费用单
            return self::CATEGORY_TYPE_COST;
        } elseif (in_array($msg_type, self::getAllowanceType())) {
            //补贴单
            return self::CATEGORY_TYPE_ALLOWANCE;
        } elseif (in_array($msg_type, self::getComplaintType())) {
            //投诉
            return self::CATEGORY_TYPE_COMPLAINT;
        } elseif (in_array($msg_type, self::getLeaveMessageType())) {
            //留言
            return self::CATEGORY_TYPE_LEAVE_MESSAGE;
        } elseif (in_array($msg_type, self::getOrderType())) {
            //工单
            return self::CATEGORY_TYPE_ORDER;
        } elseif (in_array($msg_type, self::getRechargeType())) {
            //充值
            return self::CATEGORY_TYPE_RECHARGE;
        } elseif (in_array($msg_type, self::getYimaType())) {
            //易码
            return self::CATEGORY_TYPE_YIMA;
        } elseif (in_array($msg_type, self::getRecruitType())) {
            return self::CATEGORY_TYPE_RECRUIT;
        } elseif (in_array($msg_type, self::getMasterCodeType())) {
            return self::CATEGORY_TYPE_MASTER_CODE;
        }

        return self::CATEGORY_TYPE_UNKNOWN;
    }

    public static function getUserType()
    {
        $role = AuthService::getModel();

        //默认系统
        switch ($role) {
            case AuthService::ROLE_ADMIN:
                return self::USER_TYPE_ADMIN;
            case AuthService::ROLE_FACTORY:
                return self::USER_TYPE_FACTORY;
            case AuthService::ROLE_FACTORY_ADMIN:
                return self::USER_TYPE_FACTORY_ADMIN;
            case AuthService::ROLE_WORKER:
                return self::USER_TYPE_WORKER;
            case AuthService::ROLE_WX_USER:
                return self::USER_TYPE_WX_USER;
            default:
                throw new \Exception('异常用户类型', ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
    }

}