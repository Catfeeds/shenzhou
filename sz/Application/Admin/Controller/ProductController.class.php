<?php
/**
 * File: ProductController.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 17:08
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\ProductLogic;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Illuminate\Support\Arr;
use Library\Common\Util;
use GuzzleHttp;

class ProductController extends BaseController
{

    public function info()
    {
        $id = I('get.id', 0);
        try {
            
            $product_info = D('FactoryProduct')->getInfoById($id);

            $product_info['product_title'] = $product_info['standard_name'] . $product_info['brand'] .
                $product_info['category'] . $product_info['product_xinghao'];

            $product_info['product_attrs'] = json_decode($product_info['product_attrs'] ? $product_info['product_attrs'] : '[]');
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

    public function yimaTypes()
    {
        try {
            $index = BaseModel::getInstance('yima_qr_category_index')->getList();
            $datas = BaseModel::getInstance('yima_qr_category')->getList(['field' => '*', 'index' => 'id']);

            $list = [];
            foreach ($index as $v) {
                if (!$datas[$v['master_id']]) {
                    continue;
                }
                $guige = $list[$v['master_id']]['next'];
                $guige[] = $datas[$v['release_id']];

                $list[$v['master_id']] = $datas[$v['master_id']];
                $list[$v['master_id']]['next'] = array_filter($guige) ? $guige : null;
            }
            $this->response(array_values($list));

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function lists()
    {
        try {
            // 1 分类， 2 规格， 3 品牌
            $factory_id = I('get.factory_id', 0);
            $category = I('get.category', 0);
            $guige = I('get.guige', 0);
            $brand = I('get.brand', 0);
            $xinghao = I('get.xinghao', '');
            $yima_status = I('get.yima_status', 0);
            $product_status = I('get.product_status', 0);
            $where = [
                'is_delete' => 0,
            ];

            if ($factory_id) {
                $where['factory_id'] = $factory_id;
            }

            if ($category) {
                $ids = [];
                foreach (BaseModel::getInstance('product_category')->getList(['parent_id' => $category]) as $k => $v) {
                    $ids[$v['id']] = $v['id'];
                }
                $ids = implode(',', array_filter($ids));
                if ($ids) {
                    $where['product_category'] = ['in', $ids.','.$category];
                } else {
                    $where['product_category'] = $category;
                }
            }

            if ($guige) {
                $where['product_guige'] = $guige;
            }

            if ($brand) {
                $where['product_brand'] = $brand;
            }

            if (!empty($xinghao)) {
                $where['product_xinghao'] = ['like', "%{$xinghao}%"];
            }

            if (in_array($yima_status, [1, 2])) {
                $where['yima_status'] = $yima_status - 1;
            }

            if (in_array($product_status, [1, 2])) {
                $where['product_status'] = $product_status - 1;
            }

            $model = BaseModel::getInstance('factory_product');

            $count = $model->getNum($where);

            if (!$count) {
                $this->paginate();
            }
            $opt = [
                'where' => $where,
                'limit' => getPage(),
                'order' => 'product_id desc',
                'field' => 'product_id,factory_id,product_xinghao,product_category,product_guige,product_brand,yima_status,product_status',
            ];
            $list = $model->getList($opt);

            $factory_product_ids = $product_ids = $categories = $guiges = $brands = [];
            foreach ($list as $k => $v) {
                $factory_product_ids[$v['factory_id']][$v['product_id']] = $v['product_id'];
                $product_ids[$v['product_id']] = $v['product_id'];
                $categories[$v['product_category']] = $v['product_category'];
                $guiges[$v['product_guige']] = $v['product_guige'];
                $brands[$v['product_brand']] = $v['product_brand'];
            }

            $cm_list = implode(',', array_filter($categories)) ? BaseModel::getInstance('product_category')->getList([
                'where' => [
                    'id' => ['in', implode(',', array_filter($categories))],
                ],
                'index' => 'id',
            ]) : [];

            $guige_list = implode(',', array_filter($guiges)) ? BaseModel::getInstance('product_standard')->getList([
                'where' => [
                    'standard_id' => ['in', implode(',', array_filter($guiges))]
                ],
                'index' => 'standard_id',
            ]) : [];
            $brand_list = implode(',', array_filter($brands)) ? BaseModel::getInstance('factory_product_brand')->getList([
                'where' => [
                    'id' => ['in', implode(',', array_filter($brands))]
                ],
                'index' => 'id',
            ]) : [];

            $in_nums = implode(',', array_filter($product_ids)) ? BaseModel::getInstance('factory_product_qrcode')->getList([
                'field' => 'GROUP_CONCAT(id) as ids,product_id,SUM(nums) as all_num',
                'where' => [
                    'product_id' => ['in', implode(',', array_filter($product_ids))],
                ],
                'group' => 'product_id',
                'index' => 'product_id',
            ]) : [];

            $register = [];
            foreach ($factory_product_ids as $k => $v) {
                $result = implode(',', array_filter($v)) ? BaseModel::getInstance(factoryIdToModelName($k))->getList([
                    'field' => 'product_id,COUNT(*) as register_nums',
                    'where' => [
                        'product_id' => ['in', implode(',', array_filter($v))],
                        'register_time' => ['GT', 0],
                    ],
                    'group' => 'product_id',
                    'index' => 'product_id',
                ]) : [];

                $register = $register + (array)$result;
            }

            foreach ($list as $k => $v) {
                $v['product_category_desc'] = $cm_list[$v['product_category']]['name'];
                $v['product_guige_desc'] = $guige_list[$v['product_guige']]['standard_name'];
                $v['product_brand_desc'] = $brand_list[$v['product_brand']]['product_brand'];
                $v['all_num'] = number_format($in_nums[$v['product_id']]['all_num'], 0, '.', '');
                $v['register_nums'] = number_format($register[$v['product_id']]['register_nums'], 0, '.', '');
                $list[$k] = $v;
            }

            $this->paginate($list, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function yimaStatus()
    {
        $id = I('get.id', 0);
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            
            $model = BaseModel::getInstance('factory_product');
            $data = $model->getOneOrFail([
                    'is_delete'  => 0,
                    'product_id' => $id,
                    'factory_id' => $factory_id,
                ]);

            $update = [
               'yima_status' => $data['yima_status'] ? 0 : 1,
            ];
            $model->update($id, $update);
            $this->response($update);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryCategory()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $factory = AuthService::getAuthModel();
            $categories = D('Product', 'Logic')->factoryCategory($factory['factory_id']);

            $this->responseList($categories);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function standard()
    {
        try {
            $category_id = I('category_id');
            $standards = D('Product', 'Logic')->standard($category_id);

            $this->responseList($standards);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryBrand()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $category_id = I('category_id', 0);
            $standards = D('Product', 'Logic')->factoryBrand($factory_id, $category_id);

            $this->responseList($standards);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }
    //添加厂家产品
    public function addFactoryProduct()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();

            $data = [];
            $data['product_xinghao'] = empty(I('product_xinghao')) ? '' : trim(I('product_xinghao'));
            $data['product_category'] = empty(I('product_category')) ? 0 : intval(I('product_category'));
            $data['product_guige'] = empty(I('product_guige')) ? 0 : intval(I('product_guige'));
            $data['product_brand'] = empty(I('product_brand')) ? 0 : intval(I('product_brand'));
            $data['product_thumb'] = empty(I('product_thumb')) ? '' : trim(I('product_thumb'));
            $data['product_content'] = empty(I('product_content')) ? '' : I('product_content');
            $data['product_normal_faults'] = empty(I('product_normal_faults')) ? '' : I('product_normal_faults');
            $data['product_status'] = empty(I('product_status')) ? '' : intval(I('product_status'));
            $data['yima_status'] = I('yima_status', 0);
            $data['factory_id'] = $factory_id;

            if (
                empty($data['product_xinghao']) ||
                empty($data['product_category']) ||
                empty($data['product_guige']) ||
                empty($data['product_brand'])
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $condition = [];
            $condition['product_category'] = I('product_category', 0);
            $condition['product_guige'] = I('product_guige', 0);
            $condition['product_brand'] = I('product_brand', 0);
            $condition['product_xinghao'] = I('product_xinghao', 0);

            //如果是编辑状态  则排除自己进行查重
            $model = BaseModel::getInstance('factory_product');
            $product_id = I('product_id');
            if (!empty($product_id)) {
                $condition['product_id'] = I('product_id');
                $opts = [
                    'where' => $condition,
                    'field' => 'product_id,product_attrs,product_attrs_ids',
                ];
                $product_info = $model->getOne($opts);
                $product_attr = $product_info['product_attrs'];
                $product_attr_ids = $product_info['product_attrs_ids'];

                $update_product_attr_data_modify = json_decode($product_attr, true);
                $update_product_attr_id_modify = empty($product_attr_ids)? '-1': $product_attr_ids;
                $update_product_attr_info_modify = BaseModel::getInstance('factory_product_attr')
                    ->getList([
                        'where' => ['id' => ['in', $update_product_attr_id_modify]],
                        'index' => 'attr_name',
                    ]);
            }


            $is_product_exist = $model->getNum(['where' => $condition]);
            if ($is_product_exist > 0 && empty(I('product_id'))) {
                //重复
                $this->throwException(ErrorCode::CHECK_IS__EXIST, '该产品已存在');
            }
            //多图片
            $image_list = [];

            $images = I('images');
            if (!empty($images)) {
                foreach ($images as $image) {
                    $url = trim($image['url']);
                    $name = trim($image['name']);
                    $alt = trim($image['alt']);
                    $href = trim($image['href']);
                    $sort = trim($image['sort']);

                    $image_list[] = [
                        'url'  => $url,
                        'name' => $name,
                        'alt'  => $alt,
                        'href' => $href,
                        'sort' => $sort,
                    ];
                }
            }
            $data['images'] = serialize($image_list);
            //性能属性
            $add_product_attr = empty(I('add_product_attr')) ? [] : I('add_product_attr');
            $add_product_attr = htmlEntityDecodeAndJsonDecode($add_product_attr);

            $edit_product_attr = empty(I('edit_product_attr')) ? [] : I('edit_product_attr');
            $edit_product_attr = htmlEntityDecodeAndJsonDecode($edit_product_attr);

            $del_product_attr = array_filter(explode(',', I('del_product_attr', 0)));

            $product_cat_id = I('product_category', 0);
            if (empty($product_cat_id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $product_cat_id = D('Item')->find_parent($product_cat_id);;
            $del_attr_data = $updata_attr_data_value = $add_updata_attr_data = $updata_attr_data = $attr_updata_data = $attr_data = [];
            M()->startTrans();
            if (!empty($del_product_attr)) {
                foreach ($del_product_attr as $dl => $l) {
                    $del_attr_data[] = [
                        'id'      => $l,
                        'is_show' => 1,
                    ];
                }
                D('Publics', 'Logic')->updateAll('factory_product_attr', $del_attr_data, 'id');
                //                updateAll('factory_product_attr', $del_attr_data, 'id');
            }
            //修改性能参数
            if (!empty($edit_product_attr)) {
                foreach ($edit_product_attr as $ko => $o) {
                    if (empty($o['id']) || empty($o['attr_name'])) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
                    }
                }
                $check_edit_product_attr_modify = [];
                foreach ($edit_product_attr as $js => $sd) {
                    $check_add_product_attr_modify[$sd['attr_name']] = $sd['attr_name'];
                }
                count($check_edit_product_attr_modify) != count($edit_product_attr) && $this->throwException(ErrorCode::CHECK_IS__EXIST, '类别不能相同');

                foreach ($edit_product_attr as $ks => $s) {
                    $attr_updata_data['product_cat_id'] = $product_cat_id;
                    $attr_updata_data['factory_id'] = $factory_id;
                    $attr_updata_data['attr_name'] = $s['attr_name'];
                    $attr_updata_data['is_show'] = 0;

                    $check_updata_attr = $update_product_attr_info_modify[$attr_updata_data['attr_name']];
                    //                    $check_updata_attr = BaseModel::getInstance('factory_product_attr')->getNum($attr_updata_data);

                    if (!empty($check_updata_attr['id'])) {
                        if ($check_updata_attr['id'] != $s['id']) {
                            $this->fail(ErrorCode::CHECK_IS__EXIST, '该“' . $s['attr_name'] . '”性能属性已存在');
                        }
                    }
                    if (!empty($s['attr_name'])) {
                        $updata_attr_data[] = [
                            'id'             => $s['id'],
                            'product_cat_id' => $product_cat_id,
                            'factory_id'     => $factory_id,
                            'attr_name'      => $s['attr_name'],
                        ];
                        $updata_attr_data_value[$s['id']] = [
                            'id'        => $s['id'],
                            'attr_name' => $s['attr_name'],
                            'value'     => $s['value'],
                        ];
                    }
                    $updata_attr_data_value = array_merge($update_product_attr_data_modify, $updata_attr_data_value);
                }

                D('Publics', 'Logic')->updateAll('factory_product_attr', $updata_attr_data, 'id');

                //                updateAll('factory_product_attr', $updata_attr_data, 'id');
            }
            // 清除修改前
            $updata_attr_data_value_key = array_column($updata_attr_data_value, null, 'id');
            $updata_attr_data_value_key = array_column($updata_attr_data_value_key, null, 'attr_name');
            //判断是否相同
            $check_add_product_attr_modify = [];
            foreach ($add_product_attr as $js => $sd) {
                $check_add_product_attr_modify[$sd['attr_name']] = $sd['attr_name'];
            }
            count($check_add_product_attr_modify) != count($add_product_attr) && $this->throwException(ErrorCode::CHECK_IS__EXIST, '类别不能相同');
            //添加性能参数
            if (!empty($add_product_attr)) {
                $check_attr = 0;
                foreach ($add_product_attr as $kj => $j) {
                    $attr_data['product_cat_id'] = $product_cat_id;
                    $attr_data['factory_id'] = $factory_id;
                    $attr_data['attr_name'] = $j['attr_name'];
                    $attr_data['is_show'] = 0;
                    if (!empty($updata_attr_data_value_key)) {
                        $check_attr = $updata_attr_data_value_key[$j['attr_name']];
                    } else {
                        $check_attr = $update_product_attr_info_modify[$j['attr_name']];
                    }

                    if (!empty($check_attr)) {
                        $this->throwException(ErrorCode::CHECK_IS__EXIST, '该“' . $j['attr_name'] . '”性能属性已存在');
                    }
                    if (!empty($j['attr_name'])) {
                        $attr_data['value'] = $j['value'];
                        $res_id = BaseModel::getInstance('factory_product_attr')
                            ->insert($attr_data);
                        $add_updata_attr_data[$res_id] = [
                            'id'        => $res_id,
                            'attr_name' => $j['attr_name'],
                            'value'     => $j['value'],
                        ];
                    }
                    if (empty($updata_attr_data_value) && !empty($update_product_attr_data_modify)) {
                        $add_updata_attr_data = array_merge($update_product_attr_data_modify, $add_updata_attr_data);
                    }

                }
            }
            $attr_ids = [];
            $product_attr_data = array_merge($updata_attr_data_value, $add_updata_attr_data);
            $product_attr_data = array_column($product_attr_data, null, 'id');
            $product_attr_data = array_values($product_attr_data);

            if (!empty($product_attr_data)) {
                foreach ($product_attr_data as $pi => $i) {
                    $attr_ids[] = $i['id'];
                }
                $attr_ids = implode(',', $attr_ids);
                $updata_attr_data = json_encode($product_attr_data);

                $data['product_attrs'] = $updata_attr_data;
                $data['product_attrs_ids'] = $attr_ids;
            }

            if (empty(I('product_id', 0))) {
                $model->insert($data);
            } else {
                $product_id = I('product_id', 0);
                $data['product_id'] = $product_id;
                $model->update($product_id, $data);
            }
            M()->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //添加产品品牌
    public function addFactoryBrand()
    {
        try {
            $data = $insert_data = [];
            $factory_id = I('factory_id', 0);
            if (!empty(I('brand_list', 0))) {
                $data = htmlEntityDecodeAndJsonDecode(I('brand_list', 0));
            }
            foreach ($data as $k => $v) {
                if (empty($v['id'])) {
                    $condition['product_brand'] = $v['product_brand'];
                    $condition['factory_id'] = $factory_id;
                    $red = BaseModel::getInstance('factory_product_brand')->getNum($condition);
                    unset($condition);
                    if ($red) {
                        $this->fail(ErrorCode::CHECK_IS__EXIST, '该品牌已存在');
                    }
                    $insert_data[] = [
                        'product_brand' => $v['product_brand'],
                        'factory_id' => $factory_id,
                        'product_cat_id' => $v['product_cat_id'],
                    ];
                }
            }
            if (!empty($insert_data)) {
                BaseModel::getInstance('factory_product_brand')->insertAll($insert_data);
                $this->response();
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    //获取厂家自有品牌列表
    public function getProductBrandList()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $condition = [];
            $condition['factory_id'] = $factory_id;
            $condition['is_show'] = '0';
            $list = BaseModel::getInstance('factory_product_brand')->getList([
                'where' => $condition,
                'order' => 'id desc',
                'field' => 'id,product_cat_id,product_brand'
            ]);
            $this->responseList($list);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //获取一个产品品牌
    public function getOneProductBrand()
    {
        try {
            $condition = [];
            $condition['id'] = I('id', 0);
            if (empty($condition)) {
                $this->fail(ErrorCode::CHECK_IS__EXIST);
            }
            BaseModel::getInstance('factory_product_brand')->getOne(['where' => $condition]);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //修改产品品牌
    public function editProductBrand()
    {
       try {
           $data = [];
           $factory_id = I('get.factory_id', 0);
           if (!empty(I('get.brand_list', 0))) {
               $data = htmlEntityDecodeAndJsonDecode(I('get.brand_list', 0));
           }
           foreach ($data as $k => $v) {
               if (!empty($v['id'])) {
                   $condition['product_brand'] = $v['product_brand'];
                   $condition['factory_id'] = $factory_id;
                   $red = BaseModel::getInstance('factory_product_brand')->getNum($condition);
                   unset($condition);
                   if ($red) {
                       $this->fail(ErrorCode::CHECK_IS__EXIST, '该品牌已存在');
                   }
                   $update_data[] = [
                       'id' => $v['id'],
                       'product_brand' => $v['product_brand'],
                   ];
               }
           }
           if (!empty($update_data)) {
               D('Publics', 'Logic')->updateAll('factory_product_brand', $update_data, 'id');
//               updateAll('factory_product_brand', $update_data, 'id');
               $this->response();
           }
       } catch (\Exception $e) {
           $this->getExceptionError($e);
       }
    }

    //删除/编辑/新增 品牌
    public function  operateProductBrand()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $delPrand = I('put.delPrand', 0);
            $addPrand = I('put.addPrand', 0);
            $editPrand = I('put.editPrand', 0);
            $addPrand = htmlEntityDecodeAndJsonDecode($addPrand);
            $editPrand = htmlEntityDecodeAndJsonDecode($editPrand);

            $del_data = [];
            $add_res = $update_res = $del_res = 0;
            M()->startTrans();
            //添加产品品牌
            if (!empty($addPrand)) {
                foreach ($addPrand as $k => $v) {
                    if (empty($v['id'])) {
                        $condition['product_brand'] = $v['product_brand'];
                        $condition['factory_id'] = $factory_id;
                        $red = BaseModel::getInstance('factory_product_brand')->getNum($condition);
                        unset($condition);
                        if ($red) {
                            $this->fail(ErrorCode::CHECK_IS__EXIST, '该品牌已存在,新增失败');
                        }
                        $insert_data[] = [
                            'product_brand' => $v['product_brand'],
                            'factory_id' => $factory_id,
//                        'product_cat_id' => $v['product_cat_id'], 暂时不需要品类
                            'product_cat_id' => 0,
                        ];
                    }
                }
                if (!empty($insert_data)) {
                    $add_res = BaseModel::getInstance('factory_product_brand')->insertAll($insert_data);
                }
            }

            if (!empty($editPrand)) {
                foreach ($editPrand as $ke => $ve) {
                    if (!empty($ve['id'])) {
                        $condition['product_brand'] = $ve['product_brand'];
                        $condition['factory_id'] = $factory_id;
                        $red = BaseModel::getInstance('factory_product_brand')->getNum($condition);
                        unset($condition);
                        if ($red) {
                            $this->fail(ErrorCode::CHECK_IS__EXIST, '该品牌已存在，编辑失败');
                        }
                        $update_data[] = [
                            'id' => $ve['id'],
                            'product_brand' => $ve['product_brand'],
                        ];
                    }
                }
                if (!empty($update_data)) {
                    $update_res = D('Publics', 'Logic')->updateAll('factory_product_brand', $update_data, 'id');
//                    $update_res = updateAll('factory_product_brand', $update_data, 'id');
                }
            }

            if (!empty($delPrand)) {
                $delPrand_id  = array_filter(explode(',', $delPrand));
                foreach ($delPrand_id as $dl => $d) {
                    $del_data[] = [
                        'id' => $d,
                        'is_show' => 1,
                    ];
                }
                if (!empty($del_data)) {
                    $del_res = D('Publics', 'Logic')->updateAll('factory_product_brand', $del_data, 'id');
//                    $del_res = updateAll('factory_product_brand', $del_data, 'id');
                }
            }
            M()->commit();
            if ($add_res || $update_res|| $del_res) {
                $this->response();
            } else {
                $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '操作失败');
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }


    //隐藏品牌
    public function hideProductBrand()
    {
        try {
            $condition = [];
            $condition['id'] = I('get.id', 0);
            if (empty($condition['id'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $data = [];
            $data['is_show'] = '1';
            BaseModel::getInstance('factory_product_brand')->update($condition, $data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //添加产品属性  
    public function addProductAttr()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $data = [];
            $data['factory_id'] = $factory_id;
            $data['attr_name'] = empty(I('attr_name')) ? '' : trim(I('attr_name'));
            $data['product_cat_id'] = empty(I('product_cat_id')) ? 0 : I('product_cat_id');
//            $data['product_cat_id'] = find_parent(I('product_cat_id', 0));
            $data['product_cat_id'] = D('Item')->find_parent(I('product_cat_id', 0));;

            //查询还未删除的
            $data['is_show'] = 0;

            //编辑  查重排除自己之外的有没有重名
            if (!empty(I('id'))) {
                $data['id'] = array('neq',I('id'));
            }
            $model = BaseModel::getInstance('factory_product_attr');
            $count = $model->getNum(['where' => $data]);
            //判断属性是否已经存在
            if ($count > 0) {
                $this->fail(ErrorCode::CHECK_IS__EXIST, '该属性已存在');
            }

            if (!empty(I('id'))) {
                unset($data['id']);
                $model->update(I('id'), $data);
            } else {
                $model->insert($data);
            }
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //获取产品属性
    public function getProductAttr()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $condition = [];
            //获取父分类
//            $condition['product_cat_id'] = find_parent(I('product_cat_id', 0));
            $condition['product_cat_id'] = D('Item')->find_parent(I('product_cat_id', 0));;

            $condition['factory_id'] = $factory_id;
            $condition['is_show'] = '0';

            $attr_list = BaseModel::getInstance('factory_product_attr')->getList([
                'where' => $condition,
                'field' => 'id, product_cat_id, attr_name'
            ]);

            $this->response($attr_list);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //隐藏产品属性
    public function hideProductAttr()
    {
        try {
            $condition = $data = [];
            $condition['id'] = I('id', 0);
            $data['is_show'] = '1';

            BaseModel::getInstance('factory_product_attr')->update($condition['id'], $data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }


    //获取一个产品属性
    public function getOneAttr()
    {
        try {
            $condition = [];
            $condition['id'] = I('id', 0);
            BaseModel::getInstance('factory_product_attr')->getOne($condition);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //获取一个厂家产品的详细信息
    public function getOne()
    {
        try {
            $product_id = I('product_id', 0);
            $rs = BaseModel::getInstance('factory_product')->getOne([
                'where' => ['product_id' => $product_id],
            ]);

            if (empty($rs)) {
                $this->fail(ErrorCode::CHECK_IS__EXIST, '该产品不存在');
            }

            $cat_desc = BaseModel::getInstance('cm_list_item')->getOne($rs['product_category']);
            $brand_desc = BaseModel::getInstance('factory_product_brand')->getOne([
                'where' => ['id' => $rs['product_brand']],
                'field' => 'id,product_cat_id,product_brand'
            ]);
            $guige_desc = BaseModel::getInstance('product_standard')->getOne($rs['product_guige']);

            $rs['cat_desc'] = $cat_desc['item_desc'];
            $rs['brand_desc'] = $brand_desc['product_brand'];
            $rs['guige_desc'] = $guige_desc['standard_name'];

            $brandInfo = $brand_desc;
            if ($rs) {
                unset($rs['vedio_list']);
                $rs['images'] = unserialize($rs['images']);
                $rs['product_content'] = htmlspecialchars_decode($rs['product_content']);
                $rs['product_normal_faults'] = htmlspecialchars_decode($rs['product_normal_faults']);
                $rs['product_attrs'] = json_decode($rs['product_attrs'], true);
                $rs['product_thumb'] = $rs['product_thumb'] ? Util::getServerFileUrl($rs['product_thumb']) : '';
                if (!empty($rs['images'])) {
                    foreach ($rs['images'] as $ms => $m) {
                        $m['url'] = Util::getServerFileUrl($m['url']);
                        $rs['images'][$ms] = $m;
                    }
                }
                foreach ($rs['product_attrs'] as $key => $attr) {
                    $attr_desc = BaseModel::getInstance('factory_product_attr')->getOne($attr['id']);
                    if (1 == $attr_desc['is_show']) {
                        unset($rs['product_attrs'][$key]);
                        continue;
                    }
                    $rs['product_attrs'][$key]['attr_name'] = $attr_desc['attr_name'];
                }
                $rs['product_attrs_ids'] = empty($rs['product_attrs'])? '': implode(',', array_column($rs['product_attrs'], 'id'));
                $rs['product_attrs'] = array_values($rs['product_attrs']);
                $rs['product_attrs'] = json_encode($rs['product_attrs']);

                $result['data'] = $rs;
                $result['brandInfo'] = $brandInfo;
                $this->response($result);
            } else {
                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR);
            }

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    //标记删除厂家产品
    public function delOne()
    {
        try {
            $condition = [];
            $condition['product_id'] = I('get.product_id', 0);
            if (empty($condition['product_id'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $data = [];
            $data['is_delete'] = 1;
            BaseModel::getInstance('factory_product')->update($condition, $data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    /**
     * 产品回收站
     * @request
     */
    public function recycle()
    {
        try {
            // 1 分类， 2 规格， 3 品牌
            $factory_id = I('get.factory_id', 0);
            $category = I('get.category', 0);
            $guige = I('get.guige', 0);
            $brand = I('get.brand', 0);
            $xinghao = I('get.xinghao', '');
            $yima_status = I('get.yima_status', 0);
            $product_status = I('get.product_status', 0);
            $where = [
                'is_delete' => 1,
            ];

            if ($factory_id) {
                $where['factory_id'] = $factory_id;
            }

            if ($category) {
                $ids = [];
                foreach (BaseModel::getInstance('cm_list_item')->getList(['item_parent' => $category]) as $k => $v) {
                    $ids[$v['list_item_id']] = $v['list_item_id'];
                }
                $ids = implode(',', array_filter($ids));
                if ($ids) {
                    $where['product_category'] = ['in', $ids .','. $category];
                } else {
                    $where['product_category'] = $category;
                }
            }

            if ($guige) {
                $where['product_guige'] = $guige;
            }

            if ($brand) {
                $where['product_brand'] = $brand;
            }

            if (!empty($xinghao)) {
                $where['product_xinghao'] = ['like', "%{$xinghao}%"];
            }

            if (in_array($yima_status, [1, 2])) {
                $where['yima_status'] = $yima_status - 1;
            }

            if (in_array($product_status, [1, 2])) {
                $where['product_status'] = $product_status - 1;
            }

            $model = BaseModel::getInstance('factory_product');
            $count = $model->getNum($where);

            if (!$count) {
                $this->paginate();
            }
            $opt = [
                'where' => $where,
                'limit' => getPage(),
                'field' => 'product_id,factory_id,product_xinghao,product_category,product_guige,product_brand,yima_status,product_status',
            ];
            $list = $model->getList($opt);

            $factory_product_ids = $product_ids = $categories = $guiges = $brands = [];
            foreach ($list as $k => $v) {
                $factory_product_ids[$v['factory_id']][$v['product_id']] = $v['product_id'];
                $product_ids[$v['product_id']] = $v['product_id'];
                $categories[$v['product_category']] = $v['product_category'];
                $guiges[$v['product_guige']] = $v['product_guige'];
                $brands[$v['product_brand']] = $v['product_brand'];
            }

            $cm_list = implode(',', array_filter($categories)) ? BaseModel::getInstance('cm_list_item')->getList([
                'where' => [
                    'list_item_id' => ['in', implode(',', array_filter($categories))],
                ],
                'index' => 'list_item_id',
            ]) : [];
            $guige_list = implode(',', array_filter($guiges)) ? BaseModel::getInstance('product_standard')->getList([
                'where' => [
                    'standard_id' => ['in', implode(',', array_filter($guiges))]
                ],
                'index' => 'standard_id',
            ]) : [];
            $brand_list = implode(',', array_filter($brands)) ? BaseModel::getInstance('factory_product_brand')->getList([
                'where' => [
                    'id' => ['in', implode(',', array_filter($brands))]
                ],
                'index' => 'id',
            ]) : [];

            $in_nums = implode(',', array_filter($product_ids)) ? BaseModel::getInstance('factory_product_qrcode')->getList([
                'field' => 'GROUP_CONCAT(id) as ids,product_id,SUM(nums) as all_num',
                'where' => [
                    'product_id' => ['in', implode(',', array_filter($product_ids))],
                ],
                'group' => 'product_id',
                'index' => 'product_id',
            ]) : [];

            $register = [];
            foreach ($factory_product_ids as $k => $v) {
                $result = implode(',', array_filter($v)) ? BaseModel::getInstance(factoryIdToModelName($k))->getList([
                    'field' => 'product_id,COUNT(*) as register_nums',
                    'where' => [
                        'product_id' => ['in', implode(',', array_filter($v))],
                        'register_time' => ['GT', 0],
                    ],
                    'group' => 'product_id',
                    'index' => 'product_id',
                ]) : [];
                $register = $register + (array)$result;
            }


            foreach ($list as $k => $v) {
                $v['product_category_desc'] = $cm_list[$v['product_category']]['item_desc'];
                $v['product_guige_desc'] = $guige_list[$v['product_guige']]['standard_name'];
                $v['product_brand_desc'] = $brand_list[$v['product_brand']]['product_brand'];
                $v['all_num'] = number_format($in_nums[$v['product_id']]['all_num'], 0, '.', '');
                $v['register_nums'] = number_format($register[$v['product_id']]['register_nums'], 0, '.', '');
                $list[$k] = $v;
            }

            $this->paginate($list, $count);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    /**
     * 還原改產品
     * @request int product_id 產品ID
     * @return void
     */
    public function recoveryOne()
    {
        try {
            $condition = [];
            $condition['product_id'] = I('product_id', 0);
            if (empty($condition['product_id'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $data = [];
            $data['is_delete'] = 0;
            $rs = BaseModel::getInstance('factory_product')->update($condition, $data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    public function operateProductModel()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();

            $delete = array_filter(I('delete'));
            $update = array_filter(I('update'));
            $add = array_filter(I('add'));

            $factory_product_model = BaseModel::getInstance('factory_product');
            $factory_product_model->startTrans();
            if ($delete) {
                $factory_product_model->update(['product_id' => ['IN', $delete]], ['is_delete' => 1]);
            }
            if ($update) {
                $model_list = Arr::pluck($update, 'model');
                if (count(array_unique($model_list)) != count($model_list)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '型号名称不能重复,请检查');
                }
                $update_product_ids = Arr::pluck($update, 'product_id');
                $update_products = $factory_product_model->getList([
                    'where' => [
                        'product_id' => ['IN', $update_product_ids],
                        'factory_id' => $factory_id,
                    ],
                    'field' => 'product_id,product_xinghao,product_category,product_guige,product_brand',
                    'index' => 'product_id',
                ]);
                foreach ($update as $item) {
                    $exists_product = $factory_product_model->getOne([
                        'factory_id' => $factory_id,
                        'product_id' => ['NEQ', $item['product_id']],
                        'product_category' => $update_products[$item['product_id']]['product_category'],
                        'product_guige' => $update_products[$item['product_id']]['product_guige'],
                        'product_brand' => $update_products[$item['product_id']]['product_brand'],
                        'product_xinghao' => $item['model'],
                    ], 'product_id');
                    if ($exists_product) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '型号名称已存在,修改失败');
                    }
                }
                $product_model = [];
                foreach ($update as $item) {
                    if (!$item['product_id'] || !$item['model']) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '型号更新参数错误');
                    }
                    $product_model[] = "({$item['product_id']},'{$item['model']}')";
                }
                $product_model = implode(',', $product_model);
                $sql = "INSERT INTO `factory_product`(`product_id`,`product_xinghao`) VALUES{$product_model} ON DUPLICATE KEY UPDATE product_xinghao=VALUES(product_xinghao)";
                $factory_product_model->execute($sql);
            }
            if ($add) {
                $product_logic = new ProductLogic();
                if (count(array_unique($add)) != count($add)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '添加型号名称不能重复,请检查');
                }
                $product_category_id = I('product_category_id');
                $product_standard_id = I('product_standard_id');
                $product_brand_id = I('product_brand_id');
                if (!$product_category_id || !$product_standard_id || !$product_brand_id) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择要添加的产品信息');
                }
                $products = [];
                foreach ($add as $item) {
                    $product_id = $product_logic
                        ->getFactoryProductIdByInfo($factory_id, $product_category_id, $product_standard_id, $product_brand_id, $item);
                    if ($product_id) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '型号名称已存在,修改失败');
                    }
                    $products[] = [
                        'factory_id' => $factory_id,
                        'product_xinghao' => $item,
                        'product_category' => $product_category_id,
                        'product_guige' => $product_standard_id,
                        'product_brand' => $product_brand_id,
                    ];
                }
                $factory_product_model->insertAll($products);
            }
            $factory_product_model->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
