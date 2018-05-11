<?php
/**
 * File: WorkerWithdrawController.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/26
 */

namespace Admin\Controller;


use Admin\Logic\ExportLogic;
use Common\Common\Service\AuthService;

class WorkerWithdrawController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $is_export = I('is_export', 0, 'intval');

            $param = [
                'worker_id'            => I('worker_id', 0, 'intval'),
                'date_from'            => I('date_from', 0, 'intval'),
                'date_to'              => I('date_to', 0, 'intval'),
                'complete_from'        => I('complete_from', 0, 'intval'),
                'complete_to'          => I('complete_to', 0, 'intval'),
                'card_number'          => I('card_number'),
                'real_name'            => I('real_name'),
                'fee_from'             => I('fee_from'),
                'fee_to'               => I('fee_to'),
                'status'               => I('status'),
                'bank_id'              => I('bank_id', 0, 'intval'),
                'withdraw_cash_number' => I('withdraw_cash_number'),
                'excel_id'             => I('excel_id', 0, 'intval'),
                'limit'                => $this->page(),
                'is_export'            => $is_export,
            ];

            $result = D('WorkerWithdraw', 'Logic')->getList($param);
            if (1 != $is_export) {
                $this->paginate($result['data'], $result['cnt'], ['total_fee' => $result['total_fee']]);
            }

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function edit()
    {
        try {

            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'result'      => I('result'),
                'remark'      => I('remark'),
                'withdraw_id' => I('get.withdraw_id'),
            ];

            M()->startTrans();
            D('WorkerWithdraw', 'Logic')->edit($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function editBatch()
    {
        try {

            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'result'       => I('result'),
                'remark'       => I('remark'),
                'withdraw_ids' => I('withdraw_ids'),
            ];

            M()->startTrans();
            D('WorkerWithdraw', 'Logic')->editBatch($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function bank()
    {
        try {

            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                //'limit' => $this->page()
            ];

            $result = D('WorkerWithdraw', 'Logic')->getBankList($param);

            $this->response(['data_list' => $result['list']]);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function processed()
    {
        try {

            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id'            => I('worker_id', 0, 'intval'),
                'date_from'            => I('date_from', 0, 'intval'),
                'date_to'              => I('date_to', 0, 'intval'),
                'complete_from'        => I('complete_from', 0, 'intval'),
                'complete_to'          => I('complete_to', 0, 'intval'),
                'card_number'          => I('card_number'),
                'real_name'            => I('real_name'),
                'fee_from'             => I('fee_from'),
                'fee_to'               => I('fee_to'),
                'status'               => I('status'),
                'bank_id'              => I('bank_id', 0, 'intval'),
                'withdraw_cash_number' => I('withdraw_cash_number'),
            ];

            M()->startTrans();
            $withdraw_result = D('WorkerWithdraw', 'Logic')->processed($param);
            M()->commit();

            $this->response($withdraw_result);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function excelHistory()
    {
        try {

            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'date_from' => I('date_from', 0, 'intval'),
                'date_to'   => I('date_to', 0, 'intval'),
                'limit'     => $this->page(),
            ];

            $result = D('WorkerWithdraw', 'Logic')->excelHistory($param);
            $this->paginate($result['list'], $result['cnt']);


        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function excelDownload()
    {
        try {

            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'excel_id' => I('excel_id', 0, 'intval'),
            ];

            D('WorkerWithdraw', 'Logic')->excelDownload($param);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}