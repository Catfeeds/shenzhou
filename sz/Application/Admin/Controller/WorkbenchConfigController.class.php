<?php
/**
 * File: WorkbenchConfigController.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/12
 */

namespace Admin\Controller;


use Admin\Logic\WorkbenchConfigLogic;
use Common\Common\Service\AuthService;

class WorkbenchConfigController extends BaseController
{

    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $result = (new WorkbenchConfigLogic())->getList();

            $this->response($result);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function edit()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = I('');

            M()->startTrans();
            (new WorkbenchConfigLogic())->edit($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


}