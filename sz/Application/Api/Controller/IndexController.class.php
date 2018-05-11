<?php
/**
 * @User zjz
 */

namespace Api\Controller;

use Api\Controller\BaseController;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AuthService;

class IndexController extends BaseController
{
    public function ad_position()
    {
        try {
            $data = D('Index', 'Logic')->ad_position();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
