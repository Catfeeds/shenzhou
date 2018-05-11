<?php
/**
 * @User zjz
 */

namespace Api\Controller;

use Api\Controller\BaseController;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;

class CategoryController extends BaseController
{

    public function getCategory()
    {
        $parent_id = I('parent_id', 0);
        $type = I('get.type', 2); //1-安装，2-维修
        try {
            $data = D('Category', 'Logic')->getCategory($parent_id, $type);
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 产品规格及维修项价格
     */
    public function standards()
    {
        try {
            $data = D('Category', 'Logic')->standards();
//            $data = D('Category', 'Logic')->standards_test();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
