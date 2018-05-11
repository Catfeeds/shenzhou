<?php

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Illuminate\Support\Arr;

class FactoryProductController extends BaseController
{

    public function getRecentProduct()
    {
        try {
            $factory = $this->requireAuthFactoryGetFactory();
            $category_ids = $factory['factory_category'];

            if ($category_ids) {
                $standard_ids = BaseModel::getInstance('product_standard')->getFieldVal([
                    'product_id' => ['IN', $category_ids],
                ], 'standard_id', true);
                $products = BaseModel::getInstance('worker_order_product')->getList([
                    'where' => [
                        'worker_order.factory_id' => $factory['factory_id'],
                        'worker_order_product.product_standard_id' => ['IN', $standard_ids],
                        'factory_product.is_delete' => 0,
                    ],
                    'join' => [
                        'INNER JOIN worker_order ON worker_order.id=worker_order_product.worker_order_id',
                        'INNER JOIN product_category ON product_category.id=worker_order_product.product_category_id',
                        'INNER JOIN factory_product ON factory_product.product_id=worker_order_product.product_id',
                    ],
                    'field' => 'worker_order_product.product_id,worker_order_product.product_brand_id,worker_order_product.product_category_id,worker_order_product.product_standard_id,worker_order_product.cp_product_mode product_mode',
                    'distinct' => true,
                    'limit' => 6,
                ]);
            } else {
                $products = [];
            }

            $this->responseList($products);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getRecentOrderCategory()
    {
        try {
            $factory = $this->requireAuthFactoryGetFactory();

            $product_category = BaseModel::getInstance('product_category')->getFieldVal([
                'id' => ['IN', $factory['factory_category'] ?: '-1']
            ], 'id,name', true);
            $factory_product_category_ids = $product_category ? array_keys($product_category) : '-1';
            $category_ids = BaseModel::getInstance('worker_order_product')->getList([
                'field' => 'product_category_id',
                'join' => [
                    'worker_order ON worker_order.id=worker_order_product.worker_order_id',
                ],
                'where' => [
                    'factory_id' => $factory['factory_id'],
                    'origin_type' => ['IN', [OrderService::ORIGIN_TYPE_FACTORY, OrderService::ORIGIN_TYPE_FACTORY_ADMIN]],
                    'product_category_id' => ['IN', $factory_product_category_ids],
                ],
                'group' => 'product_category_id',
                'order' => 'worker_order_product.id DESC',
                'limit' => 5,
            ]);

            $categories = [];
            foreach ($category_ids as $category_id) {
                $categories[] = [
                    'id' => $category_id['product_category_id'],
                    'name' => $product_category[$category_id['product_category_id']],
                ];
            }

            $this->responseList($categories);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getRecentOrderSpecification()
    {
        try {
            $factory = $this->requireAuthFactoryGetFactory();

            $product_category_id = I('product_category_id');
            if (!$product_category_id) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择所属分类');
            }

            $standard_ids = BaseModel::getInstance('product_standard')->getFieldVal([
                'product_id' => $product_category_id,
            ], 'standard_id', true);
            $standard_ids = implode(',', $standard_ids);
            $recent_standard_sql = "select max(product_standard_id) from worker_order_product  where `product_category_id`={$product_category_id} AND `product_standard_id` IN ({$standard_ids}) and worker_order_id in(select id from worker_order where `factory_id`={$factory['factory_id']} and `origin_type` IN (1,2)) group by product_standard_id order by max(id) desc limit 5";
            $product_standard_ids = BaseModel::getInstance('worker_order_product')->query($recent_standard_sql);
            $product_standard_ids[0] ? $specifications = BaseModel::getInstance('product_standard')->getList([
                'where' => [
                    'standard_id' => ['IN', $product_standard_ids[0]],
                ],
                'field' => 'standard_id id,standard_name name',
            ]) : [];
//            $specifications = BaseModel::getInstance('worker_order_product')->getList([
//                'where' => [
//                    'product_category_id' => $product_category_id,
//                    'product_standard_id' => ['IN', $standard_ids],
//                    '_string' => "select id from worker_order where worker_order.id=worker_order_product.worker_order_id and `factory_id`={$factory['factory_id']} and `origin_type` IN (1,2)",
//                ],
//                'field' => 'product_standard_id id,cp_product_standard_name name',
//                'distinct' => true,
//                'order' => 'worker_order_product.id DESC',
//                'limit' => 5,
//            ]);
            $this->responseList($specifications);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getAllCategory()
    {
        try {
            $this->requireAuth();
            if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
                $factory_id = I('factory_id');
                if (!$factory_id) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少厂家信息');
                }
                $factory = BaseModel::getInstance('factory')->getOneOrFail($factory_id);
            } else {
                $factory = $this->requireAuthFactoryGetFactory();
            }

            $factory_categories = explode(',', $factory['factory_category']);
            if ($factory_categories) {
                $categories = BaseModel::getInstance('product_category')->getList([
                    'where' => [
                        'id' => ['IN', $factory_categories],
                    ],
                    'field' => 'id,name',
                    'order' => 'id ASC',
                ]);
            } else {
                $categories = [];
            }
            return $this->responseList($categories);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getAllSpecification()
    {
        try {
            $this->requireAuth();

            $product_category_id = I('product_category_id');
            if (!$product_category_id) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择所属分类');
            }

            $standards = BaseModel::getInstance('product_standard')->getList([
                'product_id' => $product_category_id,
            ], 'standard_id,standard_name');

            $this->responseList($standards);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getAllBranch()
    {
        try {
            $this->requireAuth();
            if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
                $factory_id = I('factory_id');
                if (!$factory_id) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少需要修改的厂家信息');
                }
                $factory = BaseModel::getInstance('factory')->getOneOrFail($factory_id);
            } else {
                $factory = $this->requireAuthFactoryGetFactory();
            }

            $branches = BaseModel::getInstance('factory_product_brand')->getList([
                'where' => [
                    'factory_id' => $factory['factory_id'],
                    'is_delete' => 0,
                ],
                'field' => 'id,product_brand name'
            ]);
            $this->responseList($branches);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getProductMode()
    {
        try {
            $this->requireAuth();
            if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
                $factory_id = I('factory_id');
                if (!$factory_id) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少需要修改的厂家信息');
                }
                $factory = BaseModel::getInstance('factory')->getOneOrFail($factory_id);
            } else {
                $factory = $this->requireAuthFactoryGetFactory();
            }

            $product_category_id = I('product_category_id');
            !$product_category_id && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品分类');
            $factory_product_branch_id = I('factory_product_branch_id');
            !$factory_product_branch_id && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品品牌');
            $factory_product_standard_id = I('factory_product_standard_id');
            !$factory_product_standard_id && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品规格');

            $modes = BaseModel::getInstance('factory_product')->getList([
                'where' => [
                    'factory_id' => $factory['factory_id'],
                    'product_category' => $product_category_id,
                    'product_guige' => $factory_product_standard_id,
                    'product_brand' => $factory_product_branch_id,
                    'is_delete' => 0,
                ],
                'field' => 'product_id,product_xinghao mode',
            ]);
            $this->responseList($modes);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getCategoryFault()
    {
        try {
            $product_category_id = I('product_category_id');
            $product_fault_labels = BaseModel::getInstance('product_fault_label')->getList([
                'product_id' => $product_category_id
            ], 'id,label_name name');
            $this->responseList($product_fault_labels);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }
    
}