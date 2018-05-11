<?php
/**
 * File: AreaController.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 16:40
 */

namespace Admin\Controller;

use Common\Common\Service\AreaService;

class AreaController extends BaseController
{

    public function group()
    {
        $parent_id = I('parent_id');
        try {
            $city_group = AreaService::group($parent_id);
            $this->responseList($city_group);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function index()
    {
        $parent_id = I('parent_id');
        try {
            $cities = AreaService::index($parent_id);
            $this->responseList($cities);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


}
