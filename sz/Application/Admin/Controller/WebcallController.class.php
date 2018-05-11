<?php
/**
 * File: WebcallController.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/18
 */

namespace Admin\Controller;

use Admin\Logic\WebcallLogic;
use Common\Common\Service\AuthService;
use Library\Common\WebCall;

class WebcallController extends BaseController
{

    public function create()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'user_type'       => I('user_type', 0, 'intval'),
                'user_id'         => I('user_id', 0, 'intval'),
                'worker_order_id' => I('worker_order_id', 0, 'intval'),
            ];
            $data = (new WebcallLogic())->adminLink2User($param);

            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 通话结束
     */
    public function hangup()
    {
        try {

            $param = WebCall::getEventParam();
            (new WebcallLogic())->hangup($param);

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}