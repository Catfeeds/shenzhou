<?php
/**
 * File: WorkerAdjustmentLogic.class.php
 * Function: 技工奖惩
 * User: sakura
 * Date: 2017/11/26
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\OtherTransactionEvent;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\AuthService;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Common\Common\Service\UserTypeService;
use Common\Common\Service\WorkerMoneyRecordService;
use Library\Common\Util;

class WorkerAdjustmentLogic extends BaseLogic
{

    protected $tableName = 'worker_money_adjust_record';

    public function getList($param)
    {
        $worker_id = $param['worker_id'];
        $fee_from = $param['fee_from'];
        $fee_to = $param['fee_to'];
        $worker_name = $param['worker_name'];
        $orno = $param['orno'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $limit = $param['limit'];

        $is_export = $param['is_export'];

        $where = [];
        if ($worker_id > 0) {
            $where['worker_id'] = $worker_id;
        }
        if (strlen($fee_from) > 0) {
            $where['adjust_money'][] = ['egt', $fee_from];
        }
        if (strlen($fee_to) > 0) {
            $where['adjust_money'][] = ['elt', $fee_to];
        }
        if (strlen($worker_name) > 0) {
            $where['worker_id'][] = ['exp', "in (select worker_id from worker where nickname like '%{$worker_name}%')"];
        }
        if (strlen($orno) > 0) {
            $where['worker_order_id'][] = ['exp', "in (select id from worker_order where orno like '%{$orno}%')"];
        }
        if ($date_from > 0) {
            $where['create_time'][] = ['egt', $date_from];
        }
        if ($date_to > 0) {
            $where['create_time'][] = ['lt', $date_to];
        }

        if (1 == $is_export) {
            //todo 补充判断 不存在客服使用记录名字
            $export_opts = ['where' => $where];
            (new ExportLogic())->adminWorkerAdjust($export_opts);
        } else {
            $field = 'id,create_time,adjust_money,worker_last_money,adjust_remark,worker_id,worker_order_id,admin_id,cp_admin_name as admin';
            $model = BaseModel::getInstance($this->tableName);
            $cnt = $model->getNum($where);
            $total_fee = $model->getSum($where, 'adjust_money');

            $opts = [
                'field' => $field,
                'where' => $where,
                'order' => 'id desc',
                'limit' => $limit,
            ];
            $list = $model->getList($opts);

            $worker_ids = [];
            $worker_order_ids = [];
            $admin_ids = [];

            foreach ($list as $val) {
                $worker_id = $val['worker_id'];
                $worker_order_id = $val['worker_order_id'];
                $admin_id = $val['admin_id'];

                $worker_ids[] = $worker_id;
                $worker_order_ids[] = $worker_order_id;
                $admin_ids[] = $admin_id;
            }

            $orders = $this->getWorkerOrders($worker_order_ids);
            $workers = $this->getWorkers($worker_ids);
            $admins = $this->getAdmins($admin_ids);

            foreach ($list as $key => $val) {
                $worker_id = $val['worker_id'];
                $worker_order_id = $val['worker_order_id'];
                $admin_id = $val['admin_id'];
                $log_admin_name = $val['admin'];

                $val['worker'] = $workers[$worker_id]?? null;
                $admin = $admins[$admin_id] ?? null;
                if (is_null($admin) && strlen($log_admin_name) > 0) {
                    $admin = [
                        'id'        => $admin_id,
                        'user_name' => $log_admin_name,
                    ];
                }
                $val['admin'] = $admin;
                $val['order'] = $orders[$worker_order_id]?? null;

                $list[$key] = $val;
            }

            return [
                'list'      => $list,
                'cnt'       => $cnt,
                'total_fee' => $total_fee,
            ];
        }

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

            if (AuthService::ROLE_ADMIN == $role) {
                $val['user_name'] = $val['nickout'];
            }
            unset($val['nickout']);

            $data[$admin_id] = $val;
        }

        return $data;
    }

    protected function getWorkers($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $filed = 'worker_id,nickname as user_name';
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

    protected function getWorkerOrders($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'id,orno,origin_type,add_id';
        $where = ['id' => ['in', $worker_order_ids]];
        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $worker_order_id = $val['id'];

            $data[$worker_order_id] = $val;
        }

        return $data;
    }

    public function add($param)
    {
        $worker_id = $param['worker_id'];
        $orno = $param['orno'];
        $fee = $param['fee'];
        $remark = $param['remark'];

        if (empty($orno) || empty($fee)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (0 == $fee) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '金额不能为零');
        }

        $where = ['orno' => $orno];
        $field = 'id,worker_id';
        $order_model = BaseModel::getInstance('worker_order');
        $order = $order_model->getOneOrFail($where, $field);
        $worker_order_id = $order['id'];
        $order_worker_id = $order['worker_id'];

        $worker_db = BaseModel::getInstance('worker');
        $field = 'money';
        $worker_info = $worker_db->getOneOrFail($worker_id, $field);
        $worker_balance = $worker_info['money'];

        if ($order_worker_id != $worker_id) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单不属于此技工');
        }
        //产品要求 允许余额负数
        //if ($worker_balance + $fee < 0) {
        //    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '余额不能小于零');
        //}
        $worker_last_money = round($worker_balance+$fee, 2, PHP_ROUND_HALF_UP); // 变动后金额

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = $admin['id'];
        $admin_name = $admin['nickout'];
        $role_id = $admin['role_id'];

        //权限
//        $root = AdminRoleService::getRoleRoot();
//        $auditor = AdminRoleService::getRoleAuditor();
//        $valid_role = array_merge($auditor, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        //新增记录
        $insert_data = [
            'admin_id'          => $admin_id,
            'worker_id'         => $worker_id,
            'worker_order_id'   => $worker_order_id,
            'create_time'       => NOW_TIME,
            'adjust_money'      => $fee,
            'worker_last_money' => $worker_last_money,
            'adjust_remark'     => $remark,
            'cp_admin_name'     => $admin_name,
            'adjust_type'       => 0,
        ];
        $model = BaseModel::getInstance($this->tableName);
        $insert_id = $model->insert($insert_data);

        //技工金额变更
        $where = [
            'worker_id' => $worker_id,
        ];
        $update_data = [
            'money' => $worker_last_money,
        ];
        $worker_db->update($where, $update_data);

        //技工资金日志
        $insert_data = [
            'worker_id'   => $worker_id,
            'type'        => WorkerMoneyRecordService::TYPE_REWARD_AND_PUNISH,
            'data_id'     => $insert_id,
            'money'       => $fee,
            'last_money'  => $worker_last_money,
            'create_time' => NOW_TIME,
        ];
        $record_db = BaseModel::getInstance('worker_money_record');
        $adjust_id = $record_db->insert($insert_data);

        event(new OtherTransactionEvent(['type' => AppMessageService::TYPE_MONEY_ADJUST_SET, 'data_id' => $insert_id]));

    }

}