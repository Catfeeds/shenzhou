<?php
/**
 * File: WorkerWithdrawLogic.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/25
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\CashEvent;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\AuthService;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Common\Common\Service\UserTypeService;
use Common\Common\Service\WithdrawcashService;
use Common\Common\Service\WorkerMoneyRecordService;
use Common\Common\Service\WorkerWithdrawService;
use Library\Common\Util;

class WorkerWithdrawLogic extends BaseLogic
{

    protected $tableName = 'worker_withdrawcash_record';

    const BANK_ID_OTHERS = 659004728;

    public function getList($param)
    {
        //获取参数
        $worker_id = $param['worker_id'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $complete_from = $param['complete_from'];
        $complete_to = $param['complete_to'];
        $card_number = $param['card_number'];
        $real_name = $param['real_name'];
        $fee_from = $param['fee_from'];
        $fee_to = $param['fee_to'];
        $status = $param['status'];
        $bank_id = $param['bank_id'];
        $withdraw_cash_number = $param['withdraw_cash_number'];
        $limit = $param['limit'];
        $excel_id = $param['excel_id'];
        $is_export = $param['is_export'];

        $where = [];
        if ($worker_id > 0) {
            $where['worker_id'] = $worker_id;
        }
        if ($date_from > 0) {
            $where['create_time'][] = ['egt', $date_from];
        }
        if ($date_to > 0) {
            $where['create_time'][] = ['lt', $date_to];
        }
        if ($complete_from > 0) {
            $where['complete_time'][] = ['egt', $complete_from];
        }
        if ($complete_to > 0) {
            $where['complete_time'][] = ['lt', $complete_to];
        }
        if (!empty($card_number)) {
            $where['card_number'] = ['like', '%' . $card_number . '%'];
        }
        if (strlen($real_name) > 0) {
            $where['real_name'] = ['like', '%' . $real_name . '%'];
        }
        if ($fee_from > 0) {
            $where['out_money'][] = ['egt', $fee_from];
        }
        if ($fee_to > 0) {
            $where['out_money'][] = ['elt', $fee_to];
        }
        if (strlen($status) > 0) {
            if (4 == $status) {
                $where['status'][] = ['eq', WithdrawcashService::CREATE_STATUS];
                $where['withdrawcash_excel_id'] = ['gt', 0];
            } elseif (0 == $status) {
                $where['status'][] = ['eq', WithdrawcashService::CREATE_STATUS];
                $where['_complex'] = [
                    'withdrawcash_excel_id' => [
                        ['eq', 0],
                        ['exp', 'IS NULL'],
                        'or'
                    ],
                ];
            } else {
                $where['status'][] = ['eq', $status];
            }
        }
        if ($bank_id > 0) {
            $where['bank_name'] = ['exp', 'in (select item_desc from cm_list_item where list_id=42 and list_item_id=' . $bank_id . ')'];
        }
        if ($withdraw_cash_number > 0) {
            $where['withdraw_cash_number'] = ['like', '%' . $withdraw_cash_number . '%'];
        }
        if ($excel_id > 0) {
            $where['withdrawcash_excel_id'] = $excel_id;
        }

        if (1 == $is_export) {
            $export_opts = ['where' => $where];
            (new ExportLogic())->adminWorkerWithdraw($export_opts, $worker_id);
        } else {
            $model = BaseModel::getInstance($this->tableName);
            $cnt = $model->getNum($where);

            $total_where = $where;
            $total_where['status'][] = ['in', [WorkerWithdrawService::STATUS_SUCCESS]];
            $total_fee = $model->getSum($total_where, 'out_money');

            $field = 'id,worker_id,create_time,withdraw_cash_number,out_money,complete_time,card_number,other_bank_name,bank_name,real_name,status,withdrawcash_excel_id,bank_id';
            $opts = [
                'field' => $field,
                'where' => $where,
                'order' => 'id desc',
                'limit' => $limit,
            ];
            $list = $model->getList($opts);

            foreach ($list as $key => $val) {
                $complete_time = $val['complete_time'];
                $withdrawcash_excel_id = $val['withdrawcash_excel_id'];
                $bank_id = $val['bank_id'];

                $complete_time = $complete_time <= 0 ? null : $complete_time;
                if (self::BANK_ID_OTHERS != $bank_id) {
                    $val['other_bank_name'] = '';
                }

                $val['complete_time'] = $complete_time;
                $val['is_in_excel'] = $withdrawcash_excel_id > 0 ? '1' : '0';

                $list[$key] = $val;
            }

            return [
                'data'      => $list,
                'cnt'       => $cnt,
                'total_fee' => $total_fee,
            ];
        }

    }

    protected function getBanks($bank_ids)
    {
        if (empty($bank_ids)) {
            return [];
        }

        $bank_type = 42;
        $model = BaseModel::getInstance('cm_list_item');
        $where = ['list_id' => $bank_type, 'list_item_id' => ['in', $bank_ids]];
        $field = 'list_item_id as bank_id,item_desc as name';
        $opts = [
            'field' => $field,
            'where' => $where,
        ];
        $list = $model->getList($opts);

        $data = [];

        foreach ($list as $val) {
            $id = $val['bank_id'];

            $data[$id] = $val;
        }

        return $data;
    }

    public function getBankList($param)
    {
        //$limit = $param['limit'];

        $bank_type = 42;
        $model = BaseModel::getInstance('cm_list_item');

        $where = ['list_id' => $bank_type];
        $field = 'list_item_id as bank_id,item_desc as name';
        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'item_sort,list_item_id',
            //'limit' => $limit,
        ];

        $list = $model->getList($opts);
        $cnt = $model->getNum($where);

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];

    }

    public function processed($param)
    {
        //获取参数
        $worker_id = $param['worker_id'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $complete_from = $param['complete_from'];
        $complete_to = $param['complete_to'];
        $card_number = $param['card_number'];
        $real_name = $param['real_name'];
        $fee_from = $param['fee_from'];
        $fee_to = $param['fee_to'];
        $status = $param['status'];
        $bank_id = $param['bank_id'];
        $withdraw_cash_number = $param['withdraw_cash_number'];

        $where = [];
        if ($worker_id > 0) {
            $where['worker_id'] = $worker_id;
        }
        if ($date_from > 0) {
            $where['create_time'][] = ['egt', $date_from];
        }
        if ($date_to > 0) {
            $where['create_time'][] = ['lt', $date_to];
        }
        if ($complete_from > 0) {
            $where['complete_time'][] = ['egt', $complete_from];
        }
        if ($complete_to > 0) {
            $where['complete_time'][] = ['lt', $complete_to];
        }
        if (!empty($card_number)) {
            $where['card_number'] = ['like', '%' . $card_number . '%'];
        }
        if (strlen($real_name) > 0) {
            $where['real_name'] = ['like', '%' . $real_name . '%'];
        }
        if ($fee_from > 0) {
            $where['out_money'][] = ['egt', $fee_from];
        }
        if ($fee_to > 0) {
            $where['out_money'][] = ['elt', $fee_to];
        }
        if (strlen($status) > 0) {
            if (4 == $status) {
                $where['status'][] = ['eq', WithdrawcashService::CREATE_STATUS];
                $where['withdrawcash_excel_id'] = ['gt', 0];
            } elseif (0 == $status) {
                $where['status'][] = ['eq', WithdrawcashService::CREATE_STATUS];
                $where['_complex'] = [
                    'withdrawcash_excel_id' => [
                        ['eq', 0],
                        ['exp', 'IS NULL'],
                        'or'
                    ],
                ];
            } else {
                $where['status'][] = ['eq', $status];
            }
        }
        if ($bank_id > 0) {
            $where['bank_name'] = ['exp', 'in (select item_desc from cm_list_item where list_id=42 and list_item_id=' . $bank_id . ')'];
        }
        if (strlen($withdraw_cash_number) > 0) {
            $where['withdraw_cash_number'] = ['like', '%' . $withdraw_cash_number . '%'];
        }

        //获取客服信息
        $admin = AuthService::getAuthModel();
        $admin_id = $admin['id'];
        $role_id = $admin['role_id'];

        //判断权限
//        $root = AdminRoleService::getRoleRoot();
//        $auditor = AdminRoleService::getRoleAuditor();
//        $valid_role = array_merge($auditor, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $model = BaseModel::getInstance($this->tableName);
        $field = 'id,status,withdrawcash_excel_id';
        $withdraw_list = $model->getList($where, $field);

        if (empty($withdraw_list)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '导出数据为空');
        }

        foreach ($withdraw_list as $withdraw) {
            $status = $withdraw['status'];
            $withdrawcash_excel_id = $withdraw['withdrawcash_excel_id'];

            if (WorkerWithdrawService::STATUS_WORKING != $status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '提现单状态异常');
            }

            if ($withdrawcash_excel_id > 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分提现单已导出');
            }
        }

        $withdraw_ids = array_column($withdraw_list, 'id');

        $excel_model = BaseModel::getInstance('worker_withdrawcash_excel');
        $insert_data = [
            'admin_id'    => $admin_id,
            'ids'         => implode(',', $withdraw_ids),
            'create_time' => NOW_TIME,
        ];
        $insert_id = $excel_model->insert($insert_data);

        $update_where = ['id' => ['in', $withdraw_ids]];
        $update_data = ['withdrawcash_excel_id' => $insert_id];
        $model->update($update_where, $update_data);

        foreach ($withdraw_ids as $withdraw_id) {
            event(new CashEvent(['type' => AppMessageService::TYPE_CASHING, 'data_id' => $withdraw_id]));
        }

        return [
            'excel_id' => $insert_id,
        ];

    }

    public function edit($param)
    {
        $result = $param['result'];
        $remark = $param['remark'];
        $withdraw_id = $param['withdraw_id'];

        $valid_result = [WorkerWithdrawService::STATUS_SUCCESS, WorkerWithdrawService::STATUS_FAIL];
        if (!in_array($result, $valid_result)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = $admin['id'];
        $role_id = $admin['role_id'];

//        $root = AdminRoleService::getRoleRoot();
//        $auditor = AdminRoleService::getRoleAuditor();
//        $valid_role = array_merge($auditor, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $model = BaseModel::getInstance($this->tableName);

        $field = 'status,out_money,worker_id';
        $withdraw_info = $model->getOneOrFail($withdraw_id, $field);
        $status = $withdraw_info['status'];
        $out_money = $withdraw_info['out_money'];
        $worker_id = $withdraw_info['worker_id'];

        if (
            WorkerWithdrawService::STATUS_SUCCESS == $status ||
            WorkerWithdrawService::STATUS_FAIL == $status
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '提现单已完结');
        }
        if (WorkerWithdrawService::STATUS_WORKING != $status) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '提现单状态异常');
        }


        $update_data = [
            'status'        => $result,
            'fail_reason'   => $remark,
            'completer_id'  => $admin_id,
            'complete_time' => NOW_TIME,
        ];
        $model->update($withdraw_id, $update_data);

        $record_db = BaseModel::getInstance('worker_money_record');
        $where = ['type' => WorkerMoneyRecordService::TYPE_WITHDRAW_APPLY, 'data_id' => $withdraw_id];
        $message_type = 0;
        if (WorkerWithdrawService::STATUS_SUCCESS == $result) {
            //成功要更新记录
            $update_data = ['type' => WorkerMoneyRecordService::TYPE_WITHDRAW_PASS];
            $record_db->update($where, $update_data);

            $message_type = AppMessageService::TYPE_CASH_SUCCESS;
        } else {
            //失败要把金额退回技工
            $update_data = ['money' => ['exp', 'money+' . $out_money]];
            $worker_db = BaseModel::getInstance('worker');
            $worker_db->update($worker_id, $update_data);

            //删除提现记录
            $record_db->remove($where);

            $message_type = AppMessageService::TYPE_CASH_FAIL;
        }

        event(new CashEvent(['type' => $message_type, 'data_id' => $withdraw_id]));
    }

    public function editBatch($param)
    {
        $result = $param['result'];
        $remark = $param['remark'];
        $withdraw_ids = $param['withdraw_ids'];

        if (empty($withdraw_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        $valid_result = [WorkerWithdrawService::STATUS_SUCCESS, WorkerWithdrawService::STATUS_FAIL];

        if (!in_array($result, $valid_result)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = $admin['id'];
        $role_id = $admin['role_id'];

//        $root = AdminRoleService::getRoleRoot();
//        $auditor = AdminRoleService::getRoleAuditor();
//        $valid_role = array_merge($auditor, $root);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $model = BaseModel::getInstance($this->tableName);

        $field = 'status,out_money,worker_id,id';
        $withdraw_list = $model->getList(['id' => ['in', $withdraw_ids]], $field);
        foreach ($withdraw_list as $withdraw_info) {
            $status = $withdraw_info['status'];

            if (
                WorkerWithdrawService::STATUS_SUCCESS == $status ||
                WorkerWithdrawService::STATUS_FAIL == $status
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '提现单已完结');
            }
            if (WorkerWithdrawService::STATUS_WORKING != $status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '提现单状态异常');
            }
        }

        $update_data = [
            'status'        => $result,
            'fail_reason'   => $remark,
            'completer_id'  => $admin_id,
            'complete_time' => NOW_TIME,
        ];
        $where = ['id' => ['in', $withdraw_ids]];
        $model->update($where, $update_data);

        $record_db = BaseModel::getInstance('worker_money_record');
        $worker_db = BaseModel::getInstance('worker');

        $success_data_ids = [];
        $fail_data_ids = [];

        foreach ($withdraw_list as $withdraw_info) {
            $withdraw_id = $withdraw_info['id'];
            $out_money = $withdraw_info['out_money'];
            $worker_id = $withdraw_info['worker_id'];

            $message_type = 0;
            if (WorkerWithdrawService::STATUS_SUCCESS == $result) {
                //成功要更新记录
                $message_type = AppMessageService::TYPE_CASH_SUCCESS;
                $success_data_ids[] = $withdraw_id;
            } else {
                //失败要把金额退回技工
                $update_data = ['money' => ['exp', 'money+' . $out_money]];
                $worker_db->update($worker_id, $update_data);

                $message_type = AppMessageService::TYPE_CASH_FAIL;
                $fail_data_ids[] = $withdraw_id;
            }

            event(new CashEvent(['type' => $message_type, 'data_id' => $withdraw_id]));
        }

        if (!empty($success_data_ids)) {
            //提现成功,更新记录类型
            $where = ['type' => WorkerMoneyRecordService::TYPE_WITHDRAW_APPLY, 'data_id' => ['in', $success_data_ids]];
            $update_data = ['type' => WorkerMoneyRecordService::TYPE_WITHDRAW_PASS];
            $record_db->update($where, $update_data);
        }

        if (!empty($fail_data_ids)) {
            //提现失败,删除提现记录
            $where = ['type' => WorkerMoneyRecordService::TYPE_WITHDRAW_APPLY, 'data_id' => ['in', $fail_data_ids]];
            $record_db->remove($where);
        }

    }

    public function excelHistory($param)
    {
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $limit = $param['limit'];

        $model = BaseModel::getInstance('worker_withdrawcash_excel');

        $where = [];
        if ($date_from > 0) {
            $where['create_time'][] = ['egt', $date_from];
        }
        if ($date_to > 0) {
            $where['create_time'][] = ['lt', $date_to];
        }

        $opts = [
            'order' => 'id desc',
            'limit' => $limit,
            'where' => $where,
        ];

        $cnt = $model->getNum($where);

        $list = $model->getList($opts);

        foreach ($list as $key => $val) {
            $ids = $val['ids'];
            $ids = explode(',', $ids);

            $total = count($ids);
            $file_name = date('技工提现-(Y年m月d日H时i分s秒).') . 'xls';

            $val['total'] = $total;
            $val['file_name'] = $file_name;
            unset($val['ids']);

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];


    }

    public function excelDownload($param)
    {
        $excel_id = $param['excel_id'];

        if ($excel_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $where = [];
        $where['withdrawcash_excel_id'] = $excel_id;

        $export_opts = ['where' => $where];
        (new ExportLogic())->processedWithdraw($export_opts);
    }
}