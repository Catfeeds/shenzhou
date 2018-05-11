<?php
/**
 * File: ProductController.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 17:08
 */

namespace Api\Controller;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Repositories\Events\ScanYimaQrcodeEvent;
use Common\Common\Repositories\Events\WxUserRegisterProductEvent;
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

            // $product_info['product_content'] = Util::buildImgTagSource($product_info['product_content']);
            // $product_info['product_normal_faults'] = Util::buildImgTagSource($product_info['product_normal_faults']);
            $product_info['product_content'] = Util::buildImgTagSource(htmlspecialchars_decode($product_info['product_content']));
            $product_info['product_normal_faults'] = Util::buildImgTagSource(htmlspecialchars_decode($product_info['product_normal_faults']));

            $this->response($product_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function register()
    {
        $this->requireAuth();
        AuthService::getMOdel() != 'wxuser'
        &&  $this->fail(ErrorCode::REGISTER_PRODUCT_NOT_POWER);

        $code = I('post.product_code', '');
        // $md5 = D('WorkerOrderDetail')->codeToMd5Code($code);
        // $data = D('FactoryExcel')->getExcelDataByMyPidOrFail($md5);
        // var_dump($data);die;
        $logic = D('Product', 'Logic');
        $data = [];
        $is_subscribe = false;
        try {
            if (AuthService::getAuthModel()->openid && D('WeChatUser', 'Logic')->isSubscribe(AuthService::getAuthModel()->openid)) {
                $is_subscribe = true;
            }
            $post_data = I('post.');
//            $post_data['phone'] = AuthService::getAuthModel()['telephone'];
            $data = $logic->registerMyProductByCode($code, $post_data);
            event(new WxUserRegisterProductEvent($code, $post_data));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

        // $factory = BaseModel::getInstance('factory')->getOne($data['factory_id'], 'is_show_yima_ad');
        $data['active_json'] = json_decode($data['active_json'], true);
        $return = [
            'factory_id' => $data['factory_id'],
            'is_show_yima_ad' => $data['is_show_yima_ad'],
            'active_time' => (string)$data['active_time'],
            'zhibao_time' => (string)$data['zhibao_time'],
            'er_zhibao_time' => (string)$data['active_json']['active_reward_moth'],
            'end_time' => (string)get_limit_date($data['active_time'], $data['zhibao_time'] + $data['active_json']['active_reward_moth']),
            'register_time' => (string)NOW_TIME,
            'is_subscribe' => $is_subscribe,
        ];
        $this->response($return);
    }

    public function myProducts()
    {
        try {
            $wx_user_id = $this->requireAuth();
            $where = [
                'WUP.wx_user_id' => $wx_user_id
            ];
            $opt = [
                'alias' => 'WUP',
                'join'  => 'LEFT JOIN factory_product FP ON WUP.wx_product_id = FP.product_id LEFT JOIN factory F ON FP.factory_id = F.factory_id',
                'where' => $where,
            ];

            $model = BaseModel::getInstance('wx_user_product');
            $count = $model->getNum($opt);

            $opt['field'] = 'WUP.id,WUP.code,WUP.md5code,FP.product_id,FP.product_category as product_cate_id,FP.product_xinghao,FP.product_status,FP.is_delete,FP.product_thumb,FP.product_brand,F.factory_id,F.factory_full_name as factory,F.factory_short_name';
            $opt['limit'] = $this->page();
            $opt['order'] = 'WUP.id DESC';
            $list = $count ?
                $model->getList($opt):
                [];

            $category_ids = $product_brands = $md5codes = $code = $md5_to_code = [];

            foreach ($list as $k => $v) {
                $category_ids[$v['product_cate_id']] = $v['product_cate_id'];
                $product_brands[$v['product_brand']] = $v['product_brand'];
                if (!$v['code']) {
                    $md5codes[$v['md5code']] = $v['md5code'];
                } else {
                    $code[$v['code']] = $v['code'];
                }
            }

            $code = implode(',', $code);
            $md5codes = implode(',', $md5codes);
            $category_ids = implode(',', $category_ids);
            $product_brands = D('Product')->getProductBrandByBids(implode(',', $product_brands), true);

            $details = [];
            if ($code) {
                $details = (new \Api\Model\YimaModel())->getYimaInfosByCodes($code, true);
                // foreach ((new \Api\Model\YimaModel())->getYimaInfosByCodes($code) as $k => $v) {
                // 	$details[$v['code']] = $v;
                // }
            }

            $field = 'code,water,factory_product_qrcode_id,factory_id,product_id,shengchan_time,chuchang_time,zhibao_time,remarks,diy_remarks,CAST(active_json as CHAR) as active_json,member_id,user_name,user_tel,CAST(user_address as CHAR) as user_address,active_time,register_time,saomiao,is_disable';
            $products = D('WorkerOrderDetail')->getExcelDatasByMd5Codes($md5codes, $field);
            foreach ($products as $k => $v) {
                $details[$v['md5code']] = $v;

                try {
                    $v['md5code'] && $model->update(['md5code' => $v['md5code']], ['code' => $v['code']]);
                } catch (\Exception $e) {

                }
            }

            $categorys = $category_ids?
                BaseModel::getInstance('cm_list_item')->getList([
                    'where' => [
                        'list_item_id' => ['in', $category_ids]
                    ],
                    'index' => 'list_item_id',
                ]):
                [];

            $cate_id_arr = [];
            foreach ($list as $k => $v) {
                $d_data = $details[$v['code']] ? $details[$v['code']] : $details[$v['md5code']];

                $v['is_active'] = '1';
                $v['active_time'] = $d_data['active_time'];
                $v['zhibao_time'] = $d_data['zhibao_time'];
                $v['chuchuang_time'] = $d_data['chuchuang_time'];
                $v['scan_times'] = $d_data['saomiao'];
                $v['register_time'] = $d_data['register_time'];

                $v['active_end_time'] = (string)get_limit_date($d_data['active_time'], $d_data['zhibao_time'] + $d_data['active_json']['active_reward_moth']);

                if ($v['active_end_time'] >=  NOW_TIME || !$d_data['zhibao_time'] || !$d_data['code']) {
                    $v['is_in'] = '1';
                    $v['is_out'] = '0';
                } else {
                    $v['is_in'] = '0';
                    $v['is_out'] = '1';
                }
                // $v['zhibao_end_time'] = date('Y-m-d', $v['zhibao_end_time']);


                $v['product_cate_name'] = $categorys[$v['product_cate_id']]['item_desc'];
                $v['product_brand_name'] = $product_brands[$v['product_brand']]['product_brand'];
                if ($v['product_thumb']) {
                    $v['product_thumb'] = $v['product_thumb'];
                } else {
                    $cate_id_arr[$v['product_cate_id']] = $v['product_cate_id'];
                }

                $v['product_code'] = $d_data['code'];
                // $v['product_md5_code'] = $d_data['md5code'];

                $v['product_md5_code'] = encryptYimaCode($d_data['code']);

                unset($v['md5code']);
                $list[$k] = $v;
            }

            $cate_product_thumb = [];
            if (count($cate_id_arr)) {
                $opt = [
                    'where' => [
                        'list_item_id' => ['in', implode(',', array_unique($cate_id_arr))],
                    ],
                    'field' => 'list_item_id,item_thumb',
                    'index' => 'list_item_id',
                ];
                $cate_product_thumb = BaseModel::getInstance('cm_list_item')->getList($opt);
            }

            foreach ($list as $k => $v) {
                $this_product_thumb = $v['product_thumb'] ? $v['product_thumb'] : $cate_product_thumb[$v['product_cate_id']]['item_thumb'];

                $v['product_thumb'] = $this_product_thumb ? Util::getServerFileUrl($this_product_thumb) : '';
                $list[$k] = $v;
            }

            $this->paginate($list, $count);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function products()
    {
        $modle = D('FactoryProduct');

        $list = json_decode('[
      {
        "product_id": "136",
        "product_cate_id": "659004504",
        "product_xinghao": "DP-S01",
        "product_status": "0",
        "product_cate_name": null
      },
      {
        "product_id": "136",
        "product_cate_id": "659004504",
        "product_xinghao": "DP-S01",
        "product_status": "0",
        "product_cate_name": null
      },
      {
        "product_id": "174",
        "product_cate_id": "659004506",
        "product_xinghao": "49E6000",
        "product_status": "0",
        "product_cate_name": "智能云电视"
      }
    ]', true);
        $count = count($list);
        $this->paginate($list, $count);

    }

    public function faults()
    {
        $id = I('get.id');
        $data = [];
        try {
            $model = D('FactoryProduct');
            $data = $model->getOneOrFail($id, 'product_category');
            $this->cateFaults($data['product_category']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function cateFaults($id = 0)
    {
        $id = $id ? $id : I('get.id');

        $top_parent = D('FactoryProduct')->getTopProductCateByCid($id);

        if (!$top_parent['list_item_id']) {
            $this->paginate();
        }

        $where = [
            'product_id' => $top_parent['list_item_id'],
        ];

        $model = BaseModel::getInstance('product_fault');
        $opt = [
            'where' => $where,
            'limit' => $this->page(),
        ];

        $count = $model->getNum($where);
        $list = $count ?
            $model->getList($opt):
            [];
        $this->paginate($list, $count);
    }

    public function faultsLabels()
    {
        $id = I('get.id');
        $data = [];
        try {
            $model = D('FactoryProduct');
            $data = $model->getOneOrFail($id, 'product_category');
            $this->cateFaultLabels($data['product_category']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function cateFaultLabels($id = 0)
    {
        $id = $id ? $id : I('get.id');

        $top_parent = D('FactoryProduct')->getTopProductCateByCid($id);

        // if (!$top_parent['list_item_id']) {
        // 	$this->paginate();
        // }

        $ids = implode(',', array_filter(explode(',', $id.','.$top_parent['list_item_id'])));

        $where = [
            'product_id' => ['in', $ids],
        ];

        if (!$id) {
            $this->paginate();
        }

        $model = BaseModel::getInstance('product_fault_label');
        $opt = [
            'where' => $where,
            'limit' => $this->page(),
            'order' => 'sort ASC',
            'field' => 'id,product_id,label_name,label_faults',
        ];

        $count = $model->getNum($where);
        $list = $count ?
            $model->getList($opt):
            [];
        $this->paginate($list, $count);
    }

    public function getDetailByCode()
    {
        $code = I('code');
        try {
            if (!$code) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $product_info = D('Product', 'Logic')->getDetailByCode($code);
            event(new ScanYimaQrcodeEvent($product_info['code']));
            $this->response($product_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function detail()
    {
        $type = I('type', 1);
        $code = I('product_code');
        try {
            if (!$code) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            // type：1 md5码，2 产品码（未md5加密）
            // if ($type == 2) {
            //              // $code = D('WorkerOrderDetail')->codeToMd5Code(strtoupper($code));
            //              $code = encryptYimaCode($code);
            //          }

            $product_info = D('Product', 'Logic')->detail($code, $type);
            $product_info['factory_product_qrcode_id'] && event(new ScanYimaQrcodeEvent($product_info['product_code']));
            $this->response($product_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function insepect()
    {
        $code = I('product_code');
        try {
            $product_info = D('Product', 'Logic')->insepect($code);
            $this->response($product_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }
}
