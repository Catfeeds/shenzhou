<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/31
 * Time: 16:09
 */

namespace Common\Common\Service;

use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\ErrorCode;

class AdminRoleService
{
    const ROLE_SUPER_ADMIN                          = 4;     // 超级管理员
    const ROLE_FINANCIAL_MANAGER                    = 5;  // 财务主管
    const ROLE_FINANCIAL_AFFAIRS                    = 6;  // 财务
    const ROLE_PARTSMAN                             = 7;  // 配件员
    const ROLE_YIMA_MANAGER                         = 8;  // 易码主管
    const ROLE_YIMA_OPERATION                       = 9;  // 易码主管
    const ROLE_VIEW_ORDER_CS                        = 10;  // 查单客服
    const ROLE_CUSTOMER_SERVICE_SUPERVISOR          = 11;  // 客服主管
    const ROLE_CUSTOMER_SERVICE_TEAM_LEADER         = 12;  // 客服组长
    const ROLE_CHECKER                              = 13;  // 核实客服
    const ROLE_DISTRIBUTOR                          = 14;  // 派单客服
    const ROLE_RETURNEE                             = 15;  // 回访客服
    const ROLE_COMPLAINT                            = 16;  // 客诉专员
    const ROLE_CHANNEL_SUPERVISOR                   = 17; // 渠道主管
    const ROLE_CHANNEL_DEV                          = 18; // 渠道开发
    const ROLE_CHANNEL_MAINTAIN                     = 19; // 渠道维护
//    const ROLE_PICC_CHECK                           = 19;  // 人保查单
//    const ROLE_CHECKER_AND_DISTRIBUTOR              = 24;  // 核实派单客服
//    const ROLE_CHECKER_AND_RETURNEE                 = 25;  // 核实、回访客服
//    const ROLE_CHECKER_AND_DISTRIBUTOR_AND_RETURNEE = 26;  // 核实、派单、回访客服
//    const ROLE_DISTRIBUTOR_AND_RETURNEE             = 27;  // 派单回访客服

    //自动接单可接工单类型 1-核实 2-派单 4-回访 8-财务
    const AUTO_RECEIVE_TYPE_CHECKER     = 1;
    const AUTO_RECEIVE_TYPE_DISTRIBUTOR = 2;
    const AUTO_RECEIVE_TYPE_RETURNEE    = 4;
    const AUTO_RECEIVE_TYPE_AUDITOR     = 8;

    //角色级别  1-普通客服 2-主管 3-组长
    const LEVEL_DEFAULT_ADMIN           = 1;
    const LEVEL_CHARGE_ADMIN            = 2;
    const LEVEL_GROUP_ADMIN             = 3;

    const LEVEL_ALL_ARR = [
        self::LEVEL_DEFAULT_ADMIN,
        self::LEVEL_CHARGE_ADMIN,
        self::LEVEL_GROUP_ADMIN,
    ];


    const AUTO_RECEIVE_TYPE_INDEX_KEY_VALUE = [
        self::AUTO_RECEIVE_TYPE_CHECKER => '1',
        self::AUTO_RECEIVE_TYPE_DISTRIBUTOR => '2',
        self::AUTO_RECEIVE_TYPE_RETURNEE => '3',
        self::AUTO_RECEIVE_TYPE_AUDITOR => '4',
    ];

    const AUTO_RECEIVE_TYPE_VALID_ARRAY = [
        self::AUTO_RECEIVE_TYPE_CHECKER,
        self::AUTO_RECEIVE_TYPE_DISTRIBUTOR,
        self::AUTO_RECEIVE_TYPE_RETURNEE,
        self::AUTO_RECEIVE_TYPE_AUDITOR,
    ];
//
//    // 0创建工单,1自行处理,2外部工单经过厂家审核（待平台核实客服接单）,3平台核实客服接单（待平台核实客服核实信息），4平台核实客服核实用户信息 （待平台派单客服接单）,5平台派单客服接单 （待派发）,6已派发 （抢单池）,7技工接单成功 （待技工预约上门）,8预约成功 （待上门服务）,9已上门（待全部维修项完成维修）,10完成维修 （待平台回访客服接单）,11平台回访客服接单 （待回访）,12平台回访客服回访不通过 （已上门）,13平台回访客服已回访 （待平台财务客服接单）,14平台财务客服接单 （待平台财务客服审核）,15平台财务客服审核不通过 （重新回访客服回访）,16平台财务客服审核 （待厂家财务审核）,17厂家财务审核不通过 （平台财务重新审核）,18厂家财务审核 （已完成工单）
//    const ROLE_HANDLE_STATUS_MAP
//        = [
//            self::ROLE_SUPER_ADMIN                          => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 17],
//            self::ROLE_CUSTOMER_SERVICE_SUPERVISOR          => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 15],
//            self::ROLE_FINANCIAL_AFFAIRS                    => [13, 14, 17],
//            self::ROLE_FINANCIAL_MANAGER                    => [13, 14, 17],
//            self::ROLE_CUSTOMER_SERVICE_TEAM_LEADER         => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 15],
//            self::ROLE_PICC_CHECK                           => [],
//            self::ROLE_CHECKER                              => [2, 3],
//            self::ROLE_DISTRIBUTOR                          => [4, 5, 6, 7, 8, 9, 12],
//            self::ROLE_RETURNEE                             => [10, 11, 15],
//            self::ROLE_CHECKER_AND_DISTRIBUTOR              => [2, 3, 4, 5, 6, 7, 8, 9, 12],
//            self::ROLE_CHECKER_AND_RETURNEE                 => [2, 3, 10, 11, 15],
//            self::ROLE_CHECKER_AND_DISTRIBUTOR_AND_RETURNEE => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 15],
//            self::ROLE_DISTRIBUTOR_AND_RETURNEE             => [4, 5, 6, 7, 8, 9, 10, 11, 12, 15],
//        ];

//    //转单客服允许工单状态
//    const ROLE_TRANSFER_ADMIN_STATUS_MAP
//        = [
//            self::ROLE_SUPER_ADMIN                          => [3, 5, 6, 7, 8, 9, 12, 11, 15, 14, 16, 17],
//            self::ROLE_CUSTOMER_SERVICE_SUPERVISOR          => [3, 5, 6, 7, 8, 9, 12, 11, 15, 14, 16, 17],
//            self::ROLE_CUSTOMER_SERVICE_TEAM_LEADER         => [3, 5, 6, 7, 8, 9, 12, 11, 15, 14, 16, 17],
//            self::ROLE_PICC_CHECK                           => [],
//            self::ROLE_CHECKER                              => [3],
//            self::ROLE_DISTRIBUTOR                          => [5, 6, 7, 8, 9, 12],
//            self::ROLE_RETURNEE                             => [11, 15],
//            self::ROLE_CHECKER_AND_DISTRIBUTOR              => [3, 5, 6, 7, 8, 9, 12],
//            self::ROLE_CHECKER_AND_RETURNEE                 => [3, 11, 15],
//            self::ROLE_CHECKER_AND_DISTRIBUTOR_AND_RETURNEE => [3, 5, 6, 7, 8, 9, 12, 11, 12, 15],
//            self::ROLE_DISTRIBUTOR_AND_RETURNEE             => [5, 6, 7, 8, 9, 12, 11, 12, 15],
//            self::ROLE_FINANCIAL_AFFAIRS                    => [14, 16, 17],
//            self::ROLE_FINANCIAL_MANAGER                    => [14, 16, 17],
//        ];

    const STATE_YES = 0;
    const STATE_NO = 1;

    const STATE_VALID_ARRAY = [
        self::STATE_YES,
        self::STATE_NO,
    ];

    //是否被禁用
    const IS_DISABLE_NO  = '0'; // 启用
    const IS_DISABLE_YES = '1'; // 禁用

    const IS_DISABLE_VALID_ARRAY = [
        self::IS_DISABLE_YES,
        self::IS_DISABLE_NO,
    ];

    //是否已删除
    const IS_DELETE_NO  = 0; // 否
    const IS_DELETE_YES = 1; // 是

    const IS_DELETE_VALID_ARRAY = [
        self::IS_DELETE_YES,
        self::IS_DELETE_NO,
    ];

    /**
     * 获取超级管理员
     * @return array
     */
    public static function getRoleRoot()
    {
        return [self::ROLE_SUPER_ADMIN];
    }

    /**
     * 获取客服超级管理员
     * @return array
     */
    public static function getRoleAdminRoot()
    {
        return [self::ROLE_CUSTOMER_SERVICE_SUPERVISOR, self::ROLE_CUSTOMER_SERVICE_TEAM_LEADER];
    }

    /**
     * 获取渠道客服角色
     * @return array
     */
    public static function getRoleChannel()
    {
        return [self::ROLE_CHANNEL_DEV, self::ROLE_CHANNEL_MAINTAIN, self::ROLE_CHANNEL_SUPERVISOR];
    }

    /**
     * 获取财务客服角色
     * @return array
     */
    public static function getRoleAuditor()
    {
        return [self::ROLE_FINANCIAL_AFFAIRS, self::ROLE_FINANCIAL_MANAGER];
    }

    /**
     * 获取派单客服角色
     * @return array
     */
    public static function getRoleDistributor()
    {
        return [self::ROLE_DISTRIBUTOR];
    }

    /**
     * 获取核实客服角色
     * @return array
     */
    public static function getRoleChecker()
    {
        return [self::ROLE_CHECKER];
    }

    /**
     * 获取回访客服角色
     * @return array
     */
    public static function getRoleReturnee()
    {
        return [self::ROLE_RETURNEE];
    }

    /**
     * 检查客服是否有对应的操作权限
     * @param $admin_id
     * @param $role_access
     * @throws \Exception
     */
    public static function checkOrderAccess($admin_id, $role_access)
    {
        $admin_role_ids = AdminCacheModel::getRelation($admin_id, 'rel_admin_role', 'admin_id', 'admin_role_id');
        $admin_role_ids = explode(',', $admin_role_ids);
        foreach ($admin_role_ids as $admin_role_id) {
            $admin_role = AdminRoleCacheModel::getOne($admin_role_id, 'id,is_disable,type');
            if ($admin_role['is_disable'] == 0 && ($admin_role['type'] & $role_access)) {
                return ;
            }
        }

        throw new \Exception('', \Admin\Common\ErrorCode::WORKER_ORDER_ADMIN_NO_PERMISSION);
    }
    
}