<?php
/**
 * File: WorkerLabelController.class.php
 * User: sakura
 * Date: 2017/11/21
 */

namespace Admin\Controller;


use Admin\Logic\WorkerLabelLogic;
use Common\Common\Service\AuthService;

class WorkerLabelController extends BaseController
{

    public function index()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'keyword' => I('keyword'),
            ];

            $data = D('WorkerLabel', 'Logic')->getList($param);

            $this->responseList($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getHistoryLabel()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id' => I('worker_id', 0, 'intval'),
            ];

            $data = D('WorkerLabel', 'Logic')->getHistoryLabel($param);

            $this->responseList($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function label()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id' => I('get.worker_id', 0, 'intval'),
                'label_ids' => I('label_ids', []),
            ];

            M()->startTrans();
            D('WorkerLabel', 'Logic')->label($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getAdminHistoryLabel()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id' => I('worker_id', 0, 'intval'),
            ];

            $data = D('WorkerLabel', 'Logic')->getAdminHistoryLabel($param);

            $this->responseList($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function deleteLabel()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'worker_id' => I('get.worker_id', 0, 'intval'),
                'label_ids' => I('label_ids'),
            ];

            (new WorkerLabelLogic())->deleteLabel($param);

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}