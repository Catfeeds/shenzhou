<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/21
 * Time: 18:22
 */

namespace PlatformApi\Logic;


use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\PlatformApiService;
use Library\Common\Util;
use PlatformApi\Common\ErrorCode;
use PlatformApi\Model\BaseModel;

class OrderLogic extends BaseLogic
{
    public $helper;
    public $is_insured;

    /**
     * 获取厂家默认技术支持人信息
     * @param $factory_id
     * @return array|bool
     */
    public function getFactoryHelper($factory_id)
    {
        $helper = BaseModel::getInstance('factory_helper')->getOne([
            'field' => 'id,name,telephone',
            'where' => [
                'factory_id' => $factory_id,
            ],
            'order' => 'is_default desc,id asc',
        ]);

        if (!$helper) {
            if (AuthService::getModel() == AuthService::ROLE_FACTORY && AuthService::getAuthModel()->getPrimaryValue() == $factory_id) {
                $factory = AuthService::getAuthModel();
            } else {
                $factory = BaseModel::getInstance('factory')->getOne($factory_id);
            }

            $helper = [
                'id' => 0,
                'name' => $factory['linkphone'],
                'telephone' => $factory['linkman'],
            ];
        }
        return $helper;
    }

    /**
     * 获取厂家类型的平台的保内单类型
     * @param $platform_order_type
     * @return mixed
     */
    public function getFactoryWorkerOrderTypeForPlatform($platform_order_type)
    {
        return PlatformApiService::FACTORY_WORKEKR_ORDER_TYPE_ARR_VALUE[$platform_order_type];
    }

    /**
     * 创建工单
     */
    public function createOrder()
    {
        switch (AuthService::getModel()) {
            case AuthService::ROLE_FACTORY:
                return $this->factoryCreateOrder();
                break;

            default:
                $this->throwException(-405, ' 暂不支持该(' . AuthService::getModel() . ')用户类型下单');
                break;
        }
    }

    /**
     * 厂家平台创建工单
     */
    public function factoryCreateOrder()
    {
        $data = PlatformApiService::$data;

        $factory_id = AuthService::getAuthModel()->getPrimaryValue();

        if (!isset(OrderService::SERVICE_TYPE[$data['service_type']])) {
            $this->throwException(-406, '请选择正确的服务类型');
        }

        if (!isset(PlatformApiService::FACTORY_WORKEKR_ORDER_TYPE_ARR_VALUE[$data['order_type']])) {
            $this->throwException(-410, '请选择正确的保单类型');
        }

        $this->helper = $this->getFactoryHelper($factory_id);
        // 是否为保内单： true 保内单；false 保外单；
        $this->is_insured = $data['order_type'] == PlatformApiService::FACTORY_WORKEKR_ORDER_TYPE_IN ? 1 : 0;

        $result = [];
        $this->checkOrderDatas($data, $result);

        $return = [];
        $create_order_service = (new OrderService\CreateOrderService(AuthService::getAuthModel()->getPrimaryValue()));
        foreach ($result['worker_order'] as $k => $v) {
            $create_order_service->setOrderProducts($result['worker_order_product'][$k]);
            $create_order_service->setOrderUser($result['worker_order_user_info'][$k]);
            $create_order_service->setOrderExtInfo($result['worker_order_ext_info'][$k]);
            $v['is_insured'] = $this->is_insured;
            $create_order_service->setOrder($v);
            $worker_order_id = $create_order_service->create();
            $worker_order_ids[] = $worker_order_id;
            $return[] = [
                'error_code' => 1,
                'error_message' => '',
                'order_number' => $create_order_service->getCreateOrno(),
                'out_trade_number' => $k,
            ];
        }

        foreach ($result['error'] as $k => $v) {
            $v['order_number'] = null;
            $v['out_trade_number'] = $k;
            $return[] = $v;
        }
        return $return;
    }

    /**
     * 获取第三方单号新建的工单号，并排除取消的工单
     * @param $array
     * @param bool $is_key_value
     * @return array|bool
     */
    public function getOutTradeNumberAgain($array, $is_key_value = true)
    {
        $platform_sns = implode(',', $array);
        if (!$platform_sns) {
            $this->throwException(-407, '第三方单号不能为空');
        }
        $exit_sns = $platform_sns ? BaseModel::getInstance('worker_order_ext_info')->getList([
            'field' => 'out_trade_number,worker_order_id',
            'where' => [
                'out_platform' => PlatformApiService::$config['OUT_PLATFORM'] ?? 1,
                'out_trade_number' => ['in', $platform_sns],
            ],
            'index' => 'out_trade_number',
        ]) : [];

        if ($exit_sns) {
            $created_ids = array_column($exit_sns, 'worker_order_id');
            $cancels_id_index = $created_ids ? BaseModel::getInstance('worker_order')->getList([
                'field' => 'id',
                'where' => [
                    'id' => ['in', implode(',', $created_ids)],
                    'cancel_status' => ['not in', OrderService::CANCEL_TYPE_NULL . ',' . OrderService::CANCEL_TYPE_CS_STOP],
                ],
                'index' => 'id',
            ]) : [];
            foreach ($exit_sns as $k => $v) {
                if (isset($cancels_id_index[$v['worker_order_id']])) {
                    unset($exit_sns[$k]);
                }
            }
        }

        return $is_key_value ? $exit_sns : array_values($exit_sns);
    }

    /**
     * 工单用户信息检查
     * @param $user_info
     * @return string
     */
    public function checkUserInfo($user_info)
    {
        $areas = array_filter(explode('-', $user_info['areas']));

        empty($areas) && $this->throwException(-4003, '用户地区不能为空');

        empty($user_info['area_detail']) && $this->throwException(-4004, '用户详细地址不能为空');

        empty($user_info['contact_number']) && $this->throwException(-4005, '联系号码不能为空');

        empty($user_info['contact_name']) && $this->throwException(-4015, '联系人姓名不能为空');

        // 号码验证  取消药政手机号码
//        switch ($user_info['contact_type']) {
//            case PlatformApiService::CONTACT_TYPE_ALL:
//                if (!Util::isPhone($user_info['contact_number']) && !preg_match("/^([0-9]{3,4}-)?[0-9]{7,8}$/", $user_info['contact_number'])) {
//                    !Util::isPhone($user_info['contact_number']) && $this->throwException(-4017, '联系号码格式错误');
//                }
//                break;
//
//            case PlatformApiService::CONTACT_TYPE_PHONE:
//                !Util::isPhone($user_info['contact_number']) && $this->throwException(-4007, '手机号码格式错误');
//                break;
//
//            case PlatformApiService::CONTACT_TYPE_TEL:
//                !preg_match("/^([0-9]{3,4}-)?[0-9]{7,8}$/", $user_info['contact_number']) && $this->throwException(-4016, '固话格式错误');
//                break;
//
//            default :
//                $this->throwException(-4006, '暂不支持该联系方式');
//                break;
//        }
        return implode('', $areas);
    }

    /**
     * @param $products
     * @return array
     */
    public function checkProduct($products)
    {
        $products = array_filter($products);
        !$products && $this->throwException(-4008, '产品信息不能为空');

        $cate_ids = $standard_ids = $brand_ids = $model_ids = $brand_names = $model_names = [];
        foreach ($products as $k => $v) {
//            $cate_id = tableIdDecrypt('product_category', $v['category_code']);
            $cate_id = $v['category_id'];
            !$cate_id && $this->throwException(-4009, '分类不能为空');

//            $standard_id = tableIdDecrypt('product_standard', $v['standard_code']);
            $standard_id = $v['standard_id'];
            !$standard_id && $this->throwException(-4010, '规格不能为空');

//            $brand_id = tableIdDecrypt('factory_product_brand', $v['brand_code']);
//            $model_id = tableIdDecrypt('factory_product', $v['model_code']);
            $brand_id = $v['brand_id'];
            $model_id = $v['model_id'];

            $cate_ids[$cate_id] = $cate_id;
            $standard_ids[$standard_id] = $standard_id;

            $brand_id && $brand_ids[$brand_id] = $brand_id;
            !empty($v['brand_name']) && $brand_names[$v['brand_name']] = $v['brand_name'];

            $model_id && $model_ids[$model_id] = $model_id;
            !empty($v['model_name']) && $model_names[$v['model_name']] = $v['model_name'];
        }

        return [
            'category_ids' => $cate_ids,        // array_values($cate_ids),
            'standard_ids' => $standard_ids,    // array_values($standard_ids),
            'brand_ids'    => $brand_ids,       // array_values($brand_ids),
            'model_ids'    => $model_ids,       // array_values($model_ids),
            'brand_names'  => $brand_names,     // array_values($brand_names),
            'model_names'  => $model_names,     // array_values($model_names),
        ];
    }

    /**
     * @param $products
     * @return array
     */
    public function getProductDataList($products)
    {
        $cate_ids = implode(',', $products['category_ids']);
        $cate_list = $cate_ids ? BaseModel::getInstance('product_category')->getList([
            'where' => [
                'id' => ['in', $cate_ids],
            ],
        ]) : [];

        $standard_ids = implode(',', $products['standard_ids']);
        $standard_list = $standard_ids ? BaseModel::getInstance('product_standard')->getList([
            'where' => [
                'standard_id' => ['in', $standard_ids],
            ],
        ]) : [];

        $brand_list = [];
        $brand_ids = implode(',', $products['brand_ids']);
        $brand_names = implode(',', $products['brand_names']);
        if ($brand_ids || $brand_names) {
            $brand_where = [
                '_complex' => [
                    '_logic' => 'or',
                ]
            ];
            $brand_ids && $brand_where['_complex']['id'] = ['in', $brand_ids];
            $brand_names && $brand_where['_complex']['product_brand'] = ['in', $brand_names];
            $brand_where['factory_id'] = AuthService::getAuthModel()->getPrimaryValue();
            $brand_list = BaseModel::getInstance('factory_product_brand')->getList([
                'where' => $brand_where,
            ]);
        }

        $model_list = [];
        $model_ids = implode(',', $products['model_ids']);
        $model_names = implode(',', $products['model_names']);
        if ($model_ids || $model_names) {
            $model_where = [
                '_complex' => [
                    '_logic' => 'or',
                ]
            ];
            $model_ids && $model_where['_complex']['product_id'] = ['in', $model_ids];
            $model_names && $model_where['_complex']['product_xinghao'] = ['in', $model_names];
            $model_where['factory_id'] = AuthService::getAuthModel()->getPrimaryValue();
            $model_list = BaseModel::getInstance('factory_product')->getList([
                'where' => $model_where,
            ]);
        }

        return [
            'category' => $cate_list,
            'standard' => $standard_list,
            'brand' => $brand_list,
            'product' => $model_list,
        ];
    }

    /**
     * 新建订单的数据格式及数据正确性的验证
     * @param $data
     * @param array $return
     */
    public function checkOrderDatas($data, &$return = [])
    {
        $worker_order_product = $worker_order = $worker_order_user_info = $worker_order_ext_info = $worker_order_fee = [];

        $details = $data['data'];
        //  获取第三方重复下单单号数据
        $exit_sns = $this->getOutTradeNumberAgain(array_column($details, 'out_trade_number'));
        $error = [];
        $products = [];
        $filter_detail = [];
        foreach ($details as $k => $v) {
            $out_trade_number = $v['out_trade_number'];

            try {
                empty($out_trade_number) && $this->throwException(-4001, '第三方单号不能为空');

                isset($exit_sns[$out_trade_number]) && $this->throwException(-4002, '重复下单');

                foreach ($v['products'] as $dek => $dev) {
                    $v['products'][$dek]['category_id'] = tableIdDecrypt('product_category', $dev['category_code']);
                    $v['products'][$dek]['standard_id'] = tableIdDecrypt('product_standard', $dev['standard_code']);
                    $v['products'][$dek]['brand_id'] = tableIdDecrypt('factory_product_brand', $dev['brand_code']);
                    $v['products'][$dek]['model_id'] = tableIdDecrypt('factory_product', $dev['model_code']);
                }

                foreach ($this->checkProduct($v['products']) as $pk => $pv) {
                    $products[$pk] = (array)$products[$pk] + (array)$pv;
                }

                $area[$out_trade_number] = $this->checkUserInfo($v['user_info']);

                $filter_detail[$out_trade_number] = $v;
            } catch (\Exception $e) {
                $error[$out_trade_number]['error_code'] = $e->getCode();
                $error[$out_trade_number]['error_message'] = $e->getMessage();

                unset($details[$k]);
                continue;
            }
            unset($details[$k]);
        }

        $product_data_list = $this->getProductDataList($products);

        $cate_list = [];
        foreach ($product_data_list['category'] as $v) {
            isset($products['category_ids'][$v['id']]) && $cate_list[$v['id']] = [
                'id' => $v['id'],
                'name' => $v['name'],
            ];
        }
        $stanard_list = [];
        foreach ($product_data_list['standard'] as $v) {
            isset($products['standard_ids'][$v['standard_id']]) && $stanard_list[$v['standard_id']] = [
                'id' => $v['standard_id'],
                'name' => $v['standard_name'],
                'category_id' => $v['product_id'],
            ];
        }
        $brand_list = $brand_name_list = [];
        foreach ($product_data_list['brand'] as $v) {
            isset($products['brand_ids'][$v['id']]) && $brand_list[$v['id']] = [
                'id' => $v['id'],
                'name' => $v['product_brand'],
            ];
            isset($products['brand_names'][$v['product_brand']]) && $brand_name_list[$v['product_brand']] = [
                'id' => $v['id'],
                'name' => $v['product_brand'],
            ];
        }
        $model_list = $model_name_list = [];
        foreach ($product_data_list['product'] as $v) {
            isset($products['model_ids'][$v['product_id']]) && $model_list[$v['product_id']] = [
                'id' => $v['product_id'],
                'name' => $v['product_xinghao'],
                'product_category' => $v['product_category'],
                'product_guige' => $v['product_guige'],
                'product_brand' => $v['product_brand'],
            ];
            isset($products['model_names'][$v['product_xinghao']]) && $model_name_list[$v['product_xinghao']][] = [
                'id' => $v['product_id'],
                'name' => $v['product_xinghao'],
                'product_category' => $v['product_category'],
                'product_guige' => $v['product_guige'],
                'product_brand' => $v['product_brand'],
            ];
        }

        $area = AreaService::areaRuleResult($area);

        $brand_model = BaseModel::getInstance('factory_product_brand');
        $model_model = BaseModel::getInstance('factory_product');
        foreach ($filter_detail as $k => $v) {
            try {
                !$area[$k]['province']['id'] && $this->throwException(-4013, '请完善地址信息(至少省级)');
                $products = $v['products'];
                foreach ($products as $pk => $pv) {
                    $pv['nums'] < 1 && $this->throwException(-4014, '产品数量不能小于1');
                    !isset($cate_list[$pv['category_id']]) && $this->throwException(-4011, '分类不存在');
                    !isset($stanard_list[$pv['standard_id']]) && $this->throwException(-4012, '规格不存在');
                    $cate = $cate_list[$pv['category_id']];
                    $stan = $stanard_list[$pv['standard_id']];
                    $brand = $brand_list[$pv['brand_id']] ?? $brand_name_list[$pv['brand_name']];
                    $model = $model_list[$pv['model_id']];

                    if (!$brand && !empty($pv['brand_name'])) {
                        $brand_id = $brand_model->insert([
                            'factory_id' => AuthService::getAuthModel()->getPrimaryValue(),
                            'product_cat_id' => $cate['id'],
                            'product_brand' => $pv['brand_name'],
                        ]);
                        $brand = [
                            'id' => $brand_id,
                            'name' => $pv['brand_name'],
                        ];
                    }

                    if (!$model) {
                        foreach ($model_name_list[$pv['model_name']] as $mo_k => $mo_v) {
                            if ($mo_v['product_category'] == $cate['id'] && $mo_v['product_guige'] == $stan['id'] && $mo_v == $brand['id']) {
                                $model = $mo_v;
                            }
                        }
                    }

                    if (!$model && !empty($pv['model_name'])) {
                        $model_id = $model_model->insert([
                            'factory_id' => AuthService::getAuthModel()->getPrimaryValue(),
                            'product_xinghao' => $pv['model_name'],
                            'product_category' => $cate['id'],
                            'product_guige' => $stan['id'],
                            'product_brand' => $brand['id'],
                        ]);
                        $model = [
                            'id' => $model_id,
                            'name' => $pv['model_name'],
                            'product_category' => $cate['id'],
                            'product_guige' => $stan['id'],
                            'product_brand' => $brand['id'],
                        ];
                    }
                    for ($i = $pv['nums'];$i >= 1;$i--) {
                        $worker_order_product[$k][] = [
                            'product_category_id' => $cate['id'],
                            'product_standard_id' => $stan['id'],
                            'product_brand_id' => $brand['id'] ?? 0,
                            'product_id' => $model['id'] ?? 0,
                            'cp_category_name' => $cate['name'],
                            'cp_product_brand_name' => $brand['name'],
                            'cp_product_standard_name' => $stan['name'],
                            'cp_product_mode' => $model['name'],
                            'product_nums' => 1,
                            'service_fee' => 0,
                            'service_fee_modify' => 0,
                            'frozen_money' => '0.00',
                            'factory_repair_fee' => '0.00',
                            'factory_repair_fee_modify' => '0.00',
                            'user_service_request' => $pv['service_request'],
                        ];
                    }
                }

                $worker_order[$k] = [
                    'factory_id' => AuthService::getAuthModel()->getPrimaryValue(),
//                    'orno' => OrderService::generateOrno(),
                    'worker_order_type' => $this->getFactoryWorkerOrderTypeForPlatform(PlatformApiService::$data['order_type']),
                    'worker_order_status' => OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE,
                    'factory_check_order_time' => NOW_TIME,
                    'factory_check_order_type' => 1,
                    'factory_check_order_id' => AuthService::getAuthModel()->getPrimaryValue(),
                    'origin_type' => OrderService::ORIGIN_TYPE_FACTORY,
                    'add_id' => AuthService::getAuthModel()->getPrimaryValue(),
                    'service_type' => PlatformApiService::$data['service_type'],
                    'create_time' => NOW_TIME,
                    'distribute_mode' => OrderService::DISTRIBUTE_MODE_CHOOSE_WORKER ,
                    'create_remark' => PlatformApiService::$data['remark'],
                ];

                $worker_order_user_info[$k] = [
                    'province_id' => $area[$k]['province']['id'],
                    'city_id' => $area[$k]['city']['id'] ?? 0,
                    'area_id' => $area[$k]['district']['id'] ?? 0,
                    'real_name' => $v['user_info']['contact_name'],
                    'phone' => $v['user_info']['contact_number'],
                    'cp_area_names' => implode('-', $area[$k]['names']),
                    'address' => $v['user_info']['area_detail'],
                ];

                $worker_order_ext_info[$k] = [
                    'factory_helper_id' => $this->helper['id'] ?? 0,
                    'cp_factory_helper_name' => $this->helper['name'],
                    'cp_factory_helper_phone' => $this->helper['telephone'],
                    'out_trade_number' => $k,
                    'out_platform' => PlatformApiService::$config['OUT_PLATFORM'],
                ];


                $service_fee = 0;
                if ($this->is_insured && in_array(PlatformApiService::$data['service_type'], [OrderService::TYPE_WORKER_INSTALLATION, OrderService::TYPE_PRE_RELEASE_INSTALLATION])) {
                    $service_fee = C('ORDER_INSURED_SERVICE_FEE');
                }
                $worker_order_fee[$k] = [
                    'insurance_fee' => OrderService::INSURANCE_FEE_DEFAULT_VALUE,
                    'factory_repair_fee'        => 0,
                    'factory_repair_fee_modify' => 0,
                    'service_fee'               => $service_fee,
                    'service_fee_modify'        => $service_fee,
                    'factory_total_fee'         => $service_fee,
                    'factory_total_fee_modify'  => $service_fee,
                ];

            } catch (\Exception $e) {
                $error[$k]['error_code'] = $e->getCode();
                $error[$k]['error_message'] = $e->getMessage();
                continue;
            }
        }

        $return['error'] = $error;
        $return['worker_order'] = $worker_order;
        $return['worker_order_product'] = $worker_order_product;
        $return['worker_order_user_info'] = $worker_order_user_info;
        $return['worker_order_ext_info'] = $worker_order_ext_info;
        $return['worker_order_fee'] = $worker_order_fee;
    }

}
