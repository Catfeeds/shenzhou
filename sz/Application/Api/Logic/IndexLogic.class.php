<?php
/**
 * Created by PhpStorm.
 * User: 3N
 * Date: 2017/12/22
 * Time: 11:15
 */

namespace Api\Logic;

use Library\Common\Util;

class IndexLogic extends BaseLogic
{
    public function ad_position()
    {
        $key_name = I('get.key_name');
        $position = M('AdPosition')->where(['key_name' => $key_name])->find();

        $data = M('AdPositionPhoto')->where(['position_id' => $position['id']])->select();

        foreach ($data as &$v) {
            $v['pic_url'] = $v['pic_url'] ? Util::getServerFileUrl($v['pic_url']) : '';
        }
        return $data;

    }
}