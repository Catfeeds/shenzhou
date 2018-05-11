<?php
/**
 * File: ProductModel.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 17:58
 */

namespace Api\Model;

use Library\Common\Util;

class ProductModel extends BaseModel
{

    protected $trueTableName = 'factory_product';

    public function getInfoById($id)
    {
        $field = 'fp.product_id,fp.factory_id,factory_full_name factory,linkphone factory_phone,qrcode_person,qrcode_tell,product_category,product_xinghao,item_desc category,images,
        standard_name,factory_product_brand.product_brand brand,product_thumb,
        product_content,product_normal_faults,product_attrs';
        $product_info = $this->alias('fp')->getOneOrFail([
            'where' => ['fp.product_id' => $id],
            'field' => $field,
            'join' => [
                'LEFT JOIN factory ON factory.factory_id=fp.factory_id',
                'LEFT JOIN cm_list_item ON cm_list_item.list_item_id=product_category',
                'LEFT JOIN product_standard ON product_standard.standard_id=product_guige',
                'LEFT JOIN factory_product_brand ON factory_product_brand.id=fp.product_brand'
            ],
        ]);

        $product_info['images'] = unserialize($product_info['images']);
        $images = [];
        foreach ($product_info['images'] as $image) {
            $images[] = [
                'name' => $image['url'],
                'url' => Util::getServerFileUrl($image['url']),
            ];
        }

        $product_info['images'] = $images;
        return $product_info;
    }

    public function getProductBrandByBids($bids = '', $is_index = false)
    {
        $bid_arr = explode(',', $bids);

        if (!$bid_arr) {
            return [];
        }

        $where = [
            'id' => ['in', $bid_arr],
        ];

        $opt['where'] = $where;
        if ($is_index) {
            $opt['index'] = 'id';
        }
        $list = BaseModel::getInstance('factory_product_brand')->getList($opt);

        return $list;
    }   

    public function getCmListItemByIds($ids = '', $is_index = false, $order_by = '')
    {
        $id_arr = explode(',', $ids);

        if (!$id_arr) {
            return [];
        }

        $where = [
            'list_item_id' => ['in', $id_arr],
        ];

        $opt['where'] = $where;
        
        if ($is_index) {
            $opt['index'] = 'list_item_id';
        }
        
        !empty($order_by) && ($opt['order'] = $order_by);

        $list = BaseModel::getInstance('cm_list_item')->getList($opt);

        return $list;
    }

}
