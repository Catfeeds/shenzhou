<?php
/**
 * File: UploadController.class.php
 * User: sakura
 * Date: 2017/11/1
 */

namespace Qiye\Controller;

use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;
use Qiye\Controller\BaseController;

class UploadController extends BaseController
{

    public function image()
    {
        try {

            $this->requireAuth();

            $upload_logic = D('File', 'Logic');

            $upload_info = $upload_logic->upload();

            $this->response($upload_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}