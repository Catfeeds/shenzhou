<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/10/24
 * Time: 10:28
 */

namespace Admin\Controller;

use Admin\Model\BaseModel;

class ProductCategoryController extends BaseController
{

    public function index()
    {
        $this->requireAuth();

        try {
            $parent_id = I('parent_id', 0);
            $name = I('name', '');
            $where = ['parent_id' => $parent_id];
            $name && $where['name'] = ['LIKE', "%{$name}%"];
            $categories = BaseModel::getInstance('product_category')->getList([
                'where' => $where,
                'field' => 'id,name',
                'order' => 'id ASC',
            ]);
            $this->responseList($categories);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}