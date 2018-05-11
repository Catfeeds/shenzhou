<?php
/**
 * File: AreaLogic.class.php
 * User: sakura
 * Date: 2017/11/6
 */

namespace Qiye\Logic;

use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Library\Common\Util;
use Qiye\Common\ErrorCode;
use Qiye\Logic\BaseLogic;
use Qiye\Model\BaseModel;
use Library\Crypt\AuthCode;

class AreaLogic extends BaseLogic
{

    public function getFullList($parent_id=0, $level=1)
    {
        if ($level > 3) {
            return null;
        }
        $area = (new AreaService())->index($parent_id);
        if (empty($area)) {
            return null;
        }
        $level++;
        foreach ($area as $key => $val) {
            $parent_id = $val['id'];
            $children = $this->getFullList($parent_id, $level);
            $val['child'] = $children;
            $area[$key] = $val;
        }
        return $area;
    }

}