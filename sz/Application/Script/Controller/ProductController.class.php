<?php
/**
 * File: ProductController.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 17:08
 */

namespace Script\Controller;

use Script\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Common\Util;

class ProductController extends BaseController
{
    public function info()
    {
        $id = I('get.id', 0);
        try {

            $product_info = (new \Admin\Model\FactoryProductModel())->getInfoById($id);
            $product_info['product_title'] = $product_info['standard_name'] . $product_info['brand'] .
                $product_info['category'] . $product_info['product_xinghao'];

            $product_info['product_attrs'] = \GuzzleHttp\json_decode($product_info['product_attrs'] ? $product_info['product_attrs'] : '[]');
            if ($product_info['product_thumb']) {
                $product_info['product_thumb'] = Util::getServerFileUrl($product_info['product_thumb']);
            } else {
                $product_thumb = BaseModel::getInstance('cm_list_item')
                    ->getFieldVal($product_info['product_category'], 'item_thumb');
                $product_info['product_thumb'] = Util::getServerFileUrl($product_thumb);
            }

            $product_info['product_content'] = Util::buildImgTagSource($product_info['product_content']);
            $product_info['product_normal_faults'] = Util::buildImgTagSource($product_info['product_normal_faults']);

            $this->response($product_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    //同步wx_user_product - code字段
    public function codeUpdate()
    {
        $time1 = time();

        $where['code'] = ['eq', ''];
        $where['md5code'] = ['neq', ''];


        $params = [
            'field' => 'id,code,md5code',
            'where' => $where,
        ];

        $count = D('WxUserProduct')->getNum($params);

        $num = ceil($count / 100);

        for ($i = 0; $i < $num; $i++) {
            $params ['limit'] = 100;
            $data = D('WxUserProduct')->getList($params);

            foreach ($data as &$v) {

                $md5code = $v['md5code'];
                $first = mb_substr($md5code, 0, 1);

                $old_yima_code_index = 'old_yima_code_index_' . $first;

                $code = BaseModel::getInstance($old_yima_code_index)->getFieldVal(['md5code' => $md5code], 'code');

                D('WxUserProduct')->update($v['id'], ['code' => $code]);
            }

        }

        $time2 = time();
        echo "allCount:" . $count;
        echo "\nspendTime:" . ($time2 - $time1);
        die;

    }

}
