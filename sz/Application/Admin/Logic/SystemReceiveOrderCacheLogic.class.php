<?php
/**
 * File: SystemReceiveOrderCacheLogic.class.php
 * Function:自动接单缓存生成
 * User: sakura
 * Date: 2018/2/28
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Carbon\Carbon;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\CacheModel\CacheModel;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AdminConfigReceiveService;
use Common\Common\Service\AdminConfigReceiveWorkdayService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AdminService;

class SystemReceiveOrderCacheLogic extends BaseLogic
{

    /**
     * 初始化数据
     *
     * @param int $weekday 星期几 [0,6]
     * @param int $expire  期限,时间戳,单位:秒
     */
    public function init($weekday, $expire)
    {
        $begin = microtime(true);

        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        $admins = $this->getAvailableAdmin($weekday);

        $this->initAdmin($weekday, $expire, $admins); // 客服表
        $this->initAdminAuditor($weekday, $expire, $admins); // 今天财务表
        $this->initAdminAuditor(($weekday+1) % 7, $expire, $admins); // 明天财务表
//        $this->initAdminAuditorRest($weekday, $expire, $admins); //财务表
        $this->initAdminChecker($weekday, $expire, $admins); // 核实客服表
        $this->initAdminDistributor($weekday, $expire, $admins); // 派单客服表
        $this->initAdminReturnee($weekday, $expire, $admins); // 回访客服表
        $this->initAdminFactory($weekday, $expire, $admins); // 对接厂家客服表
        $this->initAdminFactoryGroup($weekday, $expire, $admins); // 对接厂家组别客服表
        $this->initAdminCategory($weekday, $expire, $admins); // 对接品类客服表
        $this->initAdminArea($weekday, $expire, $admins); // 对接地区客服表

        $end = microtime(true);

        //echo $end - $begin;
    }

    /**
     * 获取工作日 可接单客服
     *
     * @param int $weekday 星期几,[0,6]
     *
     * @return array|bool
     */
    protected function getAvailableAdmin($weekday)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取可接单的客服
        $admin_model = BaseModel::getInstance('admin');
        $default_admin = C('AUTO_RECEIVE_DEFAULT_ADMIN');
        //排除特殊账号
        $default_admin = empty($default_admin) ? '-1' : $default_admin; // 特殊账号
        $opts = [
            'field' => 'a.id,acr.max_receive_times',
            'alias' => 'a',
            'join'  => [
                'left join admin_config_receive as acr on acr.admin_id=a.id',
            ],
            'where' => [
                'a.state'             => AdminService::STATE_ENABLED,
                'acr.is_auto_receive' => AdminConfigReceiveService::IS_AUTO_RECEIVE_YES,
                'a.id'                => ['not in', $default_admin], // 排除特殊账号
            ],
            'order' => 'a.id',
        ];

        $admins = $admin_model->getList($opts);

        $admin_data = [];
        foreach ($admins as $admin) {
            $admin_id = $admin['id'];

            $admin_data[$admin_id] = $admin;
        }

        return $admin_data;
    }

    /**
     * 初始化 客服表
     *
     * @param int        $weekday 星期几[0,6]
     * @param int        $expire  缓存期限,时间戳,单位:秒
     * @param array|null $admins  客服信息
     *                            |-id int id
     *                            |-max_receive_times int 每日最大接待量
     */
    public function initAdmin($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);

        $args = [];
        if (!empty($admins)) {
            $admin_ids = array_column($admins, 'id');
            $workdays = $this->getWorkdays($admin_ids);

            $max_field_tpl = C('AUTO_RECEIVE_ADMIN.FIELD_MAX');
            $workday_field_tpl = C('AUTO_RECEIVE_ADMIN.FIELD_WORKDAY');
            foreach ($admins as $admin) {
                $admin_id = $admin['id'];
                $max_receive_times = $admin['max_receive_times'];
                $workday = '0000000';

                $admin_workdays = empty($workdays[$admin_id]) ? [] : $workdays[$admin_id];
                foreach ($admin_workdays as $admin_workday) {
                    if (AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES == $admin_workday['is_on_duty']) {
                        $workday[$admin_workday['workday']] = 1;
                    }
                }
                $workday = bindec($workday);

                $max_field_key = sprintf($max_field_tpl, $admin_id);
                $workday_field_key = sprintf($workday_field_tpl, $admin_id);

                $args[$max_field_key] = $max_receive_times;
                $args[$workday_field_key] = $workday;
            }
        }

        $redis = RedisPool::getInstance();

        $admin_tpl = C('AUTO_RECEIVE_ADMIN.LIST');
        $key = sprintf($admin_tpl, $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key); // 写锁

        $redis->delete($key);
        if (!empty($args)) {
            $redis->hMSet($key, $args);
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }

    protected function getWorkdays($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $data = [];

        $where = [
            'admin_id' => ['in', $admin_ids],
        ];
        $list = BaseModel::getInstance('admin_config_receive_workday')
            ->getList($where, 'workday,admin_id,is_on_duty');

        foreach ($list as $val) {
            $data[$val['admin_id']][] = $val;
        }

        return $data;
    }

    public function initAdminAuditor($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        $rel_model = BaseModel::getInstance('rel_admin_roles');
        $opts = [
            'field' => 'rar.admin_id',
            'alias' => 'rar',
            'join'  => [
                'left join admin_roles as ar on ar.id=rar.admin_roles_id',
            ],
            'where' => [
                'rar.admin_id' => ['in', $admin_ids],
                'ar.type'      => ['exp', '&' . AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR . '=' . AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR],
            ],
        ];
        $auditor_ids = $rel_model->getList($opts);
        $auditor_ids = empty($auditor_ids) ? '-1' : array_column($auditor_ids, 'admin_id');

        $admin_config_model = BaseModel::getInstance('admin_config_receive');

        //工作日客服关系表
        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id' => ['in', $auditor_ids],
                'type'     => AdminConfigReceiveService::TYPE_TAKE_TURN,
            ],
        ];
        $available_auditors = $admin_config_model->getList($opts);
        $auditor_ids = empty($available_auditors) ? '-1' : array_column($available_auditors, 'admin_id');

        $workday_model = BaseModel::getInstance('admin_config_receive_workday');
        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id'   => ['in', $auditor_ids],
                'workday'    => $weekday,
                'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES,
            ],
        ];
        $available_auditors = $workday_model->getList($opts);

        $key = sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_KEY_TPL'), $weekday);

        $args = [];
        foreach ($available_auditors as $auditor) {
            $auditor_id = $auditor['admin_id'];
            $args = array_merge($args, [$auditor_id, $auditor_id]);
        }

        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $redis = RedisPool::getInstance();

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($args)) {
            call_user_func_array([$redis, 'zAdd'], array_merge([$key], $args));
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);


        CacheModel::unlock($lock_info);
    }


    public function initAdminAuditorRest($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        $rel_model = BaseModel::getInstance('rel_admin_roles');
        $opts = [
            'field' => 'rar.admin_id',
            'alias' => 'rar',
            'join'  => [
                'left join admin_roles as ar on ar.id=rar.admin_roles_id',
            ],
            'where' => [
                'rar.admin_id' => ['in', $admin_ids],
                'ar.type'      => ['exp', '&' . AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR . '=' . AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR],
            ],
        ];
        $auditor_ids = $rel_model->getList($opts);
        $auditor_ids = empty($auditor_ids) ? '-1' : array_column($auditor_ids, 'admin_id');

        $admin_config_model = BaseModel::getInstance('admin_config_receive');

        //工作日客服关系表
        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id' => ['in', $auditor_ids],
                'type'     => AdminConfigReceiveService::TYPE_TAKE_TURN,
            ],
        ];
        $available_auditors = $admin_config_model->getList($opts);
        $auditor_ids = empty($available_auditors) ? '-1' : array_column($available_auditors, 'admin_id');

        $workday_model = BaseModel::getInstance('admin_config_receive_workday');
        $tomorrow = ($weekday + 1) % 7;
        $opts = [
            'where' => [
                'admin_id'   => ['in', $auditor_ids],
                'workday'    => $weekday,
                'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES,
            ],
        ];
        $today_available_auditors = $workday_model->getFieldVal($opts, 'admin_id', true);
        $today_available_auditors = empty($today_available_auditors)? '-1': $today_available_auditors;

        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id'   => ['in', $today_available_auditors],
                'workday'    => $tomorrow,
                'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES,
            ],
        ];
        $available_auditors = $workday_model->getList($opts);

        $key = sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_REST_KEY_TPL'), $weekday);

        $args = [];
        foreach ($available_auditors as $auditor) {
            $auditor_id = $auditor['admin_id'];
            $args = array_merge($args, [$auditor_id, $auditor_id]);
        }

        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $redis = RedisPool::getInstance();

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($args)) {
            $redis->expireAt($key, $expire);
        }
        call_user_func_array([$redis, 'zAdd'], array_merge([$key], $args));
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);


        CacheModel::unlock($lock_info);
    }

    public function initAdminChecker($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        $rel_model = BaseModel::getInstance('rel_admin_roles');
        $opts = [
            'field' => 'rar.admin_id',
            'alias' => 'rar',
            'join'  => [
                'left join admin_roles as ar on ar.id=rar.admin_roles_id',
            ],
            'where' => [
                'rar.admin_id' => ['in', $admin_ids],
                'ar.type'      => ['exp', '&' . AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER . '=' . AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER],
            ],
        ];
        $available_checker_ids = $rel_model->getList($opts);
        $available_checker_ids = empty($available_checker_ids) ? [] : array_column($available_checker_ids, 'admin_id');

        $redis = RedisPool::getInstance();

        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_CHECKER_KEY_TPL'), $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($available_checker_ids)) {
            $redis->sAddArray($key, $available_checker_ids);
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }

    public function initAdminDistributor($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        $rel_model = BaseModel::getInstance('rel_admin_roles');
        $opts = [
            'field' => 'rar.admin_id',
            'alias' => 'rar',
            'join'  => [
                'left join admin_roles as ar on ar.id=rar.admin_roles_id',
            ],
            'where' => [
                'rar.admin_id' => ['in', $admin_ids],
                'ar.type'      => ['exp', '&' . AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR . '=' . AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR],
            ],
        ];
        $available_distributor_ids = $rel_model->getList($opts);
        $available_distributor_ids = empty($available_distributor_ids) ? [] : array_column($available_distributor_ids, 'admin_id');

        $redis = RedisPool::getInstance();

        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_DISTRIBUTOR_KEY_TPL'), $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($available_distributor_ids)) {
            $redis->sAddArray($key, $available_distributor_ids);
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }

    public function initAdminReturnee($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        $rel_model = BaseModel::getInstance('rel_admin_roles');
        $opts = [
            'field' => 'rar.admin_id',
            'alias' => 'rar',
            'join'  => [
                'left join admin_roles as ar on ar.id=rar.admin_roles_id',
            ],
            'where' => [
                'rar.admin_id' => ['in', $admin_ids],
                'ar.type'      => ['exp', '&' . AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE . '=' . AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE],
            ],
        ];
        $available_returnee_ids = $rel_model->getList($opts);
        $available_returnee_ids = empty($available_returnee_ids) ? [] : array_column($available_returnee_ids, 'admin_id');

        $redis = RedisPool::getInstance();

        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_RETURNEE_KEY_TPL'), $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($available_returnee_ids)) {
            call_user_func_array([$redis, 'sAdd'], array_merge([$key], $available_returnee_ids));
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }

    public function initAdminFactory($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        //按接单类型(厂家 品类 地区)获取客服id
        $admin_config_model = BaseModel::getInstance('admin_config_receive');
        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id' => ['in', $admin_ids],
                'type'     => AdminConfigReceiveService::TYPE_FACTORY,
            ],
        ];
        $available_factories = $admin_config_model->getList($opts);
        $available_factories = empty($available_factories) ? [] : array_column($available_factories, 'admin_id');

        $redis = RedisPool::getInstance();

        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_FACTORY_KEY_TPL'), $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($available_factories)) {
            $redis->sAddArray($key, $available_factories);
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }


    public function initAdminFactoryGroup($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        //按接单类型(厂家 品类 地区)获取客服id
        $admin_config_model = BaseModel::getInstance('admin_config_receive');
        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id' => ['in', $admin_ids],
                'type'     => AdminConfigReceiveService::TYPE_FACTORY_GROUP,
            ],
        ];
        $available_factories = $admin_config_model->getList($opts);
        $available_factories = empty($available_factories) ? [] : array_column($available_factories, 'admin_id');

        $redis = RedisPool::getInstance();

        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_FACTORY_GROUP_KEY_TPL'), $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($available_factories)) {
            $redis->sAddArray($key, $available_factories);
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }

    public function initAdminCategory($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        $admin_config_model = BaseModel::getInstance('admin_config_receive');
        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id' => ['in', $admin_ids],
                'type'     => AdminConfigReceiveService::TYPE_CATEGORY,
            ],
        ];
        $available_categories = $admin_config_model->getList($opts);
        $available_categories = empty($available_categories) ? [] : array_column($available_categories, 'admin_id');

        $redis = RedisPool::getInstance();

        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_CATEGORY_KEY_TPL'), $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($available_categories)) {
            $redis->sAddArray($key, $available_categories);
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }

    public function initAdminArea($weekday, $expire, $admins = null)
    {
        if ($weekday < 0 || $weekday > 6) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if ($expire < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取$weekday可接单的客服
        $admins = $admins?? $this->getAvailableAdmin($weekday);
        $admin_ids = empty($admins) ? '-1' : array_column($admins, 'id');

        $admin_config_model = BaseModel::getInstance('admin_config_receive');
        $opts = [
            'field' => 'admin_id',
            'where' => [
                'admin_id' => ['in', $admin_ids],
                'type'     => AdminConfigReceiveService::TYPE_AREA,
            ],
        ];
        $available_areas = $admin_config_model->getList($opts);
        $available_areas = empty($available_areas) ? [] : array_column($available_areas, 'admin_id');

        $redis = RedisPool::getInstance();

        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_AREA_KEY_TPL'), $weekday);
        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);

        $lock_info = CacheModel::lock($key);

        $redis->delete($key);
        if (!empty($available_areas)) {
            $redis->sAddArray($key, $available_areas);
            $redis->expireAt($key, $expire);
        }
        $redis->sAdd($register_key, $key);
        $redis->expireAt($register_key, $expire);

        CacheModel::unlock($lock_info);
    }

    /**
     * 财务客服偏移量置零
     */
    public function resetAuditorOffset($weekday)
    {
        $offset = 1;

        $key = sprintf(C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_OFFSET'), $weekday);

        $redis = RedisPool::getInstance();

        $redis->set($key, $offset);

    }

    //public function flushAdminData($admin_id, $timestamp, $expire)
    //{
    //    if ($admin_id <= 0 || $timestamp <= 0 || $expire <= 0) {
    //        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
    //    }
    //
    //    $day = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
    //
    //    $redis = RedisPool::getInstance();
    //
    //    $admin_key_tpl = C('AUTO_RECEIVE_ADMIN.LIST');
    //    $day_key = sprintf($admin_key_tpl, $day);
    //
    //    //没有缓存,直接结束
    //    if (!$redis->exists($day_key)) {
    //        return 0;
    //    }
    //
    //    $field_max = sprintf(C('AUTO_RECEIVE_ADMIN.FIELD_MAX'), $admin_id);
    //    $field_cur = sprintf(C('AUTO_RECEIVE_ADMIN.FIELD_CUR'), $admin_id);
    //
    //    //获取客服信息
    //    $admin_info = AdminCacheModel::getOneOrFail($admin_id, 'state');
    //    $state = $admin_info['state'];
    //
    //    $role_ids = AdminCacheModel::getRelation($admin_id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
    //    $admin_type = 0;
    //    foreach ($role_ids as $role_id) {
    //        $role_info = AdminRoleCacheModel::getOne($role_id, 'type');
    //        if (!empty($role_info)) {
    //            $type = $role_info['type'];
    //            $admin_type = $admin_type | $type;
    //        }
    //    }
    //
    //    $admin_config_model = BaseModel::getInstance('admin_config_receive');
    //    $admin_config_info = $admin_config_model->getOneOrFail($admin_id);
    //    $is_auto_receive = $admin_config_info['is_auto_receive'];
    //    $max_receive_times = $admin_config_info['max_receive_times'];
    //    $type = $admin_config_info['type']; // 客服类型 对接厂家 品类 地区 ,财务
    //
    //    $admin_config_workday_model = BaseModel::getInstance('admin_config_receive_workday');
    //    $where = [
    //        'admin_id'   => $admin_id,
    //        'is_on_duty' => AdminConfigReceiveService::IS_AUTO_RECEIVE_YES,
    //    ];
    //    $workdays = $admin_config_workday_model->getFieldVal($where, 'workday', true);
    //
    //    $check_key_tpl = C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_CHECKER_KEY_TPL');
    //    $distributor_key_tpl = C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_DISTRIBUTOR_KEY_TPL');
    //    $returnee_key_tpl = C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_RETURNEE_KEY_TPL');
    //    $auditor_key_tpl = C('AUTO_RECEIVE_AUDITOR_ORDER.ADMIN_LIST_KEY_TPL');
    //    $factory_key_tpl = C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_FACTORY_KEY_TPL');
    //    $category_key_tpl = C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_CATEGORY_KEY_TPL');
    //    $area_key_tpl = C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_AREA_KEY_TPL');
    //
    //    $admin_status_key = sprintf(C('AUTO_RECEIVE_ADMIN.STATUS'), $day);
    //
    //    $check_key = sprintf($check_key_tpl, $day);
    //    $distributor_key = sprintf($distributor_key_tpl, $day);
    //    $returnee_key = sprintf($returnee_key_tpl, $day);
    //    $auditor_key = sprintf($auditor_key_tpl, $day);
    //    $factory_key = sprintf($factory_key_tpl, $day);
    //    $category_key = sprintf($category_key_tpl, $day);
    //    $area_key = sprintf($area_key_tpl, $day);
    //
    //    //删除旧缓存
    //    $redis->sRem($check_key, $admin_id);
    //    $redis->sRem($distributor_key, $admin_id);
    //    $redis->sRem($returnee_key, $admin_id);
    //    $redis->zRem($auditor_key, $admin_id);
    //    $redis->sRem($factory_key, $admin_id);
    //    $redis->sRem($category_key, $admin_id);
    //    $redis->sRem($area_key, $admin_id);
    //
    //    //客服为可用 可接单 并在工作日内
    //    if (
    //        AdminService::STATE_ENABLED == $state &&
    //        AdminConfigReceiveService::IS_AUTO_RECEIVE_YES == $is_auto_receive &&
    //        in_array($day, $workdays)
    //    ) {
    //        $redis->hSet($day_key, $field_max, $max_receive_times);
    //        $redis->expireAt($day_key, $expire);
    //
    //        if (AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER == ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER)) {
    //            $redis->sAdd($check_key, $admin_id);
    //            $redis->expireAt($check_key, $expire);
    //        }
    //        if (AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR == ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR)) {
    //            $redis->sAdd($distributor_key, $admin_id);
    //            $redis->expireAt($distributor_key, $expire);
    //        }
    //        if (AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE == ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE)) {
    //            $redis->sAdd($returnee_key, $admin_id);
    //            $redis->expireAt($returnee_key, $expire);
    //        }
    //        if (AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR == ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR)) {
    //            $redis->zAdd($auditor_key, $admin_id, $admin_id);
    //            $redis->expireAt($auditor_key, $expire);
    //        }
    //
    //        if (AdminConfigReceiveService::TYPE_FACTORY == $type) {
    //            $redis->sAdd($factory_key, $admin_id);
    //            $redis->expireAt($factory_key, $expire);
    //        } elseif (AdminConfigReceiveService::TYPE_CATEGORY == $type) {
    //            $redis->sAdd($category_key, $admin_id);
    //            $redis->expireAt($category_key, $expire);
    //        } elseif (AdminConfigReceiveService::TYPE_AREA == $type) {
    //            $redis->sAdd($area_key, $admin_id);
    //            $redis->expireAt($area_key, $expire);
    //        }
    //        $redis->setBit($admin_status_key, $admin_id, 1);
    //        $redis->expireAt($admin_status_key, $expire);
    //    } else {
    //        $redis->hDel($day_key, $field_max, $field_cur);
    //        $redis->setBit($admin_status_key, $admin_id, 0);
    //        $redis->expireAt($admin_status_key, $expire);
    //    }
    //
    //    return 0;
    //
    //}
    //
    //public function flushAdminRoleData($timestamp, $expire)
    //{
    //    if ($timestamp <= 0 || $expire <= 0) {
    //        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
    //    }
    //
    //    $weekday = Carbon::createFromTimestamp($timestamp)->dayOfWeek;
    //
    //    $admin = $this->getAvailableAdmin($weekday);
    //
    //    //财务
    //    $this->initAdminAuditor($weekday, $expire, $admin);
    //    //核实
    //    $this->initAdminChecker($weekday, $expire, $admin);
    //    //派单
    //    $this->initAdminDistributor($weekday, $expire, $admin);
    //    //回访
    //    $this->initAdminReturnee($weekday, $expire, $admin);
    //
    //}

}