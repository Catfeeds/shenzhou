<?php
/**
 * File: AreaController.class.php
 * User: sakura
 * Date: 2017/11/1
 */

namespace Qiye\Controller;

use Common\Common\ResourcePool\RedisPool;
use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;
use Qiye\Controller\BaseController;
use Common\Common\Service\AreaService;

class AreaController extends BaseController
{

    public function getList()
    {
        try {
            $parent_id = I('parent_id', 0, 'intval');

            $area = (new AreaService())->index($parent_id);
            $this->responseList($area);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getFullList()
    {
        try {
            $cache = S('area-full-list');
            if (empty($cache)) {
                $cache = D('Area', 'Logic')->getFullList();
                S('area-full-list', $cache, ['expire' => 7*86400]);
            }
            $this->responseList($cache);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}