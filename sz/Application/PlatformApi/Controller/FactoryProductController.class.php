<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/22
 * Time: 11:57
 */

namespace PlatformApi\Controller;


use Common\Common\Service\AuthService;
use Library\Common\Util;
use PlatformApi\Model\BaseModel;

class FactoryProductController extends BaseController
{

    public function getCategory()
    {
        try {
            $this->platFormRequireAuth(AuthService::ROLE_FACTORY);

            $list = AuthService::getAuthModel()->factory_category ? BaseModel::getInstance('product_category')->getList([
                'field' => 'id as code,name,parent_id as parent_code,thumb',
                'where' => [
                    'id' => ['in', AuthService::getAuthModel()->factory_category],
                ],
            ]) : [];

            $parent_ids = array_column($list, 'parent_code');
            $parent_ids = implode(',', $parent_ids);

            $p_list = $parent_ids ? BaseModel::getInstance('product_category')->getList([
                'field' => 'id as code,name,parent_id as parent_code,thumb',
                'where' => [
                    'id' => ['in', $parent_ids],
                ],
                'index' => 'code',
            ]) : [];

            foreach ($list as $k => &$v) {
                $v['code'] = tableIdEncrypt('product_category', $v['code']);

                $v['thumb'] = $v['thumb'] ? Util::getServerFileUrl($v['thumb']) : '';
                $v['parent_name'] = $p_list[$v['parent_code']]['name'];
                $v['parent_code'] = tableIdEncrypt('product_category', $v['parent_code']);
            }

            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getStandard()
    {
        try {
            $this->platFormRequireAuth(AuthService::ROLE_FACTORY);

            $list = [];
            if (AuthService::getAuthModel()->factory_category) {
                $cates = BaseModel::getInstance('product_category')->getList([
                    'field' => 'id as code,name',
                    'where' => [
                        'id' => ['in', AuthService::getAuthModel()->factory_category],
                    ],
                    'index' => 'code',
                ]);

                $list = BaseModel::getInstance('product_standard')->getList([
                    'field' => 'standard_id as code,standard_name as name,product_id as category_code',
                    'where' => [
                        'product_id' => ['in', AuthService::getAuthModel()->factory_category],
                    ],
                ]);

                foreach ($list as $k => &$v) {
                    $v['code'] = tableIdEncrypt('product_standard', $v['code']);

                    $v['category_name'] = $cates[$v['category_code']]['name'];
                    $v['category_code'] = tableIdEncrypt('product_category', $v['category_code']);
                }
            }

            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function getBrand()
    {
        try {
            $factory_id = $this->platFormRequireAuth(AuthService::ROLE_FACTORY);
            $list = BaseModel::getInstance('factory_product_brand')->getList([
                'field' => 'id as code,product_brand as name',
                'where' => [
                    'factory_id' => $factory_id,
                ],
                'order' => 'id asc',
                'index' => 'name',
            ]);

            foreach ($list as $k => &$v) {
                $v['code'] = tableIdEncrypt('factory_product_brand', $v['code']);
            }

            $this->responseList(array_values($list));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getProducte()
    {
        try{
            $factory_id = $this->platFormRequireAuth(AuthService::ROLE_FACTORY);
            $list = [];

            if (AuthService::getAuthModel()->factory_category) {

                $cates = BaseModel::getInstance('product_category')->getList([
                    'field' => 'id as code,name',
                    'where' => [
                        'id' => ['in', AuthService::getAuthModel()->factory_category],
                    ],
                    'index' => 'code',
                ]);

                $list = BaseModel::getInstance('factory_product')->getList([
                    'field' => 'product_id as code,product_xinghao as name,product_category as category_code,product_guige as standard_code,product_brand as brand_code',
                    'where' => [
                        'product_category' => ['in', AuthService::getAuthModel()->factory_category],
                        'factory_id' => $factory_id,
                    ],
                ]);

                $standard_ids = $brand_ids = [];
                foreach ($list as $v) {
                    $standard_ids[$v['standard_code']] = $v['standard_code'];
                    $brand_ids[$v['brand_code']] = $v['brand_code'];
                }
                $standard_ids = implode(',', $standard_ids);
                $brand_ids = implode(',', $brand_ids);

                $standards = $standard_ids ? BaseModel::getInstance('product_standard')->getList([
                    'field' => 'standard_id as code,standard_name as name',
                    'where' => [
                        'standard_id' => ['in', $standard_ids],
                    ],
                    'index' => 'code',
                ]) : [];

                $brands = $brand_ids ? BaseModel::getInstance('factory_product_brand')->getList([
                    'field' => 'id as code,product_brand as name',
                    'where' => [
                        'id' => ['in', $brand_ids],
                    ],
                    'index' => 'code',
                ]) : [];

                foreach ($list as $k => &$v) {
                    $v['code'] = tableIdEncrypt('factory_product', $v['code']);

                    $v['category_name'] = $cates[$v['category_code']]['name'];
                    $v['category_code'] = tableIdEncrypt('product_category', $v['category_code']);

                    $v['standard_name'] = $standards[$v['standard_code']]['name'];
                    $v['standard_code'] = tableIdEncrypt('product_standard', $v['standard_code']);

                    $v['brand_name'] = $brands[$v['brand_code']]['name'];
                    $v['brand_code'] = tableIdEncrypt('factory_product_brand', $v['brand_code']);
                }
            }

            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
