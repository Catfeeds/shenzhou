<?php
/**
 * File: JPushController.class.php
 * User: sakura
 * Date: 2017/11/2
 */

namespace Qiye\Controller;

use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;
use Qiye\Controller\BaseController;


class JPushController extends BaseController
{

    public function edit()
    {
        try {

            $user_id = $this->requireAuth();

            $jpush_logic = D('JPush', 'Logic');

            $jpush_logic->setParam('jpush_id', I('jpush_id'));
            $jpush_logic->setParam('user_id', $user_id);

            $upload_info = $jpush_logic->edit();

            $this->response($upload_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}