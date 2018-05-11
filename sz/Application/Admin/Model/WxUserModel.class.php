<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/23
 * Time: 18:31
 */


namespace Admin\Model;

class WxUserModel extends BaseModel
{
    protected $trueTableName = 'wx_user';

    public function getUserByPhoneOrCreate($phone, $user_info)
    {
        $user_id = $this->getFieldVal(['telephone' => $phone], 'id');
        if (!$user_id) {
            $user_id = $this->insert([
                'telephone' => $phone,
                'real_name' => $user_info['real_name'],
                'area_ids' => "{$user_info['province_id']},{$user_info['city_id']},{$user_info['area_id']}",
                'province_id' => $user_info['province_id'],
                'city_id' => $user_info['city_id'],
                'area_id' => $user_info['area_id'],
                'area' => $user_info['cp_area_names'],
                'bind_time' => NOW_TIME,
                'user_type' => 0,
                'add_time' => NOW_TIME,
            ]);
        }

        return $user_id;
    }
}