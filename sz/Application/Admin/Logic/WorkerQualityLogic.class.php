<?php
/**
 * File: WorkerQualityService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/27
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
use Common\Common\Service\WorkerQualityService;

class WorkerQualityLogic extends BaseLogic
{

    protected $tableName = 'worker_quality_money_record';

    public function getList($param)
    {
        $worker_id = $param['worker_id'];
        $limit = $param['limit'];
        $is_export = $param['is_export'];

        $where = [];
        if ($worker_id > 0) {
            $where['worker_id'] = $worker_id;
        }

        if (1 == $is_export) {
            $export_opts = ['where' => $where];
            (new ExportLogic())->adminWorkerQuality($export_opts);
        } else {
            $model = BaseModel::getInstance($this->tableName);
            $cnt = $model->getNum($where);

            $field = 'quality_money,last_quality_money,remark,create_time,admin_id,type,worker_order_id,remark';
            $opts = [
                'field' => $field,
                'where' => $where,
                'order' => 'id desc',
                'limit' => $limit,
            ];
            $list = $model->getList($opts);

            $admin_ids = [];
            $worker_order_ids = [];

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_order_id = $val['worker_order_id'];

                $admin_ids[] = $admin_id;
                $worker_order_ids[] = $worker_order_id;
            }

            $admins = $this->getAdmins($admin_ids);
            $orders = $this->getWorkerOrders($worker_order_ids);

            foreach ($list as $key => $val) {
                $admin_id = $val['admin_id'];
                $worker_order_id = $val['worker_order_id'];

                $val['admin'] = $admins[$admin_id]?? null;
                $val['order'] = $orders[$worker_order_id]?? null;

                $list[$key] = $val;
            }

            return [
                'data' => $list,
                'cnt'  => $cnt,
            ];
        }

    }

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,nickout as user_name';
        $where = ['id' => ['in', $admin_ids]];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $admin_id = $val['id'];

            $data[$admin_id] = $val;
        }

        return $data;
    }

    protected function getWorkerOrders($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'id,orno';
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
        $change_quality_money = $param['fee'];
        $worker_id = $param['worker_id'];
        $remark = $param['remark'];

        if ($worker_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $change_quality_money = round($change_quality_money, 2, PHP_ROUND_HALF_UP);
        if (0 == $change_quality_money) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '调整金额不能为0');
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $user_id = $admin['id'];
        $role_id = $admin['role_id'];

        //权限
//        $root = AdminRoleService::getRoleRoot();
//        $auditor = AdminRoleService::getRoleAuditor();
//        $valid_role = array_merge($auditor, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $worker_model = BaseModel::getInstance('worker');
        $field = 'quality_money,quality_money_need';
        $worker = $worker_model->getOneOrFail($worker_id, $field);
        $quality_money = $worker['quality_money'];
        $quality_money_need = $worker['quality_money_need'];

        $last_quality_money = round($quality_money + $change_quality_money, 2, PHP_ROUND_HALF_UP);
        if ($last_quality_money > $quality_money_need) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '已缴质保金不能大于需缴质保金');
        }

        $model = BaseModel::getInstance($this->tableName);
        $insert_data = [
            'worker_id'          => $worker_id,
            'admin_id'           => $user_id,
            'type'               => WorkerQualityService::TYPE_MANUAL,
            'quality_money'      => $change_quality_money,
            'last_quality_money' => $last_quality_money,
            'remark'             => $remark,
            'create_time'        => NOW_TIME,
        ];
        $record_id = $model->insert($insert_data);

        $worker_model = BaseModel::getInstance('worker');
        $update_data = [
            'quality_money' => $last_quality_money,
        ];
        $where = [
            'worker_id' => $worker_id,
        ];
        $worker_model->update($where, $update_data);

        event(new OtherTransactionEvent(['type' => AppMessageService::TYPE_QUALITY_MONEY_SET, 'data_id' => $record_id]));
    }

}