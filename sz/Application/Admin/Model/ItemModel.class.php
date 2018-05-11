<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/10/3
 * Time: 17:23
 */
namespace Admin\Model;

use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;

class ItemModel extends BaseModel
{
    protected $trueTableName = 'cm_list_item';
    //服务类型
    public function servicetype($id)
    {
        $limit['list_id'] = 41;
        if (!empty($id)) {
            $service_type = BaseModel::getInstance('cm_list_item')->getList([
                'where' => [
                    'list_item_id' => ['in', $id],
                    'list_id' => 41,
                ],
                'field' => 'list_item_id,item_desc',
            ]);
        }
        return $service_type;
    }

    //产品父级
    public function find_parent($id)
    {
        $cat = M('cm_list_item')->find($id);
        $parent_cat = M('cm_list_item')->find($cat['item_parent']);
        return $parent_cat['list_item_id'];
    }
}