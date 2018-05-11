<?php
/**
 * File: RecruitLogic.class副本2.php
 * Function: 开点单
 * User: sakura
 * Date: 2017/11/15
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\Service\AdminService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\RecruitService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SystemMessageService;
use Common\Common\Service\WorkerService;
use Library\Common\Util;

class RecruitLogic extends BaseLogic
{

    protected $tableName = 'worker_add_apply';

    public function add($param)
    {
        //获取参数
        $worker_order_id = $param['worker_order_id'];
        $remark = $param['remark'];

        //检查参数
        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取工单
        $model = BaseModel::getInstance('worker_order');
        $order_info = $model->getOneOrFail($worker_order_id);
        $worker_order_status = $order_info['worker_order_status'];
        $orno = $order_info['orno'];

        //工单状态检查
        $valid_status = array_merge(OrderService::getOrderDistribute(), OrderService::getOrderVisit(), OrderService::getOrderAppoint(), [OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]);
        if (!in_array($worker_order_status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单状态不允许申请开点单');
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();
        $role_id = $admin['role_id'];

//        $root_role = AdminRoleService::getRoleRoot();
//        $admin_role = AdminRoleService::getRoleAdminRoot();
//        $distributor = AdminRoleService::getRoleDistributor();
//
//        $valid_role = array_merge($distributor, $admin_role, $root_role);
//
//        //权限
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $user_info = BaseModel::getInstance('worker_order_user_info')
            ->getOneOrFail($worker_order_id);
        $area_ids = [$user_info['province_id'], $user_info['city_id'], $user_info['area_id']];

        $apply_worker_number = $this->getApplyWorkerNumber();

        $insert_data = [
            'apply_worker_number' => $apply_worker_number,
            'orno'                => $orno,
            'remark'              => $remark,
            'create_time'         => NOW_TIME,
            'status'              => RecruitService::STATUS_APPLY,
            'apply_admin_id'      => $admin_id,
            'worker_order_id'     => $worker_order_id,
            'area_ids'            => implode(',', $area_ids),
        ];

        $model = BaseModel::getInstance($this->tableName);
        $model->insert($insert_data);

        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_RECRUIT_APPLY);

        $stats = [
            'worker_add_apply_num' => ['exp', 'worker_add_apply_num+1'],
        ];
        BaseModel::getInstance('worker_order_statistics')
            ->update($worker_order_id, $stats);
    }

    protected function getApplyWorkerNumber()
    {
        $number = [];
        $bak_num = 5; // 生成多个备份号码,减少翻查次数
        for ($i = 0; $i < $bak_num; $i++) {
            $number[] = time() . mt_rand(10, 99);
        }

        $model = BaseModel::getInstance($this->tableName);

        $data = $model->getFieldVal(['apply_worker_number' => ['in', $number]], 'apply_worker_number', true);
        $data = empty($data)? []: $data;

        //查找是否有可用的号码
        $diff = array_diff($number, $data);

        if (!empty($diff)) {
            //有,则取最后一个
            return array_pop($diff);
        } else {
            //没有就继续取
            return $this->getApplyWorkerNumber();
        }
    }

    public function getList($param)
    {
        $status = $param['status'];
        $is_valid = $param['is_valid'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $orno = $param['orno'];

        $admin_ids = $param['admin_ids'];
        $admin_ids = Util::filterIdList($admin_ids);

        $area_ids = $param['area'];
        $area_ids = Util::filterIdList($area_ids);

        $factory_ids = $param['factory_ids']; //所属厂家id
        $factory_ids = Util::filterIdList($factory_ids);

        $factory_group_ids = $param['factory_group_ids']; // 厂家组别
        $factory_group_ids = explode(',', $factory_group_ids);
        $factory_group_ids = array_filter($factory_group_ids, function ($val) {
            return $val !== '';
        });

        $limit = $param['limit'];

        $where = [];
        if (strlen($status) > 0) {
            $where['status'] = $status;
        }

        if (strlen($is_valid) > 0) {
            $where['is_valid'] = $is_valid;
        }

        if ($date_from > 0) {
            $where['create_time'][] = ['gt', $date_from];
        }
        if ($date_to > 0) {
            $where['create_time'][] = ['lt', $date_to];
        }

        if (!empty($orno)) {
            $where['orno'] = ['like', '%' . $orno . '%'];
        }

        if (!empty($admin_ids)) {
            $where['auditor_id'] = ['in', $admin_ids];
        }

        //根据厂家id获取维修工单id
        if (!empty($factory_ids)) {
            $sub_where = ['factory_id' => ['in', $factory_ids]];
            $sub_query = BaseModel::getInstance('worker_order');
            $sub_query_str = $sub_query->field('id')
                ->where($sub_where)->buildSql();
            $where['worker_order_id'][] = ['exp', "in ({$sub_query_str})"];
        }

        //厂家组别
        if (!empty($factory_group_ids)) {
            $condition = ['group_id' => ['in', $factory_group_ids]];
            $sub_query_s = BaseModel::getInstance('factory');
            $sub_query_str_s = $sub_query_s->field('factory_id')
                ->where($condition)->buildSql();
            $condition_factory['factory_id'][] = ['exp', "in ({$sub_query_str_s})"];

            $sub_query = BaseModel::getInstance('worker_order');
            $sub_query_str = $sub_query->field('id')
                ->where($condition_factory)->buildSql();
            $where['worker_order_id'][] = ['exp', "in ({$sub_query_str})"];
        }

        if (!empty($area_ids)) {
            $province_id = (int)$area_ids[0];
            $city_id = (int)$area_ids[1];
            $district_id = (int)$area_ids[2];
            $sub_query = BaseModel::getInstance('worker_order_user_info');
            $sub_where = [];
            if ($province_id > 0) {
                $sub_where['province_id'] = $province_id;
            }
            if ($city_id > 0) {
                $sub_where['city_id'] = $city_id;
            }
            if ($district_id > 0) {
                $sub_where['area_id'] = $district_id;
            }
            $sub_query_str = $sub_query->field('worker_order_id')
                ->where($sub_where)->buildSql();
            $where['worker_order_id'][] = ['exp', "in ({$sub_query_str})"];
        }



        $model = BaseModel::getInstance($this->tableName);
        $num = $model->getNum($where);

        $field = 'id,create_time,status,result_evaluate,apply_admin_id,auditor_id,worker_order_id,audit_time,is_valid';
        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'id desc',
            'limit' => $limit,
        ];
        $list = $model->getList($opts);

        $worker_order_ids = [];
        $admin_ids = [];

        foreach ($list as $key => $val) {
            $worker_order_id = $val['worker_order_id'];
            $admin_id = $val['apply_admin_id'];
            $auditor_id = $val['auditor_id'];

            $worker_order_ids[] = $worker_order_id;
            $admin_ids[] = $auditor_id;
            $admin_ids[] = $admin_id;
        }
        $admins = $this->getAdmins($admin_ids);
        $worker_orders = $this->getWorkerOrders($worker_order_ids);
        $worker_order_products = $this->getWorkerOrderProducts($worker_order_ids);
        $worker_order_user_info = $this->getWorkerOrderUserInfo($worker_order_ids);

        if(!empty($worker_order_ids)) {
        $condition = ['id' => ['in', $worker_order_ids]];
        $factory_ids = BaseModel::getInstance('worker_order')
            ->getFieldVal($condition, 'factory_id', true);
            $factories = $this->getFactories($factory_ids);
        }

        foreach ($list as $key => $val) {
            $admin_id = $val['apply_admin_id'];
            $auditor_id = $val['auditor_id'];
            $worker_order_id = $val['worker_order_id'];
            $is_valid = $val['is_valid'];
            $status = $val['status'];
            $factory_id = $factory_ids[$key];

            $val['order'] = $worker_orders[$worker_order_id]?? null;
            $val['apply_cs'] = $admins[$admin_id]?? null;
            $val['auditor_cs'] = $admins[$auditor_id]?? null;
            $val['products'] = $worker_order_products[$worker_order_id]?? null;
            $val['user_info'] = $worker_order_user_info[$worker_order_id]?? null;
            $val['factory'] = $factories[$factory_id]?? null;

            //0-待评价 1-已评价 2-不能评价
            $evaluate_status = '2';
            if (
                RecruitService::STATUS_COMPLETE == $status &&
                RecruitService::RESULT_NULL == $is_valid
            ) {
                $evaluate_status = '0';
            } elseif (
                RecruitService::RESULT_VALID == $is_valid ||
                RecruitService::RESULT_INVALID == $is_valid
            ) {
                $evaluate_status = '1';
            }
            $val['evaluate_status'] = $evaluate_status;

            $list[$key] = $val;
        }

        return [
            'data' => $list,
            'cnt'  => $num,
        ];
    }

    public function userList($param)
    {
        $name = $param['name'];
        $limit = $param['limit'];

        $where = [];
        if (!empty($name)) {
            $where['nickout'] = ['like', '%' . $name . '%'];
        }

        //获取角色id
        $channel = AdminRoleService::getRoleChannel();
        $root = AdminRoleService::getRoleRoot();
        $valid_role_ids = array_merge($channel, $root);
        $valid_role_ids = empty($valid_role_ids)? '-1': $valid_role_ids;

        //获取角色对应客服(可考虑使用缓存)
        $opts = [
            'field' => 'distinct admin_id',
            'where' => [
                'admin_roles_id' => ['in', $valid_role_ids]
            ]
        ];
        $admins = BaseModel::getInstance('rel_admin_roles')->getList($opts);
        $admin_ids = empty($admins)? '-1': array_column($admins, 'admin_id');

        //数据库分页获取
        $where['id'] = ['in', $admin_ids];
        $where['state'] = AdminService::STATE_ENABLED;
        $model = BaseModel::getInstance('admin');

        $cnt = $model->getNum($where);

        $field = 'id, nickout as nickname';
        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'id',
            'limit' => $limit,
        ];
        $data = $model->getList($opts);

        return [
            'list' => $data,
            'cnt'  => $cnt,
        ];
    }

    /**
     * 指派
     *
     * @param $param
     */
    public function designate($param)
    {
        $apply_id = $param['apply_id'];
        $auditor_id = $param['auditor_id'];

        if (empty($apply_id) || empty($auditor_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $role_id = $admin['role_id'];

//        $channel = AdminRoleService::getRoleChannel();
//        $root = AdminRoleService::getRoleRoot();
//
//        $valid_role = array_merge($channel, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        //指派客服
        //$auditor_info = BaseModel::getInstance('admin')
        //    ->getOneOrFail($auditor_id);
        //$auditor_role_id = $auditor_info['role_id'];
        //if (!in_array($auditor_role_id, $valid_role)) {
        //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '指派客服没有权限');
        //}

        $model = BaseModel::getInstance($this->tableName);
        $apply_info = $model->getOneOrFail($apply_id);
        $status = $apply_info['status'];

        if (RecruitService::STATUS_APPLY != $status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '开点单状态不是待处理');
        }

        $update_data = [
            'auditor_id' => $auditor_id,
            'status'     => RecruitService::STATUS_WORKING,
        ];
        $model->update($apply_id, $update_data);
    }

    /**
     * 评价
     *
     * @param $param
     */
    public function evaluate($param)
    {
        //获取参数
        $apply_id = $param['apply_id'];
        $is_valid = $param['is_valid'];
        $remark = $param['remark'];

        //检查参数
        if (empty($apply_id) || empty($is_valid)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (
            RecruitService::RESULT_VALID != $is_valid &&
            RecruitService::RESULT_INVALID != $is_valid
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $role_id = $admin['role_id'];

//        $distributor = AdminRoleService::getRoleDistributor();
//        $root = AdminRoleService::getRoleRoot();
//
//        //权限判断
//        $valid_role = array_merge($distributor, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        //获取开点单
        $model = BaseModel::getInstance($this->tableName);
        $apply_info = $model->getOneOrFail($apply_id);
        $status = $apply_info['status'];

        //检查开点单
        if (RecruitService::STATUS_COMPLETE != $status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '开点单状态异常');
        }

        //更新数据
        $update_data = [
            'result_evaluate' => $remark,
            'is_valid'        => $is_valid,
        ];
        $model->update($apply_id, $update_data);

    }

    /**
     * 历史
     *
     * @param $param
     *
     * @return array
     */
    public function history($param)
    {
        $worker_order_id = $param['worker_order_id'];

        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $model = BaseModel::getInstance($this->tableName);
        $field = 'id,apply_admin_id,auditor_id,worker_id,remark,create_time,status,result_remark,worker_info,result_evaluate,is_valid';
        $opts = [
            'field' => $field,
            'where' => ['worker_order_id' => $worker_order_id],
            'order' => 'id desc',
        ];
        $list = $model->getList($opts);

        $admin_ids = [];
        $worker_ids = [];

        foreach ($list as $key => $val) {
            $admin_id = $val['apply_admin_id'];
            $auditor_id = $val['auditor_id'];
            $worker_id = $val['worker_id'];

            $admin_ids[] = $auditor_id;
            $admin_ids[] = $admin_id;
            $worker_ids[] = $worker_id;
        }

        $admins = $this->getAdmins($admin_ids);
        $workers = $this->getWorkers($worker_ids);

        foreach ($list as $key => $val) {
            $admin_id = $val['apply_admin_id'];
            $auditor_id = $val['auditor_id'];
            $worker_id = $val['worker_id'];
            $is_valid = $val['is_valid'];
            $status = $val['status'];

            $val['apply_cs'] = $admins[$admin_id]?? null;
            $val['auditor_cs'] = $admins[$auditor_id]?? null;
            $val['worker'] = $workers[$worker_id]?? null;

            //0-待评价 1-已评价 2-不能评价
            $evaluate_status = '2';
            if (
                RecruitService::STATUS_COMPLETE == $status &&
                RecruitService::RESULT_NULL == $is_valid
            ) {
                $evaluate_status = '0';
            } elseif (
                RecruitService::RESULT_VALID == $is_valid ||
                RecruitService::RESULT_INVALID == $is_valid
            ) {
                $evaluate_status = '1';
            }
            $val['evaluate_status'] = $evaluate_status;

            $list[$key] = $val;
        }

        return $list;
    }

    /**
     * 取消
     *
     * @param $param
     */
    public function cancel($param)
    {
        //获取参数
        $apply_id = $param['apply_id'];

        //检查参数
        if (empty($apply_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $role_id = $admin['role_id'];

//        $distributor = AdminRoleService::getRoleDistributor();
//        $root = AdminRoleService::getRoleRoot();
//
//        //权限判断
//        $valid_role = array_merge($distributor, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        //获取开点单
        $model = BaseModel::getInstance($this->tableName);
        $apply_info = $model->getOneOrFail($apply_id);
        $status = $apply_info['status'];

        //检查开点单
        $complete_status = [RecruitService::STATUS_COMPLETE, RecruitService::STATUS_FORBIDDEN];
        if (in_array($status, $complete_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '开点单已完结');
        }
        if (RecruitService::STATUS_CANCEL == $status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '开点单已取消');
        }

        //更新开点单
        $update_data = [
            'status' => RecruitService::STATUS_CANCEL,
        ];
        $model->update($apply_id, $update_data);

    }

    /**
     * 提交开点结果
     *
     * @param $param
     */
    public function feedback($param)
    {
        //获取参数
        $apply_id = $param['apply_id'];
        $result = $param['result'];
        $worker_id = $param['worker_id'];
        $remark = $param['remark'];

        //检查参数
        if (empty($result)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (
            RecruitService::STATUS_COMPLETE == $result &&
            empty($worker_id)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '开点成功的单需要提交技工信息');
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $role_id = $admin['role_id'];

//        $channel = AdminRoleService::getRoleChannel();
//        $root = AdminRoleService::getRoleRoot();
//
//        //权限判断
//        $valid_role = array_merge($channel, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $valid_status = [RecruitService::STATUS_COMPLETE, RecruitService::STATUS_FORBIDDEN, RecruitService::STATUS_FOLLOWING];
        if (!in_array($result, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '开点结果异常');
        }

        //获取开点单
        $model = BaseModel::getInstance($this->tableName);
        $apply = $model->getOneOrFail($apply_id);
        $status = $apply['status'];
        $worker_order_id = $apply['worker_order_id'];

        $field = 'distributor_id,orno';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $distributor_id = $order_info['distributor_id'];
        $orno = $order_info['orno'];

        //检查开点单
        if (
            RecruitService::STATUS_COMPLETE == $status ||
            RecruitService::STATUS_FORBIDDEN == $status
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '开点单已完结');
        }

        //更新开点单
        $update_data = [
            'result_remark' => $remark,
            'status'        => $result,
            'worker_id'     => $worker_id,
        ];
        if (
            RecruitService::STATUS_COMPLETE == $result ||
            RecruitService::STATUS_FORBIDDEN == $result
        ) {
            $update_data['audit_time'] = NOW_TIME; // 已完结开点单,记录完结时间
        }
        $model->update($apply_id, $update_data);

        $sys_msg = '';
        $sys_type = 0;
        if (RecruitService::STATUS_COMPLETE == $result) {
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_RECRUIT_ADMIN_SUCCESS;
            $sys_msg = "工单号{$orno}，开点成功";
        } elseif (RecruitService::STATUS_FORBIDDEN == $result) {
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_RECRUIT_ADMIN_FAIL;
            $sys_msg = "工单号{$orno}，开点失败";
        } elseif (RecruitService::STATUS_FOLLOWING == $result) {
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_RECRUIT_ADMIN_FOLLOW;
            $sys_msg = "工单号{$orno}，开点跟进中";
        }

        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, $sys_msg, $worker_order_id, $sys_type);
    }

    /**
     * 详情
     *
     * @param $param
     *
     * @return array
     */
    public function info($param)
    {
        //获取参数
        $apply_id = $param['apply_id'];

        // 检查参数
        if (empty($apply_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $field = 'id,worker_order_id,worker_id,auditor_id,apply_admin_id,apply_worker_number,remark,status,create_time,result_remark,worker_info,is_valid,result_evaluate,audit_time';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($apply_id, $field);

        $admin_id = $info['apply_admin_id'];
        $auditor_id = $info['auditor_id'];
        $worker_order_id = $info['worker_order_id'];
        $worker_id = $info['worker_id'];

        $field = 'orno,service_type,origin_type,add_id';
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id, $field);

        $field = 'id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,user_service_request,fault_label_ids';
        $where = ['worker_order_id' => $worker_order_id];
        $product = BaseModel::getInstance('worker_order_product')
            ->getList($where, $field);
        if (!empty($product)) {
            $label_ids = array_column($product, 'fault_label_ids');
            $label_ids = implode(',', $label_ids);
            $fault_label_ids = Util::filterIdList($label_ids);
            $labels = $this->getFaultLabels($fault_label_ids);

            foreach ($product as $key => $item) {
                $fault_label_ids = Util::filterIdList($item['fault_label_ids']);

                $cp_fault_name = '';
                if (!empty($fault_label_ids)) {
                    foreach ($fault_label_ids as $label_id) {
                        $cp_fault_name .= $labels[$label_id] ? $labels[$label_id]['label_name'] . ',' : '';
                    }

                    $cp_fault_name = rtrim($cp_fault_name, ',');
                }

                $product[$key]['cp_fault_name'] = $cp_fault_name;
            }

        }

        $user_info = $this->getWorkerOrderUserInfo([$worker_order_id]);
        $admins = $this->getAdmins([$admin_id, $auditor_id]);
        $workers = $this->getWorkers([$worker_id]);

        $info['order'] = $order;
        $origin_type = $order['origin_type'];
        $add_id = $order['add_id'];

        $order_factory = null;
        $factory_group_list = (new FactoryLogic())->getFactoryGroup();
        if (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
            $field = 'linkman,linkphone,factory_full_name,group_id';
            $factory = BaseModel::getInstance('factory')
                ->getOne($add_id, $field);
            if (!empty($factory)) {
                $order_factory = [
                    'linkman'           => $factory['linkman'],
                    'linkphone'         => $factory['linkphone'],
                    'factory_full_name' => $factory['factory_full_name'],
                    'group_name'          => $factory_group_list[$factory['group_id']]['name'],
                ];
            }
        } elseif (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
            $field = 'linkman,linkphone,factory_full_name,group_id';
            $factory = BaseModel::getInstance('factory')
                ->getOne($add_id, $field);
            if (!empty($factory)) {
                $order_factory = [
                    'linkman'           => $factory['linkman'],
                    'linkphone'         => $factory['linkphone'],
                    'factory_full_name' => $factory['factory_full_name'],
                    'group_name'          => $factory_group_list[$factory['group_id']]['name'],
                ];
            }
            $field = 'factory_id,nickout,tell';
            $factory_admin = BaseModel::getInstance('factory_admin')
                ->getOne($add_id, $field);
            if (!empty($factory_admin)) {
                $factory_id = $factory_admin['factory_id'];
                $field = 'factory_full_name,group_id';
                $factory = BaseModel::getInstance('factory')
                    ->getOne($factory_id, $field);
                $order_factory = [
                    'linkman'           => $factory_admin['nickout'],
                    'linkphone'         => $factory_admin['tell'],
                    'factory_full_name' => empty($factory) ? '' : $factory['factory_full_name'],
                    'group_name'          => $factory_group_list[$factory['group_id']]['name'],
                ];
            }
        }

        $info['apply_cs'] = $admins[$admin_id]?? null;
        $info['auditor_cs'] = $admins[$auditor_id]?? null;
        $info['products'] = empty($product) ? null : $product;
        $info['user_info'] = $user_info[$worker_order_id]?? null;
        $info['factory'] = $order_factory;
        $info['worker'] = $workers[$worker_id]?? null;

        return $info;

    }

    protected function getFaultLabels($label_ids)
    {
        if (empty($label_ids)) {
            return [];
        }

        $data = [];

        $model = BaseModel::getInstance('product_fault_label');
        $list = $model->getList(['id' => ['in', $label_ids]], 'id,label_name');

        foreach ($list as $val) {
            $label_id = $val['id'];

            $data[$label_id] = $val;
        }

        return $data;
    }

    public function workerList($param)
    {
        $name = $param['name'];
        $limit = $param['limit'];

        $where = [
            'is_qianzai'       => WorkerService::IDENTIFY_OFFICIAL,
            'is_check'         => WorkerService::CHECK_PASS,
            'is_complete_info' => WorkerService::DATA_PASS,
            'receivers_status' => ['in', [WorkerService::RECEIVE_STATUS_RECOMMEND, WorkerService::RECEIVE_STATUS_NORMAL]],
        ];

        if (!empty($name)) {
            $complex = [
                'nickname'         => ['like', '%' . $name . '%'],
                'worker_telephone' => ['like', '%' . $name . '%'],
                '_logic'           => 'or',
            ];
            $where['_complex'] = $complex;
        }

        $model = BaseModel::getInstance('worker');
        $cnt = $model->getNum($where);

        $filed = 'worker_id as id,nickname,worker_telephone';
        $opts = [
            'field' => $filed,
            'where' => $where,
            'order' => 'worker_id',
            'limit' => $limit,
        ];
        $list = $model->getList($opts);

        return [
            'data' => $list,
            'cnt'  => $cnt,
        ];
    }

    protected function getWorkerOrders($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'id,orno,worker_order_status,cancel_status';
        $where = ['id' => ['in', $worker_order_ids]];
        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $worker_order_id = $val['id'];
            $worker_order_status = $val['worker_order_status'];
            $cancel_status = $val['cancel_status'];

            $val['status_str'] = OrderService::getStatusStr($worker_order_status, $cancel_status);

            $data[$worker_order_id] = $val;
        }

        return $data;
    }

    protected function getFactories($factory_ids)
    {
        if (empty($factory_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory');
        $where = ['factory_id' => ['in', $factory_ids]];
        $filed = 'factory_id,linkman,factory_full_name,group_id';
        $list = $model->getList($where, $filed);

        $data = [];

        $factory_group_list = (new FactoryLogic())->getFactoryGroup();
        foreach ($list as $val) {
            $factory_id = $val['factory_id'];
            $val['group_name'] = $factory_group_list[$val['group_id']]['name'];

            $data[$factory_id] = $val;
        }

        return $data;
    }

    protected function getFactoryAdmins($factory_admin_ids)
    {
        if (empty($factory_admin_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory_admin');
        $opts = [
            'field' => 'fa.id,fa.nickout',
            'alias' => 'fa',
            'where' => ['fa.id' => ['in', $factory_admin_ids]],
            'join'  => ['left join factory_adtags as fat on fat.id=fa.tags_id'],
        ];
        $list = $model->getList($opts);

        $data = [];

        foreach ($list as $val) {
            $id = $val['id'];

            $data[$id] = $val;
        }

        return $data;

    }

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,nickout,user_name';
        $where = ['id' => ['in', $admin_ids]];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($where, $filed);

        $data = [];

        $role = AuthService::getModel();

        foreach ($list as $val) {
            $admin_id = $val['id'];

            if (
                AuthService::ROLE_FACTORY == $role ||
                AuthService::ROLE_FACTORY_ADMIN == $role
            ) {
                $val['nickout'] = $val['user_name'];
            }
            unset($val['user_name']);

            $data[$admin_id] = $val;
        }

        return $data;
    }

    protected function getWorkers($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $filed = 'worker_id,nickname';
        $where = ['worker_id' => ['in', $worker_ids]];
        $model = BaseModel::getInstance('worker');
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $worker_id = $val['worker_id'];

            $data[$worker_id] = $val;
        }

        return $data;
    }

    protected function getWorkerOrderProducts($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('worker_order_product');
        $field = 'worker_order_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,cp_fault_name,user_service_request';
        $where = ['worker_order_id' => ['in', $worker_order_ids]];
        $products = $model->getList($where, $field);

        $data = [];

        foreach ($products as $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id][] = $val;
        }

        return $data;
    }

    protected function getWorkerOrderUserInfo($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('worker_order_user_info');
        $where = ['worker_order_id' => ['in', $worker_order_ids]];
        $user_info = $model->getList($where);

        $area_ids = [];

        foreach ($user_info as $info) {
            $province_id = $info['province_id'];
            $city_id = $info['city_id'];
            $area_id = $info['area_id'];

            $area_ids[] = $province_id;
            $area_ids[] = $city_id;
            $area_ids[] = $area_id;
        }

        $areas = $this->getAreas($area_ids);

        $data = [];
        foreach ($user_info as $info) {
            $worker_order_id = $info['worker_order_id'];
            $province_id = $info['province_id'];
            $city_id = $info['city_id'];
            $area_id = $info['area_id'];
            $real_name = $info['real_name'];
            $phone = $info['phone'];
            $address = $info['address'];

            $province = $areas[$province_id]?? null;
            $city = $areas[$city_id]?? null;
            $district = $areas[$area_id]?? null;

            $data[$worker_order_id] = [
                'real_name' => $real_name,
                'phone'     => $phone,
                'province'  => $province,
                'city'      => $city,
                'district'  => $district,
                'address'   => $address,
            ];
        }

        return $data;
    }

    protected function getAreas($area_ids)
    {
        if (empty($area_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('area');
        $field = 'id,name';
        $where = ['id' => ['in', $area_ids]];
        $products = $model->getList($where, $field);

        $data = [];

        foreach ($products as $val) {
            $id = $val['id'];

            $data[$id] = $val;
        }

        return $data;
    }

}