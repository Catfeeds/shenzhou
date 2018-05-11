<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2018/2/12
 * Time: 15:14
 */

namespace Common\Common\CacheModel;

use Common\Common\Model\BaseModel;

class FrontendRoutingCacheModel extends CacheModel
{
    public static function getTableName()
    {
        return 'frontend_routing';
    }

    public static function remove($id)
    {
        self::getCache()->delete(self::getCacheKey($id));
        self::getModel()->update($id, ['is_delete' => NOW_TIME]);

        $table = 'rel_backend_frontend_routing';
        BaseModel::getInstance($table)->remove([
            'frontend_routing_id' => $id,
        ]);
        self::removeRelation($id, $table);
    }

}