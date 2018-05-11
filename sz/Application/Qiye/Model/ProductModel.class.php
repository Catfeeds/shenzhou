<?php
/**
 * File: ProductModel.class.php
 * User: zjz
 * Date: 2016/03/21 19:28
 */

namespace Qiye\Model;

use Library\Common\Util;

class ProductModel extends \Api\Model\ProductModel
{

    public function getCmChildrens($ids = '', $field= '*', $is_index = false, $order_by = '')
    {
        $ids = implode(',', array_filter(explode(',', $ids)));

        if (!$ids) {
            return null;
        }
        $where = [
            'item_parent' => ['in', $ids],
        ];
        $opt = [
            'where'=> $where,
        ];

        $is_index && ($opt['index'] = 'list_item_id');

        !empty($order_by) && ($opt['order'] = $order_by);

        $list = BaseModel::getInstance('cm_list_item')->getList($opt);
        return $list;
    }

}
