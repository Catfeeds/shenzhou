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
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerService;

class WorkerMoneyLogic extends BaseLogic
{
	public function ruleAllBalanceLogs($list = [])
    {
    	$order_ds   = [];
        $adjust_ids = [];
        $type_2 = [
            '0' => '进行中',
            '1' => '提现成功',
            '2' => '提现失败' ,
            '4' => '已导出',
        ];

        $news_list = [];
        foreach ($list as $k => $vs) {
            switch ($vs['type']) {
                case WorkerService::WORKER_MONEY_RECORD_REPAIR:
                    $order_ds[$vs['id']] = $vs['id'];               
                    break;

                case WorkerService::WORKER_MONEY_RECORD_ADJUST:
                    $vs['title'] = '单号：'.$vs['orno'];
                    break;

                case WorkerService::WORKER_MONEY_WITHDRAWCASH_RECORD_TYPE:
                	$adjust_ids[$vs['id']] = $vs['id'];
                    $vs['remarks'] = $type_2[$v['remarks']] ?? '';
                    break;
            }

            $news_list[$vs['type'].'_'.$v['id']] = $vs;
        }

        $datas = count($order_ds) ? BaseModel::getInstance('worker_order_product')->getList([
                'alias' => 'WOD',
                'join'  => 'LEFT JOIN worker_order WO ON WOD.worker_order_id = WO.id',
                'where' => [
                    'WO.id' => ['in', implode(',', $order_ds)],
                ],
                'field' => 'WOD.*,WO.service_type,WO.orno',
                'order' => 'WOD.id DESC',
                'index' => 'orno',
            ]) : [];
        var_dump($datas);die;
        foreach ($datas as $v) {
            $news_key = WorkerService::WORKER_MONEY_RECORD_REPAIR.'_'.$v['id'];
            var_dump($news_key);die;
            $news_list[$news_key]['title'] = $v['servicebrand_desc'].$v['stantard_desc'].$v['servicepro_desc'] ?? '';
            $news_list[$news_key]['service_type'] = OrderService::SERVICE_TYPE_FRO_FAULT_TYPE_ARR[$v['service_type']] ?? '';
        }

        return array_values($news_list);
    }

    public function setBalanceLogs($list = [])
    {
        $type_1 = [];
        $type_2 = [
            '0' => '进行中',
            '1' => '提现成功',
            '2' => '提现失败' ,
            '4' => '已导出',
        ];
        $news_list = [];
        foreach ($list as $k => $v) {

            $vs = [
                'id' => $v['id'],
                'title' => '',
                'type' => $v['type'],
                'money' => '0.00',
                'remarks' => '',
                'other' => '',
                'add_time' => $v['add_time'],
            ];

            switch ($v['type']) {
                case '1':
                    $vs['id'] = $v['order_id'];
                    $type_1[$v['id']] = $v['order_id'];
                    $vs['money'] = $v['order_money'];
                    if ($v['quality_money'] != 0) {
                        $vs['remarks'] = '本单已存入质保金¥'.$v['quality_money'];   
                        $vs['money'] = number_format($vs['money'] - $v['quality_money'], 2, '.', '');
                    }                   
                    break;

                case '2':
                    $vs['title'] = '提现单号：'.$v['mono'];
                    $vs['money'] = sprintf("%.2f", -$v['out_money']);
                    // $vs['other'] = $type_2[$v['status']];
                    $vs['other'] = $v['status'];
                    $vs['other'] = $v['status'];
                    $vs['remarks'] = $v['desc'];
                    break;

                case '3':
                    $vs['title'] = '单号：'.$v['orno'];
                    // $vs['money'] = $v['add_money'];
                    $vs['money'] = $v['add_money'];
                    $vs['remarks'] = $v['add_money_desc'];
                    break;

                case '4':
                    $vs['money'] = $v['quality_money'];
                    if ($v['data_type'] == 1) {
                        $vs['title'] = '后台操作调整';
                        $remarks = explode('--操作人', $v['order_sn']);
                        $vs['remarks'] = $remarks[0];
                        // $vs['remarks'] = $v['order_sn'];
                    } elseif ($v['data_type'] == 0) {
                        $vs['title'] = '工单号：'.$v['order_sn'];
                        $vs['remarks'] = '工单结算自动转入';
                    }
                    break;
            }

            $news_list[] = $vs;
        }

        $datas = count($type_1) ? BaseModel::getInstance('worker_order_detail')->getList([
                'alias' => 'WOD',
                'join'  => 'LEFT JOIN worker_order WO ON WOD.worker_order_id = WO.order_id',
                'where' => [
                    'WOD.worker_order_id' => ['in', implode(',', $type_1)],
                ],
                'field' => 'WOD.*,WO.servicetype',
                'order' => 'WOD.order_detail_id DESC',
                'index' => 'worker_order_id',
            ]) : [];

        $products = [];
        foreach ($datas as $k => $v) {
            $vs  = [
                'title' => $v['servicebrand_desc'].$v['stantard_desc'].$v['servicepro_desc'],
                'servicetype' => '',
            ];
            switch ($v['servicetype']) {
                case '107':
                    $vs['servicetype'] = '1';// 维修
                    break;
                case '106':
                    $vs['servicetype'] = '2';// 安装
                    break;
                case '108':
                    $vs['servicetype'] = '3';// 维护
                    break;
                case '109':
                    $vs['servicetype'] = '4';// 送修
                    break;
                case '110':
                    $vs['servicetype'] = '2';
                    break;
            }   
            $products[$v['worker_order_id']] = $vs;
        }
        

        foreach ($news_list as $k => $v) {
            if ($v['type'] == 1) {
                $v['title'] = $products[$v['id']]['title'];
                $v['other'] = $products[$v['id']]['servicetype'];
            } else {
                continue;
            }
            $news_list[$k] = $v;
        }

        return $news_list;
    }
}
