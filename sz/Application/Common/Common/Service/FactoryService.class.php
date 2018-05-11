<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/10
 * Time: 11:08
 */

namespace Common\Common\Service;

use Illuminate\Support\Arr;

class FactoryService
{
	const FACTORY_FEE_MONEY_ERROR = 1000; // 厂家账户余额少于{$money}时，发送后台系统消息


    const FACTORY_GROUP = [
        ['id' => 0,'name' => 'A组'],
        ['id' => 1,'name' => 'B组'],
        ['id' => 2,'name' => 'C组'],
        ['id' => 3,'name' => 'D组'],
        ['id' => 4,'name' => 'E组'],
        ['id' => 5,'name' => 'F组'],
        ['id' => 6,'name' => 'G组'],
    ];

    const FACTORY_INTERNAL_GROUP = [
        'id' => 0,
        'name' => '系统默认组',
    ];

    static $factory_group_id_name_map = [];

    public static function getGroupNameByGroupId($group_id)
    {
        if (!self::$factory_group_id_name_map) {
            self::$factory_group_id_name_map = Arr::pluck(self::FACTORY_GROUP, 'name', 'id');
        }

        return self::$factory_group_id_name_map[$group_id];
    }

}