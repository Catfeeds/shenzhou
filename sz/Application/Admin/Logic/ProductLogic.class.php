<?php
/**
 * File: ProductLogic.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 17:11
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Admin\Logic\BaseLogic;
use Illuminate\Support\Arr;

class ProductLogic extends BaseLogic
{

    public function getProductExendInfoListByIds($product_ids, $is_index = false)
    {
        if (!is_array($product_ids)) {
            $product_ids = explode(',', $product_ids);
        }

        $product_ids = implode(',', array_unique(array_filter($product_ids)));
        $pro_list = $product_ids ? BaseModel::getInstance('factory_product')->getList([
                'where' => [
                    'product_id' => ['in', $product_ids],
                ],
                'field' => 'product_id,product_guige,product_brand,product_category,product_xinghao',
                'index' => $is_index ? 'product_id' : ''
            ]) : [];

        $guige_ids = $cate_ids = $brand_ids = [];
        foreach ($pro_list as $k => $v) {
            $guige_ids[$v['product_guige']] = $v['product_guige'];
            $brand_ids[$v['product_brand']] = $v['product_brand'];
            $cate_ids[$v['product_category']] = $v['product_category'];
        }

        $cate_ids = implode(',', array_filter($cate_ids));
        $cates = $cate_ids ? BaseModel::getInstance('cm_list_item')->getList([
                'where' => ['list_item_id' => ['IN', $cate_ids]],
                'index' => 'list_item_id',
            ]) : [];

        $brand_ids = implode(',', array_filter($brand_ids));
        $brands = $brand_ids ? BaseModel::getInstance('factory_product_brand')->getList([
                'where' => ['id' => ['IN', $brand_ids]],
                'index' => 'id',
            ]) : [];

        $guige_ids = implode(',', array_filter($guige_ids));
        $guiges = $guige_ids ? BaseModel::getInstance('product_standard')->getList([
                'where' => ['standard_id' => ['IN', $guige_ids]],
                'index' => 'standard_id',
            ]) : [];

        foreach ($pro_list as $k => $v) {
            $v['product_guige_desc'] = $guiges[$v['product_guige']]['standard_name'];
            $v['product_brand_desc'] = $brands[$v['product_brand']]['product_brand'];
            $v['product_category_desc'] = $cates[$v['product_category']]['item_desc'];
            $pro_list[$k] = $v;
        }
        
        return $pro_list;
    }

    public function loadProductExtraInfo(&$factory_products, $unique = '')
    {
        $product_categories = [];
        $product_guides = [];
        $product_brands = [];
        $product_id_product_map = [];
        $b = [];
        foreach ($factory_products as $factory_product) {
        // foreach ($factory_products as &$factory_product) {
            // $key = $unique ? $factory_product['product_id'] . $factory_product[$unique] : $factory_product['product_id'];
            // $product_id_product_map[$key] = &$factory_product;

            $product_categories[] = $factory_product['product_category'];
            $product_guides[] = $factory_product['product_guige'];
            $product_brands[] = $factory_product['product_brand'];
        }

        $product_category_id_name_map = BaseModel::getInstance('cm_list_item')->getFieldVal([
            'list_item_id' => ['IN', $product_categories]
        ], 'list_item_id,item_desc', true);
        $product_standard_id_name_map = BaseModel::getInstance('product_standard')->getFieldVal([
            'standard_id' => ['IN', $product_guides],
        ], 'standard_id,standard_name', true);
        $product_branch_id_name_map = BaseModel::getInstance('factory_product_brand')->getFieldVal([
            'id' => ['IN', $product_brands],
        ], 'id,product_brand');

        foreach ($factory_products as &$item) {
            // $key = $unique ? $item['product_id'] . $item[$unique] : $item['product_id'];
            // $product_id_product_map[$key]['category'] = $product_category_id_name_map[$item['product_category']];
            // $product_id_product_map[$key]['standard'] = $product_standard_id_name_map[$item['product_guige']];
            // $product_id_product_map[$key]['branch'] = $product_branch_id_name_map[$item['product_brand']];
            $item['category'] = $product_category_id_name_map[$item['product_category']];
            $item['standard'] = $product_standard_id_name_map[$item['product_guige']];
            $item['branch'] = $product_branch_id_name_map[$item['product_brand']];
        }
    }

    public function factoryCategory($factory_id)
    {
        $categories = BaseModel::getInstance('factory')
            ->getFieldVal($factory_id, 'factory_category');

        if (!$categories) {
            return [];
        }

        $product_categories = BaseModel::getInstance('cm_list_item')
            ->getList([
                'where' => ['list_item_id' => ['IN', $categories]],
                'field' => 'list_item_id id,item_desc name'
            ]);

        return $product_categories;
    }

    public function standard($category_id)
    {
        $standards = BaseModel::getInstance('product_standard')
            ->getList([
                'where' => [
                    'product_id' => $category_id,
                ],
                'field' => 'standard_id id,standard_name name',
            ]);
        return $standards;
    }


    // 
    public function factoryBrand($factory_id = 0, $category_id)
    {
        $where = [
                'factory_id' => $factory_id,
                'is_show' => 0,
            ];
        if (!$category_id) {
            unset($where['product_cat_id']);
        }
        
        $standards = BaseModel::getInstance('factory_product_brand')
            ->getList([
                'where' => $where,
                'field' => 'id,product_cat_id category_id,product_brand brand_name',
                // 'limit' => 10,
            ]);
        return $standards;
    }

    public function getProductFaultByCategoryIds($category_ids)
    {
        return BaseModel::getInstance('product_miscellaneous')->getList([
            'where' => [
                'product_id' => ['IN', $category_ids],
            ],
            'field' => 'product_id,product_faults',
            'index' => 'product_id',
        ]);

    }

    function getProductCategoryInPrice($category_id, $stantard_id)
    {
        $fault_ids = BaseModel::getInstance('product_miscellaneous')->getFieldVal([
            'product_id' => $category_id,
        ], 'product_faults');

        if (!$fault_ids) {
            $product_category_name = BaseModel::getInstance('product_category')->getFieldVal($category_id, 'name');
            $this->throwException(ErrorCode::PRODUCT_CATEGORY_NO_MAINTENANCE_ITEM, ['product_category_name' => $product_category_name]);
        }

        $in_price = BaseModel::getInstance('product_fault')->getFieldVal([
            'alias' => 'PF',
            'join' => 'LEFT JOIN product_fault_price PFP ON PF.id = PFP.fault_id',
            'where' => [
                'PF.id' => ['IN', $fault_ids],
                'PFP.standard_id' => $stantard_id,
                'PF.fault_type' => 1,
            ],
            'order' => 'PF.sort ASC,PFP.factory_in_price ASC',
        ], 'factory_in_price');

        return $in_price;
    }

    public function getProductCategoryIdNameMapById($product_category_ids)
    {
        $product_category_id_name_map = $product_category_ids ? BaseModel::getInstance('product_category')->getList([
            'where' => ['id' => ['IN', $product_category_ids]],
            'field' => 'id,name',
            'index' => 'id'
        ]) : [];

        return $product_category_id_name_map;
    }

    public function getProductStandardIdNameMapById($product_standard_ids)
    {
        $product_standard_id_name_map = $product_standard_ids ? BaseModel::getInstance('product_standard')->getList([
            'where' => ['id' => ['IN', $product_standard_ids]],
            'field' => 'standard_id id,standard_name name',
            'index' => 'id'
        ]) : [];

        return $product_standard_id_name_map;
    }

    public function getProductBranchIdNameMapById($product_branch_ids)
    {
        $product_branch_id_name_map = $product_branch_ids ? BaseModel::getInstance('factory_product_brand')->getList([
            'where' => ['id' => ['In', $product_branch_ids]],
            'field' => 'id,product_brand name',
            'index' => 'id'
        ]) : [];

        return $product_branch_id_name_map;
    }

    public function getFactoryFaultPriceByCategoryIdAndStandardId($factory_id, $category_id, $standard_id, $fault_ids = '', $fault_type = null)
    {
        $where = [
            'fp.product_id' => $category_id,
            'fp.standard_id' => $standard_id,
            'fp.factory_id' => $factory_id,
        ];

        // 服务项类型 0维修 2维护 1安装
        $fault_type !== null && $where['product_fault.fault_type'] = intval($fault_type);

        $fault_ids && $where['fp.fault_id'] = ['IN', $fault_ids];
        $fault_price_info = BaseModel::getInstance('factory_product_fault_price')->getList([
            'alias' => 'fp',
            'where' => $where,
            'join'  => 'INNER JOIN product_fault ON product_fault.id=fp.fault_id',
            'field' => 'product_fault.fault_name,fp.factory_in_price,fp.factory_out_price,product_fault.fault_desc,product_fault.fault_type,fp.worker_in_price,fp.worker_out_price',
            'order' => 'product_fault.fault_type desc,product_fault.sort asc',
            'group' => 'fp.fault_id',
        ]);
        
        if (!$fault_price_info) {
            unset($where['fp.factory_id']);
            $fault_price_info = BaseModel::getInstance('product_fault_price')->getList([
                'alias' => 'fp',
                'where' => $where,
                'join' => 'INNER JOIN product_fault ON product_fault.id=fp.fault_id',
                'field' => 'product_fault.fault_name,fp.factory_in_price,fp.factory_out_price,product_fault.fault_desc,product_fault.fault_type,fp.worker_in_price,fp.worker_out_price',
                'order' => 'product_fault.fault_type desc,product_fault.sort desc',
                'group' => 'fp.fault_id',
            ]);
        }

        return $fault_price_info;
    }


    public function loadProductCpDetailInfo(&$products)
    {
        $product_category_id_name_map = $this->getProductCategoryIdNameMapById(Arr::pluck($products, 'product_category_id'));
        $product_standard_id_name_map = $this->getProductStandardIdNameMapById(Arr::pluck($products, 'product_standard_id'));
        $product_branch_id_name_map = $this->getProductBranchIdNameMapById(Arr::pluck($products, 'product_brand_id'));

        foreach ($products as $key => $product) {
            $products[$key]['cp_category_name'] = $product_category_id_name_map[$product['product_category_id']]['name'] ?? '';
            $products[$key]['cp_product_brand_name'] = $product_branch_id_name_map[$product['product_brand_id']]['name'] ?? '';
            $products[$key]['cp_product_standard_name'] = $product_standard_id_name_map[$product['product_standard_id']]['name'] ?? '';
        }
    }

    public function getFactoryProductIdByInfo($factory_id, $product_category_id, $product_standard_id, $product_brand_id, $product_mode)
    {
        if (!$factory_id || !$product_category_id || !$product_standard_id || !$product_brand_id || !$product_mode) {
            return '0';
        }
        $product_id = BaseModel::getInstance('factory_product')->getFieldVal([
            'where' => [
                'factory_id' => $factory_id,
                'product_xinghao' => $product_mode,
                'product_category' => $product_category_id,
                'product_guige' => $product_standard_id,
                'product_brand' => $product_brand_id,
            ],
        ], 'product_id');
        return $product_id;
    }


}
