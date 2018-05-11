<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/8
 * Time: 15:57
 */

namespace Admin\Logic;


use Admin\Model\BaseModel;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AreaService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\XinYingYngService;
use Illuminate\Support\Arr;

class XinYingYanLogic extends BaseLogic
{
    /**
     * 检查新迎燕下单数据，并组装成工单数据，创建工单
     * @param $data
     * @param array $error
     */
    public function platformOrderDataCheckAndCreate($factory, $data, &$error = [])
    {
        // throw new \Exception("", '');
        $factory_id = $factory['factory_id'];
        $order_datas = (array)$data['data'];

        $worker_order_type = XinYingYngService::WORKEKR_ORDER_TYPE_ARR_VALUE[$data['type']];
        $worker_order_status = OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE;
        $service_type = OrderService::TYPE_PRE_RELEASE_INSTALLATION;
        $create_remark = $data['remark'];
        // 是否为保内单： true 保内单；false 保外单；
        $is_insured = $data['type'] == XinYingYngService::WORKEKR_ORDER_TYPE_IN ? true : false;

        $helper = BaseModel::getInstance('factory_helper')->getOne([
            'field' => 'id,name,telephone',
            'where' => [
                'factory_id' => $factory_id,
            ],
            'order' => 'is_default desc,id asc',
        ]);
        if (!$helper) {
            $helper = [
                'id' => 0,
                'name' => $factory['linkphone'],
                'telephone' => $factory['linkman'],
            ];
        }

        $worker_order = [];
        $order_products = [];
        $ext_info = [];
        $fee = [];
        $user = [];
        $express = [];

        // 组装数据 方便后续 添加数据时再验证数据
        $next_check = [];
        $area = $product_data = $categories = $standards = $brands = $models = [];

        $platform_sns = implode(',', array_column($order_datas, 'platform_order_sn'));
        $exit_sns = $platform_sns ? BaseModel::getInstance('worker_order_ext_info')->getList([
            'field' => 'out_trade_number,worker_order_id',
            'where' => [
                'out_platform' => 1,
                'out_trade_number' => ['in', $platform_sns],
            ],
            'index' => 'out_trade_number',
        ]) : [];

        $cancels_id_index = [];
        if ($exit_sns) {
            $created_ids = array_column($exit_sns, 'worker_order_id');
            $cancels_id_index = $created_ids ? BaseModel::getInstance('worker_order')->getList([
                'field' => 'id',
                'where' => [
                    'id' => ['in', implode(',', $created_ids)],
                    'cancel_status' => ['not in', OrderService::CANCEL_TYPE_NULL.','.OrderService::CANCEL_TYPE_CS_STOP],
                ],
                'index' => 'id',
            ]) : [];
        }

        foreach ($order_datas as $k => $v) {
            $platform_sn = $v['platform_order_sn'];

            if (isset($product_data[$platform_sn])) {
                continue;
            }

            $created_id = $exit_sns[$platform_sn]['worker_order_id'];
            if ($created_id && !$cancels_id_index[$created_id]) {
                $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_HAS_PLATFORM_SN;
                $error[$platform_sn]['error_message'] = '重复下单';
                continue;
            }

            $products = $v['products'];

            if (!$platform_sn) {
                $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$platform_sn]['error_message'] = '平台订单号不能为空';
                continue;
            }

            if (empty($products)) {
                $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$platform_sn]['error_message'] = '产品信息不能为空';
                continue;
            }

            $receiver_area = array_filter(explode('-', $v['receiver_area']));
            if (empty($receiver_area)) {
                $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$platform_sn]['error_message'] = '用户地区不能为空';
                continue;
            }

            if (empty($v['receiver_address'])) {
                $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$platform_sn]['error_message'] = '用户地址不能为空';
                continue;
            }

            if (empty($v['receiver_name'])) {
                $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$platform_sn]['error_message'] = '用户姓名不能为空';
                continue;
            }

            if (empty($v['receiver_phone'])) {
                $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$platform_sn]['error_message'] = '用户手机号码不能为空';
                continue;
            }

            $area[$platform_sn] = implode('', $receiver_area);

            $product_data[$platform_sn] = $products;
            foreach ($products as $p_k => $product) {
                if (isset($error[$platform_sn])) {
                    continue;
                }

                if ($product['number'] < 1) {
                    $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                    $error[$platform_sn]['error_message'] = '产品数量不能为空';
                }

                $cp_category_name = $product['cp_category_name'];
                $cp_standard_name = $product['cp_standard_name'];

                if (empty($cp_category_name)) {
                    $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                    $error[$platform_sn]['error_message'] = '分类不能为空';
                    continue;
                }

                if (empty($cp_standard_name)) {
                    $error[$platform_sn]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                    $error[$platform_sn]['error_message'] = '规格不能为空';
                    continue;
                }

                $cp_brand_name = $product['cp_brand_name'];
                $cp_model_name = $product['cp_model_name'];

                $categories[$platform_sn][$cp_category_name] = $cp_category_name;
                $standards[$platform_sn][$cp_standard_name] = $cp_standard_name;
                $brands[$platform_sn][$cp_brand_name] = $cp_brand_name;
                $models[$platform_sn][$cp_model_name] = $cp_model_name;
            }

            if (isset($error[$platform_sn])) {
                unset($area[$platform_sn], $product_data[$platform_sn]);
                continue;
            }

            !empty($v['expresses']) && $express[$platform_sn] = $v['expresses'];
            unset($v['expresses']);
            $next_check[$platform_sn] = $v;
        }

        if (empty($next_check)) {
            return ;
        }
        // 地址
        $area = AreaService::areaRuleResult($area);
        // 分类
        $searh_cates = [];
        foreach ($categories as $v) {
            $searh_cates = array_merge($searh_cates, $v);
        }
        $searh_cates = implode(',', $searh_cates);
        $cate_list = BaseModel::getInstance('product_category')->getList([
            'field' => 'id,name',
            'where' => [
                'name' => ['in', $searh_cates],
            ],
            'index' => 'name',
        ]);
        // 规格
        $searh_stans = [];
        foreach ($standards as $v) {
            $searh_stans = array_merge($searh_stans, $v);
        }
        $searh_stans = implode(',', $searh_stans);
        $stan_list = BaseModel::getInstance('product_standard')->getList([
            'field' => 'standard_id as id,standard_name as name,concat(product_id,"_",standard_name) as search_key',
            'where' => [
                'standard_name' => ['in', $searh_stans],
            ],
            'index' => 'search_key',
        ]);
        // 品牌
        $searh_brans = [];
        foreach ($brands as $v) {
            $searh_brans = array_merge($searh_brans, $v);
        }
        $searh_brans = implode(',', $searh_brans);
        $bran_list = BaseModel::getInstance('factory_product_brand')->getList([
            'field' => 'id,concat(product_cat_id,"_",product_brand) as search_key',
            'where' => [
                'product_brand' => ['in', $searh_brans],
                'factory_id' => $factory_id,
            ],
            'index' => 'search_key',
        ]);
        // 型号
        $searh_modes = [];
        foreach ($models as $v) {
            $searh_modes = array_merge($searh_modes, $v);
        }
        $searh_modes = implode(',', $searh_modes);
        $mode_where = [
            'product_xinghao' => ['in', $searh_modes],
            'factory_id' => $factory_id,
//            'product_category' => ['in', $searh_cates],
//            'product_guige' => ['in', $searh_stans],
        ];
        $mode_list = BaseModel::getInstance('factory_product')->getList([
            'field' => 'product_id as id,concat(product_category,"_",product_guige,"_",product_brand,"_",product_xinghao) as search_key',
            'where' => $mode_where,
            'index' => 'search_key',
        ]);
        $cate_ids = $order_frozen_money = $operation_recode_products = [];
        $factory_frozen_money = 0;

        foreach ($next_check as $k => $v) {
            $order_frozen_money[$k] = 0;
            // 地址数据检查
            if (!$area[$k]['province']['id']) { //  || !$area[$k]['city']['id'] || !$area[$k]['district']['id']
                $error[$k]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$k]['error_message'] = '地址信息不足，请完善地址信息(省)';
                continue;
            }
            !$area[$k]['city']['id'] && $area[$k]['city']['id'] = 0;
            !$area[$k]['district']['id'] && !$area[$k]['district']['id'] = 0;

            // 产品
            $mode_error = $bran_error = $cate_error = $stan_error = [];
            $add_product = [];
            $brand_model = BaseModel::getInstance('factory_product_brand');
            $model_model = BaseModel::getInstance('factory_product');
            foreach ($v['products'] as $product) {
                // 分类
                if (!$cate_list[$product['cp_category_name']]) {
                    $cate_error[] = $product['cp_category_name'];
                    continue;
                }
                $cate_id = $categories[$k][$product['cp_category_name']] = $cate_list[$product['cp_category_name']]['id'];
                // 规格
                $stan_key = $cate_id.'_'.$product['cp_standard_name'];
                if (!$stan_list[$stan_key]) {
                    $stan_erro[] = $product['cp_standard_name'];
                    continue;
                }
                $stan_id = $standards[$k][$product['cp_standard_name']] = $stan_list[$stan_key]['id'];
                // 品牌、 型号
                $bran_id = '';
                if (!empty($product['cp_brand_name'])) {
                    $bran_key = $cate_id.'_'.$product['cp_brand_name'];
                    if (!$bran_list[$bran_key]) {
                        try {
                            $bran_id = $brand_model->insert([
                                'factory_id' => $factory_id,
                                'product_cat_id' => $cate_id,
                                'product_brand' => $product['cp_brand_name'],
                                'is_show' => 1,
                            ]);
                        } catch (\Exception $e) {
                            $bran_error[] = $product['cp_brand_name'];
                            continue;
                        }
                    } else {
                        $bran_id = $bran_list[$bran_key]['id'];
                    }
                    $brands[$k][$product['cp_brand_name']] = $bran_id;
                }
                $prod_id = '';
                if (!empty($product['cp_model_name'])) {
                    $mode_key = $cate_id.'_'.$stan_id.'_';
                    $mode_key .= !empty($product['cp_brand_name']) ? $bran_id : '';
                    $mode_key .= '_'.$product['cp_model_name'];
                    if (!$mode_list[$mode_key]) {
                        try {
                            $prod_id = $model_model->insert([
                                'factory_id' => $factory_id,
                                'product_xinghao' => $product['cp_model_name'],
                                'product_category' => $cate_id,
                                'product_guige' => $stan_id,
                                'product_brand' => $bran_id,
                            ]);
                        } catch (\Exception $e) {
                            $mode_error[] = $product['cp_model_name'];
                            continue;
                        }
                    } else {
                        $prod_id = $mode_list[$mode_key]['id'];
                    }
                }
                $operation_recode_products[$k][] = "{$product['cp_brand_name']}-{$product['cp_standard_name']}-{$product['cp_model_name']}-{$product['cp_category_name']} X{$product['number']}";
                $cate_ids[$cate_id] = $cate_id;
                $frozen_money = $is_insured ? FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($service_type, $factory_id, $cate_id, $stan_id, $factory['default_frozen']) : 0;
                for ($i = $product['number'];$i >= 1;$i--) {
                    $add_product[] = [
                        'product_category_id' => $cate_id,
                        'product_standard_id' => $stan_id,
                        'product_brand_id' => $bran_id ?? 0,
                        'product_id' => $prod_id ?? 0,
                        'cp_category_name' => $product['cp_category_name'],
                        'cp_product_brand_name' => $product['cp_brand_name'],
                        'cp_product_standard_name' => $product['cp_standard_name'],
                        'cp_product_mode' => $product['cp_model_name'],
                        'product_nums' => 1,
                        'service_fee' => 0,
                        'service_fee_modify' => 0,
                        'frozen_money' => $frozen_money,
                        'factory_repair_fee' => $frozen_money,
                        'factory_repair_fee_modify' => $frozen_money,
                    ];
                    $factory_frozen_money += $frozen_money;
                    $order_frozen_money[$k] += $frozen_money;
                }
            }

            if (!empty($cate_error)) {
                $error[$k]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$k]['error_message'] = reset($cate_error).'：分类不存在';
                continue;
            }
            if (!empty($stan_error)) {
                $error[$k]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$k]['error_message'] = reset($stan_error).'：规格不存在';
                continue;
            }
            if (!empty($bran_error)) {
                $error[$k]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$k]['error_message'] = reset($bran_error).'：品牌添加失败';
                continue;
            }
            if (!empty($mode_error)) {
                $error[$k]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_DATA_IMPERFECT;
                $error[$k]['error_message'] = reset($mode_error).'：型号添加失败';
                continue;
            }

            $worker_order[$k] = [
                'factory_id' => $factory_id,
                'orno' => OrderService::generateOrno(),
                'worker_order_type' => $worker_order_type,
                'worker_order_status' => $worker_order_status,
                'factory_check_order_time' => NOW_TIME,
                'factory_check_order_type' => 1,
                'factory_check_order_id' => $factory_id,
                'origin_type' => OrderService::ORIGIN_TYPE_FACTORY,
                'add_id' => $factory_id,
                'service_type' => $service_type,
                'create_time' => NOW_TIME,
                'distribute_mode' => OrderService::DISTRIBUTE_MODE_CHOOSE_WORKER ,
                'create_remark' => $create_remark,
            ];
            $ext_info[$k] = [
                'factory_helper_id' => $helper['id'],
                'cp_factory_helper_name' => $helper['name'],
                'cp_factory_helper_phone' => $helper['telephone'],
                'out_trade_number' => $k,
                'out_platform' => OrderService::OUT_PLATFORM_XINYINGYAN,
            ];
            $order_products[$k] = $add_product;

            $service_fee = 0;
            if ($is_insured && in_array($service_type, [OrderService::TYPE_WORKER_INSTALLATION, OrderService::TYPE_PRE_RELEASE_INSTALLATION])) {
                $service_fee = C('ORDER_INSURED_SERVICE_FEE');
                $order_frozen_money[$k] += $service_fee;
                $factory_frozen_money += $service_fee;
            }
            $fee[$k] = [
                'insurance_fee' => OrderService::INSURANCE_FEE_DEFAULT_VALUE,
                'factory_repair_fee'        => 0,
                'factory_repair_fee_modify' => 0,
                'service_fee'               => $service_fee,
                'service_fee_modify'        => $service_fee,
                'factory_total_fee'         => $service_fee,
                'factory_total_fee_modify'  => $service_fee,
            ];

            $user[$k] = [
                'province_id' => $area[$k]['province']['id'],
                'city_id' => $area[$k]['city']['id'],
                'area_id' => $area[$k]['district']['id'],
                'real_name' => $v['receiver_name'],
                'phone' => $v['receiver_phone'],
                'cp_area_names' => implode('-', $area[$k]['names']),
                'address' => $v['receiver_address'],
            ];
        }
//        $factory_money = BaseModel::getInstance('factory')->getOne($factory_id, 'money,frozen_money');
//        if ($factory_money['money'] - $factory_money['frozen_money'] - $factory_frozen_money < 0) {
//            throw new \Exception('资金不足，下单失败');
//        }

        M()->startTrans();
        $order_model = BaseModel::getInstance('worker_order');
        $fee_model = BaseModel::getInstance('worker_order_fee');
        $product_model = BaseModel::getInstance('worker_order_product');
        $userinfo_model = BaseModel::getInstance('worker_order_user_info');
        $ext_model = BaseModel::getInstance('worker_order_ext_info');
        $statistics_model = BaseModel::getInstance('worker_order_statistics');
        $express_model = BaseModel::getInstance('express_tracking');
        foreach ($worker_order as $k => $v) {
            $factory_money = BaseModel::getInstance('factory')->getOne($factory_id, 'money,frozen_money');
            if ($factory_money['money'] - $factory_money['frozen_money'] - $order_frozen_money[$k] < 0) {
                $error[$k]['error_code'] = XinYingYngService::ORDER_RETURN_CODE_OTHER;
                $error[$k]['error_message'] = '资金不足';
                continue;
            }

            $id = $order_model->insert($v);

            $fee[$k]['worker_order_id'] = $id;
            $fee_model->insert($fee[$k]);

            $user[$k]['worker_order_id'] = $id;
            $userinfo_model->insert($user[$k]);

            $ext_info[$k]['worker_order_id'] = $id;
            $ext_model->insert($ext_info[$k]);

            $statistics_model->insert(['worker_order_id' => $id]);

            foreach ($order_products[$k] as $pro_k => &$pro_v) {
                $pro_v['worker_order_id'] = $id;
            }

            $product_model->insertAll($order_products[$k]);

            // 冻结金资金记录
            if ($is_insured && $worker_order_status != OrderService::STATUS_CREATED) {
                FactoryMoneyFrozenRecordService::process($id, FactoryMoneyFrozenRecordService::TYPE_FACTORY_ADD_ORDER, $order_frozen_money[$k]);
            }

            // 添加操作记录 新迎燕只有预发件安装单
            OrderOperationRecordService::create($id, OrderOperationRecordService::FACTORY_ORDER_CREATE, [
                'operator_id'    => $factory_id,
                'content_replace' => [
                    'pre_text' => '（电商API）',
                    'order_products' => '<br/>' . implode('<br/>', $operation_recode_products[$k]) . '<br/>',
                    'service_type'   => OrderService::SERVICE_TYPE[$service_type],
                ],
            ]);

            if ($express[$k]) {
                $insert_express = [];
                foreach ($express[$k] as $ex_k => $ex_v) {
                    if (!$ex_v['number']) {
                        continue;
                    }
                    $insert_express[] = [
                        'express_number' => $ex_v['number'],
                        'data_id' => $id,
                        'state' => $ex_v['status'],
                        'is_book' => 0,
                        'conten' => json_encode($ex_v['detail'], JSON_UNESCAPED_UNICODE),
                        'type' => '3',
                        'create_time' => NOW_TIME,
                    ];
                }
                $insert_express && $express_model->insertAll($insert_express);
            }

            $error[$k] = [
                'result' => XinYingYngService::RETURN_RESULT_CREATE_ORDER_SUCCESS,
                'error_code' => null,
                'error_message' => '',
                'platform_order_sn' => $next_check[$k]['platform_order_sn'],
                'order_sn' => $v['orno'],
                'status' => $this->returnCodeOrderStatus($v['worker_order_status']) ,
                'tag' => XinYingYngService::RETURN_TAG_NEEK_CHECKER_CHECK_OR_NOT_AUDIT, // 下单时属于无标识
            ];
        }
        M()->commit();
    }

    public function returnCodeOrderStatus($worker_order_status, $cancel_status = 0)
    {
        if (in_array($cancel_status, XinYingYngService::CANCEL_ORDER_STATUS_ARR)) {
            return XinYingYngService::ORDER_STATUS_WORKER_CANCEL_ORDER;
        }
        return XinYingYngService::ORDER_STATUS_KEY_VALUE[$worker_order_status] ??  false;
    }

    public function returnTagAccessoryStatus($acce_status, $cancel_status, $is_giveup_return)
    {
        if (AccessoryService::STATUS_WORKER_TAKE && $is_giveup_return != AccessoryService::RETURN_ACCESSORY_PASS) {
            return XinYingYngService::RETURN_TAG_WAIT_FOR_WORKER_GET_GETED_OR_FACTORY_NOT_CHECK_END_OR_ADMIN_END;
        }
        if (in_array($cancel_status, XinYingYngService::RETURN_TAG_CANCELL_STATUS)) {
            return XinYingYngService::RETURN_TAG_CANCELL_STATUS;
        }
        return XinYingYngService::RETURN_TAG_KEY_VALUE[$acce_status] ?? false;
    }

    public function setPushRuleTime($n = 1)
    {
        $arr = [
            1 => 10*60,
            2 => 30*60,
            3 => 60*60,
            4 => 100*60,
            5 => 150*60,
        ];
        if (!$arr[$n]) {
            return false;
        }
        return NOW_TIME + $arr[$n];
    }

}
