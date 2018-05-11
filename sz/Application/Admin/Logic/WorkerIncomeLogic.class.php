<?php
/**
 * File: WorkerIncomeLogic.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/26
 */

namespace Admin\Logic;

use Admin\Model\BaseModel;

class WorkerIncomeLogic extends BaseLogic
{
    protected $tableName = 'worker_repair_money_record';

    public function getList($param)
    {
        $worker_id = $param['worker_id'];
        $orno = $param['orno'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $limit = $param['limit'];

        $is_export = $param['is_export'];

        $where = [];
        if ($worker_id > 0) {
            $where['worker_id'] = $worker_id;
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
            $export_opts = ['where' => $where];
            (new ExportLogic())->adminWorkerIncome($export_opts, $worker_id);
        } else {

            $model = BaseModel::getInstance($this->tableName);
            $cnt = $model->getNum($where);

            $total_fee = $model->getSum($where, 'order_money');

            $opts = [
                'where' => $where,
                'order' => 'id desc',
                'limit' => $limit,
            ];
            $list = $model->getList($opts);;
            $worker_order_ids = [];
            $worker_ids = [];

            foreach ($list as $val) {
                $worker_order_id = $val['worker_order_id'];
                $worker_id = $val['worker_id'];

                $worker_order_ids[] = $worker_order_id;
                $worker_ids[] = $worker_id;
            }

            $orders = $this->getWorkerOrders($worker_order_ids);
            $workers = $this->getWorkers($worker_ids);
            $products = $this->getWorkerOrderProducts($worker_order_ids);
            $userinfos = $this->getUserInfo($worker_order_ids);


            foreach ($list as $key => $val) {
                $worker_order_id = $val['worker_order_id'];
                $worker_id = $val['worker_id'];

                $val['order'] = $orders[$worker_order_id]?? null;
                $val['worker'] = $workers[$worker_id]?? null;
                $val['product'] = $products[$worker_order_id]?? null;
                $val['user_info'] = $userinfos[$worker_order_id]?? null;

                $list[$key] = $val;

            }

            return [
                'cnt'       => $cnt,
                'data'      => $list,
                'total_fee' => $total_fee,
            ];
        }

    }

    protected function getWorkerOrders($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'id,orno,service_type';
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

    protected function getWorkerOrderProducts($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'id,worker_order_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,cp_fault_name';
        $where = ['worker_order_id' => ['in', $worker_order_ids]];
        $model = BaseModel::getInstance('worker_order_product');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id][] = $val;
        }

        return $data;
    }

    protected function getWorkers($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $filed = 'worker_id,nickname,worker_telephone';
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

    //维修工单用户信息
    protected function getUserInfo($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'worker_order_id,cp_area_names,address';
        $where = ['worker_order_id' => ['in', $worker_order_ids]];
        $model = BaseModel::getInstance('worker_order_user_info');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        return $data;
    }
}