<?php
/**
 * Created by PhpStorm.
 * User: cjy
 * Date: 2017/12/22
 * Time: 10:35
 */

namespace Admin\Logic;

use Library\Common\Util;
use Admin\Common\ErrorCode;

class AdPositionLogic extends BaseLogic
{
    //宣传图位置
    public function getList()
    {
        $data = M('AdPosition')->select();
        return $data;
    }

    //宣传图位置详情
    public function read()
    {

        $data = M('AdPosition')->where(['id' => I('get.id')])->find();

        (empty($data)) && $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);

        $photos = M('AdPositionPhoto')->where(['position_id' => I('get.id')])->select();

        foreach ($photos as &$v) {
            $v['pic'] = $v['pic_url'] ? Util::getServerFileUrl($v['pic_url']) : '';
        }

        $data['ad_photos'] = $photos;

        return $data;
    }

    //宣传图位置修改
    public function update($request)
    {
        $positionId = I('get.id');
        $photos = $request['ad_photos'];
        $count = count($photos);
        ($count > 4) && $this->throwException(ErrorCode::DATA_WRONG, '图片数量超过4张');

        M('AdPositionPhoto')->where(['position_id' => $positionId])->delete();

        foreach ($photos as &$v) {
            $v['link'] = !empty($v['link']) ? (strpos($v['link'], 'http') === false ? 'http://' . $v['link'] : $v['link']) : '';
            $v['position_id'] = $positionId;
            $v['create_time'] = time();
        }

        M('AdPositionPhoto')->addAll($photos);

    }

}