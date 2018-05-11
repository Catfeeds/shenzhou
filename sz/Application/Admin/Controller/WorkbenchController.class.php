<?php
/**
 * File: WorkbenchController.class.php
 * Function:工作台
 * User: sakura
 * Date: 2018/3/12
 */

namespace Admin\Controller;


use Admin\Logic\WorkbenchLogic;
use Common\Common\Service\AuthService;

class WorkbenchController extends BaseController
{

    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'group_id'   => I('group_id', 0, 'intval'),
                'admin_id'   => I('admin_id', 0, 'intval'),
                'query_type' => I('query_type', 0, 'intval'),
            ];

            $result = (new WorkbenchLogic())->getList($param);

            $this->paginate($result['list'], $result['cnt']);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function statsList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'group_id' => I('group_id', 0, 'intval'),
                'admin_id' => I('admin_id', 0, 'intval'),
            ];

            $logic = new WorkbenchLogic();
            $stats = $logic->getStatsList($param);
            $summary = $logic->getStatsSummary($param);

            $data = [
                'summary' => $summary,
                'stats'   => $stats,
            ];

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}