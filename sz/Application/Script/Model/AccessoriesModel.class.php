<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/09/30
 * PM 14:34
 */
namespace Script\Model;

use Script\Model\BaseModel;
use QiuQiuX\IndexedArray\IndexedArray;

class WorkerOrderModel extends BaseModel
{
    const WORKER_ORDER_TYPE = [

    ];

    public function __construct($new_name = '', $prev = '', $conf = []) {
        if (!empty($new_name) && $conf) {
            parent::__construct($new_name, $prev, $conf);
        } else {
            parent::__construct();
        }
    }
/*** 表实际迁移情况
    // 旧数据 的多个转态 转换 成 OrderStatus
    public function getListSetOrderStatus($where = [], $limit = 0 ,$order = 'order_id ASC')
    {
        $where['order_id'] = 255000;
        // var_dump($this->getNum([
        // 	'where' => [
        // 		'is_check|is_distribute|is_receive|is_appoint|is_repair|is_return|is_platform_check|is_factory_check' => 2,
        // 	],
        // ]));die;

        // $concat = 'concat(IF(`is_need_factory_confirm`,0,1),",",`is_check`,",",`is_distribute`,",",`is_receive`,",",`is_appoint`,",",`is_repair`,",",`is_return`,",",`is_platform_check`,",",`is_factory_check`) as old_status';
        $concat = 'is_need_factory_confirm,is_check,is_distribute,is_receive,is_appoint,is_repair,is_return,is_platform_check,is_factory_check,is_complete';
        $field = 'order_id,'.$concat;
        $opt = [
            // 'field' => $field,
            'where' => $where,
            'limit' => $limit,
            'order' => $order,
        ];

        $list = $this->getList($opt);
        // $list = (new IndexedArray())->createFormArray($this->getList($opt));

        $sql = [];
        foreach ($list as $k => $v) {
            $sql[] = $this->ruleWorkerDataToMysql($v);
        }
        return $sql;
    }

    public function ruleWorkerDataToMysql($data = [])
    {
        $record = $this->setOtherAtModel('worker_order_operation_record')->getList([
            'where' => [
                'order_id' => $data['order_id'],
            ],
            'order' => 'add_time DESC',
        ]);

        // $auth_ids = $this->getAccessAuthId($data['order_id']);
        $auth_ids = $this->getAccessAuthId($data['order_id'], $record);

        $worker_order = [
            'id' => $data['order_id'],
            'worker_id' => $data['worker_id'],
            'factory_id' => $data['factory_id'],
            'orno' => $data['orno'],
            'checker_id' => $auth_ids['checker_id'],
            'distributor_id' => $auth_ids['distributor_id'],
            'returnee_id' => $auth_ids['returnee_id'],
            'auditor_id' => $auth_ids['auditor_id'],
            'worker_order_type' => $data['order_type'],
            'cancel_status' => 0,
            'cancel_time' => 0,
            'cancel_type' => $data['giveup_reason'],
            'cancel_remark' => '',
            'origin_type' => $this->ruleOriginType($data['order_origin']),
        ];

        $worker_order['worker_order_status'] = $this->ruleOrderStatus($data, $worker_order);
        // list($worker_order['cancel_status'], $worker_order['cancel_time']) = ;
        $this->ruleOrderCancelStatus($data, $worker_order, $record);

        $worker_order_ext_info = [
            'worker_order_id' 				=> $data['order_id'],
            'factory_helper_id' 			=> 0,
            'cp_factory_supporter_name'		=> $data['technology_name'],
            'cp_factory_supporter_phone' 	=> $data['technology_tell'],
            'appoint_start_time' 			=> $data['add_member_appoint_stime'],
            'appoint_end_time' 				=> $data['add_member_appoint_etime'],
            'is_send_user_message' 			=> $data['is_send_user_mess'],
            'user_message' 					=> $data['user_message'],
            'is_send_worker_message' 		=> $data['is_send_worker_mess'],
            'worker_message' 				=> $data['worker_message'],
            'est_miles' 					=> $data['est_miles'],
            'straight_miles' 				=> $data['straight_miles'],
            'service_evaluate' 				=> $data['service_evaluate'],
        ];

        return $data;
    }

    // 下单来源：F:厂家，FC：厂家外部客户，C：C端客户，FCD：厂家外部客户自行处理
    //
    public function ruleOriginType($old_type = '')
    {
        var_dump($old_type);
        die;
    }

    // 确保$record（工单操作记录数据）根据时间倒叙排列 （工单取消状态 0正常，1C端用户取消，2C端经销商取消，3厂家取消，4客服取消，5 客服终止工单（可结算））
    public function ruleOrderCancelStatus($data = [], &$news = [], $record = [])
    {
        $time = $status = 0;
        $remark = '';
        // $is = [];
        $arr = $last = $times = $remarks = $result = [];
        // SO 客服取消，FY厂家取消。FI重新下单(FL)，SZ客服终止，FZ厂家终止（无数据）
        if ($data['is_fact_cancel'] == 1 && $data['is_cancel'] == 0) {
            $arr = ['SO', 'FY', 'FI', 'FL'];
        } elseif ($data['is_fact_cancel'] == 0 && $data['is_cancel'] == 1) {
            $arr = ['SZ', 'FI', 'FL'];
        } elseif ($data['is_fact_cancel'] == 1 && $data['is_cancel'] == 1) {
            $arr = ['SO', 'FY', 'SZ', 'FI', 'FL'];
        }


        foreach ($record as $k => $v) {
            if (!in_array($v['ope_role'], ['admin', 'factory']) || !in_array($v['ope_type'], $arr)) {
                continue;
            }
            $last[] = strtolower($v['ope_type']);
            $times[] = $v['add_time'];
            $remarks[] = $v['desc'];
        }
        switch (reset($last)) {
            case 'so':
                $status = 4;
                $time = reset($times);
                $remark = reset($remarks);
                break;
            case 'fy':
                $status = 3;
                $time = reset($times);
                $remark = reset($remarks);
                break;
            case 'sz':
                if (in_array($last[1], ['fi', 'fl'])) {
                    $status = 5;
                    $time = reset($times);
                    $remark = reset($remarks);
                } elseif ($last[1] == 'so') {
                    $status = 4;
                    $time = $times[1];
                    $remark = $remarks[1];
                } elseif ($last[1] == 'fy') {
                    $status = 3;
                    $remark = $remarks[1];
                }
                break;
        }

        $news['cancel_status'] = $status;
        $news['cancel_time'] = $time;
        $news['cancel_remark'] = $remark;
        // return [$status, $time];
    }

    // 工单状态
    public function ruleOrderStatus($data = [], $news = [], $record = [])
    {
        $status = 0;
        if ($data['is_complete'] == 1 || $data['is_factory_check'] == 1) {
            $status = 18;
        } elseif ($data['is_factory_check'] == 2 && $data['is_platform_check'] == 0) {
            $status = 17;
        } elseif ($data['is_platform_check'] == 1) {
            $status = 16;
            // } elseif ($data['']) {
            // 	$status = 15;
        } elseif ($news['auditor_id'] && $data['is_return'] == 1) {
            $status = 14;
        } elseif ($data['is_return'] == 1) {
            $status = 13;
        } elseif ($data['is_appoint'] == 1 && $data['is_repair'] == 0 && $this->setOtherAtModel('worker_order_revisit')->getOne(['worker_order_id' => $data['order_id']])) {
            $status = 12;
        } elseif ($news['returnee_id'] && $data['is_repair'] ==  1) {
            $status = 11;
        } elseif ($data['is_repair'] ==  1) {
            $status = 10;
        } elseif ($data['is_appoint'] == 1 && $this->setOtherAtModel('worker_order_appoint')->getOne(['worker_order_id' => $data['order_id'], 'is_over' => 1])) {
            $status = 9;
        } elseif ($data['is_appoint'] == 1) {
            $status = 8;
        } elseif ($data['is_receive'] == 1) {
            $status = 7;
        } elseif ($data['is_distribute'] == 1) {
            $status = 6;
        } elseif ($news['distributor_id'] && $data['is_check'] == 1) {
            $status = 5;
        } elseif ($data['is_check'] == 1) {
            $status = 4;
        } elseif ($news['checker_id'] && $data['is_need_factory_confirm'] == 0) {
            $status = 3;
        } elseif ($data['is_need_factory_confirm'] == 0) {
            $status = 2;
        } elseif ($data['is_need_factory_confirm'] == 1) {
            $status = 1;
        } else {
            $status = 0;
        }

        // var_dump($this->setOtherAtModel('worker_order_revisit')->getOne(['worker_order_id' => $data['order_id']]));

        var_dump($data['order_id'].'_'.$status);
        // die;
        return $status;
    }

    // 获取指定工单的平台各种接单客服 （根据操作记录识别）
    public function getAccessAuthId($order_id = 0, $record = [])
    {
        $return = [
            'checker_id' => 0,
            'distributor_id' => 0,
            'returnee_id' => 0,
            'auditor_id' => 0,
        ];

        $result = [];
        foreach ($record as $k => $v) {
            if ($v['ope_role'] != 'admin') {
                continue;
            }
            $result[strtolower($v['ope_type'])][] = [
                $v['add_time'] => $v['ope_user_id']
            ];
        }

        $sf = reset(reset($result['sf'])); // 客服财务审核
        $sh = reset(reset($result['sh'])); // 派单客服
        $sl = reset(reset($result['sl'])); // 回访客服
        $sa = reset(reset($result['sa'])); // 核实客服
        // 财务客服
        $return['auditor_id'] = $sf ? $sf : 0;
        // 派单客服
        $return['distributor_id'] = $sh ? $sh : 0;
        // 回访客服
        $return['returnee_id'] = $sl ? $sl : 0;
        // 核实客服
        $return['checker_id'] = $sa ? $sa : 0;
        return $return;
    }
*/
}
