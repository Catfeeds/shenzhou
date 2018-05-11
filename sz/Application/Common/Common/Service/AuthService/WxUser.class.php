<?php
/**
 * File: WxUser.class.php
 * User: zjz
 * Date: 2016/12/07
 */

namespace Common\Common\Service\AuthService;

use Common\Common\CacheModel\WxUserCacheModel;

class WxUser extends Auth implements \ArrayAccess
{
    
    use AuthDataTrait;

    public function loadData($id)
    {
        $this->data = WxUserCacheModel::getOne($id, 'id,telephone,password,openid,unionid,avtive_nums,real_name,nickname,headimgurl,sex,area,product,user_type,area_ids,add_time,bind_time,province_id,city_id,area_id,is_delete');
//        $this->data = D('WxUser')->where(['id' => $id])->find();
    }
}
