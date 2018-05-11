<?php
/**
 * File: WorkerMoneyLogic.class.php
 * User: zjz
 * Date: 2017/11/21
 */

namespace Qiye\Logic;

use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Library\Common\Util;
use Qiye\Common\ErrorCode;
use Qiye\Logic\BaseLogic;
use Qiye\Model\BaseModel;
use Library\Crypt\AuthCode;
use Api\Logic\UserLogic;
use Common\Common\Service\SMSService;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerService;
use Common\Common\Service\FaultTypeService;
use Common\Common\Service\OrderUserService;

class WorkerMoneyLogic extends BaseLogic
{
    public function ruleAllBalanceLogs($list = [])
    {
        $order_ids   = [];
        $adjust_ids = [];
        $adjust_type_desc = [ // 新加的调整，为了不影响客户端与前端，后端做处理
            '2_0' => '系统调整',
            '2_1' => '系统调账',
        ];
        $type_2 = [
            '0' => '1',   // '待处理',
            '0_1' => '4', // '提现中',
            '1' => '2',   // '提现成功',
            '2' => '3',   // '提现失败' ,
        ];
        $news_list = [];

        foreach ($list as $k => $vs) {
            // 屏蔽掉系统调账
            if ($vs['other'] == '2_1') {
                continue;
            }
            switch ($vs['type']) {
                case WorkerService::WORKER_MONEY_RECORD_REPAIR: // 1
                    $vs['other'] = '';
                    $order_ids[$vs['id']] = $vs['id'];
                    break;

                case WorkerService::WORKER_MONEY_RECORD_REPAIR_OUT: // 5
                    $vs['other'] = '';
                    $order_ids[$vs['id']] = $vs['id'];               
                    break;

                case WorkerService::WORKER_MONEY_RECORD_ADJUST: // 2
                    $adjust_ids[$vs['id']] = $vs['id'];
                    $title = $adjust_type_desc[$vs['other']]; // 新加的调整，临近上线为了不影响客户端与前端，后端做处理
                    $vs['title'] = $title;
                    $vs['other'] = '';                   // union做的处理，返回字段有限。调整记录无 other数据返回
                    break;

                case WorkerService::WORKER_MONEY_WITHDRAWCASH_RECORD_TYPE: // 3
                    $vs['other']   = $type_2[$vs['other']] ?? $type_2[reset(explode('_', $vs['other']))];
                    break;
            }
            $news_list[$vs['type'].'_'.$vs['id']] = $vs;
        }

        $adjust_ids = implode(',', $adjust_ids);
        $adjusts = $adjust_ids ? BaseModel::getInstance('worker_money_adjust_record')->getList([
                'field' => 'id,worker_order_id,adjust_remark',
                'where' => [
                    'id' => ['in', $adjust_ids],
                ],
                // 'index' = 'id',
            ]) : [];
        foreach ($adjusts as $v) {
            // 加入搜索工单信息
            $order_ids[$v['worker_order_id']] = $v['worker_order_id'];
        }

        $order_ids = implode(',', $order_ids);
        $datas = $order_ids ? BaseModel::getInstance('worker_order_product')->getList([
                'alias' => 'WOD',
                'join'  => 'LEFT JOIN worker_order WO ON WOD.worker_order_id = WO.id',
                'where' => [
                    'WO.id' => ['in', $order_ids],
                ],
                'field' => 'WO.worker_order_type,WOD.cp_product_brand_name,WOD.cp_product_standard_name,WOD.cp_category_name,service_type,WO.id,WO.orno',
                'order' => 'WOD.id DESC',
                'index' => 'id',
            ]) : [];

        // 保外单fee表搜索
        $is_out_arr = [];
        foreach ($datas as $v) {
            // if (!isInWarrantPeriod($v['worker_order_type'])) {
            //    $is_out_arr[] = $v['id'];
            // }
            !isInWarrantPeriod($v['worker_order_type']) && $is_out_arr[$v['id']] = $v['id'];
        }
        $is_out_id = implode(',', $is_out_arr);
        $out_list_cash = $is_out_id ? BaseModel::getInstance('worker_order_user_info')->getList([
                'where' => [
                    'worker_order_id'   => ['in',  $is_out_id],
                    'pay_type'          => OrderUserService::PAY_TYPE_CASH,
                ],
                'field' => 'worker_order_id,pay_type',
                'index' => 'worker_order_id',
            ]) : [];
        
        $fees = $order_ids ? BaseModel::getInstance('worker_order_fee')->getList([
                'where' => [
                    'worker_order_id'   => ['in',  $order_ids],
//                    'quality_fee'       => ['neq', 0],
                ],
                'field' => 'worker_order_id,quality_fee,worker_net_receipts,worker_repair_fee_modify,accessory_out_fee,service_fee_modify,worker_total_fee_modify',
                'index' => 'worker_order_id',
            ]) : [];

        foreach ($datas as $v) {
            $is_out = isset($is_out_arr[$v['id']]);
            $news_key =  $is_out ? WorkerService::WORKER_MONEY_RECORD_REPAIR_OUT.'_'.$v['id'] : WorkerService::WORKER_MONEY_RECORD_REPAIR.'_'.$v['id'];

            if (!isset($news_list[$news_key])) {
                continue;
            }
            $news_list[$news_key]['title'] = $v['cp_product_brand_name'].$v['cp_product_standard_name'].$v['cp_category_name'] ?? '';
            $news_list[$news_key]['other'] = (string)OrderService::SERVICE_TYPE_VALUE_FOR_APP[$v['service_type']];

            $fee_data = $fees[$v['id']];
//            $news_list[$news_key]['money'] = $fee_data['worker_total_fee_modify'];
            if (isset($out_list_cash[$v['id']])) {
//                $fee = $fee_data['worker_repair_fee_modify'] + $fee_data['accessory_out_fee'] - $fee_data['service_fee_modify'];
                $fee = $fee ?? '0.00';
                $news_list[$news_key]['remarks'] = '你已收取用户现金¥'.$fee_data['worker_total_fee_modify'].'元';
//            } elseif (isset($fees[$v['id']])) {
            } elseif (ceil($fees[$v['id']]['quality_fee'])) {
                $news_list[$news_key]['remarks'] = '本单已存入质保金¥'.$fees[$v['id']]['quality_fee'];
            }
        }

        foreach ($adjusts as $k => $v) {
            $news_key = WorkerService::WORKER_MONEY_RECORD_ADJUST.'_'.$v['id'];
            if (!isset($news_list[$news_key])) {
                continue;
            }
            $orno = $datas[$v['worker_order_id']]['orno'];
            // $news_list[$news_key]['title'] = $orno ? '工单号：'.$orno : '';
            $orno && $news_list[$news_key]['title'] = '工单号 '.$orno;
            $news_list[$news_key]['remarks'] = $v['adjust_remark'];
        }

        return array_values($news_list);
    }
}
