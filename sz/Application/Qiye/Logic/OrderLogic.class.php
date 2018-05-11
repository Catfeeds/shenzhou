<?php
/**
 * @User 嘉诚
 * @Date 2017/11/13
 * @mess 订单
 */
namespace Qiye\Logic;

use Admin\Logic\WorkbenchLogic;
use Admin\Repositories\Events\WorkbenchEvent;
use Common\Common\Repositories\Events\UpdateOrderNumberEvent;
use Common\Common\Service\AuthService;
use Common\Common\Service\FaultTypeService;
use Common\Common\Service\GroupService;
use Common\Common\Service\OrderUserService;
use Common\Common\Service\OrderExtInfoService;
use Common\Common\Service\SMSService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use Flc\Dysms\Request\SendSms;
use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Library\Common\Util;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\SystemMessageService;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerOrderAppointRecordService;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\ApplyCostService;
use Common\Common\Service\ProductFaultService;

class OrderLogic extends BaseLogic
{

    /*
     * 工单列表
     */
    public function getList($request, $user_id)
    {
        $where['wo.worker_id|wo.children_worker_id'] = $user_id;
        if (isset($request['keyword']) && !empty($request['keyword'])) {
            $where['wou.phone|wou.address'] = ['like', '%'.$request['keyword'].'%'];
        }
        if (isset($request['group_id']) && !empty($request['group_id'])) {
            $where['wo.worker_group_id'] = $request['group_id'];
        }
        if (isset($request['worker_id']) && !empty($request['worker_id'])) {
            $group_owner_id = GroupService::getOwnerId($request['group_id']);
            if ($request['worker_id'] != $group_owner_id) {
                $where['wo.children_worker_id'] = $request['worker_id'];
                $where['wo.worker_id'] = $group_owner_id;
            }
        }
        if ($request['show_distribute_type'] == '1') {
            $where['wo.children_worker_id'] = ['exp', 'is null'];
        } elseif ($request['show_distribute_type'] == '2') {
            $where['wo.children_worker_id'] = ['gt', 0];
        }
        $where['wo.cancel_status'] = ['IN', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]];
        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');
        switch ($request['status']) {
            case '2' :
                //待服务
                if (!empty($request['worker_id']) && !empty($request['group_id'])) {
                    $where['wo.worker_order_status'] = OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE;
                } else {
                    $where['wo.worker_order_status'] = ['in', OrderService::getOrderInService()];
                }
                break;
            case '3' :
                //待签收
                $worker_order_ids = $accessory_model->getFieldVal([
                    'worker_id' => !empty($request['group_id']) ? $group_owner_id : $user_id,
                    'accessory_status' => AccessoryService::STATUS_FACTORY_SENT,
                    'cancel_status' => 0
                ], 'worker_order_id', true);
                $worker_order_ids = !empty($worker_order_ids) ? implode(',', $worker_order_ids) : '0';
                $where['wo.id'] = ['in', $worker_order_ids];
                break;
            case '4' :
                //待返件
                $worker_order_ids = $accessory_model->getFieldVal([
                    'worker_id' => !empty($request['group_id']) ? $group_owner_id : $user_id,
                    'accessory_status' => AccessoryService::STATUS_WORKER_TAKE,
                    'cancel_status'    => 0,
                    'is_giveup_return' => 0
                ], 'worker_order_id', true);
                $worker_order_ids = !empty($worker_order_ids) ? implode(',', $worker_order_ids) : '0';
                $where['wo.id'] = ['in', $worker_order_ids];
                break;
            case '5' :
                //待结算
                $where['wo.worker_order_status'] = ['in', [10, 11, 13, 14, 15]];
                break;
            case '6' :
                //已完结
                if (!empty($request['worker_id']) && !empty($request['group_id'])) {
                    $where['wo.worker_order_status'] = ['in', OrderService::getOrderCompleteInGroup()];
                } else {
                    $where['wo.worker_order_status'] = ['in', OrderService::getOrderCompleteIn()];
                }
                break;
            case '7' :
                //需安排上门工单
                if (empty($request['date'])) {
                    $request['date'] = NOW_TIME;
                }
                $request['date'] = strtotime(date('Y-m-d', $request['date']));
                $where['wo.worker_order_status'] = ['in', [8, 9, 12]];
                $appoint_model = BaseModel::getInstance('worker_order_appoint_record');
                $appoints = $appoint_model->getList([
                    'where' => [
                        'appoint_time' => ['between', [$request['date'], $request['date']+86400]],
                        'worker_id' => !empty($request['group_id']) ? $group_owner_id : $user_id
                    ],
                    'field' => 'worker_order_id, create_time'
                ]);
                foreach ($appoints as $k => $v) {
                    $appoint_id = $appoint_model->getFieldVal([
                        'create_time' => ['gt', $v['create_time']],
                        'appoint_time' => ['gt', $request['date']+86400],
                        'worker_id' => !empty($request['group_id']) ? $group_owner_id : $user_id
                    ], 'id');
                    if (empty($appoint_id)) {
                        $worker_order_ids[] = $v['worker_order_id'];
                    }
                }
                $worker_order_ids = !empty($worker_order_ids) ? implode(',', $worker_order_ids) : '0';
                $appoint_worker_order_ids = $appoint_model->getFieldVal([
                    'where' => [
                        'appoint_time' => ['between', [$request['date'], $request['date']+86400]],
                        'appoint_status' => ['in', '1,2,5'],
                        'worker_order_id' => ['in', $worker_order_ids]
                    ]
                ], 'worker_order_id', true);
                $appoint_worker_order_ids = !empty($appoint_worker_order_ids) ? implode(',', $appoint_worker_order_ids) : '0';
                $where['wo.id'] = ['in', $appoint_worker_order_ids];
                break;
            case '8':
                //服务中
                $where['wo.worker_order_status'] = ['in', [OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE]];
                break;
            default:
                //待预约
                $where['wo.worker_order_status'] = ['in', [OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]];
                break;
        }
        $option = [
            'alias' => 'wo',
            'where' => $where,
            'join'  => 'left join worker_order_user_info as wou on wou.worker_order_id=wo.id',
            'field' => 'wo.id, wo.worker_id, wo.orno, wo.service_type, wo.worker_order_type, wo.cancel_status, wo.worker_order_status, wo.extend_appoint_time, wo.create_time, wo.worker_receive_time, wo.worker_first_appoint_time, wo.add_id, wo.origin_type, wo.children_worker_id, wo.worker_group_id'
        ];
        $worker_order_model = BaseModel::getInstance('worker_order');
        $product_model      = BaseModel::getInstance('worker_order_product');
        $count = $worker_order_model->getNum([
            'alias' => 'wo',
            'where' => $where,
            'join'  => 'left join worker_order_user_info as wou on wou.worker_order_id=wo.id'
        ]);
        $option['order'] = 'wo.create_time desc, wo.id desc';
        $option['group'] = 'wo.id';
        $option['limit'] = $this->page();
        $list = $worker_order_model->getList($option);
        $key_out_fees = $is_not_insurance_ids = [];
        if (!empty($list)) {
            $ids = $is_not_insurance_ids = [];
            foreach ($list as $k => $v) {
                $ids[] = $v['id'];
                !in_array($v['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)
                &&  $is_not_insurance_ids[] = $v['id'];
            }
            $ids = array_unique($ids);
            $is_not_insurance_ids = array_unique($is_not_insurance_ids);

            //用户信息
            $users_arr = BaseModel::getInstance('worker_order_user_info')->getList([
                'where' => [
                    'worker_order_id' => ['in', $ids]
                ],
                'group' => 'worker_order_id'
            ]);
            foreach ($users_arr as $user_val) {
                $user[$user_val['worker_order_id']] = $user_val;
                $area_name = explode('-', $user[$user_val['worker_order_id']]['cp_area_names']);
                $user[$user_val['worker_order_id']]['province_name'] = $area_name[0];
                $user[$user_val['worker_order_id']]['city_name'] = $area_name[1];
                $user[$user_val['worker_order_id']]['area_name'] = $area_name[2];
                $user[$user_val['worker_order_id']]['pay_type_detail'] = WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO;
            }

            $ext_info = BaseModel::getInstance('worker_order_ext_info')->getList([
                'where' => [
                    'worker_order_id' => ['in', $ids]
                ],
                'index' => 'worker_order_id',
                'field' => 'worker_order_id,worker_group_set_tag',
            ]);
            $worker_group_set_tags = getXarr(array_column($ext_info, 'worker_group_set_tag'));

            //worker_order_statistics工单额外信息
            $worker_order_statistics = BaseModel::getInstance('worker_order_statistics')->getList([
                'where' => [
                    'worker_order_id' => ['in', $ids]
                ],
                'index' => 'worker_order_id',
                'field' => 'worker_order_id, total_accessory_num, cost_order_num'
            ]);

            //预约信息
            $appoint_times = BaseModel::getInstance('worker_order_appoint_record')->getList([
                'where' => [
                    'worker_order_id' => ['in', $ids],
                    'is_sign_in'      => ['gt', 0]
                ],
                'index' => 'worker_order_id',
                'field' => 'worker_order_id, appoint_time',
                'order' => 'create_time',
            ]);

            //维修项信息
            $products_arr = $product_model->getList([
                'alias' => 'p',
                'where' => [
                    'p.worker_order_id' => ['in', $ids]
                ],
                'join'  => 'left join factory_product as fp on fp.product_id=p.product_id
                            left join cm_list_item as cli on cli.list_item_id=p.product_category_id',
                'field' => 'p.id, p.worker_order_id, p.cp_category_name, p.cp_product_brand_name, p.cp_product_standard_name, p.cp_product_mode, fp.product_thumb, p.cp_fault_name, p.is_complete, fp.product_thumb, p.fault_label_ids, p.cp_fault_name, cli.item_thumb'
            ]);

            // 保外单费用
            $out_fees = $is_not_insurance_ids ? BaseModel::getInstance('worker_order_out_worker_add_fee')->getList([
                'field' => 'worker_order_id,is_add_fee,pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee_modify,pay_time',
                'where' => [
                    'worker_order_id' => ['in', $is_not_insurance_ids],
                ],
            ]) : [];

            foreach ($out_fees as $k => $v) {
                $id = $v['worker_order_id'];
                $total_fee_modify = $v['total_fee_modify'];
                unset($v['worker_order_id'], $v['total_fee_modify']);
                $key_out_fees[$id]['total_fee'] += $total_fee_modify;

                if ($v['pay_type'] != WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO && $v['pay_time']) {
                    $key_out_fees[$id]['pay_total_fee'] += $total_fee_modify;
                    $user[$id]['pay_type_detail'] = $v['pay_type'];
                }

                $key_out_fees[$id]['out_fees'][] = $v;
            }

        }

        foreach ($list as $k => $v) {
            // 标记
            $order_tag_value = $ext_info[$v['id']]['worker_group_set_tag'];
            $worker_group_tag = array_values(array_intersect_key(OrderExtInfoService::WORKER_GROUP_SET_TAG_INDEX_KEY_VALUE, array_flip($worker_group_set_tags[$order_tag_value])));
//            $list[$k]['worker_group_tag'] = $worker_group_tag ?? null;
            $list[$k]['worker_is_settlement'] = in_array(OrderExtInfoService::WORKER_GROUP_SET_TAG_SETTLEMENT_ON_WORKER_MEMBER, $worker_group_tag) ? '1' : '0';

            foreach ($products_arr as $product_key => $product_val) {
                if ($product_val['worker_order_id'] == $v['id']) {
                    if (!strpos($product_val['product_thumb'], 'http')) {
                        $products_arr[$product_key]['product_thumb'] = !empty($product_val['product_thumb']) ? Util::getServerFileUrl($product_val['product_thumb']) : Util::getServerFileUrl($product_val['item_thumb']);
                    }
                    $products_arr[$product_key]['product_schedule'] = $this->getProductSchedule($v['id'], $product_val['is_complete']);
                    $list[$k]['product'][] = $products_arr[$product_key];
                }
            }
            $list[$k]['product_num'] = count($list[$k]['product']);

            //配件单、费用单数
            $list[$k]['return_accessory_num'] = $this->getApplyAccessoryCount($v['id']);

            //用户信息
            //$list[$k]['user'] = $this->getOrderUserInfo($v['id']);
            $list[$k]['user'] = $user[$v['id']];

            if ($v['service_type'] == '110') {
                $data_id = $v['id'];
                $accessory_status = '';
            } else {
                //获取配件单信息
                $accessory_info = $this->getApplyAccessory($v['id'], 'id, accessory_status');
                $data_id = $accessory_info['id'];
                $accessory_status = $accessory_info['accessory_status'];
            }
            //物流单号
            $list[$k]['express_track'] = $this->getExpressTracking($data_id, $v['service_type'], $accessory_status);

            //标签
            $list[$k]['label'] = $this->getLabel($v['service_type'], $v['worker_order_type'], $list[$k]['product_num']);

            //工单最新进度
            $list[$k]['total_accessory_num'] = $worker_order_statistics[$v['id']]['total_accessory_num'] ?? '0';
            $list[$k]['cost_order_num'] = $worker_order_statistics[$v['id']]['cost_order_num'] ?? '0';
            $list[$k]['appoint_time'] = $appoint_times[$v['id']]['appoint_time'] ?? null;
            $order_schedule = $this->getOrderSchedule($v, [
                'total_accessory_num' => $worker_order_statistics[$v['id']]['total_accessory_num'] ?? '0',
                'cost_order_num' => $worker_order_statistics[$v['id']]['cost_order_num'] ?? '0'
            ], $list[$k]['user'], '', $user_id);
            $list[$k]['order_schedule'] = $order_schedule['order_schedule'];
            $list[$k]['is_show_schedule'] = $order_schedule['is_show_schedule'];

            //获取下单人信息
            $list[$k]['add_user_info'] = D('Order')->getAddUserInfo($v['add_id'], $v['origin_type']);
            $list[$k]['out_fee_info'] = null;
            in_array($v['id'], $is_not_insurance_ids)
            &&  isset($key_out_fees[$v['id']])
            &&  $list[$k]['out_fee_info'] = [
                'total_fee' => number_format($key_out_fees[$v['id']]['total_fee'], 2, '.', ''),
                'pay_total_fee' => number_format($key_out_fees[$v['id']]['pay_total_fee'], 2, '.', ''),
                'out_fees' => $key_out_fees[$v['id']]['out_fees'] ?? null,
            ];
//            if (is_array($list[$k]['out_fee_info'])) {
//                $list[$k]['out_fee_info'] += [
//
//                ];
//            }

            //子账号信息
            $handle_worker_id = !empty($v['children_worker_id']) ? $v['children_worker_id'] : $v['worker_id'];
            $list[$k]['handle_worker_nickname'] = BaseModel::getInstance('worker')->getFieldVal($handle_worker_id, 'nickname').'师傅';
        }
        return $this->paginate($list, $count);
    }

    /*
     * 工单详情
     */
    public function detail($order_id, $user_id)
    {
//        $worker_order_model = BaseModel::getInstance('worker_order');
        $product_model      = BaseModel::getInstance('worker_order_product');
        $fault_label_model  = BaseModel::getInstance('product_fault_label');

        $field = 'id, worker_id, orno, parent_id, service_type, worker_order_type, extend_appoint_time, create_time, distribute_time, worker_first_appoint_time, cancel_status, worker_order_status, distributor_id, worker_receive_time, add_id, origin_type, create_remark, worker_group_id, children_worker_id';
        $detail = $this->checkWorkerOrder($order_id, $user_id, $field);
        if (!empty($detail)) {
            $detail['classification'] = OrderService::getClassificationByOrno($detail['orno']);
            $detail['has_rework_order'] = '0';
            if ($detail['classification'] == OrderService::CLASSIFICATION_COMMON_ORDER_TYPE) {
                $parent_order_id = BaseModel::getInstance('worker_order')->getFieldVal(['orno' => ['LIKE', 'F%'], 'parent_id' => $detail['id']], 'id');
                $parent_order_id && $detail['has_rework_order'] = '1';
            }

            $detail['appoint_time'] = $this->getLastAppointTime($order_id, $detail['worker_id']);

            if ($detail['worker_order_status'] == '7') {
                $detail['status'] = '1';
            } elseif ($detail['worker_order_status'] == '8') {
                $detail['status'] = '2';
            } elseif (in_array($detail['worker_order_status'], ['9', '12'])) {
                $detail['status'] = '3';
            } elseif (in_array($detail['worker_order_status'], ['10', '11', '13', '14', '15'])) {
                $detail['status'] = '4';
            } elseif (in_array($detail['worker_order_status'], ['16', '17', '18'])) {
                $detail['status'] = '5';
            }

            //维修项
            $detail['product_num'] = $product_model->getNum([
                'worker_order_id' => $detail['id']
            ]);
            $product = $product_model->getList([
                'alias' => 'p',
                'where' => [
                    'p.worker_order_id' => $detail['id']
                ],
                'join'  => 'left join factory_product as fp on fp.product_id=p.product_id
                            left join cm_list_item as cli on cli.list_item_id=p.product_category_id',
                'field' => 'p.id, p.cp_category_name, p.cp_product_brand_name, p.cp_product_standard_name, p.cp_product_mode, p.user_service_request, p.is_complete, fp.product_thumb, p.fault_label_ids, p.cp_fault_name, cli.item_thumb'
            ]);
            foreach ($product as $k => $v) {
                if (!strpos($v['product_thumb'], 'http')) {
                    $product[$k]['product_thumb'] = !empty($v['product_thumb']) ? Util::getServerFileUrl($v['product_thumb']) : Util::getServerFileUrl($v['item_thumb']);
                }
                if (!empty($v['fault_label_ids'])) {
                    $label_names = $fault_label_model->getFieldVal([
                        'id' => ['in', $v['fault_label_ids']]
                    ], 'label_name', true);
                    foreach ($label_names as $v) {
                        $product[$k]['fault_label'][] = $v ;
                    }
                }
                $product[$k]['product_schedule'] = $this->getProductSchedule($detail['id'], $v['is_complete']);
            }
            $detail['product'] = $product;

            //获取最新的预约记录
            $appoint_info = BaseModel::getInstance('worker_order_appoint_record')->getOne([
                'where' => [
                    'worker_order_id' => $order_id,
                    'worker_id' => $detail['worker_id']
                ],
                'field' => 'id, appoint_status',
                'order' => 'create_time desc'
            ]);
            if (in_array($appoint_info['appoint_status'], ['3', '4', '6'])) {
                $detail['worker_is_sign'] = '1';
            } else {
                $detail['worker_is_sign'] = '0';
            }

            if ($detail['service_type'] == '110') {
                $data_id = $detail['id'];
                $accessory_status = '';
            } else {
                //获取配件单信息
                $accessory_info = $this->getApplyAccessory($detail['id'], 'id, accessory_status');
                $data_id = $accessory_info['id'];
                $accessory_status = $accessory_info['accessory_status'];
            }
            //物流单号
            $detail['express_track'] = $this->getExpressTracking($data_id, $detail['service_type'], $accessory_status);

            //工单用户信息
            $detail['user'] = $this->getOrderUserInfo($detail['id']);
            $detail['user']['pay_type_detail'] = WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO;

            //配件单、费用单数
            $statistics = $this->getStatistics($detail['id'], 'total_accessory_num, cost_order_num');
            $detail['return_accessory_num'] = $this->getApplyAccessoryCount($detail['id']);;
            $detail['cost_order_num'] = $statistics['cost_order_num'];
            $detail['total_accessory_num'] = $statistics['total_accessory_num'];

            //工单最新进度
            $order_schedule = $this->getOrderSchedule($detail, $statistics, $detail['user'], 1, $user_id);
            $detail['order_schedule'] = $order_schedule['order_schedule'];
            $detail['is_show_schedule'] = $order_schedule['is_show_schedule'];

            //获取客服信息
            $detail['custom'] = $this->getCustom($detail['distributor_id'], 'user_name as name, tell_out as phone');
            $detail['custom_service'] = $detail['custom'];

            //获取附加信息
            $detail['ext'] = $this->getExt($detail['id'], 'cp_factory_helper_name as factory_helper_name, cp_factory_helper_phone as factory_helper_phone, est_miles, service_evaluate,worker_repair_out_fee_reason,accessory_out_fee_reason');

            //标签
            $detail['label'] = $this->getLabel($detail['service_type'], $detail['worker_order_type'], $detail['product_num']);

            //获取下单人信息
            $detail['add_user_info'] = D('Order')->getAddUserInfo($detail['add_id'], $detail['origin_type']);

            // 是否是保内单
            $is_insurance = in_array($detail['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST);
            $detail['out_fee_info'] = null;
            if (!$is_insurance) {
                $fee =  BaseModel::getInstance('worker_order_fee')
                    ->getOne(['worker_order_id' => $order_id], 'worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,service_fee_modify,worker_total_fee,worker_total_fee_modify');
                $out_fees = BaseModel::getInstance('worker_order_out_worker_add_fee')->getList([
                    'field' => 'is_add_fee,pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee_modify,pay_time',
                    'where' => [
                        'worker_order_id' => $order_id
                    ],
                ]);
                $total = $is_apy_total = 0;
                foreach ($out_fees as $k => $v) {
                    if ($v['pay_type'] != WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO && $v['pay_time']) {
                        $is_apy_total += $v['total_fee_modify'];
                        $detail['user']['pay_type_detail'] = $v['pay_type'];
                    }

                    $total += $v['total_fee_modify'];
                    unset($out_fees[$k]['total_fee_modify']);
                }
                $detail['out_fee_info'] = [
                    'total_worker_fee' => $fee['worker_total_fee'],
                    'total_worker_fee_modify' => $fee['worker_total_fee_modify'],
                    'total_worker_repair_fee' => $fee['worker_repair_fee'],
                    'total_worker_repair_fee_modify' => $fee['worker_repair_fee_modify'],
                    'total_worker_repair_fee_reason' => $detail['ext']['worker_repair_out_fee_reason'],
                    'total_accessory_out_fee' => $fee['accessory_out_fee'],
                    'total_accessory_out_fee_modify' => $fee['accessory_out_fee_modify'],
                    'total_accessory_out_fee_reason' => $detail['ext']['accessory_out_fee_reason'],
                    'need_pay_total_fee' => number_format($total, 2, '.', ''),
                    'pay_total_fee' => number_format($is_apy_total, 2, '.', ''),
                    'out_fees' => $out_fees,
                ];
            }

            //工单总费用
            $detail['worker_total_fee_modify'] = $this->getOrderFee($detail['id'], $detail['worker_order_type'], $detail['user'], [
                'user_id' => $user_id,
                'worker_group_id' => $detail['worker_group_id'],
                'worker_id' => $detail['worker_id'],
                'children_worker_id' => $detail['children_worker_id']
            ]);

            // 获取派单备注
            $detail['distribute_remark'] = '';
            $order_operation_record = BaseModel::getInstance('worker_order_operation_record')->getOne([
                'where' => [
                    'worker_order_id' => $order_id,
                    'operation_type' => OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE,
                ],
                'order' => 'id DESC',
                'field' => 'remark',
            ]);
            $remark_pos = strrpos($order_operation_record['remark'], '，');
            $remark_pos && $detail['distribute_remark'] = substr($order_operation_record['remark'], 0, $remark_pos);


            //获取子账号信息
            $handle_worker_id = !empty($detail['children_worker_id']) ? $detail['children_worker_id'] : $detail['worker_id'];
            $detail['handle_worker_nickname'] = BaseModel::getInstance('worker')->getFieldVal($handle_worker_id, 'nickname');

            return $detail;
        }
    }

    /*
     * 获取用户信息
     */
    public function getOrderUserInfo($worker_order_id)
    {
        $user = BaseModel::getInstance('worker_order_user_info')->getOne([
            'where' => [
                'worker_order_id' => $worker_order_id
            ],
            'field' => '*'
        ]);
        $area_name = explode('-', $user['cp_area_names']);
        $user['province_name'] = $area_name[0];
        $user['city_name'] = $area_name[1];
        $user['area_name'] = $area_name[2];
        return $user;
    }

    /*
     * 获取标签
     */
    public function getLabel($service_type, $worker_order_type, $product_num)
    {
        $label = [];
        switch ($service_type) {
            case '106':
                $content = '安装';
                break;
            case '107':
                $content = '维修';
                break;
            case '108':
                $content = '维护';
                break;
            case '109':
                $content = '送修';
                break;
            case '110':
                $content = '安装';
                break;
        }
        $label[] = [
            'type' => '1',
            'content' => $content
        ];
        if (in_array($worker_order_type, OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            $label[] = [
                'type' => '2',
                'content' => '保内'
            ];
        } else {
            $label[] = [
                'type' => '2',
                'content' => '保外'
            ];
        }
        if ($product_num > 1) {
            $label[] = [
                'type' => '3',
                'content' => '多产品'
            ];
        }
        return $label;
    }

    /*
     * 获取配件单物流信息
     */
    public function getExpressTracking($data_id, $service_type='', $accessory_status='')
    {
        if ($service_type == '110') {
            $type = 3;
        } else {
            $type = 1;
        }
        $express_track = BaseModel::getInstance('express_tracking')->getOne([
            'where' => [
                'data_id' => $data_id,
                'type' => $type
            ],
            'field' => 'state, express_number'
        ]);
        return $express_track;
    }

    /*
     * 获取工单最新进度
     */
    public function getOrderSchedule($request, $statistics='', $user_info='', $is_detail='', $user_id = '')
    {
        $is_show_schedule = '1';
        if ($request['worker_order_status'] == 7) {
            //待预约
            $extend_appoint_time = $request['extend_appoint_time'] != '0' ? $request['extend_appoint_time'] : 3;
            $time = $request['worker_receive_time'] + $extend_appoint_time * 3600 - time();
            if ($request['service_type'] != '110' || $request['express_track']['state'] == '3') {
                if ($time > 0) {
                    $time = $this->strTimeToDate($time);
                    $order_schedule = '此工单将于'.$time.'后过时';
                } else {
                    $order_schedule = '工单已超时,请尽快与用户预约上门';
                }
            } else {
                //查找预发件物流状态
                $state = BaseModel::getInstance('express_tracking')->getFieldVal([
                    'data_id' => $request['id'],
                    'type'    => 3
                ], 'state');
                if ($state == '3' && $time > 0) {
                    $time = $this->strTimeToDate($time);
                    $order_schedule = '此工单将于'.$time.'后过时';
                } else {
                    $order_schedule = '预发件工单，产品未签收';
                }
            }
        } elseif ($request['worker_order_status'] == 8 || $request['worker_order_status'] == 9 || $request['worker_order_status'] == 12) {
            //待服务
            //判断是否有上次预约是否结束
            $appoint_record = BaseModel::getInstance('worker_order_appoint_record')->getOne([
                'where' => [
                    'worker_order_id' => $request['id']
                ],
                'order' => 'create_time desc',
                'field' => 'is_over, sign_in_time, appoint_time'
            ]);
            if ($appoint_record['is_over'] == '1' && !empty($is_detail)) {
                $order_schedule = '上次上门时间:'.date('Y-m-d H:i', $appoint_record['sign_in_time']);
            } else {
                if ($request['total_accessory_num'] > 0 || $request['cost_order_num'] > 0 || $statistics['total_accessory_num'] > 0 || $statistics['cost_order_num']) {
                    //配件单
                    $accessory = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
                        'where' => [
                            'worker_order_id' => $request['id']
                        ],
                        'field' => 'accessory_status, create_time, cancel_status',
                        'order' => 'create_time desc'
                    ]);
                    //费用单
                    $cost = BaseModel::getInstance('worker_order_apply_cost')->getOne([
                        'where' => [
                            'worker_order_id' => $request['id']
                        ],
                        'field' => 'status, create_time',
                        'order' => 'create_time desc'
                    ]);
                    if ($accessory['create_time'] > $cost['create_time']) {
                        //使用配件单信息
                        if ($accessory['cancel_status'] != '0') {
                            $order_schedule = '配件单已终止';
                        } else {
                            switch ($accessory['accessory_status']) {
                                case '1' :
                                    $order_schedule = '配件单待客服审核';
                                    break;
                                case '2' :
                                    $order_schedule = '配件单客服审核不通过';
                                    break;
                                case '3' :
                                    $order_schedule = '配件单待厂家审核';
                                    break;
                                case '4' :
                                    $order_schedule = '配件单厂家审核不通过';
                                    break;
                                case '5' :
                                    $order_schedule = '配件单待厂家发件';
                                    break;
                                case '6' :
                                    $order_schedule = '配件单待技工收件';
                                    break;
                                case '7' :
                                    $order_schedule = '配件单待技工返件';
                                    break;
                                case '8' :
                                    $order_schedule = '配件单待厂家确认收件';
                                    break;
                                case '9' :
                                    $order_schedule = '配件单已完结';
                                    break;
                            }
                        }
                    } else {
                        //使用费用单信息
                        switch ($cost['status']) {
                            case '0' :
                                $order_schedule = '费用单待客服审核';
                                break;
                            case '1' :
                                $order_schedule = '费用单客服审核不通过';
                                break;
                            case '2' :
                                $order_schedule = '费用单待厂家审核';
                                break;
                            case '3' :
                                $order_schedule = '费用单厂家审核不通过';
                                break;
                            case '4' :
                                $order_schedule = '费用单完结';
                                break;
                        }
                    }
                    //如果是详情 则不需要显示
                    if (!empty($is_detail)) {
                        $is_show_schedule = '0';
                    }
                } else {
                    $time = $appoint_record['appoint_time'] - time();
                    if ($time > 0) {
                        $time = $this->strTimeToDate($time);
                        $order_schedule = '距上门还有'.$time;
                    } else {
                        $time = $this->strTimeToDate(abs($time));
                        $order_schedule = '工单已超时'.$time.'，请尽快上门服务';
                    }
                }
            }
        } else {
            $worker_order_fee = $this->getOrderFee($request['id'], $request['worker_order_type'], $user_info, [
                'user_id' => $user_id,
                'worker_group_id' => $request['worker_group_id'],
                'worker_id' => $request['worker_id'],
                'children_worker_id' => $request['children_worker_id']
            ]);
            if (in_array($request['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
                //已完结
                $order_schedule = '总费用: '.$worker_order_fee;
            } else {
                //保外单
                if ($user_info['is_user_pay'] == OrderUserService::IS_USER_PAY_SUCCESS) {
                    //已支付
                    $pay_type_detail = BaseModel::getInstance('worker_order_out_worker_add_fee')->order('create_time asc,id asc')->getFieldVal(['worker_order_id' => $request['id']], 'pay_type');
                    if ($user_info['pay_type'] == OrderUserService::PAY_TYPE_CASH && in_array($pay_type_detail, WorkerOrderOutWorkerAddFeeService::PAY_TYPE_CASH_PAY_LIST)) {
                        //现金支付
                        $order_schedule = '总费用: '.$worker_order_fee.'(用户现金支付)';
                    } else {
                        $order_schedule = '总费用: '.$worker_order_fee;
                    }
                } else {
                    $order_schedule = '总费用: '.$worker_order_fee.'(用户未支付)';
                }
            }
        }
        return [
            'order_schedule'  => $order_schedule,
            'is_show_schedule' => $is_show_schedule
        ];
    }

    /*
     * 获取工单总费用
     */
    public function getOrderFee($order_id, $worker_order_type, $user_info = '', $worker_info)
    {

        $fees = BaseModel::getInstance('worker_order_fee')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => 'worker_repair_fee, accessory_out_fee, worker_net_receipts, service_fee_modify, insurance_fee, worker_total_fee_modify, worker_repair_fee_modify, cp_worker_proportion'
        ]);
        $worker_proportion = 1;
        if (!empty($worker_info['worker_group_id'])) {
            if ($worker_info['user_id'] == $worker_info['worker_id']) {
                $worker_proportion = 1 - $fees['cp_worker_proportion'] / 10000;
            } elseif ($worker_info['user_id'] == $worker_info['children_worker_id']) {
                $worker_proportion = $fees['cp_worker_proportion'] / 10000;
            }
        }
        if (in_array($worker_order_type, OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            //保内单
            $worker_order_fee = '¥'.number_format($fees['worker_total_fee_modify'] * $worker_proportion, 2, '.', '');
        } else {
            //保外单
            if ($user_info['is_user_pay'] == OrderService::USER_PAY_TYPE_FOR_TURE) {
                $pay_type_detail = BaseModel::getInstance('worker_order_out_worker_add_fee')->order('create_time asc,id asc')->getFieldVal(['worker_order_id' => $order_id], 'pay_type');
                //已支付
                if ($user_info['pay_type'] == OrderService::USER_PAY_TYPE_FOR_CASH && in_array($pay_type_detail, WorkerOrderOutWorkerAddFeeService::PAY_TYPE_CASH_PAY_LIST)) {
                    //现金支付
                    $worker_order_fee = '-¥'.number_format(($fees['service_fee_modify'] + $fees['insurance_fee']) * $worker_proportion, 2, '.', '');
                } else {
                    //微信支付
                    $worker_order_fee = '¥'.number_format($fees['worker_net_receipts'] * $worker_proportion, 2, '.', '');
                }
            } else {
                //未支付
                $worker_order_fee = '¥'.number_format(($fees['worker_repair_fee_modify'] + $fees['accessory_out_fee']) * $worker_proportion, 2, '.', '');
            }
        }
        return $worker_order_fee;
    }

    /*
     * 获取产品最新进度
     */
    public function getProductSchedule($order_id, $is_complete)
    {
        if ($is_complete == '1') {
            $product_schedule = '已完成';
        } elseif ($is_complete == '2') {
            $product_schedule = '无法完成';
        } else {
            //配件单
            $accessory = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
                'where' => [
                    'worker_order_id' => $order_id
                ],
                'field' => 'accessory_status, create_time, cancel_status',
                'order' => 'create_time desc'
            ]);
            //费用单
            $cost = BaseModel::getInstance('worker_order_apply_cost')->getOne([
                'where' => [
                    'worker_order_id' => $order_id
                ],
                'field' => 'status, create_time',
                'order' => 'create_time desc'
            ]);
            if ($accessory['create_time'] > $cost['create_time']) {
                //使用配件单信息
                if ($accessory['cancel_status'] != '0') {
                    $product_schedule = '配件单已终止';
                } else {
                    switch ($accessory['accessory_status']) {
                        case '1' :
                            $product_schedule = '配件单待客服审核';
                            break;
                        case '2' :
                            $product_schedule = '配件单客服审核不通过';
                            break;
                        case '3' :
                            $product_schedule = '配件单待厂家审核';
                            break;
                        case '4' :
                            $product_schedule = '配件单厂家审核不通过';
                            break;
                        case '5' :
                            $product_schedule = '配件单待厂家发件';
                            break;
                        case '6' :
                            $product_schedule = '配件单待技工收件';
                            break;
                        case '7' :
                            $product_schedule = '配件单待技工返件';
                            break;
                        case '8' :
                            $product_schedule = '配件单待厂家确认收件';
                            break;
                        case '9' :
                            $product_schedule = '配件单已完结';
                            break;
                    }
                }
            } else {
                //使用费用单信息
                switch ($cost['status']) {
                    case '0' :
                        $product_schedule = '费用单待客服审核';
                        break;
                    case '1' :
                        $product_schedule = '费用单客服审核不通过';
                        break;
                    case '2' :
                        $product_schedule = '费用单待厂家审核';
                        break;
                    case '3' :
                        $product_schedule = '费用单厂家审核不通过';
                        break;
                    case '4' :
                        $product_schedule = '费用单完结';
                        break;
                }
            }
        }
        if (empty($product_schedule)) {
            $product_schedule = '';
        }

        return $product_schedule;
    }

    /*
     * 秒数转日期
     */
    public function strTimeToDate($time)
    {
        $days = floor($time / (24*3600));
        $sec = $time % (24*3600);
        $hours = floor($sec / 3600);
        $remainSeconds = $sec % 3600;
        $minutes = floor($remainSeconds / 60);
        $seconds = intval($sec - $this->hours * 3600 - $this->minutes * 60);

        $date = '';
        if (!empty($days)) {
            $date = $days.'天';
        }
        if (!empty($hours)) {
            $date .= $hours.'小时';
        }
        $date .= $minutes.'分钟';
        return $date;
    }

    /*
     * 获取工单附加信息
     */
    public function getStatistics($order_id, $field='*')
    {
        $statistics = BaseModel::getInstance('worker_order_statistics')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => $field
        ]);
        return $statistics;
    }

    /*
     * 获取需返件配件数
     */
    public function getApplyAccessoryCount($order_id)
    {
        $apply_accessory_ids = BaseModel::getInstance('worker_order_apply_accessory')->getFieldVal([
            'where' => [
                'worker_order_id' => $order_id,
                'is_giveup_return' => '0',
                'accessory_status' => 7,
                'cancel_status'    => 0
            ]
        ], 'id', true);
        if (!empty($apply_accessory_ids)) {
            $apply_accessory_ids = implode(',', $apply_accessory_ids);
            $num = BaseModel::getInstance('worker_order_apply_accessory_item')->getNum([
                'accessory_order_id' => ['in', $apply_accessory_ids]
            ]);
            return $num;
        } else {
            return '0';
        }
    }

    /*
     * 获取配件单信息
     */
    public function getApplyAccessory($order_id, $field='*')
    {
        $apply_accessory = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => $field,
            'order' => 'create_time desc'
        ]);
        return $apply_accessory;
    }

    /*
     * 获取最后一次预约时间
     */
    public function getLastAppointTime($order_id, $user_id)
    {
        $appoint_time = BaseModel::getInstance('worker_order_appoint_record')->getFieldVal([
            'where' => [
                'worker_order_id' => $order_id,
                'worker_id'       => $user_id
            ],
            'order' => 'create_time desc'
        ], 'appoint_time');
        return $appoint_time;
    }

    /*
     * 获取维修项信息
     */
    public function getProduct($order_id, $field='*')
    {
        $product = BaseModel::getInstance('worker_order_product')->getList([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => $field
        ]);
        return $product;
    }

    /*
     * 获取客服信息
     */
    public function getCustom($distributor_id, $field='*')
    {
        $custom = BaseModel::getInstance('admin')->getOne([
            'where' => [
                'id' => $distributor_id
            ],
            'field' => $field
        ]);
        return $custom;
    }

    /*
     * 获取技工信息
     */
    public function getWorkerInfo($worker_id, $field='*')
    {
        $worker = BaseModel::getInstance('worker')->getOne([
            'where' => [
                'worker_id' => $worker_id
            ],
            'field' => $field
        ]);
        return $worker;
    }

    /*
     * 获取附加信息
     */
    public function getExt($order_id, $field='*')
    {
        $custom = BaseModel::getInstance('worker_order_ext_info')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => $field
        ]);
        return $custom;
    }

    /*
     * 提交预约
     */
    public function addAppoint($order_id, $request, $user_id)
    {
        $worker_order_model   = BaseModel::getInstance('worker_order');
        $appoint_record_model = BaseModel::getInstance('worker_order_appoint_record');
        $order = $this->checkWorkerOrder($order_id, $user_id, '*', [
            'worker_order_status' => ['egt', OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]
        ]);

        //判断
        if ($order['worker_order_status'] < OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单当前状态不允许预约客户');
        } elseif ($request['appoint_time'] < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预约时间不能小于当前时间');
        } elseif (strtotime(date('Y-m-d',time())) + 7 * 86400 < $request['appoint_time']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预约时间不能超过7天');
        }

        //开启事务
        M()->startTrans();

        //查找技工是否有预约记录
        $record_id = $appoint_record_model->getFieldVal([
            'where' => [
                'worker_id'       => $order['worker_id'],
                'worker_order_id' => $order_id,
                'is_over'         => WorkerOrderAppointRecordService::IS_OVER_YES
            ]
        ], 'id');

        $add = [
            'worker_id'       => $order['worker_id'],
            'worker_order_id' => $order_id,
            'appoint_status'  => !empty($record_id) ? 5 : 1,
            'appoint_time'    => $request['appoint_time'],
            'appoint_remark'  => !empty($request['remark']) ? $request['remark'] : '',
            'create_time'     => NOW_TIME,
        ];
        $appoint_id = $appoint_record_model->insert($add);

        if (empty($record_id) || $order['worker_order_status'] == OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT) {
            $worker_order_model->update([
                'id' => $order_id
            ], [
                'worker_first_appoint_time' => $request['appoint_time'], 'worker_order_status' => OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                'last_update_time' => NOW_TIME
            ]);
            $type = OrderOperationRecordService::WORKER_APPOINT_SUCCESS;
            // 防止多次重复预约
            $this->checkWorkerIsRepeatedlyAppoint($order_id, $order['worker_id']);
            //群内工单修改数量
            event(new UpdateOrderNumberEvent([
                'worker_order_id'              => $order_id,
                'operation_type'               => OrderOperationRecordService::WORKER_APPOINT_SUCCESS
            ]));
        } else {
            $type = OrderOperationRecordService::WORKER_APPOINT_AGAIN;
        }

        //添加操作记录
        OrderOperationRecordService::create($order_id, $type, [
            'operator_id' => $user_id,
            'content_replace' => [
                'appoint_time' => date('Y-m-d H:i', $request['appoint_time']),
            ],
            'remark' => !empty($request['remark']) ? $request['remark'] : ''
        ]);
        //后台推送
        $content = '工单号'.$order['orno'].',技工已经上传预约';
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order['distributor_id'], $content, $order_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_UPLOAD_APPOINT);

        //发送短信
        $user_info = $this->getOrderUserInfo($order_id);
        $product   = $this->getProduct($order_id, 'cp_product_brand_name, cp_category_name');
        $admin = $this->getCustom($order['distributor_id'], 'user_name, tell_out');
        sendSms($user_info['phone'], SMSService::TMP_WORKER_APPOINT_SUCCESS_SEND, [
            'user'      => $user_info['real_name'], //用户名
            'product'   => $product[0]['cp_product_brand_name'].$product[0]['cp_category_name'], //品牌+产品类别
            'time'      => date('Y-m-d H:i', $request['appoint_time']), //师傅上传的时间
            'user_name' => $admin['user_name'], //工单的当前工单客服对外昵称
            'tell_out'  => $admin['tell_out']  //客服的工作座机
        ]);
        M()->commit();
    }

    /*
     * 预约签到
     */
    public function appointmentSign($order_id, $request, $user_id)
    {
        if (empty($request['lon'])) {
            $request['lon'] = 0;
        }
        if (empty($request['lat'])) {
            $request['lat'] = 0;
        }
        $appoint_record_model = BaseModel::getInstance('worker_order_appoint_record');
        $record = $appoint_record_model->getOne([
            'alias' => 'ar',
            'where' => [
                'ar.worker_order_id'  => $order_id
            ],
            'join'  => 'left join worker_order_user_info as wou on wou.worker_order_id=ar.worker_order_id 
                        left join worker_order as wo on wo.id=ar.worker_order_id',
            'order' => 'ar.create_time desc',
            'field' => 'ar.id, ar.appoint_time, ar.worker_id, ar.appoint_status, wou.lon, wou.lat, wo.worker_first_sign_time'
        ]);
        $this->checkWorkerOrder($order_id, $user_id);

        if ($record['appoint_status'] != 3 && $record['appoint_status'] != 4 && $record['appoint_status'] != 6) {
            if ($this->getLongDistance($request['lon'], $request['lat'], $record['lon'], $record['lat']) > C('signInDistance')) {
                //超出签到范围
                $option = '失败,与维修地址不一致';
                $appoint_status = 3;
                $is_sign_in = 2;
            } else {
                //签到成功
                $option = '成功,'.date('Y-m-d H:i:s', NOW_TIME);
                $appoint_status = 4;
                $is_sign_in = 1;
            }
            M()->startTrans();
            $appoint_record_model->update([
                'id' => $record['id']
            ], [
                'is_sign_in'       => $is_sign_in,
                'appoint_status'   => $appoint_status,
                'sign_in_time'     => NOW_TIME,
                'last_update_time' => NOW_TIME,
                'is_over'          => 1
            ]);
            //签到成功修改工单状态
            $order_data['worker_order_status'] = OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE;
            if ($record['worker_first_sign_time'] == '0') {
                $order_data['worker_first_sign_time'] = NOW_TIME;
                $order_data['last_update_time'] = NOW_TIME;
                //群内工单修改数量
                event(new UpdateOrderNumberEvent([
                    'worker_order_id' => $order_id,
                    'operation_type'  => OrderOperationRecordService::WORKER_SIGN_SUCCESS
                ]));
            }
            BaseModel::getInstance('worker_order')->update([
                'id' => $order_id
            ], $order_data);
            //添加操作记录
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_SIGN_SUCCESS, [
                'operator_id' => $user_id,
                'content_replace' => [
                    'status' => $option,
                ]
            ]);
            M()->commit();
        } else {
            $this->throwException(ErrorCode::SYS_NOT_POWER);
        }
    }

    /*
     * 修改预约
     */
    public function updateAppoint($order_id, $request, $user_id)
    {
        if (empty($request['reason'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '修改预约理由不能为空');
        }
        if (empty($request['appoint_time'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预约时间不能为空');
        }
        $appoint_record_model = BaseModel::getInstance('worker_order_appoint_record');

        $order = $this->checkWorkerOrder($order_id, $user_id, '*', [
            'worker_order_status' => ['egt', OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]
        ]);

        //判断
        if (!in_array($order['worker_order_status'], [OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE, OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE, OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单当前状态不允许修改预约');
        } elseif ($request['appoint_time'] < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预约时间不能小于当前时间');
        } elseif (strtotime(date('Y-m-d', NOW_TIME)) + 7 * 86400 < $request['appoint_time']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预约时间不能超过7天');
        }

        M()->startTrans();
        $add = [
            'worker_id'       => $order['worker_id'],
            'worker_order_id' => $order_id,
            'appoint_status'  => 2,
            'update_reason'   => $request['reason'],
            'appoint_time'    => $request['appoint_time'],
            'appoint_remark'  => !empty($request['remark']) ? $request['remark'] : '',
            'create_time'     => NOW_TIME,
        ];
        $appoint_id = $appoint_record_model->insert($add);

        $update_reason = [
            '',
            '用户不在家',
            '我临时有事',
            '用户没收到产品',
            '收到的产品有问题',
            '收到的配件有问题',
            '其他'
        ];

        //添加操作记录
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_UPDATE_APPOINT_TIME, [
            'operator_id' => $user_id,
            'content_replace' => [
                'appoint_time' => date('Y-m-d H:i', $request['appoint_time']).',修改原因:'.$update_reason[$request['reason']],
            ],
            'remark' => !empty($request['remark']) ? $request['remark'] : ''
        ]);
        //发送短信
        $user_info = $this->getOrderUserInfo($order_id);
        $product   = $this->getProduct($order_id, 'cp_product_brand_name, cp_category_name');
        $admin = $this->getCustom($order['distributor_id'], 'user_name, tell_out');
        sendSms($user_info['phone'], SMSService::TMP_WORKER_UPDATE_APPOINT_TIME_SEND, [
            'user'      => $user_info['real_name'], //用户名
            'product'   => $product[0]['cp_product_brand_name'].$product[0]['cp_category_name'], //品牌+产品类别
            'time'      => date('Y-m-d H:i', $request['appoint_time']), //师傅上传的时间
            'user_name' => $admin['user_name'], //工单的当前工单客服对外昵称
            'tell_out'  => $admin['tell_out']  //客服的工作座机
        ]);
        M()->commit();
    }

    /*
     * 预约记录
     */
    public function appointmentLog($order_id, $user_id)
    {
        $order_info = $this->checkWorkerOrder($order_id, $user_id);
        if (!empty($order_info['worker_group_id'])) {
            $owner_worker_id = GroupService::getOwnerId($order_info['worker_group_id']);
        }
        $list = BaseModel::getInstance('worker_order_operation_record')->getList([
            'where' => [
                'worker_order_id' => $order_id,
                'operator_id' => ['in', [$owner_worker_id, $user_id]],
                'operation_type' => ['in', OrderOperationRecordService::WORKER_APPOINT_SUCCESS.','.OrderOperationRecordService::WORKER_EXTEND_APPOINT_TIME.','.OrderOperationRecordService::WORKER_UPDATE_APPOINT_TIME.','.OrderOperationRecordService::WORKER_APPOINT_AGAIN]
            ],
            'field' => 'id, worker_order_id, create_time, content, remark as appoint_remark, operation_type, see_auth',
            'order' => 'create_time desc'
        ]);
        foreach ($list as $k => $v) {
            $appoint_msg = explode(',', $v['content']);
            $list[$k]['appoint_type'] = $appoint_msg[0];
            $list[$k]['content'] = $appoint_msg[1];
            if ($v['operation_type'] == OrderOperationRecordService::WORKER_UPDATE_APPOINT_TIME) {
                $list[$k]['update_reason'] = $appoint_msg[2];
            } else {
                $list[$k]['update_reason'] = '';
            }
            $see_auth = OrderOperationRecordService::getOperationRecordSeeAuth($v['see_auth']);
            if (in_array('技工', $see_auth)) {
                $return_list[] = $list[$k];
            }
        }
        return $return_list;
    }

    /*
     * 获取服务项目列表
     */
    public function getServices($order_id, $product_id)
    {
        $product_model = BaseModel::getInstance('worker_order_product');
        $order_info = BaseModel::getInstance('worker_order')->getOne([
            'id' => $order_id
        ], 'service_type, factory_id, worker_order_type');
        $product_info = $product_model->getOne([
            'where' => [
                'worker_order_id' => $order_id,
                'id' => $product_id
            ],
            'field' => 'id, product_standard_id, fault_id, product_category_id'
        ]);
//        $list = $this->getFactoryFaultPriceByCategoryIdAndStandardId($order_info['factory_id'], $product_info['product_category_id'], $product_info['product_standard_id'], '', FaultTypeService::getFaultType($order_info['service_type']), $product_info['fault_id'], $order_info['worker_order_type']);
        $list = $this->getFaultFeeList([
            'factory_id' => $order_info['factory_id'],
            'category_id' => $product_info['product_category_id'],
            'standard_id' => $product_info['product_standard_id'],
            'fault_type' => FaultTypeService::getFaultType($order_info['service_type']),
            'fault_id' => $product_info['fault_id']
        ]);
        return $list;
    }

    public function getFaultFeeList($param, $is_check_price = false)
    {
        $factory_id = $param['factory_id'];
        $category_id = $param['category_id'];
        $standard_id = $param['standard_id'];
        $fault_type = $param['fault_type'];
        $select_fault_id = !empty($param['fault_id']) ? $param['fault_id'] : 0;

        //获取厂家关联品类
        $miscellaneous_model = BaseModel::getInstance('product_miscellaneous');
        $where = ['product_id' => $category_id,];
        $product_fault_ids = $miscellaneous_model->getFieldVal($where, 'product_faults');
        //品类关联的维修项
        $product_fault = Util::filterIdList($product_fault_ids);
        $product_fault_ids = empty($product_fault_ids) ? '-1' : $product_fault_ids;
        $fault_order = empty($product_fault) ? null : "field(id," . implode(',', $product_fault) . ")";

        $fault_model = BaseModel::getInstance('product_fault');
        $opts = [
            'field' => 'id,fault_name,fault_desc',
            'where' => [
                'id'         => ['in', $product_fault_ids],
                'fault_type' => $fault_type,
            ],
            'order' => $fault_order,
        ];
        $faults = $fault_model->getList($opts);

        $fault_ids = empty($faults) ? '-1' : array_column($faults, 'id');

        //厂家维修项费用
        $factory_fault_model = BaseModel::getInstance('factory_product_fault_price');
        $opts = [
            'field' => 'fault_id as id,factory_in_price,factory_out_price,worker_in_price,worker_out_price',
            'where' => [
                'factory_id'  => $factory_id,
                'standard_id' => $standard_id,
                'fault_id'    => ['in', $fault_ids],
            ],
            'order' => 'id',
            'group' => 'fault_id',
            'index' => 'id',
        ];
        $factory_fault = $factory_fault_model->getList($opts);

        //平台维修项费用
        $admin_fault_model = BaseModel::getInstance('product_fault_price');
        $opts = [
            'field' => 'fault_id as id,factory_in_price,factory_out_price,worker_in_price,worker_out_price',
            'where' => [
                'standard_id' => $standard_id,
                'fault_id'    => ['in', $fault_ids],
            ],
            'order' => 'id',
            'group' => 'fault_id',
            'index' => 'id',
        ];
        $admin_fault = $admin_fault_model->getList($opts);

        //根据设好的排序输出
        $check_price_arr = [];
        foreach ($faults as $key => $fault) {
            $fault_id = $fault['id'];

            //获取厂家保内保外价
            if (array_key_exists($fault_id, $factory_fault)) {
                $fault['factory_in_price'] = $factory_fault[$fault_id]['factory_in_price'];
                $fault['factory_out_price'] = $factory_fault[$fault_id]['factory_out_price'];
            } elseif (array_key_exists($fault_id, $admin_fault)) {
                //其次获取平台设置
                $fault['factory_in_price'] = $admin_fault[$fault_id]['factory_in_price'];
                $fault['factory_out_price'] = $admin_fault[$fault_id]['factory_out_price'];
            } else {
                //默认价格
                $fault['factory_in_price'] = C('FACTORY_DEFAULT_FAULT_IN_PRICE');
                $fault['factory_out_price'] = C('FACTORY_DEFAULT_FAULT_OUT_PRICE');
            }

            //获取技工保内保外价
            if (array_key_exists($fault_id, $admin_fault)) {
                $fault['worker_in_price'] = $admin_fault[$fault_id]['worker_in_price'];
                $fault['worker_out_price'] = $admin_fault[$fault_id]['worker_out_price'];
            } else {
                $fault['worker_in_price'] = C('WORKER_DEFAULT_FAULT_IN_PRICE');
                $fault['worker_out_price'] = C('WORKER_DEFAULT_FAULT_OUT_PRICE');
            }

            $fault['is_select'] = '0';
            if ($fault_id == $select_fault_id) {
                $fault['is_select'] = '1';
                $check_price_arr = $fault;
            }

            $faults[$key] = $fault;
        }

        if ($is_check_price) {
            return $check_price_arr;
        }

        return $faults;

    }

    public function getFactoryFaultPriceByCategoryIdAndStandardId($factory_id, $category_id, $standard_id, $fault_ids = '', $fault_type = null, $select_fault_id='', $order_type = 0)
    {
        $where = [
            'fp.product_id' => $category_id,
            'fp.standard_id' => $standard_id,
            'fp.factory_id' => $factory_id,
        ];

        if (empty($select_fault_id)) {
            $select_fault_id = 0;
        }
        $field = ', case when fp.fault_id='.$select_fault_id.' then 1 else 0 end as is_select';


        // 服务项类型 0维修 2维护 1安装
        $fault_type !== null && $where['product_fault.fault_type'] = intval($fault_type);

        if (empty($fault_ids)) {
            $fault_ids = BaseModel::getInstance('product_miscellaneous')->getFieldVal([
                'product_id' => $category_id
            ], 'product_faults');
        }
        $fault_ids && $where['fp.fault_id'] = ['IN', $fault_ids];

        $fault_price_info = BaseModel::getInstance('factory_product_fault_price')->getList([
            'alias' => 'fp',
            'where' => $where,
            'join'  => 'INNER JOIN product_fault ON product_fault.id=fp.fault_id',
            'field' => 'fp.fault_id as id, product_fault.fault_name,fp.factory_in_price,fp.factory_out_price,fp.worker_in_price,fp.worker_out_price,product_fault.fault_desc,product_fault.fault_type'.$field,
            'order' => 'product_fault.fault_type desc,product_fault.sort desc',
            'group' => 'fp.fault_id',
        ]);

        if (!$fault_price_info || !isInWarrantPeriod($order_type)) {
            unset($where['fp.factory_id']);
            $fault_price_info = BaseModel::getInstance('product_fault_price')->getList([
                'alias' => 'fp',
                'where' => $where,
                'join' => 'INNER JOIN product_fault ON product_fault.id=fp.fault_id',
                'field' => 'fp.fault_id as id, product_fault.fault_name,fp.factory_in_price,fp.factory_out_price,fp.worker_in_price,fp.worker_out_price,product_fault.fault_desc,product_fault.fault_type'.$field,
                'order' => 'product_fault.fault_type desc,product_fault.sort desc',
                'group' => 'fp.fault_id',
            ]);
        }

        return $fault_price_info;
    }

    public function getFaultPriceByCategoryIdAndStandardId($fault_id, $factory_id, $category_id, $standard_id)
    {
        if (!$fault_id || !$factory_id || !$category_id || !$standard_id) {return [];}

        $where = [
            'fp.product_id' => $category_id,
            'fp.standard_id' => $standard_id,
            'fp.factory_id' => $factory_id,
        ];

        $where['fp.fault_id'] = $fault_id;
        $fault_price_info = BaseModel::getInstance('factory_product_fault_price')->getOne([
            'alias' => 'fp',
            'where' => $where,
            'join'  => 'INNER JOIN product_fault ON product_fault.id=fp.fault_id',
            'field' => 'fp.fault_id as id, product_fault.fault_name,fp.worker_in_price,fp.worker_out_price,fp.factory_in_price,fp.factory_out_price,product_fault.fault_desc,product_fault.fault_type',
            'order' => 'product_fault.fault_type desc,product_fault.sort desc',
            'group' => 'fp.fault_id',
        ]);

        if (!$fault_price_info) {
            unset($where['fp.factory_id']);
            $fault_price_info = BaseModel::getInstance('product_fault_price')->getOne([
                'alias' => 'fp',
                'where' => $where,
                'join' => 'INNER JOIN product_fault ON product_fault.id=fp.fault_id',
                'field' => 'fp.fault_id as id, product_fault.fault_name,fp.worker_in_price,fp.worker_out_price,fp.factory_in_price,fp.factory_out_price,product_fault.fault_desc,product_fault.fault_type',
                'order' => 'product_fault.fault_type desc,product_fault.sort desc',
                'group' => 'fp.fault_id',
            ]);
        }

        return $fault_price_info;
    }

    /*
     * 选择服务项
     */
    public function selectService($order_id, $product_id, $service_id, $user_id)
    {
        $model = BaseModel::getInstance('product_fault');
        $product_model = BaseModel::getInstance('worker_order_product');

        $this->checkWorkerOrder($order_id, $user_id);

        $fault = $model->getOne([
            'id' => $service_id
        ]);

        M()->startTrans();
        $product_model->update([
            'worker_order_id' => $order_id,
            'id' => $product_id
        ], [
            'fault_id' => $fault['id'],
            'cp_fault_name' => $fault['fault_name']
        ]);
        //添加操作记录
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_SELECT_FAULT, [
            'operator_id' => $user_id,
            'worker_order_product_id' => $product_id,
            'content_replace' => [
                'fault' => $fault['fault_name']
            ],
            'worker_order_product_ids' => $product_id // 计算费用需要
        ]);
        //修改服务项的时候重新计算技工厂家维修费及厂家改产品类别的服务费
        OrderSettlementService::autoSettlement();
        M()->commit();
    }

    /*
     * 上传服务报告
     */
    public function uploadServiceReport($order_id, $product_id, $request, $user_id)
    {
        !isset($request['accessory_out_fee']) && isset($request['accessory_fee']) && $request['accessory_out_fee'] = $request['accessory_fee'];
        if (!isset($request['is_complete'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '服务报告状态不能为空');
        }
        if (empty($request['report_imgs'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '服务报告图片不能为空');
        }
        if (empty($request['report_desc'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单报告不能为空');
        }

        $worker_order_model   = BaseModel::getInstance('worker_order');
        $product_model = BaseModel::getInstance('worker_order_product');

        $order_info = $this->checkWorkerOrder($order_id, $user_id, '*', [
            'worker_order_status' => ['egt', OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]
        ]);

        //检查产品是否已上传服务报告
        $product_info = $product_model->getOne([
            'id' => $product_id
        ], 'is_complete');
        if ($product_info['is_complete'] != '0') {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '产品已处理');
        }

        //检查是否有未返件或未完结配件单
        $num1 = BaseModel::getInstance('worker_order_apply_accessory')->getNum([
            'worker_order_id'  => $order_id,
            'cancel_status'    => 0,
            // 'accessory_status' => ['lt', 8]
            'accessory_status' => ['in', implode(',', AccessoryService::STATUS_IS_NOT_RETURN_ONGOING)],
            'worker_order_product_id' => $product_id
        ]);

        //检查是否有未完结费用单
        $num2 = BaseModel::getInstance('worker_order_apply_cost')->getNum([
            'worker_order_id'  => $order_id,
            'status'           => ['in', implode(',', ApplyCostService::STATUS_IS_ONGOING)],
            'worker_order_product_id' => $product_id
        ]);

        if ($num1 + $num2 > 0) {
            $this->throwException(ErrorCode::ACCESSORY_OR_COST_HAS_UNFINISHED);
        }

        if ($request['is_complete'] == '1') {
            $is_complete = '完成服务，我要结算';
        } elseif ($request['is_complete'] == '2') {
            $is_complete = '无法维修，产品需返厂';
        } else {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '维修状态错误');
        }

        //  开启事务
        M()->startTrans();

        //更新服务报告
        $data = [
            'is_complete' => $request['is_complete'],
            'worker_report_imgs' => html_entity_decode($request['report_imgs']),
            'worker_report_remark' => $request['report_desc']
        ];
        $product_model->update([
            'worker_order_id' => $order_id,
            'id' => $product_id
        ], $data);

        //判断最后一次预约有没有签到
        $this->checkLastAppoint($order_id, $order_info['worker_id']);
        // 保外单
        if (!in_array($order_info['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            $total_fee = $request['worker_repair_fee'] + $request['accessory_out_fee'];
            $out_fee_model = BaseModel::getInstance('worker_order_out_worker_add_fee');
            $field_arr = [
                'count(*) as nums',
                'sum(if(pay_time=0,1,0)) as not_pay_nums',
                'sum(worker_repair_fee) as worker_repair_fee',
                'sum(worker_repair_fee_modify) as worker_repair_fee_modify',
                'sum(accessory_out_fee) as accessory_out_fee',
                'sum(accessory_out_fee_modify) as accessory_out_fee_modify',
            ];
            $out_fees_nums_data = reset($out_fee_model->getList([
                'field' => implode(',', $field_arr),
                'where' => [
                    'worker_order_id' => $order_id,
                ],
            ]));

            $out_fees_nums = $out_fees_nums_data['nums'];

            if ($out_fees_nums_data['not_pay_nums']) {
                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '有 费用/加收费用 未支付');
            }

            //判断是维修还是安装
            $service_type = OrderService::SERVICE_TYPE_SHORT_NAME_FOR_APP[$order_info['service_type']] ?? '维修';
            $request['worker_repair_fee'] = number_format($request['worker_repair_fee'], 2, '.', '');
            $request['accessory_out_fee'] = number_format($request['accessory_out_fee'], 2, '.', '');
            $out_fee_data = [
                'worker_order_id' => $order_id,
                'worker_id' => $user_id,
                'worker_order_product_id' => $product_id,
                'is_add_fee' => $out_fees_nums ? WorkerOrderOutWorkerAddFeeService::IS_ADD_FEE_YES : WorkerOrderOutWorkerAddFeeService::IS_ADD_FEE_NO,
                'pay_type' => WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO,
                'worker_repair_fee' => $request['worker_repair_fee'],
                'worker_repair_fee_modify' => $request['worker_repair_fee'],
                'accessory_out_fee' => $request['accessory_out_fee'],
                'accessory_out_fee_modify' => $request['accessory_out_fee'],
                'total_fee' => $total_fee,
                'total_fee_modify' => $total_fee,
                'create_time' => NOW_TIME,
            ];

            $operation_type = OrderOperationRecordService::WORKER_SUBMIT_WARRANTY_BILL;
            $request['remark'] = $service_type.'费:'.$request['worker_repair_fee'].'元,配件费:'.$request['accessory_out_fee'].'元,合计:'.($total_fee).'元';
            // 第一次上传服务报告没有费用，之后每次 没有服务费 则不做处理，即不选择加收费用
            $user_info_update = [
                'pay_type' => $request['pay_type'],
                'is_user_pay' => OrderUserService::IS_USER_PAY_DEFAULT,
            ];
            if (!$out_fees_nums && $total_fee < 1) { // 第一上传服务报告
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保外单支付金额不能小于1元');
            }
            $user_model = BaseModel::getInstance('worker_order_user_info');

            if ($out_fees_nums && $total_fee >= 1) { // 技工选择加收费用
                $operation_type = OrderOperationRecordService::WORKER_ADD_OUT_ORDER_FEE;

                $user_info = $user_model->getOne($order_id, 'pay_type');
                $user_info['pay_type'] != $request['pay_type'] &&  $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请联系客服处理');
                $user_info_update['is_user_pay'] = OrderUserService::IS_USER_PAY_HAD_PAY;
            } elseif ($out_fees_nums) { // 技工选择不加收费用
                $operation_type = OrderOperationRecordService::WORKER_NOT_ADD_OUT_ORDER_FEE;
                $request['remark'] = '';
                unset($out_fee_data, $user_info_update);
            }

            // 加收费用 或第一次上传服务报告 验证支付类型
            if ((!$out_fees_nums || $out_fee_data) && (!in_array($request['pay_type'], OrderUserService::PAY_TYPE_LIST) || !isset(OrderUserService::PAY_TYPE_NAME_KEY_VALUE[$request['pay_type']]))) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '支付类型错误');
            }
            
            // 更新worker_order_info
            $user_info_update && $user_model->update($order_id, $user_info_update);
            $pay_type = OrderUserService::PAY_TYPE_NAME_KEY_VALUE[$request['pay_type']];

            $out_fee_data && $out_fee_model->insert($out_fee_data);
//            保外单结算
//            OrderSettlementService::warrantyBillSettlement($order_id, $request);
            //保外单操作记录
            $request['operator_id'] = $user_id;
            $request['content_replace'] = [
                'content' => $pay_type
            ];
            $request['worker_order_product_id'] = $product_id;
            $request['all_accessory_out_fee'] = $out_fees_nums_data['accessory_out_fee_modify'] + $request['accessory_out_fee'];
            $request['all_worker_repair_fee'] = $out_fees_nums_data['worker_repair_fee_modify'] + $request['worker_repair_fee'];
            OrderOperationRecordService::create($order_id, $operation_type, $request);
        }

        // 已上门
        $update_data['worker_order_status'] = OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE;
        !$order_info['worker_first_sign_time'] && $update_data['worker_first_sign_time'] = NOW_TIME;

        // 全部完成维修
        $complete_num = $product_model->getNum([
            'worker_order_id' => $order_id,
            'is_complete' => ['not in', '1,2']
        ]);
        $is_completed = false;
        if (!$complete_num) {
            $update_data['worker_repair_time'] = NOW_TIME;
            $update_data['worker_order_status'] = OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE;
            $update_data['last_update_time'] = NOW_TIME;
            $is_completed = true;
        }

        //更新订单状态
        $worker_order_model->update([
            'id' => $order_id
        ], $update_data);

        //添加操作记录
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT, [
            'operator_id' => $user_id,
            'worker_order_product_id' => $product_id,
            'content_replace' => [
                'content' => $is_complete
            ],
            'remark' => $data['worker_report_remark'].$this->handleImage($data['worker_report_imgs'])
        ]);
        //全部完成维修之后，群内工单修改数量
        if (!$complete_num) {
            event(new UpdateOrderNumberEvent([
                'worker_order_id' => $order_id,
                'operation_type'  => OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT
            ]));
            event(new WorkbenchEvent(['worker_order_id' => $order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.WORKER_FINISH')]));
        }
        //后台推送
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order_info['distributor_id'], '工单号'.$order_info['orno'].'，完成维修', $order_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_WORKER_UPLOAD_REPORT);
        // 完成服务结算
//        if ($worker_order_status == 10 && in_array($order_info['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
//            OrderSettlementService::autoSettlement();
//        }
        OrderSettlementService::autoSettlement();

        //  结束事务
        M()->commit();
    }

    /*
     * 保外单修改费用
     */
    public function updateWarrantyFee($order_id, $request, $user_id)
    {
        if ($request['worker_repair_fee'] + $request['accessory_fee'] < 1) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保外单支付金额不能小于1元');
        }

        // 加收费用 或第一次上传服务报告 验证支付类型
        if (!in_array($request['pay_type'], OrderUserService::PAY_TYPE_LIST) || !isset(OrderUserService::PAY_TYPE_NAME_KEY_VALUE[$request['pay_type']])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '支付类型错误');
        }

        $pay_type = OrderUserService::PAY_TYPE_NAME_KEY_VALUE[$request['pay_type']];

        $order_info = $this->checkWorkerOrder($order_id, $user_id, 'service_type, worker_id, worker_group_id', [
            'worker_order_status' => ['egt', OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]
        ]);
        $worker_order_user_info = $this->getOrderUserInfo($order_id);

        if ($worker_order_user_info['is_user_pay'] == OrderUserService::IS_USER_PAY_SUCCESS) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '已支付成功');
        }

        $out_model = BaseModel::getInstance('worker_order_out_worker_add_fee');
        $out_fees = $out_model->getOne([
            'order' => 'create_time desc,id desc',
            'where' => [
                'worker_order_id' => $order_id,
            ],
        ]);

        //判断是维修还是安装
        $service_type = OrderService::SERVICE_TYPE_SHORT_NAME_FOR_APP[$order_info['service_type']] ?? '维修';

        $user_model = BaseModel::getInstance('worker_order_user_info');
        M()->startTrans();
        if ($out_fees['is_add_fee'] == WorkerOrderOutWorkerAddFeeService::IS_ADD_FEE_YES) {
            $user_info = $user_model->getOne($order_id, 'pay_type');
            $user_info['pay_type'] != $request['pay_type'] &&  $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请联系客服处理');
            $user_model->update($order_id, ['pay_type' => $request['pay_type']]);
        } elseif($out_fees['is_add_fee'] == WorkerOrderOutWorkerAddFeeService::IS_ADD_FEE_NO) {
            $user_model->update($order_id, ['pay_type' => $request['pay_type']]);
        }

        $out_total_fee = $request['worker_repair_fee'] + $request['accessory_fee'];
        $out_model->update($out_fees['id'], [
            'worker_repair_fee' => $request['worker_repair_fee'],
            'worker_repair_fee_modify' => $request['worker_repair_fee'],
            'accessory_out_fee' => $request['accessory_fee'],
            'accessory_out_fee_modify' => $request['accessory_fee'],
            'total_fee' => $out_total_fee,
            'total_fee_modify' => $out_total_fee,
        ]);

        //保外单结算
        //OrderSettlementService::warrantyBillSettlement($order_id, $request);

        //添加操作记录
        $request['operator_id'] = $user_id;
        $request['content_replace'] = [
            'content' => '师傅修改工单费用信息,并选择：'.$pay_type
        ];
        $request['remark'] = $service_type.'费:'.$request['worker_repair_fee'].'元,配件费:'.$request['accessory_fee'].'元,合计:'.($request['worker_repair_fee'] + $request['accessory_fee']).'元';

        $field_arr = [
            'sum(worker_repair_fee) as worker_repair_fee',
            'sum(worker_repair_fee_modify) as worker_repair_fee_modify',
            'sum(accessory_out_fee) as accessory_out_fee',
            'sum(accessory_out_fee_modify) as accessory_out_fee_modify',
        ];
        $out_fees_nums_data = reset($out_model->getList([
            'field' => implode(',', $field_arr),
            'where' => [
                'worker_order_id' => $order_id,
            ],
        ]));

        $request['worker_order_product_id'] = $out_fees['worker_order_product_id'];
        $request['before_update_worker_repair_fee'] = $out_fees['worker_repair_fee_modify'];

        $request['all_accessory_out_fee'] = $out_fees_nums_data['accessory_out_fee_modify'];
        $request['all_worker_repair_fee'] = $out_fees_nums_data['worker_repair_fee_modify'];
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_UPDATE_WARRANTY_BILL, $request);
        OrderSettlementService::autoSettlement();

        M()->commit();

        return ;
    }

    /*
     * 保外单现金支付
     */
    public function cashPaySuccess($order_id, $user_id, $request = [])
    {
        $operation_type = OrderOperationRecordService::CS_CONFIRM_USER_PAID;
        $this_pay_type = WorkerOrderOutWorkerAddFeeService::PAY_TYPE_USER_CASH;
        if (AuthService::getModel() == AuthService::ROLE_WORKER) {
            $operation_type = OrderOperationRecordService::WORKER_ORDER_USER_PAY_SUCCESS;
            $this_pay_type = WorkerOrderOutWorkerAddFeeService::PAY_TYPE_WOKRER_COMFIRM_USER_CASH;
            // 技工不允许在确认现金支付的时候修改费用信息
            unset($request);
        }

        $order = $this->checkWorkerOrder($order_id, $user_id, '*', [
            'worker_order_status' => ['egt', OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE]
        ]);
        if (in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单不是保外单');
        }
        if (!in_array($order['cancel_status'], [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单已取消');
        }

        $out_model = BaseModel::getInstance('worker_order_out_worker_add_fee');
        $out_fees_list = $out_model->getList([
            'order' => 'create_time desc,id desc',
            'where' => [
                'worker_order_id' => $order_id,
            ],
            'limit' => 2,
        ]);
        $out_fees = reset($out_fees_list);
        $pre_out_fees = $out_fees_list[1];

        $order_user = BaseModel::getInstance('worker_order_user_info')->getOne($order_id, 'pay_type,is_user_pay');

        if ($order_user['is_user_pay'] == OrderUserService::IS_USER_PAY_SUCCESS) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单已支付,无需重复支付');
        }

        $out_fees['pay_time'] && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '无未支付的费用');
        
        if ($pre_out_fees) {
            !in_array($pre_out_fees['pay_type'], WorkerOrderOutWorkerAddFeeService::PAY_TYPE_CASH_PAY_LIST) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '该工单上次支付不是现金支付');
            $order_user['pay_type'] != OrderUserService::PAY_TYPE_CASH && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单不是现金支付');
        }

        $add_fee_data = [];
        $remark = "确认技工填写的费用属实，维修费/安装费:{$out_fees['worker_repair_fee_modify']}元,配件费:{$out_fees['accessory_out_fee_modify']}元,合计:{$out_fees['total_fee_modify']}元";
        $add_fee_update_data = [
            'pay_type' => $this_pay_type,
            'pay_time' => NOW_TIME,
            'worker_repair_fee_modify' => $out_fees['worker_repair_fee_modify'],
            'accessory_out_fee_modify' => $out_fees['accessory_out_fee_modify'],
        ];

        // 费用改动时
        $it_is_ture = true;
        if (isset($request['worker_repair_fee'])) {
            $add_fee_update_data['worker_repair_fee_modify'] = $request['worker_repair_fee'];
            $it_is_ture = false;
        }
        if (isset($request['accessory_out_fee'])) {
            $add_fee_update_data['accessory_out_fee_modify'] = $request['accessory_out_fee'];
            $it_is_ture = false;
        }
        $add_fee_update_data['total_fee_modify'] = $add_fee_update_data['worker_repair_fee_modify'] + $add_fee_update_data['accessory_out_fee_modify'];

        !$it_is_ture && $remark = "确认技工填写的费用不属实，维修费/安装费:{$add_fee_update_data['worker_repair_fee_modify']}元,配件费:{$add_fee_update_data['accessory_out_fee_modify']}元,合计:{$add_fee_update_data['total_fee_modify']}元";
        
        $extras = [
            'remark' => $remark,
        ];
        
        M()->startTrans();
        //支付成功修改对应支付状态
        BaseModel::getInstance('worker_order_user_info')->update([
            'worker_order_id' => $order_id,
        ], [
            'is_user_pay' => OrderUserService::IS_USER_PAY_SUCCESS,
            'pay_type'    => OrderUserService::PAY_TYPE_CASH,
            'pay_time'    => NOW_TIME
        ]);

        $out_fees['id'] && $out_model->update($out_fees['id'], $add_fee_update_data);

        // 重新计算总费用
        if ($out_fees['total_fee_modify'] != $add_fee_update_data['total_fee_modify']) {
            $fee_model = BaseModel::getInstance('worker_order_fee');
            $fee_data = $fee_model->getOne($order_id, 'worker_repair_fee_modify,accessory_out_fee_modify');
            $settle_data = [
                'worker_repair_fee_modify' => $fee_data['worker_repair_fee_modify'] - $out_fees['worker_repair_fee_modify'] + $add_fee_update_data['worker_repair_fee_modify'],
                'accessory_out_fee_modify' => $fee_data['accessory_out_fee_modify'] - $out_fees['accessory_out_fee_modify'] + $add_fee_update_data['accessory_out_fee_modify'],
            ];
            $settle_data['service_fee_modify'] = ($settle_data['worker_repair_fee_modify'] + $settle_data['accessory_out_fee_modify']) * C('NOTINRUANCE_SERVICE_FEE_PERENT');

            OrderSettlementService::orderFeeStatisticsUpdateFee($order_id, $settle_data);
        }

        //保外单操作记录
        OrderOperationRecordService::create($order_id, $operation_type, $extras);
        OrderSettlementService::autoSettlement();

        M()->commit();

    }

    /*
     * 工单退回
     */
    public function orderReturn($order_id, $request, $user_id)
    {
        if (empty($request['reason'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '退单理由不能为空');
        }
        $model = BaseModel::getInstance('worker_order');

        $order_info = $this->checkWorkerOrder($order_id, $user_id, 'worker_id, worker_order_status, distributor_id, orno, worker_group_id, children_worker_id', [
            'worker_order_status' => ['egt', OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]
        ]);

        if ($request['reason'] == '1') {
            $reason = '无法满足用户要求';
        } elseif ($request['reason'] == '2') {
            $reason = '不会维修';
        } elseif ($request['reason'] == '3') {
            $reason = '退回工单';
        } else {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '退单理由不能为其他');
        }
        M()->startTrans();
        if (!empty($order_info['worker_group_id']) && $user_id == $order_info['children_worker_id']) {
            // 群成员退单，工单退回给群主
            $model->update([
                'id' => $order_id
            ], [
                'children_worker_id' => null
            ]);
            //添加操作记录
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_RETURN_ORDER_TO_OWNER, [
                'operator_id' => $user_id,
                'remark' => $reason.'-'.$request['remark'],
                'original_handle_worker_id' => $order_info['worker_id'],
                'original_worker_order_status' => $order_info['worker_order_status']
            ]);
            //群内工单修改数量
            event(new UpdateOrderNumberEvent([
                'worker_order_id'             => $order_id,
                'operation_type'              => OrderOperationRecordService::WORKER_RETURN_ORDER_TO_OWNER,
                'original_children_worker_id' => $order_info['children_worker_id']
            ]));
        } else {
            // 普通技工或群主操作，工单直接退回
            //群内工单修改数量
            event(new UpdateOrderNumberEvent([
                'worker_order_id'              => $order_id,
                'operation_type'               => OrderOperationRecordService::WORKER_RETURN_ORDER,
                'original_worker_id'           => $order_info['worker_id'],
                'original_children_worker_id'  => $order_info['children_worker_id'],
                'original_worker_order_status' => $order_info['worker_order_status']
            ]));
            $model->update([
                'id' => $order_id
            ], [
                'worker_id'           => null,
                'worker_order_status' => 5,
                'last_update_time'    => NOW_TIME,
                'worker_group_id'     => null,
                'children_worker_id'  => null,
            ]);
            //添加操作记录
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_RETURN_ORDER, [
                'operator_id' => $user_id,
                'remark' => $reason.'-'.$request['remark'],
                'see_auth' => OrderOperationRecordService::PERMISSION_CS | OrderOperationRecordService::PERMISSION_WORKER
            ]);
            //后台推送
            $content = '工单号'.$order_info['orno'].',技工已经退单';
            SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order_info['distributor_id'], $content, $order_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_WORKER_CHARGE_BACK);
        }
        //重置fee表里面的分成比例
        BaseModel::getInstance('worker_order_fee')->update([
            'worker_order_id' => $order_id
        ], [
            'cp_worker_proportion' => 0
        ]);
        M()->commit();
    }

    /*
     * 工单延时
     */
    public function orderDelay($order_id, $request, $user_id)
    {
        $model = BaseModel::getInstance('worker_order');

        $order_info = $this->checkWorkerOrder($order_id, $user_id, 'worker_id, worker_order_status, worker_receive_time, extend_appoint_time, worker_group_id', [
            'worker_order_status' => OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT
        ]);

        if ($order_info['worker_receive_time'] + $order_info['extend_appoint_time'] * 3600 > NOW_TIME) {
            //如果工单到期时间大于当前时间
            $extend_appoint_time = $order_info['extend_appoint_time'] + 3;
        } else {
            $extend_appoint_time = ceil((NOW_TIME - $order_info['worker_receive_time']) / (3 * 3600)) * 3;
        }
        if ($extend_appoint_time < 6) {
            $extend_appoint_time = 6;
        }
        M()->startTrans();
        $model->update([
            'id' => $order_id
        ], [
            'extend_appoint_time' => $extend_appoint_time
        ]);
        $receive_time = $order_info['worker_receive_time'] + $extend_appoint_time * 3600;
        //添加操作记录
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_EXTEND_APPOINT_TIME, [
            'operator_id' => $user_id,
            'content_replace' => [
                'appoint_time' => date('Y-m-d H:i', $receive_time),
            ],
            'remark' => !empty($request['remark']) ? $request['remark'] : ''
        ]);
        M()->commit();
    }

    /*
     * 工单跟踪
     */
    public function orderTrack($order_id, $user_id)
    {
        $list = BaseModel::getInstance('worker_order_operation_record')->getList([
            'where' => [
                'worker_order_id' => $order_id,
                'operation_type' => ['in', OrderOperationRecordService::getOrderTrackStatus()]
            ],
            'field' => 'id, create_time, content, remark, operation_type',
            'order' => 'create_time desc'
        ]);
        return $list;
    }

    /*
     * 工单费用明细
     */
    public function orderCharge($order_id, $user_id)
    {
        $order_info = BaseModel::getInstance('worker_order')->getOne([
            'where' => [
                'id' => $order_id
            ],
            'field' => 'worker_order_status, service_type, factory_id, worker_order_type, worker_group_id, worker_id, children_worker_id'
        ]);
        // 是否是保内单
        $is_insurance = in_array($order_info['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST);
        $fee = BaseModel::getInstance('worker_order_fee')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => 'homefee_mode, insurance_fee, worker_repair_fee,worker_repair_fee_modify, accessory_out_fee,accessory_out_fee_modify, user_discount_out_fee, service_fee, service_fee_modify, cp_worker_proportion,worker_total_fee,worker_total_fee_modify,worker_net_receipts'
        ]);
        $worker_proportion = 1;
        if (!empty($order_info['worker_group_id'])) {
            if ($user_id == $order_info['worker_id']) {
                $worker_proportion = 1 - $fee['cp_worker_proportion'] / 10000;
            } elseif ($user_id == $order_info['children_worker_id']) {
                $worker_proportion = $fee['cp_worker_proportion'] / 10000;
            }
        }
        $pay_type = BaseModel::getInstance('worker_order_user_info')->getFieldVal([
            'worker_order_id' => $order_id
        ], 'pay_type');

        //检查是否有未返件的配件单
        $accessory_id = BaseModel::getInstance('worker_order_apply_accessory')->getFieldVal([
            'where' => [
                'worker_order_id'  => $order_id,
                'accessory_status' => 7,
                'cancel_status'    => 0
            ]
        ], 'id');
        if (!empty($accessory_id)) {
            $audit_status = '1';
        } else {
            if (in_array($order_info['worker_order_status'], ['10', '11', '13', '14', '15'])) {
                $audit_status = '2';
            } elseif (in_array($order_info['worker_order_status'], ['16', '17', '18'])) {
                $audit_status = '3';
            }
        }

        //判断是维修还是安装
        if ($order_info['service_type'] == '106' || $order_info['service_type'] == '110') {
            //安装
            $product['type'] = '2';
        } else {
            //维修
            $product['type'] = '1';
        }
        $product['list'] = $this->getProduct($order_id, 'id, fault_id, cp_fault_name, concat(worker_repair_fee * '.$worker_proportion.') as worker_repair_fee, concat(worker_repair_fee_modify * '.$worker_proportion.') as worker_repair_fee_modify, worker_repair_reason, cp_category_name');

        //上门费
        $appoint_model = BaseModel::getInstance('worker_order_appoint_record');
        $door_fee['count'] = $appoint_model->getNum([
            'worker_order_id' => $order_id,
            'is_over' => 1
        ]);
        $door_fee['list'] = $appoint_model->getList([
            'where' => [
                'worker_order_id' => $order_id,
                'is_over' => 1
            ],
            'order' => 'create_time asc',
            'field' => 'id, concat(worker_appoint_fee * '.$worker_proportion.') as worker_appoint_fee, concat(worker_appoint_fee_modify * '.$worker_proportion.') as worker_appoint_fee_modify, worker_appoint_reason, worker_appoint_remark'
        ]);

        //费用单
        $costs['cost_list'] = BaseModel::getInstance('worker_order_apply_cost')->getList([
            'where' => [
                'worker_order_id' => $order_id,
                'status' => 4,
            ],
            'order' => 'create_time desc',
            'field' => 'id, type, concat(fee * '.$worker_proportion.') as fee'
        ]);
        $costs['accessory_list'] = BaseModel::getInstance('worker_order_apply_accessory')->getList([
            'where' => [
                'worker_order_id' => $order_id,
                'worker_return_pay_method' => 1
            ],
            'order' => 'create_time desc',
            'field' => 'id, concat(worker_transport_fee_modify * '.$worker_proportion.') as worker_transport_fee,worker_transport_fee_modify * '.$worker_proportion.' as worker_transport_fee_modify,worker_transport_fee_reason'
        ]);

        //补贴
        $subsidy['list'] = BaseModel::getInstance('worker_order_apply_allowance')->getList([
            'where' => [
                'worker_order_id' => $order_id,
                'status' => 1,
            ],
            'order' => 'create_time desc',
            'field' => 'id, type, concat(apply_fee * '.$worker_proportion.') as apply_fee, concat(apply_fee_modify * '.$worker_proportion.') as apply_fee_modify, modify_reason'
        ]);

        // 因为不想App改动太大，所以保外单费用需要做特别处理
        $ext_info = [];
        $pay_type_detail = WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO;
        if (!$is_insurance) {
            $first_add_fee = BaseModel::getInstance('worker_order_out_worker_add_fee')->getOne([
                'field' => 'pay_type',
                'order' => 'create_time desc,id desc',
                'where' => [
                    'worker_order_id' => $order_id,
                    'pay_time'        => ['neq', 0],
                ],
            ]);
            $pay_type_detail = $first_add_fee['pay_type'];
            $ext_info = BaseModel::getInstance('worker_order_ext_info')->getOne($order_id, 'worker_repair_out_fee_reason,accessory_out_fee_reason');
            // 限制死只能返回一个费用
            $frist = reset($product['list']);
            if ($frist) {
                $frist['worker_repair_fee'] = number_format($fee['worker_repair_fee'] * $worker_proportion, 2, '.', '');
                $frist['worker_repair_fee_modify'] = number_format($fee['worker_repair_fee_modify'] * $worker_proportion, 2, '.', '');
                $frist['worker_repair_reason'] = $ext_info['worker_repair_out_fee_reason'];
                $product['list'] = [$frist];
            }
        }

        return [
            'audit_status'             => $audit_status,
            'total_fee'         => $fee['worker_total_fee'],
            'total_fee_modify'  => $fee['worker_total_fee_modify'],
            'net_receipts'      => $fee['worker_net_receipts'],
            'homefee_mode'             => $fee['homefee_mode'],
            'insurance_fee'            => number_format($fee['insurance_fee'] * $worker_proportion, 2, '.', ''),
            'worker_repair_fee'        => number_format($fee['worker_repair_fee'] * $worker_proportion, 2, '.', ''),
            'worker_repair_fee_modify' => number_format($fee['worker_repair_fee_modify'] * $worker_proportion, 2, '.', ''),
            'worker_repair_out_fee_reason' => $ext_info['worker_repair_out_fee_reason'],
            'accessory_out_fee'        => number_format($fee['accessory_out_fee'] * $worker_proportion, 2, '.', ''),
            'accessory_out_fee_modify' => number_format($fee['accessory_out_fee_modify'] * $worker_proportion, 2, '.', ''),
            'accessory_out_fee_reason' => $ext_info['accessory_out_fee_reason'],
            'user_discount_out_fee'    => number_format($fee['user_discount_out_fee'] * $worker_proportion, 2, '.', ''),
            'worker_order_type'        => $order_info['worker_order_type'],
            'pay_type'                 => $pay_type,
            'pay_type_detail'          => $pay_type_detail,
            'product'                  => $product,
            'door_fee'                 => $door_fee,
            'costs'                    => $costs,
            'subsidy'                  => $subsidy,
            'service'                  => [
                'service_fee'        => number_format($fee['service_fee'] * $worker_proportion, 2, '.', ''),
                'service_fee_modify' => number_format($fee['service_fee_modify'] * $worker_proportion, 2, '.', '')
            ]
        ];
    }

    /*
     * 保外单费用详情
     */
    public function warrantyFeeInfo($order_id, $request, $user_id)
    {
        $order_info = $this->checkWorkerOrder($order_id, $user_id);
        $worker_order_user_info = $this->getOrderUserInfo($order_id);
        if ($request['is_check_pay_result'] == '1') {
            return [
                'is_user_pay' => $worker_order_user_info['is_user_pay']
            ];
        }

        $out_model = BaseModel::getInstance('worker_order_out_worker_add_fee');
        $not_pay_out_fees = $out_model->getOne([
            'field' => 'sum(accessory_out_fee_modify) as accessory_out_fee_modify,sum(worker_repair_fee_modify) as worker_repair_fee_modify',
            'where' => [
                'worker_order_id' => $order_id,
                'pay_time' => 0,
            ],
        ]);

//        $worker_order_user_info == OrderUserService::IS_USER_PAY_SUCCESS && $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '没有未支付的费用');

        $product_info = BaseModel::getInstance('worker_order_product')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => 'product_category_id, product_standard_id, fault_id'
        ]);
//        $fault = $this->getFactoryFaultPriceByCategoryIdAndStandardId($order_info['factory_id'], $product_info['product_category_id'], $product_info['product_standard_id'], $product_info['fault_id'], FaultTypeService::getFaultType($order_info['service_type']), $product_info['fault_id'], $order_info['worker_order_type']);
        $fault = $this->getFaultFeeList([
            'factory_id' => $order_info['factory_id'],
            'category_id' => $product_info['product_category_id'],
            'standard_id' => $product_info['product_standard_id'],
            'fault_type' => FaultTypeService::getFaultType($order_info['service_type']),
            'fault_id' => $product_info['fault_id']
        ], true);
        return [
            'id' => $order_id,
            'orno' => $order_info['orno'],
            'worker_repair_fee_modify' => $not_pay_out_fees['worker_repair_fee_modify'] ?? '0.00',
            'accessory_out_fee'        => $not_pay_out_fees['accessory_out_fee_modify'] ?? '0.00',
            'pay_type' => $worker_order_user_info['pay_type'],
            'is_user_pay' => $worker_order_user_info['is_user_pay'],
            'fault' => $fault
        ];
    }

    /*
     * 产品规格列表
     */
    public function productStandards($order_id, $product_id, $user_id)
    {
        $this->checkWorkerOrder($order_id, $user_id);
        $category_id = BaseModel::getInstance('worker_order_product')->getFieldVal([
            'id' => $product_id
        ], 'product_category_id');
        $standards = BaseModel::getInstance('product_standard')->getList([
            'where' => [
                'product_id' => $category_id,
            ],
            'field' => 'standard_id as id,standard_name as name',
        ]);
        return $standards;
    }

    /*
     * 选择产品规格
     */
    public function updateProductStandard($order_id, $product_id, $standard_id, $user_id)
    {
        $this->checkWorkerOrder($order_id, $user_id, '*', [
            'worker_order_status' => ['in', OrderService::getOrderInService()]
        ]);
        $product_model = BaseModel::getInstance('worker_order_product');
        $standard_model = BaseModel::getInstance('product_standard');
        $product_info = $product_model->getOne([
            'id' => $product_id
        ], 'product_brand_id, product_category_id, product_standard_id');
        if (!$standard_model->dataExist([
            'product_id'  => $product_info['product_category_id'],
            'standard_id' => $standard_id
        ])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '规格不匹配,请重新选择');
        }
        if ($product_info['product_standard_id'] != $standard_id) {
            $update_products = [];
            $update_products['fault_id'] = 0;
            $update_products['cp_fault_name'] = '';
            $update_products['cp_product_standard_name'] = $standard_model->getFieldVal([
                'standard_id' => $standard_id
            ], 'standard_name');
            $update_products['product_standard_id'] = $standard_id;

            M()->startTrans();
            $product_model->update([
                'id' => $product_id
            ], $update_products);

            OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_UPDATE_ORDER_PRODUCT, [
                'operator_id' => $user_id,
                'remark' => '工单产品规格修改为：' . $update_products['cp_product_standard_name'],
            ]);
            M()->commit();
        }
    }

    protected function checkWorkerIsRepeatedlyAppoint($worker_order_id, $worker_id)
    {
        $cs_last_distribute_record_id = BaseModel::getInstance('worker_order_operation_record')->getFieldVal([
            'where' => [
                'worker_order_id' => $worker_order_id,
                'operation_type' => OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE,
            ],
            'order' => 'id DESC'
        ], 'id');
        // 防止多次重复预约
        $appoint_record_id = BaseModel::getInstance('worker_order_operation_record')->getFieldVal([
            'where' => [
                'worker_order_id' => $worker_order_id,
                'operator_id' => $worker_id,
                'operation_type' => OrderOperationRecordService::WORKER_APPOINT_SUCCESS,
                'id' => ['GT', intval($cs_last_distribute_record_id)],
            ],
            'order' => 'id DESC'
        ], 'id');
        if ($appoint_record_id) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '您已预约成功,无需重新续约或请直接修改预约');
        }
    }

}
