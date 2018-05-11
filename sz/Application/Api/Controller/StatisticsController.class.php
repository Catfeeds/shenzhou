<?php
/**
 * File: StatisticsController.class.php
 * User: xieguoqiu
 * Date: 2017/3/3 10:25
 */

namespace Api\Controller;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Carbon\Carbon;
use Library\Common\Util;

class StatisticsController extends BaseController
{

    public function kfOrder()
    {
        ini_set('memory_limit', '500M');
        set_time_limit(0);

        $start_time = I('start_time');
        $end_time = I('end_time');

        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);

        if (!$start_time || !$end_time) {
            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写开始和结束时间');
        }

        try {
            // 本月工单
            $orders = BaseModel::getInstance('worker_order')->getList(
                [
                    'field' => 'order_id,distribute_time,return_time',
                    'where' => [
                        'platform_check_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time]
                        ],
                        'is_cancel' => 0,
                        'is_delete' => 0,
                    ]
                ]
            );



            $order_ids = [];
            $order_id_map = [];
            foreach ($orders as $order) {
                $order_ids[] = $order['order_id'];
                $order_id_map[$order['order_id']] = $order;
            }

            // 工单访问权限客服
            $access_admins = BaseModel::getInstance('worker_order_access')->getList([
                'field' => 'worker_order_access.*,admin.nickout',
                'join' => [
                    'LEFT JOIN admin ON admin.id=worker_order_access.admin_id',
                    'LEFT JOIN worker_order ON worker_order.order_id=worker_order_access.link_order_id',
                ],
                'where' => [
                    'platform_check_time' => [
                        ['EGT', $start_time],
                        ['LT', $end_time]
                    ],
                    'is_cancel' => 0,
                    'is_delete' => 0,
                ],
                'order' => 'id DESC'
            ]);
            $access_admin_order_group = [];
            foreach ($access_admins as $access_admin) {
                $access_admin_order_group[$access_admin['link_order_id']][] = $access_admin;
            }

            // 提交给神州财务审核的客服
            $visit_admins = BaseModel::getInstance('worker_order_operation_record')
                ->getFieldVal([
                    'join' => [
                        'LEFT JOIN admin ON admin.id=worker_order_operation_record.ope_user_id',
                        'LEFT JOIN worker_order ON worker_order.order_id=worker_order_operation_record.order_id',
                    ],
                    'where' => [
                        'ope_type' => 'SL',                 // 确认完成维修,订单变为待财务审核
                        'platform_check_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time]
                        ],
                        'is_cancel' => 0,
                        'is_delete' => 0,
                    ],
                    'order' => 'worker_order_operation_record.id ASC'
                ], 'worker_order_operation_record.order_id,worker_order_operation_record.add_time,ope_user_id admin_id,admin.nickout');

            // 有配件单的工单
            $has_acce_worker_order_ids = BaseModel::getInstance('factory_acce_order')
                ->getFieldVal([
                    'join' => [
                        'LEFT JOIN worker_order ON worker_order.order_id=factory_acce_order.worker_order_id',
                    ],
                    'where' => [
                        'platform_check_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time]
                        ],
                        'worker_order.is_cancel' => 0,
                        'worker_order.is_delete' => 0,
                        'factory_acce_order.is_complete' => 1
                    ]
                ], 'worker_order_id', true);
            $has_acce_worker_order_ids = array_unique($has_acce_worker_order_ids);

            $statistics = [];
            foreach ($order_id_map as $order_id => $item) {

                // 回访客服
                $visit_kf = $visit_admins[$order_id];
                // 接单客服
                $access_kf = end($access_admin_order_group[$order_id]);
                // 客服对内名称
                $statistics[$visit_kf['admin_id']]['name'] = $visit_kf['nickout'];
                // 工单总数
                $statistics[$visit_kf['admin_id']]['total'] += 1;

                // 18点为下班时间，8点半为上班时间，下班后接的单统一归为第二天8点半
                $access_time = $access_kf['add_time'];
                $carbon_access_time = new Carbon(date('Y-m-d H:i:s', $access_time));
                if ($carbon_access_time->hour >= 18) {
                    $carbon_access_time->addDay();
                    $carbon_access_time->setTime(8, 30);
                    $access_time = $carbon_access_time->timestamp;
                }

                $distribute_time = $item['distribute_time'];
                $carbon_distribute_time = new Carbon(date('Y-m-d H:i:s', $distribute_time));
                if ($carbon_distribute_time->hour >= 18) {
                    $carbon_distribute_time->addDay();
                    $carbon_distribute_time->setTime(8, 30);
                    $distribute_time = $carbon_distribute_time->timestamp;
                }
                if ($distribute_time - $access_time <= 1800) {
                    $statistics[$visit_kf['admin_id']]['t1'] += 1;
                } elseif ($distribute_time - $access_time <= 3600) {
                    $statistics[$visit_kf['admin_id']]['t2'] += 1;
                } elseif ($distribute_time - $access_time <= 5400) {
                    $statistics[$visit_kf['admin_id']]['t3'] += 1;
                } elseif ($distribute_time - $access_time <= 7200) {
                    $statistics[$visit_kf['admin_id']]['t4'] += 1;
                } else {
                    $statistics[$visit_kf['admin_id']]['t5'] += 1;
                }

                if (in_array($order_id, $has_acce_worker_order_ids)) {  // 有配件
                    if ($item['return_time'] - $access_kf['add_time'] <= 604800) {
                        $statistics[$visit_kf['admin_id']]['has_acce_t1'] += 1;
                    } else {
                        $statistics[$visit_kf['admin_id']]['has_acce_t2'] += 1;
                    }
                } else {
                    if ($item['return_time'] - $access_kf['add_time'] <= 172800) {
                        $statistics[$visit_kf['admin_id']]['no_acce_t1'] += 1;
                    } else {
                        $statistics[$visit_kf['admin_id']]['no_acce_t2'] += 1;
                    }
                }
            }

            // 取消工单
            $cancel_order_info = BaseModel::getInstance('worker_order_operation_record')
                ->getList([
                    'field' => 'ope_user_id,count(*) cancel_num',
                    'where' => [
                        'ope_type' => 'SO',
                        'add_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time],
                        ]
                    ],
                    'group' => 'ope_user_id'
                ]);
            foreach ($cancel_order_info as $item) {
                $statistics[$item['ope_user_id']]['cancel_order_num'] = $item['cancel_num'];
            }

            // 投诉工单
            $complaint_order_id_time_map = BaseModel::getInstance('worker_order_complaint')
                ->getList([
                    'field' => 'worker_order_id,count(*) num',
                    'where' => [
                        'add_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time],
                        ]
                    ],
                    'group' => 'worker_order_id'
                ]);
            $complaint_order_id_num_map = [];
            foreach ($complaint_order_id_time_map as $item) {
                $complaint_order_id_num_map[$item['worker_order_id']] = $item['num'];
            }
            $complaint_order_ids = array_keys($complaint_order_id_num_map);
            $complain_visit_admins = BaseModel::getInstance('worker_order_operation_record')
                ->getFieldVal([
                    'join' => 'LEFT JOIN admin ON admin.id=worker_order_operation_record.ope_user_id',
                    'where' => [
                        'order_id' => ['IN', $complaint_order_ids],
                        'ope_type' => 'SL',                 // 确认完成维修,订单变为待财务审核
                    ],
                    'order' => 'worker_order_operation_record.id ASC'
                ], 'order_id,worker_order_operation_record.add_time,ope_user_id admin_id,admin.nickout');
            foreach ($complain_visit_admins as $order_id => $complain_visit_admin) {
                $statistics[$complain_visit_admin['admin_id']]['complaint_order_num']
                    = $complaint_order_id_num_map[$order_id];
                $statistics[$complain_visit_admin['admin_id']]['name'] = $complain_visit_admin['nickout'];
            }

            Util::sortByField($statistics, 'total', 1);

            $filePath = './Public/customer_service_kpi.xls';
            Vendor('PHPExcel.PHPExcel');
            $objPHPExcel = \PHPExcel_IOFactory::load($filePath);
            $row = 3;
            foreach ($statistics as $statistic) {
                $column = 'A';
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue($column++ . $row, $statistic['name'])
                    ->setCellValue($column++ . $row, $statistic['total'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['t1'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['t2'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['t3'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['t4'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['t5'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['no_acce_t1'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['no_acce_t2'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['has_acce_t1'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['has_acce_t2'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['cancel_order_num'] ? : 0)
                    ->setCellValue($column++ . $row, $statistic['complaint_order_num'] ? : 0);
                ++$row;
            }

            // 取消订单列表
            $cancel_list = BaseModel::getInstance('worker_order_operation_record')
                ->getList([
                    'field' => 'orno,giveup_reason,worker_order_operation_record.add_time,desc,nickout',
                    'join' => [
                        'LEFT JOIN admin ON admin.id=worker_order_operation_record.ope_user_id',
                        'LEFT JOIN worker_order ON worker_order.order_id=worker_order_operation_record.order_id'
                    ],
                    'where' => [
                        'ope_type' => 'SO',
                        'worker_order_operation_record.add_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time],
                        ]
                    ],
                    'order' => 'ope_user_id ASC,worker_order_operation_record.add_time DESC'
                ]);
            $row = 2;
            foreach ($cancel_list as $item) {
                $column = 'A';

                // 1：没网点，2：用户原因，3：厂家原因,4:其他
                if ($item['giveup_reason'] == 1) {
                    $giveup_reason = '没网点';
                } elseif ($item['giveup_reason'] == 2) {
                    $giveup_reason = '用户原因';
                } elseif ($item['giveup_reason']) {
                    $giveup_reason = '厂家原因';
                } else {
                    $giveup_reason = '其他';
                }
                $objPHPExcel->setActiveSheetIndex(1)
                    ->setCellValue($column++ . $row, $item['orno'])
                    ->setCellValue($column++ . $row, $item['nickout'])
                    ->setCellValue($column++ . $row, date('Y-m-d H:i:s', $item['add_time']))
                    ->setCellValue($column++ . $row, $giveup_reason)
                    ->setCellValue($column++ . $row, str_replace('=', ' ', $item['desc']));
                ++$row;
            }

            $fileName = '神州客服KPI考核数据' . date('Y-m-d') . '.xls';

            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename='.$fileName);
            header('Cache-Control: max-age=0');

            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');

            $this->response($statistics);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    public function week()
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);

        if (!$start_time || !$end_time) {
            exit('请填写正确的时间');
        }

        $model = M('');
        $total_c_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` >= 1484409600
            AND `factory_check_time` < {$end_time}
            AND order_origin = 'FC'
            AND is_complete = 1
            AND is_check = 1";
        $total_c = $model->query($total_c_sql)[0]['c'];

        $total_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` < {$end_time}
            AND is_complete = 1
            AND is_check = 1";
        $total = $model->query($total_sql)[0]['c'];

        $range_c_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` >= {$start_time}
            AND `factory_check_time` < {$end_time}
            AND order_origin = 'FC'
            AND is_complete = 1
            AND is_check = 1";
        $range_c = $model->query($range_c_sql)[0]['c'];

        $range_sql = "SELECT
             COUNT(*) c,sum(factory_check_time-datetime) sum_time
            FROM
             worker_order
            WHERE
             `factory_check_time` >= {$start_time}
            AND `factory_check_time` < {$end_time}
            AND is_complete = 1
            AND is_check = 1";
        $range_result = $model->query($range_sql);
        $range = $range_result[0]['c'];
        $range_process_time_acg = round($range_result[0]['sum_time'] / $range / 86400, 2);

        $type_106_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` >= {$start_time}
            AND `factory_check_time` < {$end_time}
            AND is_complete = 1
            AND is_check = 1
            AND servicetype='106'";
        $type_106 = $model->query($type_106_sql)[0]['c'];

        $type_107_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` >= {$start_time}
            AND `factory_check_time` < {$end_time}
            AND is_complete = 1
            AND is_check = 1
            AND servicetype='107'";
        $type_107 = $model->query($type_107_sql)[0]['c'];

        $type_108_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` >= {$start_time}
            AND `factory_check_time` < {$end_time}
            AND is_complete = 1
            AND is_check = 1
            AND servicetype='108'";
        $type_108 = $model->query($type_108_sql)[0]['c'];

        $type_109_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` >= {$start_time}
            AND `factory_check_time` < {$end_time}
            AND is_complete = 1
            AND is_check = 1
            AND servicetype='109'";
        $type_109 = $model->query($type_109_sql)[0]['c'];

        $type_110_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `factory_check_time` >= {$start_time}
            AND `factory_check_time` < {$end_time}
            AND is_complete = 1
            AND is_check = 1
            AND servicetype='110'";
        $type_110 = $model->query($type_110_sql)[0]['c'];

        $new_order_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order
            WHERE
             `datetime` >= {$start_time}
            AND `datetime` < {$end_time}";
        $new_order = $model->query($new_order_sql)[0]['c'];

        $cancel_order_sql = "SELECT
             COUNT(*) c
            FROM
             worker_order_operation_record
            WHERE
             `ope_type` IN('SO', 'FY', 'FK')
            AND `add_time` >= {$start_time}
            AND `add_time` < {$end_time}";
        $cancel_order = $model->query($cancel_order_sql)[0]['c'];

//        $before_15_days = $end_time - 86400 * 15;
//        $unfinished_order_sql = "SELECT
//            COUNT(*) c
//        FROM
//            worker_order
//        WHERE
//            `datetime` < {$end_time}
//        AND `is_need_factory_confirm` = 0
//        AND `is_delete` = 0
//        AND `is_return` = 0
//        AND is_complete = 0
//        AND order_id NOT IN (
//            SELECT
//                order_id
//            FROM
//                worker_order_operation_record
//            WHERE
//                `ope_type` IN ('SO', 'FY', 'FK', 'SL', 'SF', 'FY')
//            AND `add_time` < {$end_time}
//        )
//        AND order_id NOT IN (
//            SELECT
//                order_id
//            FROM
//                worker_order
//            WHERE
//                order_id NOT IN (
//                    SELECT
//                        order_id
//                    FROM
//                        worker_order_operation_record
//                    WHERE
//                        `ope_type` IN ('SO', 'FY', 'FK', 'SL', 'SF', 'FY')
//                    AND `add_time` < {$end_time}
//                )
//            AND (
//                is_fact_cancel = 1
//                OR is_cancel = 1
//            )
//            AND datetime < {$before_15_days}
//        )";
        $unfinished_order_sql = "SELECT count( DISTINCT worker_order_operation_record.order_id ) c FROM worker_order_operation_record INNER JOIN worker_order ON worker_order_operation_record.order_id = worker_order.order_id WHERE worker_order_operation_record.order_id NOT IN ( SELECT DISTINCT order_id FROM worker_order_operation_record WHERE `ope_type` IN ( 'SO', 'FY', 'FK', 'SL', 'SF', 'FY', 'AB' ) AND `add_time` < {$end_time} ) AND worker_order_operation_record.order_id NOT IN ( SELECT order_id FROM worker_order WHERE is_fact_cancel = 1 AND NOT EXISTS ( SELECT order_id FROM worker_order_operation_record WHERE ope_type IN ('SL', 'SO') AND worker_order_operation_record.order_id = worker_order.order_id ) ) AND `add_time` < {$end_time} AND is_delete = 0 AND is_need_factory_confirm = 0";
        $unfinished_order = $model->query($unfinished_order_sql)[0]['c'];


//        $all_order_sql = "SELECT
//             COUNT(*) c
//            FROM
//             worker_order
//            WHERE `datetime` < {$end_time}";
//        $all_order = $model->query($all_order_sql)[0]['c'];
//
//        $all_cancel_order_sql = "SELECT
//             COUNT(*) c
//            FROM
//             worker_order_operation_record
//            WHERE
//             `ope_type` IN('SO', 'FY', 'FK')
//            AND `add_time` < {$end_time}";
//        $all_cancel_order = $model->query($all_cancel_order_sql)[0]['c'];
//
//        $all_factory_not_confirm_sql = "SELECT
//             COUNT(*) c
//            FROM
//             worker_order
//            WHERE
//             `is_need_factory_confirm` = 1
//            AND `datetime` < {$end_time}";
//        $all_factory_not_confirm = $model->query($all_factory_not_confirm_sql)[0]['c'];

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


        $this->response([
            'C端总工单量' => $total_c,
            '总工单量(不包括C端)' => $total - $total_c,
            'C端时间段工单量' => $range_c,
            '时间段总工单量(不包括C端)' => $range - $range_c,
            '时间段平均工单处理时间' => $range_process_time_acg,
            '上门安装量' => $type_106,
            '上门维修量' => $type_107,
            '上门维护量' => $type_108,
            '用户送修量' => $type_109,
            '预发件安装单' => $type_110,
            '未完成工单' => $unfinished_order,
            '新建工单量' => $new_order,
            '取消工单量' => $cancel_order,
            '易码发放量' => $yi_out,
            '易码厂家使用量' => $yi_used,
            '易码激活量' => $yi_actived,
        ]);
    }

    public function factoryCancelOrder()
    {
        try {
            $factory_id = I('factory_id');

            // 取消订单列表
            $cancel_list = BaseModel::getInstance('worker_order_operation_record')
                ->getList([
                    'field' => 'orno,worker_order_operation_record.add_time,desc,nickout',
                    'join' => [
                        'LEFT JOIN admin ON admin.id=worker_order_operation_record.ope_user_id',
                        'LEFT JOIN worker_order ON worker_order.order_id=worker_order_operation_record.order_id'
                    ],
                    'where' => [
                        'worker_order.factory_id' => $factory_id,
                        'ope_type' => 'SO',
                    ],
                    'order' => 'ope_user_id ASC,worker_order_operation_record.add_time DESC'
                ]);

            $filePath = './Public/customer_service_kpi.xls';
            Vendor('PHPExcel.PHPExcel');
            $objPHPExcel = \PHPExcel_IOFactory::load($filePath);

            $row = 2;
            foreach ($cancel_list as $item) {
                $column = 'A';
                $objPHPExcel->setActiveSheetIndex(1)
                    ->setCellValue($column++ . $row, $item['orno'])
                    ->setCellValue($column++ . $row, $item['nickout'])
                    ->setCellValue($column++ . $row, date('Y-m-d H:i:s', $item['add_time']))
                    ->setCellValue($column++ . $row, $item['desc']);
                ++$row;
            }

            $fileName = '客服' . date('Y-m-d') . '.xls';

            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename='.$fileName);
            header('Cache-Control: max-age=0');

            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            $objWriter->save('php://output');
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function kfDetail()
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $kf_ids = I('ids');
        $kf_ids = explode(',', $kf_ids);

        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);

        if (!$start_time || !$end_time) {
            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写开始和结束时间');
        }

        try {
            // 本月工单
            $orders = BaseModel::getInstance('worker_order')->getList(
                [
                    'field' => 'order_id,distribute_time,return_time,orno',
                    'where' => [
                        'platform_check_time' => [
                            ['EGT', $start_time],
                            ['LT', $end_time]
                        ],
                        'is_cancel' => 0,
                        'is_delete' => 0,
                    ]
                ]
            );


            $order_ids = [];
            $order_id_map = [];
            foreach ($orders as $order) {
                $order_ids[] = $order['order_id'];
                $order_id_map[$order['order_id']] = $order;
            }

            // 工单访问权限客服
            $access_admins = BaseModel::getInstance('worker_order_access')->getList([
                'field' => 'worker_order_access.*,admin.nickout',
                'join' => 'LEFT JOIN admin ON admin.id=worker_order_access.admin_id',
                'where' => [
                    'link_order_id' => ['IN', $order_ids],
                ],
                'order' => 'id DESC'
            ]);
            $access_admin_order_group = [];
            foreach ($access_admins as $access_admin) {
                $access_admin_order_group[$access_admin['link_order_id']][] = $access_admin;
            }

            // 提交给神州财务审核的客服
            $visit_admins = BaseModel::getInstance('worker_order_operation_record')
                ->getFieldVal([
                    'join' => 'LEFT JOIN admin ON admin.id=worker_order_operation_record.ope_user_id',
                    'where' => [
                        'order_id' => ['IN', $order_ids],
                        'ope_user_id' => ['IN', $kf_ids],
                        'ope_type' => 'SL',                 // 确认完成维修,订单变为待财务审核
                    ],
                    'order' => 'admin_id ASC'
                ], 'order_id,worker_order_operation_record.add_time,ope_user_id admin_id,admin.nickout');


            $statistics = [];
            foreach ($order_id_map as $order_id => $item) {
                if (in_array($visit_admins[$order_id]['admin_id'], $kf_ids)) {
                    echo '订单号:' . $item['orno'] . '  __' . '操作人：' . $visit_admins[$order_id]['nickout'] . '<br/>';
                }
//                // 回访客服
//                $visit_kf = $visit_admins[$order_id];
//                // 接单客服
//                $access_kf = end($access_admin_order_group[$order_id]);
//                // 客服对内名称
//                $statistics[$visit_kf['admin_id']]['name'] = $visit_kf['nickout'];
//                // 工单总数
//                $statistics[$visit_kf['admin_id']]['total'] += 1;
//
//                // 18点为下班时间，8点半为上班时间，下班后接的单统一归为第二天8点半
//                $access_time = $access_kf['add_time'];
//                $carbon_access_time = new Carbon(date('Y-m-d H:i:s', $access_time));
//                if ($carbon_access_time->hour >= 18) {
//                    $carbon_access_time->addDay();
//                    $carbon_access_time->setTime(8, 30);
//                    $access_time = $carbon_access_time->timestamp;
//                }
//
//                $distribute_time = $item['distribute_time'];
//                $carbon_distribute_time = new Carbon(date('Y-m-d H:i:s', $distribute_time));
//                if ($carbon_distribute_time->hour >= 18) {
//                    $carbon_distribute_time->addDay();
//                    $carbon_distribute_time->setTime(8, 30);
//                    $distribute_time = $carbon_distribute_time->timestamp;
//                }
//                if ($distribute_time - $access_time <= 1800) {
//                    $statistics[$visit_kf['admin_id']]['t1'] += 1;
//                } elseif ($distribute_time - $access_time <= 3600) {
//                    $statistics[$visit_kf['admin_id']]['t2'] += 1;
//                } elseif ($distribute_time - $access_time <= 5400) {
//                    $statistics[$visit_kf['admin_id']]['t3'] += 1;
//                } elseif ($distribute_time - $access_time <= 7200) {
//                    $statistics[$visit_kf['admin_id']]['t4'] += 1;
//                } else {
//                    $statistics[$visit_kf['admin_id']]['t5'] += 1;
//                }

            }
            $this->response($statistics);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryActiveQrocde()
    {
        $factory_id = I('factory_id');
        $factory_name = BaseModel::getInstance('factory')->getFieldVal($factory_id, 'factory_short_name');

        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (!$start_time || !$end_time) {
            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写开始和结束时间');
        }

        $yima_table = factoryIdToModelName($factory_id);
        $data = BaseModel::getInstance($yima_table)
            ->getList([
                'where' => [
                    'factory_id' => $factory_id,
                    'active_time' => [
                        ['EGT', $start_time],
                        ['LT', $end_time],
                    ]
                ],
                'field' => 'code,active_time,shengchan_time,chuchang_time,zhibao_time,register_time,user_tel,user_address,user_name',
            ]);

//        $data = BaseModel::getInstance('factory_excel_datas_z')
//            ->getList([
//                'where' => [
//                    'factory_id' => $factory_id,
//                    'active_time' => [
//                        ['EGT', $start_time],
//                        ['LT', $end_time],
//                    ]
//                ],
//                'field' => 'code,active_time,shengchan_time,chuchuang_time,zhibao_time,register_time,user_tel,user_address,user_name',
//            ]);

        foreach ($data as $key => $item) {
            $data[$key]['active_time'] = $item['active_time'] ? date('Y年m月d日H:i', $item['active_time']) : '';
            $data[$key]['shengchan_time'] = $item['shengchan_time'] ? date('Y年m月d日H:i', $item['shengchan_time']) : '';
            $data[$key]['chuchang_time'] = $item['chuchuang_time'] ? date('Y年m月d日H:i', $item['chuchang_time']) : '';
            $data[$key]['register_time'] = $item['register_time'] ? date('Y年m月d日H:i', $item['register_time']) : '';
            $data[$key]['active_time'] = $item['active_time'] ? date('Y年m月d日H:i', $item['active_time']) : '';
        }

        $filePath = './Public/factory_qrcode_template.xlsx';
        Vendor('PHPExcel.PHPExcel');
        $objPHPExcel = \PHPExcel_IOFactory::load($filePath);

        $row = 2;
        foreach ($data as $item) {
            $column = 'A';
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($column++ . $row, $item['code'])
                ->setCellValue($column++ . $row, $item['register_time'])
                ->setCellValue($column++ . $row, $item['shengchan_time'])
                ->setCellValue($column++ . $row, $item['chuchang_time'])
                ->setCellValue($column++ . $row, $item['zhibao_time'])
                ->setCellValue($column++ . $row, $item['active_time'])
                ->setCellValue($column++ . $row, $item['user_tel'])
                ->setCellValue($column++ . $row, $item['user_address'])
                ->setCellValue($column++ . $row, $item['user_name']);
            ++$row;
        }

        $fileName = $factory_name . date('Y-m-d') . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename='.$fileName);
        header('Cache-Control: max-age=0');

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }

    public function importExcelOrder()
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (!$start_time || !$end_time) {
            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写开始和结束时间');
        }
        $orders = BaseModel::getInstance('worker_order')
            ->getList([
                'field' => 'order_id,order_origin,datetime',
                'where' => [
                    'order_origin' => 'FD',
                    'datetime' => [
                        ['EGT', $start_time],
                        ['LT', $end_time]
                    ]
                ],
                'order' => 'order_id ASC',
            ]);
        $order_list = [];
        foreach ($orders as $order) {
            $order_list[date('Y-m-d', $order['datetime'])] += 1;
        }

        foreach ($order_list as $key => $item) {
            echo $key,  ' 订单量: ', $item, '<br/>';
        }
    }

    public function orders()
    {
//        exit('暂不支持导出');
        ini_set('memory_limit', '500M');
        $start_time = I('start_time');
        $end_time = I('end_time');
        $type = I('type', 1);
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (!$start_time || !$end_time) {
            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写开始和结束时间');
        }

        $orders = BaseModel::getInstance('worker_order')
            ->getList([
                'field' => 'order_id,orno,full_name,tell,area_desc,address,fact_totals,work_totals,is_insurance_cost,datetime,factory_check_time',
                'where' => [
                    'factory_check_time' => [
                        ['EGT', $start_time],
                        ['LT', $end_time]
                    ],
                    'is_complete' => 1,
                ],
                'order' => 'order_id ASC'
            ]);

        $order_list = [];
        $order_ids = [];
        $total_fact_pay = 0;
        $total_order_num = 0;
        foreach ($orders as $order) {
            $order_ids[] = $order['order_id'];
            $order_list[] = [
                'order_id' => $order['order_id'],
                'orno' => $order['orno'],
                'full_name' => $order['full_name'],
                'tell' => $order['tell'],
                'area_desc' => $order['area_desc'],
                'address' => $order['address'],
                'fact_totals' => $order['fact_totals'],
                'work_totals' => $order['work_totals'] + $order['is_insurance_cost'],
                'order_process_time' => round(($order['factory_check_time'] - $order['datetime']) / 86400, 2),
            ];

            $total_fact_pay += $order['fact_totals'];
            ++$total_order_num;
        }


        $order_details = BaseModel::getInstance('worker_order_detail')
            ->getList([
                'field' => 'worker_order_id,servicepro_desc,stantard_desc,servicebrand_desc,model,fault_desc',
                'where' => [
                    'worker_order_id' => ['IN', $order_ids],
                ]
            ]);

        $order_id_detail_map = [];
        foreach ($order_details as $order_detail) {
            $order_id_detail_map[$order_detail['worker_order_id']][] = $order_detail;
        }

        $filePath = './Public/orders_template_' . $type . '.xls';
        Vendor('PHPExcel.PHPExcel');
        $objPHPExcel = \PHPExcel_IOFactory::load($filePath);

        $row = 2;
        foreach ($order_list as $item) {
            $details = $order_id_detail_map[$item['order_id']];
            $products = [];
            $faults = [];
            foreach ($details as $detail) {
                $products[] = $detail['servicepro_desc'] . '-' . $detail['stantard_desc'] . '-'
                    . $detail['servicebrand_desc'] . '-' . $detail['model'];
                $faults[] = $detail['fault_desc'];
            }
            $column = 'A';
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($column++ . $row, $item['orno'])
                ->setCellValue($column++ . $row, $item['full_name'])
                ->setCellValue($column++ . $row, $item['tell'])
                ->setCellValue($column++ . $row, $item['area_desc'])
                ->setCellValue($column++ . $row, $item['address'])
                ->setCellValue($column++ . $row, implode('；', $products))
                ->setCellValue($column++ . $row, implode('；', $faults))
                ->setCellValue($column++ . $row, $item['fact_totals']);
            if ($type == 1) {
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue($column++ . $row, $item['work_totals'])
                    ->setCellValue($column++ . $row, $item['fact_totals'] - $item['work_totals'])
                    ->setCellValue($column++ . $row, $item['order_process_time']);
                if ($row == 2) {
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue($column++ . $row, round($total_fact_pay / $total_order_num, 2));
                }
            }

            ++$row;
        }

        $fileName = date('ymd', $start_time) . '-' . date('ymd', $end_time) . '工单数据' . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename='.$fileName);
        header('Cache-Control: max-age=0');

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }


    public function newData()
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (!$start_time || !$end_time) {
            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写开始和结束时间');
        }
        $new_factory_num = BaseModel::getInstance('factory')->getNum([
            'add_time' => [
                ['EGT', $start_time],
                ['LT', $end_time]
            ]
        ]);
        $new_worker_num = BaseModel::getInstance('worker')->getNum([
            'add_time' => [
                ['EGT', $start_time],
                ['LT', $end_time]
            ]
        ]);

        $all_user_num = BaseModel::getInstance('wx_user')->getNum([
            'user_type' => 0
        ]);

        $all_dealer_num = BaseModel::getInstance('wx_user')->getNum([
            'user_type' => 1
        ]);

        $this->response([
            '新厂家数量' => $new_factory_num,
            '新技工数量' => $new_worker_num,
            '用户总数' => $all_user_num,
            '经销商总数' => $all_dealer_num,
        ]);

    }

}
