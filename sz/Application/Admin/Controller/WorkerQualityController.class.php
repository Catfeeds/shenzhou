<?php
/**
 * File: WorkerQualityController.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/28
 */

namespace Admin\Controller;


use Common\Common\Service\AuthService;

class WorkerQualityController extends BaseController
{

    public function index()
    {

        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $is_export = I('is_export', 0, 'intval');

            $param = [
                'worker_id' => I('worker_id', 0, 'intval'),
                'limit'     => $this->page(),
                'is_export' => $is_export
            ];

            $result = D('WorkerQuality', 'Logic')->getList($param);
            if (1 != $is_export) {
                $this->paginate($result['data'], $result['cnt']);
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function add()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id' => I('worker_id', 0, 'intval'),
                'fee'       => I('fee'),
                'remark'    => I('remark'),
            ];

            M()->startTrans();
            D('WorkerQuality', 'Logic')->add($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

}