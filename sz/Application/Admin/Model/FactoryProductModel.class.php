<?php
/**
* 
*/
namespace Admin\Model;

use Admin\Model\BaseModel;
use Library\Common\Util;

class FactoryProductModel extends BaseModel
{
	protected $trueTableName = 'factory_product';

    public function getInfoById($id)
    {
        $field = 'fp.product_id,fp.factory_id,factory_full_name factory,linkphone factory_phone,product_category,product_xinghao,item_desc category,images,
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

}
