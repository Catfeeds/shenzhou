<?php
/**
 * File: WorkerIncomeController.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/26
 */

namespace Admin\Controller;


use Common\Common\Service\AuthService;

class WorkerIncomeController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $is_export = I('is_export', 0, 'intval');

            $param = [
                'worker_id'   => I('worker_id', 0, 'intval'),
                'fee_from'    => I('fee_from'),
                'fee_to'      => I('fee_to'),
                'worker_name' => I('worker_name'),
                'orno'        => I('orno'),
                'date_from'   => I('date_from', 0, 'intval'),
                'date_to'     => I('date_to', 0, 'intval'),
                'limit'       => $this->page(),
                'is_export' => $is_export
            ];

            $result = D('WorkerIncome', 'Logic')->getList($param);

            if (1 != $is_export) {
                $this->paginate($result['data'], $result['cnt'], ['total_fee' => $result['total_fee']]);
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}