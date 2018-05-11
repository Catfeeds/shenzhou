<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/4/25
 * Time: 14:20
 */

namespace Script\Controller;


use Common\Common\Service\OrderOperationRecordService;
use Script\Model\BaseModel;

class OrderController extends BaseController
{
    /**
     * 处理 更新语句结果后的 worker_id,worker_order_product_id,pay_type
     * 语句位置 deployment/V3.0.4/feature-order-out-warranty-period-process/sql_backup/01_worker_order_additional_fee.sql
     */
    public function outOrderAddFeeRule()
    {
        try {
            $model = BaseModel::getInstance('worker_order_out_worker_add_fee');
            $add_fee = $model->getList([ // 旧数据只能有一张费用数据
                'field' => 'worker_order_id,pay_type,count(worker_order_id) as nums',
                'where' => [
                    'pay_time' => 0
                ],
                'group' => 'worker_order_id',
                'index' => 'worker_order_id',
                'having' => ' nums = 1 ',
            ]);
            $ids_arr = array_column($add_fee, 'worker_order_id');

            $fields = [
                'worker_order_id',
                'sum(if(operation_type='.OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT.',1,0)) as up_nums',
                'SUBSTRING_INDEX(group_concat(if(operation_type='.OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT.',operator_id,null) order by create_time desc), ",", 1) as worker_id',
                'SUBSTRING_INDEX(group_concat(if(operation_type='.OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT.',worker_order_product_id,null) order by create_time desc), ",", 1) as worker_order_product_id',
                'SUBSTRING_INDEX(group_concat(if(operation_type='.OrderOperationRecordService::WORKER_ORDER_USER_PAY_SUCCESS.',create_time,null) order by create_time desc), ",", 1) as worker_confirm_user_pay_time',
                'SUBSTRING_INDEX(group_concat(if(operation_type='.OrderOperationRecordService::CS_CONFIRM_USER_PAID.',create_time,null) order by create_time desc), ",", 1) as cs_confirm_user_pay_time',

            ];
//            $go_time = NOW_TIME;
            $logs = BaseModel::getInstance('worker_order_operation_record')->getList([
                'field' => implode(',', $fields),
                'where' => [
                    'worker_order_id' => ['in', $ids_arr],
//                    'create_time' => ['egt', $go_time], // 上线时间
                ],
                'group' => 'worker_order_id',
            ]);

//            $this->response($logs);
//            die;
            $delete = [];

            foreach ($logs as $v) {
//                if ($v['worker_id'] && $v['worker_order_product_id']) {
                if ($v['up_nums']) { // 未上传过服务报告，不应该有这些数据
                    // 支付类别：0 未支付；1 技工代微信用户支付通道支付；2 客服确认用户现金支付；3 技工确认现金支付；4 微信用户支付通道支付

                    $update = [
                        'worker_id' => $v['worker_id'] ?? 0,
                        'worker_order_product_id' => $v['worker_order_product_id'] ?? 0,
                    ];

                    if (!$add_fee[$v['worker_order_id']]['pay_type'] && $v['worker_confirm_user_pay_time'] || $v['cs_confirm_user_pay_time']) {
                        if ($v['cs_confirm_user_pay_time'] >= $v['worker_confirm_user_pay_time']) {
                            $pay_type = 2;
                            $pay_time = $v['cs_confirm_user_pay_time'];
                        }
                        if ($v['worker_confirm_user_pay_time'] > $v['cs_confirm_user_pay_time']) {
                            $pay_type = 3;
                            $pay_time = $v['worker_confirm_user_pay_time'];
                        }

                        $update['pay_type'] = $pay_type ?? 0;
                        $update['pay_time'] = $pay_time ?? 0;
                    }

//                    if ($pay_time) {
//                        var_dump($v, $update);
//                        die;
//                    }
                    $model->update([
                        'worker_order_id' => $v['worker_order_id'],
                    ], $update);
                } elseif ($v['worker_order_id']) {
                    $delete[] = $v['worker_order_id'];
                }

            }
            $delete && $model->remove([
                'worker_order_id' => ['in', $delete],
            ]);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
