<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/23
 * Time: 17:54
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\ComplaintService;
use Common\Common\Service\OrderService;
use Illuminate\Support\Arr;
use Library\Common\ExcelExport;

class StatisticsController extends BaseController
{

    /**
     * 工单费用导出(财务用)
     */
    public function orderFeeExport()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
//            $user = AuthService::getAuthModel();
            if (!in_array(AuthService::getAuthModel()->getPrimaryValue(), C('ORDER_STATISTIC_PERMISSION_USER'))) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '您无权限使用~');
            }

            ini_set('memory_limit', '1024M');
            set_time_limit(0);
            $start_time = I('start_time');
            $end_time = I('end_time');
            $start_time = strtotime($start_time);
            $end_time = strtotime($end_time);
            if (!$start_time || !$end_time) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写开始和结束时间');
            }

            $orders = BaseModel::getInstance('worker_order')
                ->getList([
                    'field' => 'id,orno,real_name,phone,cp_area_names,address,factory_total_fee_modify,worker_total_fee_modify,create_time,service_type,worker_order_type,audit_time,factory_audit_time,factory_id',
                    'join' => [
                        'LEFT JOIN worker_order_user_info ON worker_order_user_info.worker_order_id=worker_order.id',
                        'LEFT JOIN worker_order_fee ON worker_order_fee.worker_order_id=worker_order.id',
                    ],
                    'where' => [
                        'factory_audit_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time]
                        ],
                        'worker_order_status' => OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
                    ],
                    'order' => 'id ASC'
                ]);

            $order_list = [];
            $order_ids = [];
            $factory_ids = [];
            $total_fact_pay = 0;
            $total_order_num = 0;
            foreach ($orders as $order) {
                $order_ids[] = $order['id'];
                $order['factory_id'] && $factory_ids[] = $order['factory_id'];

                $service_type_name = OrderService::SERVICE_TYPE[$order['service_type']];

                $order_type_desc = OrderService::getOrderTypeName($order['worker_order_type']);

                $areas = explode('-', $order['cp_area_names']);
                $order_list[] = [
                    'order_id' => $order['id'],
                    'factory_id' => $order['factory_id'],
                    'orno' => $order['orno'],
                    'type_desc' => $service_type_name,
                    'order_type_desc' => $order_type_desc,
                    'add_time' => date('Y.m.d H:i', $order['create_time']),
                    'real_name' => $order['real_name'],
                    'phone' => $order['phone'],
                    'province' => $areas[0],
                    'city' => $areas[1],
                    'district' => $areas[2],
                    'area_desc' => $order['cp_area_names'],
                    'address' => $order['address'],
                    'platform_check_time' => date('Y.m.d H:i', $order['audit_time']),
                    'factory_total_fee_modify' => $order['factory_total_fee_modify'],
                    'worker_total_fee_modify' => $order['worker_total_fee_modify'],
                    'order_process_time' => round(($order['factory_audit_time'] - $order['create_time']) / 86400, 2),
                ];

                $total_fact_pay += $order['factory_total_fee_modify'];
                ++$total_order_num;
            }


            $factory_ids = array_unique($factory_ids) ? : 'null';
            $factory_id_map = BaseModel::getInstance('factory')->getList([
                'where' => ['factory_id' => ['IN', $factory_ids]],
                'field' => 'factory_id,factory_full_name',
                'index' => 'factory_id'
            ]);
            $worker_order_id_where = $order_ids ? ['IN', array_unique($order_ids)] : 'null';
            $order_details = BaseModel::getInstance('worker_order_product')
                ->getList([
                    'field' => 'worker_order_id,cp_category_name,cp_product_standard_name,cp_product_brand_name,cp_product_mode,cp_fault_name',
                    'where' => [
                        'worker_order_id' => $worker_order_id_where,
                    ]
                ]);

            $order_id_detail_map = [];
            foreach ($order_details as $order_detail) {
                $order_id_detail_map[$order_detail['worker_order_id']][] = $order_detail;
            }

            $export_obj = new ExcelExport();
            $export_obj->setSheet(0)->setStartPlace('A', 2);
            $export_obj->setTplPath('Public/caiwu_excel.xls');
            foreach ($order_list as $key => $val) {
                $details = $order_id_detail_map[$val['order_id']];
                $data = [$val['orno'], $val['add_time'], $val['add_time'], $val['order_type_desc'], $val['real_name'], $val['phone'], $val['province'], $val['city'], $val['district'], $val['address'], $details[0]['cp_category_name'], $details[0]['cp_product_standard_name'], $details[0]['cp_product_brand_name'], $details[0]['cp_product_mode'], $details[0]['cp_fault_name'], $factory_id_map[$val['factory_id']]['factory_full_name'], $val['platform_check_time'], $val['order_process_time'], $val['factory_total_fee_modify'], $val['worker_total_fee_modify'], $val['factory_total_fee_modify'] - $val['worker_total_fee_modify']];
                if ($key == 0) {
                    $data[] = round($total_fact_pay / $total_order_num, 2);
                }
                $export_obj->setRowData($data);
                array_splice($details, 0, 1);
                foreach ($details as $detail) {
                    $export_obj->setRowData(['', '', '', '', '', '', '', '', '', '', $detail['cp_category_name'], $detail['cp_product_standard_name'], $detail['cp_product_brand_name'], $detail['cp_product_mode'], $detail['cp_fault_name'], $factory_id_map[$val['factory_id']]['factory_full_name']]);
                }
            }
            $fileName = date('ymd', $start_time) . '-' . date('ymd', $end_time) . '工单数据' . '.xls';
            $export_obj->download($fileName);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    // 周报
    public function week()
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);

        if (!$start_time || !$end_time) {
            exit('请填写正确的时间');
        }

        $order_finish_status = OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED;
        $model = M('');
        $total_c_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_audit_time` >= 1484409600
            AND `factory_audit_time` < {$end_time}
            AND worker_order_type IN(5,6)
            AND worker_order_status = {$order_finish_status}";
        $total_c = $model->query($total_c_sql)[0]['c'];

        $total_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_audit_time` < {$end_time}
            AND worker_order_status = {$order_finish_status}";
        $total = $model->query($total_sql)[0]['c'];

        $range_rework_total_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `orno` LIKE 'F%'
            AND `factory_audit_time` >= {$start_time}
            AND `factory_audit_time` < {$end_time}
            AND worker_order_status = {$order_finish_status}";
        $range_rework_total = $model->query($range_rework_total_sql)[0]['c'];

        $range_c_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_audit_time` >= {$start_time}
            AND `factory_audit_time` < {$end_time}
            AND worker_order_type  IN(5,6)
            AND worker_order_status = {$order_finish_status}";
        $range_c = $model->query($range_c_sql)[0]['c'];

        $range_sql = "SELECT
             COUNT(*) c,sum(factory_audit_time-create_time) sum_time
            FROM
             worker_order
            WHERE
             `factory_audit_time` >= {$start_time}
            AND `factory_audit_time` < {$end_time}
            AND worker_order_status = {$order_finish_status}";
        $range_result = $model->query($range_sql);
        $range = $range_result[0]['c'];
        $range_process_time_acg = round($range_result[0]['sum_time'] / $range / 86400, 2);

        $service_type_num_map = BaseModel::getInstance('worker_order')->getList([
            'where' => [
                'worker_order_status' => $order_finish_status,
                'factory_audit_time' => [
                    ['EGT', $start_time],
                    ['LT', $end_time],
                ]
            ],
            'group' => 'service_type',
            'field' => 'service_type,count(*) service_type_num',
            'index' => 'service_type',
        ]);

        $new_order_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `create_time` >= {$start_time}
            AND `create_time` < {$end_time}";
        $new_order = $model->query($new_order_sql)[0]['c'];

        // 取消工单数量
        $cancel_order_num = BaseModel::getInstance('worker_order')->getNum([
            'cancel_status' => ['IN', [OrderService::CANCEL_TYPE_WX_USER, OrderService::CANCEL_TYPE_WX_DEALER, OrderService::CANCEL_TYPE_FACTORY, OrderService::CANCEL_TYPE_CS, OrderService::CANCEL_TYPE_FACTORY_ADMIN]],
            'cancel_time' => [
                ['EGT', $start_time],
                ['LT', $end_time],
            ]
        ]);
        $cancel_order_num += BaseModel::getInstance('worker_order')->getNum([
           'worker_order_status' => 1,
            'factory_check_order_time' => [
                ['EGT', $start_time],
                ['LT', $end_time],
            ]
        ]);

        $unfinished_order = BaseModel::getInstance('worker_order')->getNum([
            'worker_order_status' => ['NOT IN', [OrderService::STATUS_CREATED, OrderService::STATUS_FACTORY_SELF_PROCESSED, OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED]],
            'cancel_status' => OrderService::CANCEL_TYPE_NULL,
        ]);

        $yi_out_sql = "SELECT
                sum(nums) s
            FROM
                factory_excel
            where add_time >= {$start_time}
            AND add_time < {$end_time}";
        $yi_out = $model->query($yi_out_sql)[0]['s'];

        $yi_used_sql = "SELECT
             sum(nums) s
            FROM
             factory_product_qrcode
            WHERE
             datetime >= {$start_time}
            AND datetime <  {$end_time}";
        $yi_used = $model->query($yi_used_sql)[0]['s'];

        $yi_actived = 0;
        for ($i = 0; $i < 16; ++$i) {
            $yima_table = 'yima_' . $i;
            $yi_actived_sql = "SELECT
             count(*) s
            FROM
             {$yima_table}
            WHERE
             register_time >= {$start_time}
            AND register_time<{$end_time}";
            $yi_actived += $model->query($yi_actived_sql)[0]['s'];
        }

        $money_total = BaseModel::getInstance('worker_order')->getOne([
            'where' => [
                'worker_order_status' => $order_finish_status,
                'factory_audit_time' => [
                    ['EGT', $start_time],
                    ['LT', $end_time],
                ],
            ],
            'join' => 'LEFT JOIN worker_order_fee ON worker_order_fee.worker_order_id=worker_order.id',
            'field' => 'sum(factory_total_fee_modify) factory_total,sum(worker_total_fee_modify) worker_total',
        ]);

        $factory_online_charge = BaseModel::getInstance('factory_money_change_record')->getSum([
            'change_type' => ['IN', [1,2,3]],
            'create_time' => [
                ['EGT', $start_time],
                ['LT', $end_time],
            ],
            'status' => 1,
        ], 'change_money');
        $factory_adjust = BaseModel::getInstance('factory_money_change_record')->getSum([
            'change_type' => 5,
            'create_time' => [
                ['EGT', $start_time],
                ['LT', $end_time],
            ],
            'status' => 1,
        ], 'change_money');
        $worker_adjust = BaseModel::getInstance('worker_money_adjust_record')->getSum([
            'create_time' => [
                ['EGT', $start_time],
                ['LT', $end_time],
            ],
        ], 'adjust_money');

        $order_handlers = BaseModel::getInstance('worker_order')->getOne([
            'where' => [
                'worker_order_status' => $order_finish_status,
                'factory_audit_time' => [
                    ['EGT', $start_time],
                    ['LT', $end_time],
                ],
            ],
            'field' => 'count(distinct(factory_id)) total_factory,count(distinct(worker_id)) worker_total',
        ]);


        $this->response([
            'C端总工单量' => $total_c,
            '总工单量(不包括C端)' => $total - $total_c,
            'C端时间段工单量' => $range_c,
            '时间段总工单量(不包括C端)' => $range - $range_c,
            '时间段平均工单处理时间' => $range_process_time_acg,
            '上门安装量' => (int)$service_type_num_map[OrderService::TYPE_WORKER_INSTALLATION]['service_type_num'],
            '上门维修量' => (int)$service_type_num_map[OrderService::TYPE_WORKER_REPAIR]['service_type_num'],
            '上门维护量' => (int)$service_type_num_map[OrderService::TYPE_WORKER_MAINTENANCE]['service_type_num'],
            '用户送修量' => (int)$service_type_num_map[OrderService::TYPE_USER_SEND_FACTORY_REPAIR]['service_type_num'],
            '预发件安装单' => (int)$service_type_num_map[OrderService::TYPE_PRE_RELEASE_INSTALLATION]['service_type_num'],
            '完成返修单量' => (int)$range_rework_total,
            '未完成工单' => $unfinished_order,
            '新建工单量' => $new_order,
            '取消工单量' => $cancel_order_num,
            '易码发放量' => (int)$yi_out,
            '易码厂家使用量' => (int)$yi_used,
            '易码激活量' => (int)$yi_actived,
            '工单完结率' => round(1 - $cancel_order_num / $new_order, 4),
            '工单收入总额(保内)' => $money_total['factory_total'],
            '技工总收入' => $money_total['worker_total'],
            '技工总调整' => $worker_adjust,
            '厂家在线充值总额' => $factory_online_charge,
            '厂家总调整' => $factory_adjust,
            '总下单厂家' => $order_handlers['total_factory'],
            '总派单技工' => $order_handlers['worker_total'],
        ]);
    }

    /**
     * 计算技工分数
     */
    public function calWorkerScore()
    {
        set_time_limit(0);
        $batch_no = 10000;
        $worker_logic = D('Worker', 'Logic');
        $order_logic = D('Order', 'Logic');
        $statistic_model = BaseModel::getInstance('worker_statistics');
        $start_id = 0;
        $statistic_model->startTrans();
        while (true) {
            $workers = BaseModel::getInstance('worker')
                ->getList([
                    'field' => 'worker_id,worker_telephone,quality_money,quality_money_need',
                    'order' => 'worker_id ASC',
                    'where' => [
                        'worker_id' => ['GT', $start_id],
                    ],
                    'limit' => $batch_no,
                ]);
            if (!$workers) {
                break;
            }

            $worker_ids = Arr::pluck($workers, 'worker_id');

            // 返回 return_fraction 返件得分; appiont_fraction 快速预约得分; return_fraction 快速上门得分; all_order_nums 总共单量; cancel_order_nums 取消工单总数； zjz
            $reputation = $worker_logic->loadWorkerOrderReputationByWorkers($worker_ids);

            $worker_id_coop_score_map = $worker_logic->loadContractQualification($workers, $worker_ids);
            $worker_id_order_score_map = $order_logic->loadWorkerPaidOrder($worker_ids);
            // 无投诉单
            $worker_id_complaint_score_map = $worker_logic->complaintScore($workers, $worker_ids);

            $start_id = last($worker_ids);

            unset($workers);

            $timestamp = NOW_TIME;
            $worker_data = [];
            foreach ($worker_ids as $worker_id) {
                $w_id = $worker_id;

                // 跟信誉分相关的维度， 假设根据信誉分对应的分数为A， 则调整为：A*（已结算工单量对应的分数*20/100） 无投诉单,无取消单,上门时效,预约时效,返件时效
                $bfb = ($worker_id_order_score_map[$w_id]/100) * 20 / 100;

                $worker_id_complaint_score_map[$w_id] = $worker_id_complaint_score_map[$w_id] * $bfb;       // 无投诉单
                $reputation[$w_id]['cancel_score'] = $reputation[$w_id]['cancel_score'] * $bfb;             // 无取消单
                $reputation[$w_id]['return_fraction_score'] = $reputation[$w_id]['return_fraction_score'] * $bfb; // 返件时效 (返件得分)
                $reputation[$w_id]['appiont_fraction_score'] = $reputation[$w_id]['appiont_fraction_score'] * $bfb; // 预约时效 (快速预约得分)
                $reputation[$w_id]['arrive_fraction_score'] = $reputation[$w_id]['arrive_fraction_score'] * $bfb; // 上门时效 (快速上门得分)

                $worker_data[] = "({$w_id},{$worker_id_coop_score_map[$w_id][0]},{$worker_id_coop_score_map[$w_id][1]},{$worker_id_order_score_map[$w_id]},{$worker_id_complaint_score_map[$w_id]},{$reputation[$w_id]['cancel_score']},{$reputation[$w_id]['arrive_fraction_score']},{$reputation[$w_id]['appiont_fraction_score']},{$reputation[$w_id]['return_fraction_score']},{$timestamp})";
            }

            $worker_data = implode(',', $worker_data);
            $sql = "INSERT INTO `worker_statistics`(`worker_id`,`contract_qualification_a`,`contract_qualification_b`,`paid_order`,`no_complaint`,`no_cancel`,`on_work_time`,`appoint_time`,`return_time`,`updated_time`) VALUES{$worker_data} ON DUPLICATE KEY UPDATE `contract_qualification_a`=VALUES(`contract_qualification_a`),`contract_qualification_b`=VALUES(`contract_qualification_b`),`paid_order`=VALUES(`paid_order`),`no_complaint`=VALUES(`no_complaint`),`no_cancel`=VALUES(`no_cancel`),`on_work_time`=VALUES(`on_work_time`),`appoint_time`=VALUES(`appoint_time`),`return_time`=VALUES(`return_time`),`updated_time`=VALUES(`updated_time`)";
            $statistic_model->execute($sql);
        }
        $statistic_model->commit();
    }



}