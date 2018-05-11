<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/18
 * Time: 20:58
 */

namespace Common\Common\Service;

use Admin\Logic\ComplaintLogic;
use Common\Common\Model\BaseModel;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Think\Exception;

class ComplaintService
{
    const FROM_TYPE_CS            = 1;
    const FROM_TYPE_FACTORY       = 2;
    const FROM_TYPE_FACTORY_ADMIN = 3;
    const FROM_TYPE_WORKER        = 4;
    const FROM_TYPE_WX_USER       = 5;
    const FROM_TYPE_WX_DEALER     = 6;

    const FROM_TYPE_VALID_ARRAY
        = [
            self::FROM_TYPE_CS,
            self::FROM_TYPE_FACTORY,
            self::FROM_TYPE_FACTORY_ADMIN,
            self::FROM_TYPE_WORKER,
            self::FROM_TYPE_WX_USER,
            self::FROM_TYPE_WX_DEALER,
        ];

    const TO_TYPE_CS            = 1;
    const TO_TYPE_FACTORY       = 2;
    const TO_TYPE_FACTORY_ADMIN = 3;
    const TO_TYPE_WORKER        = 4;
    const TO_TYPE_WX_USER       = 5;

    const RESPONSE_TYPE_CS            = 1;
    const RESPONSE_TYPE_FACTORY       = 2;
    const RESPONSE_TYPE_FACTORY_ADMIN = 3;
    const RESPONSE_TYPE_WORKER        = 4;
    const RESPONSE_TYPE_WX_USER       = 5;
    const RESPONSE_TYPE_WX_DEALER     = 6;

    //是否已提示被投诉人 0-否 1-是
    const IS_PROMPT_COMPLAINT_TO_YES = 1;
    const IS_PROMPT_COMPLAINT_TO_NO  = 0;

    //创建投诉用户类型 1-客服；2-厂家 3-厂家子账号 4-技工 5-用户
    const CREATE_TYPE_CS            = 1;
    const CREATE_TYPE_FACTORY       = 2;
    const CREATE_TYPE_FACTORY_ADMIN = 3;
    const CREATE_TYPE_WORKER        = 4;
    const CREATE_TYPE_WX_USER       = 5;
    const CREATE_TYPE_WX_DEALER     = 6;

    //是否满意
    const IS_SATISFY_YES = 1;
    const IS_SATISFY_NO  = 2;
    const IS_SATISFY_VALID_ARRAY
                         = [
            self::IS_SATISFY_YES,
            self::IS_SATISFY_NO,
        ];

    const IS_DELETE_NO  = 0;
    const IS_DELETE_YES = 1;

    public static function generateComplaintNumber()
    {
        return NOW_TIME . mt_rand(10, 99);
    }

    public static function getResponsePerson($user_type, $worker_order_id)
    {
        $real_user_type = $user_type;
        $user_id = 0;

        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id, 'add_id,checker_id,distributor_id,origin_type,worker_id');
        // 99为下单人，责任人为下单人时，根据工单下单人获取对应的下单人类型
        if (
            $user_type == 99 ||
            $user_type == self::RESPONSE_TYPE_FACTORY ||
            $user_type == self::RESPONSE_TYPE_FACTORY_ADMIN
        ) {
            $user_id = $order['add_id'];
            if (
                $order['origin_type'] == OrderService::ORIGIN_TYPE_FACTORY ||
                $order['origin_type'] == OrderService::ORIGIN_TYPE_FACTORY_ADMIN
            ) {
                $real_user_type = self::RESPONSE_TYPE_FACTORY;
            } elseif ($order['origin_type'] == OrderService::ORIGIN_TYPE_WX_USER) {
                $real_user_type = self::RESPONSE_TYPE_WX_USER;
            } elseif ($order['origin_type'] == OrderService::ORIGIN_TYPE_WX_DEALER) {
                $real_user_type = self::RESPONSE_TYPE_WX_DEALER;
            }
        } elseif ($user_type == self::RESPONSE_TYPE_CS) {
            $real_user_type = self::RESPONSE_TYPE_CS;
            $user_id = (new ComplaintLogic())->getMatchAdmin($worker_order_id);
        } elseif ($user_type == self::RESPONSE_TYPE_WORKER) {
            $real_user_type = self::RESPONSE_TYPE_WORKER;
            if (!empty($order['worker_id'])) {
                $user_id = $order['worker_id'];
            }
        } elseif ($user_type == self::RESPONSE_TYPE_WX_USER) {
            $real_user_type = self::RESPONSE_TYPE_WX_USER;
            $user_id = BaseModel::getInstance('worker_order_user_info')
                ->getFieldVal($worker_order_id, 'wx_user_id');
        } elseif ($user_type == self::RESPONSE_TYPE_WX_DEALER) {
            $real_user_type = self::RESPONSE_TYPE_WX_DEALER;
            $user_id = BaseModel::getInstance('worker_order_user_info')
                ->getFieldVal($worker_order_id, 'wx_user_id');
        }

        return [
            'user_type' => $real_user_type,
            'user_id'   => $user_id,
        ];
    }

    public static function loadComplaintFromUser(&$data)
    {
        foreach ($data as $key => $item) {
            $user_info = UserTypeService::getTypeData($item['complaint_from_type'], $item['complaint_from_id'], UserInfoType::USER_COMPLAINT_FROM_TYPE);
            $data[$key]['complaint_from']['name'] = $user_info->getName();
            $data[$key]['complaint_from']['phone'] = $user_info->getPhone();
        }
    }

    public static function loadComplaintToUser(&$data)
    {
        foreach ($data as $key => $item) {
            $user_info = UserTypeService::getTypeData($item['complaint_to_type'], $item['complaint_to_id']);
            $data[$key]['complaint_to']['name'] = $user_info->getName();
            $data[$key]['complaint_to']['phone'] = $user_info->getPhone();
        }
    }

    public static function loadComplaintResponseUser(&$data)
    {
        foreach ($data as $key => $item) {
            $user_info = UserTypeService::getTypeData($item['response_type'], $item['response_type_id']);
            // 不返回厂家子账号责任人为3类型
            $item['response_type'] == self::RESPONSE_TYPE_FACTORY_ADMIN && $data[$key]['response_type'] = self::RESPONSE_TYPE_FACTORY;
            $data[$key]['response']['name'] = $user_info->getName();
            $data[$key]['response']['phone'] = $user_info->getPhone();
        }
    }

    public static function getCanReplyRoles()
    {
        return [AdminRoleService::ROLE_CHECKER, AdminRoleService::ROLE_CHECKER_AND_DISTRIBUTOR, AdminRoleService::ROLE_CHECKER_AND_RETURNEE, AdminRoleService::ROLE_CHECKER_AND_DISTRIBUTOR_AND_RETURNEE, AdminRoleService::ROLE_DISTRIBUTOR, AdminRoleService::ROLE_DISTRIBUTOR_AND_RETURNEE, AdminRoleService::ROLE_SUPER_ADMIN];
    }

    public static function getCanVerifyRoles()
    {
        return [AdminRoleService::ROLE_COMPLAINT, AdminRoleService::ROLE_SUPER_ADMIN];
    }

    public static function getComplaintToTypeStr($complaint_to_type)
    {
        switch ($complaint_to_type) {
            case self::TO_TYPE_CS:
                return '客服';
            case self::TO_TYPE_FACTORY:
            case self::TO_TYPE_FACTORY_ADMIN:
                return '厂家';
            case self::TO_TYPE_WORKER:
                return '技工';
            case self::TO_TYPE_WX_USER:
                return '用户';
        }

        return '';

    }

    public static function getComplaintResponseTypeStr($complaint_response_type)
    {
        switch ($complaint_response_type) {
            case self::RESPONSE_TYPE_CS:
                return '客服';
            case self::RESPONSE_TYPE_FACTORY:
            case self::RESPONSE_TYPE_FACTORY_ADMIN:
                return '厂家';
            case self::RESPONSE_TYPE_WORKER:
                return '技工';
            case self::RESPONSE_TYPE_WX_USER: // no break
            case self::TO_TYPE_WX_USER:
                return '用户';
        }

        return '';
    }

}