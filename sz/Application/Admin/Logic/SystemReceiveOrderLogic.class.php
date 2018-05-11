<?php
/**
 * File: SystemReceiveOrderLogic.class.php
 * Function:自动接单
 * User: sakura
 * Date: 2018/2/6
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Carbon\Carbon;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminConfigReceiveAreaCacheModel;
use Common\Common\CacheModel\AdminConfigReceiveCategoryCacheModel;
use Common\Common\CacheModel\AdminConfigReceiveFactoryCacheModel;
use Common\Common\CacheModel\AdminConfigReceiveFactoryGroupModel;
use Common\Common\CacheModel\AdminConfigReceivePartnerModel;
use Common\Common\CacheModel\CacheModel;
use Common\Common\CacheModel\CmListItemCacheModel;
use Common\Common\CacheModel\FactoryCacheModel;
use Common\Common\CacheModel\WorkerOrderProductCacheModel;
use Common\Common\CacheModel\WorkerOrderUserInfoCacheModel;
use Common\Common\Job\AuditorReceiveOrderJob;
use Common\Common\Job\CheckReceiveOrderJob;
use Common\Common\Job\DistributorReceiveOrderJob;
use Common\Common\Job\ReturnReceiveOrderJob;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SMSService;
use Library\Common\Util;

class SystemReceiveOrderLogic extends BaseLogic
{

    /**
     * 财务接单
     *
     * @param $param
     */
    public function auditorReceive($param)
    {
        $begin = microtime(true);

        $this->display('================ 财务接单 ==============');
        $this->display('方法参数', $param);
        $this->display('发生时间', date('H:i:s', time()));

        $worker_order_id = $param['worker_order_id'];
        $timestamp = $param['timestamp'];

        //检查参数
        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($timestamp <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取工单
        $order_model = BaseModel::getInstance('worker_order');
        $field = 'worker_order_status,cancel_status,orno';
        $worker_order = $order_model->getOneOrFail($worker_order_id, $field);
        $worker_order_status = $worker_order['worker_order_status'];
        $cancel_status = $worker_order['cancel_status'];

        $this->display('工单信息', $worker_order);
        $this->display('工单号', $worker_order['orno']);
        $this->display('工单状态', OrderService::getStatusStr($worker_order['worker_order_status'], $worker_order['cancel_status'], AuthService::ROLE_ADMIN));

        //检查工单
        if (OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE != $worker_order_status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态错误');
        }
        if (
            OrderService::CANCEL_TYPE_NULL != $cancel_status &&
            OrderService::CANCEL_TYPE_CS_STOP != $cancel_status
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已取消');
        }

        //获取客服列表下标
        $redis = RedisPool::getInstance();
        $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
        $rest_at = strtotime(date('Ymd')) + 18 * 3600;
        $auditor_key = sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_KEY_TPL'), $weekday);
        if (time() > $rest_at) {
            $weekday = ($weekday + 1) % 7;
            //休息时间,使用休息时间客服缓存
//            $auditor_key = sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_REST_KEY_TPL'), $weekday);
            $auditor_key = sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_KEY_TPL'), $weekday);
        }

        //键不存在,补充缓存
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);
        if (!$redis->sIsMember($register_key, sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_KEY_TPL'), $weekday))) {
            $expire = strtotime(date('Ymd', $timestamp)) + 86399;
            (new SystemReceiveOrderCacheLogic())->initAdminAuditor($weekday, $expire);
        }

//      if (!$redis->sIsMember($register_key, sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_REST_KEY_TPL'), $tomorrow))) {
        if (!$redis->sIsMember($register_key, sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_KEY_TPL'), $weekday))) {
            $expire = strtotime(date('Ymd', $timestamp)) + 86399;
//            (new SystemReceiveOrderCacheLogic())->initAdminAuditorRest($weekday % 7, $expire);
            (new SystemReceiveOrderCacheLogic())->initAdminAuditor($weekday, $expire);
        }


        //获取当前客服总数
        $auditor_num = $redis->zCard($auditor_key);
        $this->display('redis key', $auditor_key);
        $this->display('财务总数', $auditor_num);

        $match_admin_id = 0;
        if (0 == $auditor_num) {

            $this->display('没有财务客服,通知默认客服');

            $match_admin_id = C('AUTO_RECEIVE_DEFAULT_ADMIN.AUDITOR');

        } else {

            $this->display('有财务客服');

            //获取当前待接单的财务客服
            $offset = $this->getAuditorOffset($weekday);

            $this->display('偏移量', $offset);

            $index = $offset % $auditor_num;

            $this->display('下标', $index);

            $admin_ids = $redis->zRange($auditor_key, 0, -1);
            $this->display('######## 财务列表 ########');
            foreach ($admin_ids as $admin_id) {
                $admin = AdminCacheModel::getOneOrFail($admin_id, 'nickout');
                $this->display('客服id', $admin_id);
                $this->display('客服名', $admin['nickout']);
            }
            $this->display('######## 财务列表END ########');

            $this->display('匹配第' . $index . '个客服');

            $match_admin_id = $redis->zRange($auditor_key, $index, $index);
            if (empty($match_admin_id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服id错误');
            }
            $match_admin_id = $match_admin_id[0];
        }
        $this->display('匹配客服id', $match_admin_id);

        $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'nickout');
        $this->display('匹配客服名', $match_admin['nickout']);

        $admin_info = AdminCacheModel::getOneOrFail($match_admin_id, 'user_name');
        $user_name = $admin_info['user_name'];

        M()->startTrans();
        //接单
        $order_model->update($worker_order_id, [
            'worker_order_status'  => OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
            'auditor_id'           => $match_admin_id,
            'auditor_receive_time' => time(),
            'last_update_time'     => time(),
        ]);

        //工单日志
        $operation_type = OrderOperationRecordService::CS_AUDITOR_RECEIVED;
        OrderOperationRecordService::create($worker_order_id, $operation_type, [
            'content_replace'  => [
                'admin_name' => $user_name,
            ],
            'operator_id'      => 0,
            'see_auth'         => null,
            'is_system_create' => 1,
        ]);

        M()->commit();

        $end = microtime(true);

        $this->display('执行时长', $end - $begin);
        $this->display('================= 财务接单 END =====================');
        $this->log('auditor');
    }

    /**
     * 工单接单
     *
     * @param $param
     */
    public function workerOrderReceive($param)
    {
        $begin = microtime(true);

        $this->display('================ 工单接单 ==============');
        $this->display('方法参数', $param);
        $this->display('发生时间', date('H:i:s', time()));

        $worker_order_id = $param['worker_order_id'];
        $receive_role_type = $param['receive_role_type'];
        $timestamp = $param['timestamp'];

        //检查参数
        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if (!in_array($receive_role_type, C('AUTO_RECEIVE_ROLE_TYPE'))) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '接单类型错误');
        }
        if ($timestamp <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工作日错误');
        }

        //获取工单
        $order_model = BaseModel::getInstance('worker_order');
        $field = 'worker_order_status,checker_id,distributor_id,returnee_id,factory_id,cancel_status,orno';
        $worker_order = $order_model->getOneOrFail($worker_order_id, $field);
        $worker_order_status = $worker_order['worker_order_status'];
        $checker_id = $worker_order['checker_id'];
        $distributor_id = $worker_order['distributor_id'];
        $factory_id = $worker_order['factory_id'];
        $cancel_status = $worker_order['cancel_status'];

        $this->display('工单信息', $worker_order);
        $this->display('工单号', $worker_order['orno']);
        $this->display('工单状态', OrderService::getStatusStr($worker_order['worker_order_status'], $worker_order['cancel_status'], AuthService::ROLE_ADMIN));

        //获取工单产品
        $product_ids = WorkerOrderProductCacheModel::getWorkerOrderProductIds($worker_order_id);
        $product_id = min($product_ids);
        $product = WorkerOrderProductCacheModel::getWorkerOrderProduct($product_id, 'product_category_id');
        $category_id = $product['product_category_id'];

        $ancestor_category_id = $this->getCategoryAncestor($category_id);

        //获取工单地区
        $user_info = WorkerOrderUserInfoCacheModel::getWorkerOrderUserInfo($worker_order_id, 'province_id');
        $area_id = $user_info['province_id'];

        $admin_key = '';
        $next_worker_order_status = 0; // 接单后工单状态
        $update_admin_field = ''; //更新客服字段名称
        $update_receive_time_field = ''; // 更新接单时间
        $operation_type = 0; // 工单日志类型
        $precedence_admin_id = 0; // 匹配的客服id
        $redis = RedisPool::getInstance();

        $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        if (C('AUTO_RECEIVE_ROLE_TYPE.CHECKER') == $receive_role_type) {
            $this->display('当前工单类型:核实工单');
            //核实
            if (OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE != $worker_order_status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '待核实工单状态错误');
            }
            if (OrderService::CANCEL_TYPE_NULL != $cancel_status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已取消');
            }

            $admin_key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_CHECKER_KEY_TPL'), $weekday);

            if (!$redis->sIsMember($register_key, $admin_key)) {
                $expire = strtotime(date('Ymd', $timestamp)) + 86399;
                (new SystemReceiveOrderCacheLogic())->initAdminChecker($weekday, $expire);
            }

            $next_worker_order_status = OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK;

            $update_admin_field = 'checker_id';
            $update_receive_time_field = 'checker_receive_time';

            $operation_type = OrderOperationRecordService::CS_CHECKER_RECEIVED;

        } elseif (C('AUTO_RECEIVE_ROLE_TYPE.DISTRIBUTOR') == $receive_role_type) {
            $this->display('当前工单类型:派单工单');
            //派单
            if (OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE != $worker_order_status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '待派单工单状态错误');
            }
            if (
                OrderService::CANCEL_TYPE_NULL != $cancel_status &&
                OrderService::CANCEL_TYPE_CS_STOP != $cancel_status
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已取消');
            }

            $precedence_admin_id = $checker_id;

            $admin_key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_DISTRIBUTOR_KEY_TPL'), $weekday);
            if (!$redis->sIsMember($register_key, $admin_key)) {
                $expire = strtotime(date('Ymd', $timestamp)) + 86399;
                (new SystemReceiveOrderCacheLogic())->initAdminDistributor($weekday, $expire);
            }

            $next_worker_order_status = OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE;

            $update_admin_field = 'distributor_id';
            $update_receive_time_field = 'distributor_receive_time';
            $operation_type = OrderOperationRecordService::CS_DISTRIBUTOR_RECEIVED;

        } elseif (C('AUTO_RECEIVE_ROLE_TYPE.RETURNEE') == $receive_role_type) {
            //回访
            $this->display('当前工单类型:回访工单');
            if (OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE != $worker_order_status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '待回访工单状态错误');
            }
            if (
                OrderService::CANCEL_TYPE_NULL != $cancel_status &&
                OrderService::CANCEL_TYPE_CS_STOP != $cancel_status
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单已取消');
            }

            $precedence_admin_id = $distributor_id;

            $admin_key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_RETURNEE_KEY_TPL'), $weekday);
            if (!$redis->sIsMember($register_key, $admin_key)) {
                $expire = strtotime(date('Ymd', $timestamp)) + 86399;
                (new SystemReceiveOrderCacheLogic())->initAdminReturnee($weekday, $expire);
            }

            $next_worker_order_status = OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT;

            $update_admin_field = 'returnee_id';
            $update_receive_time_field = 'returnee_receive_time';
            $operation_type = OrderOperationRecordService::CS_RETURNEE_RECEIVED;
        }

        $this->display('redis角色键', $admin_key);
        $this->display('厂家id', $factory_id);
        $factory_info = BaseModel::getInstance('factory')
            ->getOneOrFail($factory_id);
        $this->display('厂家全称', $factory_info['factory_full_name']);
        $this->display('厂家登录账号', $factory_info['linkphone']);
        $this->display('顶级品类id', $ancestor_category_id);
        $category_info = BaseModel::getInstance('cm_list_item')
            ->getOne($ancestor_category_id);
        $this->display('顶级品类名', empty($category_info) ? '不存在' : $category_info['item_desc']);
        $this->display('地区id', $area_id);
        $area_info = BaseModel::getInstance('area')->getOne($area_id);
        $this->display('省', empty($area_info) ? '不存在' : $area_info['name']);
        $this->display('是否有优先匹配客服', $precedence_admin_id > 0 ? '是' : '否');
        if ($precedence_admin_id > 0) {
            $this->display('优先匹配客服id', $precedence_admin_id);
            $precedence_admin_info = AdminCacheModel::getOneOrFail($precedence_admin_id, 'nickout');
            $this->display('客服名', $precedence_admin_info['nickout']);
        }
        $this->display('匹配前执行时长', microtime(true) - $begin);

        if (
            (false === $match_admin_id = $this->matchFactory($admin_key, $timestamp, $factory_id, $area_id, $precedence_admin_id)) &&
            (false === $match_admin_id = $this->matchFactoryGroup($admin_key, $timestamp, $factory_id, $area_id, $precedence_admin_id)) &&
            (false === $match_admin_id = $this->matchCategory($admin_key, $timestamp, $ancestor_category_id, $area_id, $precedence_admin_id)) &&
            (false === $match_admin_id = $this->matchArea($admin_key, $timestamp, $area_id, $precedence_admin_id))
        ){
            $this->display('没有匹配厂家 品类 地区的客服');

            $match_admin_id = C('AUTO_RECEIVE_DEFAULT_ADMIN.WORKER_ORDER');
        }

        $this->display('匹配客服id', $match_admin_id);
        $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'nickout');
        $this->display('匹配客服名', $match_admin['nickout']);

        if (!in_array($match_admin_id, C('AUTO_RECEIVE_DEFAULT_ADMIN'))) {
            $redis->hIncrBy(
                sprintf(C('AUTO_RECEIVE_ADMIN.LIST'), $weekday),
                sprintf(C('AUTO_RECEIVE_ADMIN.FIELD_CUR'), $match_admin_id),
                1
            );
        }

        $admin_info = AdminCacheModel::getOneOrFail($match_admin_id, 'user_name');
        $user_name = $admin_info['user_name'];

        M()->startTrans();

        //接单
        $order_model->update($worker_order_id, [
            'worker_order_status'      => $next_worker_order_status,
            $update_admin_field        => $match_admin_id,
            'last_update_time'         => time(),
            $update_receive_time_field => time(),
        ]);

        //工单日志
        OrderOperationRecordService::create($worker_order_id, $operation_type, [
            'content_replace'  => [
                'admin_name' => $user_name,
            ],
            'operator_id'      => 0,
            'see_auth'         => null,
            'is_system_create' => 1,
        ]);

        M()->commit();

        $end = microtime(true);

        $this->display('执行时长', $end - $begin);
        $this->display('================ 工单接单 END ==============');

        $this->log('worker_order');
    }

    /**
     * 获取顶级品类
     *
     * @param int $category_id 品类id
     * @param int $level       搜索层级 用于避免死循环
     *
     * @return bool
     */
    protected function getCategoryAncestor($category_id, $level = 0)
    {
        if (empty($category_id)) {
            return 0;
        }

        $category = CmListItemCacheModel::getOne($category_id, 'list_item_id,item_parent');
        if (empty($category)) {
            return 0;
        }

        $item_parent = $category['item_parent'];
        $list_item_id = $category['list_item_id'];

        if (0 == $item_parent) {
            return $list_item_id;
        }

        $max_level = 5;
        //防止死循环,强制跳出
        if ($level > $max_level) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '找不到顶级品类');
        }

        $level++;

        return $this->getCategoryAncestor($item_parent, $level);

    }

    protected function matchFactoryGroup($role_key, $timestamp, $factory_id, $area_id, $precedence_admin_id)
    {
        $this->display('********* 尝试匹配对接厂家组别客服 *********');
        $redis = RedisPool::getInstance();

        $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
        $factory_group_key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_FACTORY_GROUP_KEY_TPL'), $weekday);
        $this->display('redis厂家键', $factory_group_key);

        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);
        if (!$redis->sIsMember($register_key, $factory_group_key)) {
            $expire = strtotime(date('Ymd', $timestamp)) + 86399;
            (new SystemReceiveOrderCacheLogic())->initAdminFactoryGroup($weekday, $expire);
        }

        $admin_ids_role = $redis->sMembers($role_key);
        $admin_ids_role = empty($admin_ids_role) ? [] : $admin_ids_role;

        $this->display('角色客服id', $admin_ids_role);

        $admin_ids_factory_group = $redis->sMembers($factory_group_key);
        $admin_ids_factory_group = empty($admin_ids_factory_group) ? [] : $admin_ids_factory_group;

        $this->display('厂家组别客服id', $admin_ids_factory_group);

        $available_factory_group_admin_ids = array_intersect($admin_ids_role, $admin_ids_factory_group);
        $this->display('redis厂家+角色客服id交集', $available_factory_group_admin_ids);

        if (empty($available_factory_group_admin_ids)) {
            $this->display('没有匹配的厂家客服');

            return false;
        }

        //$factory_info = FactoryCacheModel::getOne($factory_id, 'group_id');
        $factory_info = BaseModel::getInstance('factory')->getOne($factory_id, 'group_id');
        if (empty($factory_info)) {
            return false;
        }
        $group_id = $factory_info['group_id'];

        $this->display('厂家信息', $factory_info);
        $this->display('厂家分组', FactoryService::FACTORY_GROUP[$group_id] ?? '不存在');

        //可接单 对应角色 启用 客服
        //获取工单对应厂家对接客服
        $group_admin_ids = AdminConfigReceiveFactoryGroupModel::getAdminIds($group_id);
        $this->display('对接厂家组别客服id', $group_admin_ids);

        $match_admin_ids = array_intersect($available_factory_group_admin_ids, $group_admin_ids);

        if (empty($match_admin_ids)) {
            $this->display('没有匹配的厂家客服');

            return false;
        }

        $this->display('匹配厂家客服结果id', $match_admin_ids);
        $this->display('######## 匹配厂家客服列表 ########');
        foreach ($match_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配厂家客服列表END ########');

        $this->display('尝试匹配是否存在符合对接同地区的客服');

        //查找对接工单地区的客服
        $admin_ids = AdminConfigReceiveAreaCacheModel::getAdminIds($area_id);
        $match_area_admin_ids = array_intersect($match_admin_ids, $admin_ids);

        $this->display('是否存在匹配地区的客服', empty($match_area_admin_ids) ? '否' : '是');

        $this->display('对接区域的客服id', $match_area_admin_ids);
        $this->display('######## 匹配地区客服列表 ########');
        foreach ($match_area_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配地区客服列表END ########');

        $is_permitted_overload = false;
        $match_admin_id = $this->getMatchAdmin($match_area_admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload);

        if ($match_admin_id > 0) {
            $this->display('存在匹配厂家地区客服id', $match_admin_id);

            return $match_admin_id;
        }

        $this->display('不存在匹配厂家地区客服id');

        //没有对应地区客服,获取剩余客服
        $this->display('匹配其他地区客服');
        $this->display('######## 匹配其他地区客服列表 ########');
        foreach ($match_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配其他地区客服END ########');

        $is_permitted_overload = true;

        return $this->getMatchAdmin($match_admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload);
    }

    protected function matchFactory($role_key, $timestamp, $factory_id, $area_id, $precedence_admin_id)
    {
        $this->display('********* 尝试匹配对接厂家客服 *********');
        $redis = RedisPool::getInstance();

        $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
        $factory_key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_FACTORY_KEY_TPL'), $weekday);
        $this->display('redis厂家键', $factory_key);

        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);
        if (!$redis->sIsMember($register_key, $factory_key)) {
            $expire = strtotime(date('Ymd', $timestamp)) + 86399;
            (new SystemReceiveOrderCacheLogic())->initAdminFactory($weekday, $expire);
        }

        $admin_ids_role = $redis->sMembers($role_key);
        $admin_ids_role = empty($admin_ids_role) ? [] : $admin_ids_role;

        $this->display('角色客服id', $admin_ids_role);

        $admin_ids_factory = $redis->sMembers($factory_key);
        $admin_ids_factory = empty($admin_ids_factory) ? [] : $admin_ids_factory;

        $this->display('厂家客服id', $admin_ids_factory);

        $available_factory_admin_ids = array_intersect($admin_ids_role, $admin_ids_factory);
        $this->display('redis厂家+角色客服id交集', $available_factory_admin_ids);

        if (empty($available_factory_admin_ids)) {
            $this->display('没有匹配的厂家客服');

            return false;
        }

        //获取对接的厂家客服
        $admin_ids = AdminConfigReceiveFactoryCacheModel::getAdminIds($factory_id);
        $this->display('对接厂家客服id', $admin_ids);

        $match_admin_ids = array_intersect($available_factory_admin_ids, $admin_ids);

        if (empty($match_admin_ids)) {
            $this->display('没有匹配的厂家客服');

            return false;
        }

        $this->display('匹配厂家客服结果id', $match_admin_ids);
        $this->display('######## 匹配厂家客服列表 ########');
        foreach ($match_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配厂家客服列表END ########');

        $this->display('尝试匹配是否存在符合对接同地区的客服');

        //查找对接工单地区的客服
        $admin_ids = AdminConfigReceiveAreaCacheModel::getAdminIds($area_id);
        $match_area_admin_ids = array_intersect($match_admin_ids, $admin_ids);

        $this->display('是否存在匹配地区的客服', empty($match_area_admin_ids) ? '否' : '是');

        $this->display('对接区域的客服id', $match_area_admin_ids);
        $this->display('######## 匹配地区客服列表 ########');
        foreach ($match_area_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配地区客服列表END ########');

        $is_permitted_overload = false;
        $match_admin_id = $this->getMatchAdmin($match_area_admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload);

        if ($match_admin_id > 0) {
            $this->display('存在匹配厂家地区客服id', $match_admin_id);

            return $match_admin_id;
        }

        $this->display('不存在匹配厂家地区客服id');

        //没有对应地区客服,获取剩余客服
        $this->display('匹配其他地区客服');
        $this->display('######## 匹配其他地区客服列表 ########');
        $diff_match_admin_ids = array_diff($match_admin_ids, $match_area_admin_ids);
//        foreach ($match_admin_ids as $match_admin_id) {
        foreach ($diff_match_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配其他地区客服END ########');

        $is_permitted_overload = true;

        return $this->getMatchAdmin($match_admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload);

    }

    protected function matchCategory($role_key, $timestamp, $category_id, $area_id, $precedence_admin_id)
    {
        $this->display('********* 尝试匹配对接品类客服 *********');
        $redis = RedisPool::getInstance();

        $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
        $category_key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_CATEGORY_KEY_TPL'), $weekday);
        $this->display('redis品类键', $category_key);

        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);
        if (!$redis->sIsMember($register_key, $category_key)) {
            $expire = strtotime(date('Ymd', $timestamp)) + 86399;
            (new SystemReceiveOrderCacheLogic())->initAdminCategory($weekday, $expire);
        }

        $admin_ids_role = $redis->sMembers($role_key);
        $admin_ids_role = empty($admin_ids_role) ? [] : $admin_ids_role;

        $this->display('角色客服id', $admin_ids_role);

        $admin_ids_category = $redis->sMembers($category_key);
        $admin_ids_category = empty($admin_ids_category) ? [] : $admin_ids_category;

        $this->display('品类客服id', $admin_ids_category);

        $available_category_admin_ids = array_intersect($admin_ids_role, $admin_ids_category);

        $this->display('redis品类+角色交集', $available_category_admin_ids);

        if (empty($available_category_admin_ids)) {
            $this->display('没有匹配的品类客服');

            return false;
        }

        //获取对接的品类客服
        $admin_ids = AdminConfigReceiveCategoryCacheModel::getAdminIds($category_id);
        $match_admin_ids = array_intersect($available_category_admin_ids, $admin_ids);

        if (empty($match_admin_ids)) {
            $this->display('没有匹配的品类客服');

            return false;
        }

        $this->display('匹配品类客服结果id', $match_admin_ids);
        $this->display('######## 匹配品类客服列表 ########');
        foreach ($match_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配品类客服列表END ########');

        //查找对接工单地区的客服
        $admin_ids = AdminConfigReceiveAreaCacheModel::getAdminIds($area_id);
        $match_area_admin_ids = array_intersect($match_admin_ids, $admin_ids);

        $this->display('是否存在匹配地区的客服', empty($match_area_admin_ids) ? '否' : '是');

        $this->display('对接区域的客服id', $match_area_admin_ids);
        $this->display('######## 匹配地区客服列表 ########');
        foreach ($match_area_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配地区客服列表END ########');

        $is_permitted_overload = false;
        $match_admin_id = $this->getMatchAdmin($match_area_admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload);
        if ($match_admin_id > 0) {
            $this->display('存在匹配品类地区客服id', $match_admin_id);

            return $match_admin_id;
        }

        $this->display('不存在匹配品类地区客服id');

        //没有对应地区客服,获取剩余客服
        $this->display('匹配其他地区客服');
        $this->display('######## 匹配其他地区客服列表 ########');
        $diff_match_admin_ids = array_diff($match_admin_ids, $match_area_admin_ids);
//        foreach ($match_admin_ids as $match_admin_id) {
        foreach ($diff_match_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('######## 匹配其他地区客服END ########');

        $is_permitted_overload = true;

        return $this->getMatchAdmin($match_admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload);

    }

    protected function matchArea($role_key, $timestamp, $area_id, $precedence_admin_id)
    {
        $this->display('********* 尝试匹配对接地区客服 *********');
        $redis = RedisPool::getInstance();

        $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
        $area_key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_AREA_KEY_TPL'), $weekday);
        $this->display('redis地区键', $area_key);

        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);
        if (!$redis->sIsMember($register_key, $area_key)) {
            $expire = strtotime(date('Ymd', $timestamp)) + 86399;
            (new SystemReceiveOrderCacheLogic())->initAdminArea($weekday, $expire);
        }

        $admin_ids_role = $redis->sMembers($role_key);
        $admin_ids_role = empty($admin_ids_role) ? [] : $admin_ids_role;

        $this->display('角色客服id', $admin_ids_role);

        $admin_ids_area = $redis->sMembers($area_key);
        $admin_ids_area = empty($admin_ids_area) ? [] : $admin_ids_area;

        $this->display('地区客服id', $admin_ids_area);

        $available_area_admin_ids = array_intersect($admin_ids_role, $admin_ids_area);

        $this->display('redis地区+角色交集', $available_area_admin_ids);

        if (empty($available_area_admin_ids)) {
            $this->display('没有匹配的地区客服');

            return false;
        }

        //获取对接的地区客服
        $admin_ids = AdminConfigReceiveAreaCacheModel::getAdminIds($area_id);
        $match_admin_ids = array_intersect($available_area_admin_ids, $admin_ids);

        $this->display('匹配地区客服结果', $match_admin_ids);

        if (empty($match_admin_ids)) {
            $this->display('没有匹配的地区客服');

            return false;
        }

        $this->display('匹配地区客服结果id', $match_admin_ids);
        $this->display('########## 匹配地区客服列表 ##########');
        foreach ($match_admin_ids as $match_admin_id) {
            $match_admin = AdminCacheModel::getOneOrFail($match_admin_id, 'id,nickout');
            $this->display('客服id', $match_admin['id']);
            $this->display('客服名', $match_admin['nickout']);
        }
        $this->display('########## 匹配地区客服列表END ##########');

        $is_permitted_overload = false;

        return $this->getMatchAdmin($match_admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload);

    }

    /**
     * 获取当前财务偏移量
     * @param int $weekday
     * @return int
     */
    public function getAuditorOffset($weekday)
    {
        $redis = RedisPool::getInstance();
//        $key = C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_OFFSET');
        $key = sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_OFFSET'), $weekday);

        $offset = $redis->incr($key);
        // redis incr 不存在时 + 1 = 1
        return $offset - 1;

    }

    /**
     * 检查未被接单工单
     */
    public function checkUnReceiveOrder()
    {
        $lock_key = 'checkUnReceiveOrder';

        $timeout = 1000 * 120; // 2分钟,避免定时任务执行超过间隔时间,造成重复进入队列
        $lock_info = CacheModel::lock($lock_key, $timeout);

        $order_model = BaseModel::getInstance('worker_order');

        $last_id = 0;

        $limit = 1000;

        $where = [
            'worker_order_status' => ['in', [
                OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE,
                OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
                OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
                OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
            ]],
            'cancel_status'       => ['in', [
                OrderService::CANCEL_TYPE_NULL,
                OrderService::CANCEL_TYPE_CS_STOP,
            ]],
            'id'                  => ['gt', &$last_id],
        ];

        $field = 'worker_order_status,id';
        $opts = [
            'where' => $where,
            'field' => $field,
            'order' => 'id',
            'limit' => $limit,
        ];

        while (true) {

            $worker_orders = $order_model->getList($opts);
            if (empty($worker_orders)) {
                break;
            }

            //获取未接单工单
            $orders = [];
            foreach ($worker_orders as $worker_order) {
                $worker_order_id = $worker_order['id'];

                $orders[$worker_order_id] = $worker_order;
            }
            $worker_order_ids = array_column($worker_orders, 'id');

            //获取待接单 或 需要通知没有人接单的工单
            $redis = RedisPool::getInstance();
            $queue_name = C('AUTO_RECEIVE_QUEUE');

            $queue_data = $redis->lRange($queue_name, 0, -1);
            $queue_data = empty($queue_data) ? [] : $queue_data;
            $exclude = [];
            foreach ($queue_data as $job) {
                $worker_order_id = $job->getWorkerOrderId();
                $exclude[] = $worker_order_id;
            }

            $diffs = array_diff($worker_order_ids, $exclude);

            $queues = [];

            foreach ($diffs as $worker_order_id) {
                $order = $orders[$worker_order_id];

                if (empty($order)) {
                    continue;
                }

                $worker_order_status = $order['worker_order_status'];
                if (OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE == $worker_order_status) {
                    //核实
                    $queues[] = new CheckReceiveOrderJob($worker_order_id);
                } elseif (OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE == $worker_order_status) {
                    //派单
                    $queues[] = new DistributorReceiveOrderJob($worker_order_id);
                } elseif (OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE == $worker_order_status) {
                    //回访
                    $queues[] = new ReturnReceiveOrderJob($worker_order_id);
                } elseif (OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE == $worker_order_status) {
                    //财务
                    $queues[] = new AuditorReceiveOrderJob($worker_order_id);
                }

            }

            $queue_name = C('AUTO_RECEIVE_QUEUE');
            $key = C('AUTO_RECEIVE_QUEUE');
            queueBatch($key, $queues, $queue_name);

            $last_id = max($worker_order_ids);

        }

        CacheModel::unlock($lock_info);

    }

    public function notificationAuditor()
    {
        $default_admin_id = C('AUTO_RECEIVE_DEFAULT_ADMIN.AUDITOR');

        $opts = [
            'field' => 'id',
            'where' => [
                '_string' => "(
                (auditor_id={$default_admin_id} and worker_order_status=".OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT.")
                )",
                'cancel_status' => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
            ],
        ];
        $worker_order_ids = BaseModel::getInstance('worker_order')
            ->getList($opts);

        if (!empty($worker_order_ids)) {
            $admin_info = AdminCacheModel::getOneOrFail($default_admin_id, 'tell');
            $tel = $admin_info['tell'];

            sendSms($tel, SMSService::TMP_AUTO_RECEIVE_AUDITOR_NOBODY_RECEIVE, []);

        }

    }

    public function notificationWorkerOrder()
    {
        $default_admin_id = C('AUTO_RECEIVE_DEFAULT_ADMIN.WORKER_ORDER');

        $opts = [
            'field' => 'id',
            'where' => [
                '_string' => "(
                (checker_id={$default_admin_id} and worker_order_status=".OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK.") or 
                (distributor_id={$default_admin_id} and worker_order_status=".OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE.") or 
                (returnee_id={$default_admin_id} and worker_order_status=".OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT.")
                )",
                'cancel_status' => ['in', [
                    OrderService::CANCEL_TYPE_NULL,
                    OrderService::CANCEL_TYPE_CS_STOP,
                ]],
            ],
        ];
        $worker_order_ids = BaseModel::getInstance('worker_order')
            ->getList($opts);

        if (!empty($worker_order_ids)) {

            $admin_info = AdminCacheModel::getOneOrFail($default_admin_id, 'tell');
            $tel = $admin_info['tell'];

            sendSms($tel, SMSService::TMP_AUTO_RECEIVE_WORKER_ORDER_NOBODY_RECEIVE, []);
        }

    }

    protected function getMatchAdmin($admin_ids, $timestamp, $precedence_admin_id, $is_permitted_overload)
    {
        if (empty($admin_ids)) {
            return false;
        }

        $this->display('系统根据匹配的客服排列获取合适客服');

        $this->display('是否存在优先匹配客服', $precedence_admin_id > 0 ? '是' : '否');
        if ($precedence_admin_id > 0) {
            $precedence_admin = AdminCacheModel::getOneOrFail($precedence_admin_id, 'nickout');
            $this->display('优先匹配客服id', $precedence_admin_id);
            $this->display('优先匹配客服名', $precedence_admin['nickout']);
        }

        $admin_ids = array_unique($admin_ids);

        $redis = RedisPool::getInstance();

        $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;

        $admin_list_tpl = C('AUTO_RECEIVE_ADMIN.LIST');
        $admin_list_key = sprintf($admin_list_tpl, $weekday);

        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);
        if (!$redis->sIsMember($register_key, $admin_list_key)) {
            $expire = strtotime(date('Ymd', $timestamp)) + 86399;
            (new SystemReceiveOrderCacheLogic())->initAdmin($weekday, $expire);
        }

        $max_field_tpl = C('AUTO_RECEIVE_ADMIN.FIELD_MAX');
        $cur_field_tpl = C('AUTO_RECEIVE_ADMIN.FIELD_CUR');
        $workday_field_tpl = C('AUTO_RECEIVE_ADMIN.FIELD_WORKDAY');

        $len = count($admin_ids);

        $raw_data = [];
        $count = 0;
        $batch_count = 0;
        $batch_len = 100;
        $fields = [];
        foreach ($admin_ids as $admin_id) {
            $max_field = sprintf($max_field_tpl, $admin_id);
            $cur_field = sprintf($cur_field_tpl, $admin_id);
            $workday_field = sprintf($workday_field_tpl, $admin_id);

            $fields[] = $max_field;
            $fields[] = $cur_field;
            $fields[] = $workday_field;

            $count++;
            $batch_count += 3;

            $partner_ids = AdminConfigReceivePartnerModel::getPartnerIds($admin_id);
            foreach ($partner_ids as $partner_id) {
                $max_field = sprintf($max_field_tpl, $partner_id);
                $cur_field = sprintf($cur_field_tpl, $partner_id);
                $workday_field = sprintf($workday_field_tpl, $partner_id);
                $fields[] = $max_field;
                $fields[] = $cur_field;
                $fields[] = $workday_field;
                $batch_count += 3;
            }

            if ($batch_count > $batch_len || $len == $count) {
                $temp = $redis->hMGet($admin_list_key, $fields);
                $raw_data = array_merge($raw_data, $temp);
                $fields = []; // 清空
                $batch_count = 0;
            }

        }

        $overload = []; // 超过最大可接单量客服
        $free = []; //未超过最大可接单量客服

        $bak_admin_ids = [];
        foreach ($admin_ids as $admin_id) {

            $max_field = sprintf($max_field_tpl, $admin_id);
            $cur_field = sprintf($cur_field_tpl, $admin_id);
            $workday_field = sprintf($workday_field_tpl, $admin_id);

            $cur_receive_times = empty($raw_data[$cur_field]) ? 0 : $raw_data[$cur_field];
            $max_receive_times = empty($raw_data[$max_field]) ? 0 : $raw_data[$max_field];
            $workday = empty($raw_data[$workday_field]) ? 0 : $raw_data[$workday_field];

            if ($cur_receive_times < $max_receive_times && $precedence_admin_id == $admin_id) {
                //优先返回
                $this->display('尝试优先匹配客服id', $admin_id);
                if ($this->isAdminAcceptAble($workday, $weekday)) {
                    $this->display('优先匹配客服可接单');

                    return $admin_id;
                }
                $this->display('优先匹配客服休息,尝试找对接人');
                //不工作,找对接客服(后备)
                $partner_ids = AdminConfigReceivePartnerModel::getPartnerIds($admin_id);
                $this->display('优先匹配的对接人id', $partner_ids);
                foreach ($partner_ids as $partner_id) {
                    $workday_field = sprintf($workday_field_tpl, $partner_id);
                    $partner_workday = empty($raw_data[$workday_field]) ? 0 : $raw_data[$workday_field];
                    if ($this->isAdminAcceptAble($partner_workday, $weekday)) {
                        $this->display('优先匹配的对接人接单,id为', $partner_id);

                        return $partner_id;
                    }

                }
            }

            $admin_info = [
                'max_receive_times' => $max_receive_times,
                'cur_receive_times' => $cur_receive_times,
                'admin_id'          => $admin_id,
            ];
            if (!$this->isAdminAcceptAble($workday, $weekday)) {
                $partner_ids = AdminConfigReceivePartnerModel::getPartnerIds($admin_id);
                $bak_admin_ids = array_merge($bak_admin_ids, $partner_ids);
                continue;
            }

            if ($cur_receive_times < $max_receive_times) {
                $free[] = $admin_info;
            } else {
                $overload[] = $admin_info;
            }
        }

        $this->display('是否所有对接客服不工作,是-找对接客服(后备) 否-委派当前对接客服', empty($free) && empty($overload) ? '是' : '否');

        if (empty($free) && empty($overload)) {
            foreach ($bak_admin_ids as $admin_id) {

                $max_field = sprintf($max_field_tpl, $admin_id);
                $cur_field = sprintf($cur_field_tpl, $admin_id);
                $workday_field = sprintf($workday_field_tpl, $admin_id);

                $cur_receive_times = $raw_data[$cur_field];
                $max_receive_times = $raw_data[$max_field];
                $workday = empty($raw_data[$workday_field]) ? 0 : $raw_data[$workday_field];

                $admin_info = [
                    'max_receive_times' => $max_receive_times,
                    'cur_receive_times' => $cur_receive_times,
                    'admin_id'          => $admin_id,
                ];

                if (!$this->isAdminAcceptAble($workday, $weekday)) {
                    continue;
                }

                if ($cur_receive_times < $max_receive_times) {
                    $free[] = $admin_info;
                } else {
                    $overload[] = $admin_info;
                }
            }
        }

        $admin_list = $free;
        if (empty($admin_list)) {
            if (!$is_permitted_overload) {
                return false;
            }
            $admin_list = $overload;
        }

        $this->display('当前是否有空闲客服', empty($free) ? '否' : '是');

        $this->display('######## 当前空闲客服列表 ########');
        foreach ($free as $admin) {
            $admin_msg = AdminCacheModel::getOneOrFail($admin['admin_id'], 'nickout');
            $this->display('客服id', $admin['admin_id']);
            $this->display('客服名', $admin_msg['nickout']);
            $this->display('当前接单量', $admin['cur_receive_times']);
            $this->display('最大接单量', $admin['max_receive_times']);
        }
        $this->display('######## 当前空闲客服列表END ########');

        $this->display('当前是否有超过最大接单量客服', empty($overload) ? '否' : '是');
        $this->display('当前超过最大接单量客服');
        foreach ($overload as $admin) {
            $admin_msg = AdminCacheModel::getOneOrFail($admin['admin_id'], 'nickout');
            $this->display('客服id', $admin['admin_id']);
            $this->display('客服名', $admin_msg['nickout']);
            $this->display('当前接单量', $admin['cur_receive_times']);
            $this->display('最大接单量', $admin['max_receive_times']);
        }

        array_multisort(
            array_column($admin_list, 'cur_receive_times'),
            SORT_ASC,
            array_column($admin_list, 'admin_id'),
            SORT_ASC,
            SORT_NUMERIC,
            $admin_list
        );

        $this->display('######## 根据客服id排序后列表 ########');
        foreach ($admin_list as $admin) {
            $admin_msg = AdminCacheModel::getOneOrFail($admin['admin_id'], 'nickout');
            $this->display('客服id', $admin['admin_id']);
            $this->display('客服名', $admin_msg['nickout']);
            $this->display('当前接单量', $admin['cur_receive_times']);
            $this->display('最大接单量', $admin['max_receive_times']);
        }
        $this->display('######## 根据客服id排序后列表END ########');

        return empty($admin_list) ? false : $admin_list[0]['admin_id'];
    }

    protected function isAdminAcceptAble($workday_dec, $week_today)
    {
        $cur_time = time();

        $bin = decbin($workday_dec);
        $bin = sprintf('%07d', $bin);

        $this->display('######## 具体工作日 ########');

        $this->display('具体工作日', $bin);

        $this->display('######## 具体工作日 end ########');

//        if (0 == $bin[$week_today]) {
//            return false;
//        }

        //休息时间 18:00
        $rest_at = strtotime(date('Ymd'), $cur_time) + 18 * 3600;
        $what_day = $rest_at > $cur_time ? $week_today : ($week_today + 1) % 7;

        if ($bin[$what_day]) {
            //工作时间 直接委派
            return true;
        }

        return false;
    }

    protected $str;

    protected function display($title, $data = [])
    {
        $this->str .= $title . ':' . PHP_EOL;
        if (!empty($data)) {
            $this->str .= print_r($data, true) . PHP_EOL;
        }
        $this->str .= PHP_EOL;
    }

    protected function log($type)
    {
        $dir_path = 'logs/' . date('Ymd');
        is_dir($dir_path) || mkdir($dir_path, 0777, true);
        $path = $dir_path . '/' . $type . '_receive_order.txt';
        file_put_contents($path, $this->str, FILE_APPEND);
    }

}