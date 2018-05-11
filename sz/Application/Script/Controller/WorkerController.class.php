<?php
/**
* 
*/
namespace Script\Controller;

use Script\Model\BaseModel;
use Script\Common\ErrorCode;

class WorkerController extends BaseController
{
    public function setWorkerMoneyRecordAdjustNotOrnoDefault()
    {
        $worker_id = I('get.id', 0);
        try {
            !$worker_id && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);
            $old_model = new BaseModel('worker_money_adjust_record', '', C('DB_CONFIG_OLD_V3'));
            $old_list = $old_model->getList([
                'alias' => 'a',
                'join'  => 'left join worker_order b on a.orno = b.orno',
                'where' => [
                    'a.worker_id' => $worker_id,
                    '_string' => ' (a.orno is not null or a.orno != "") and b.worker_id is not null ',
                ],
                'field' => 'a.id,b.order_id,a.orno',
                'index' => 'id',
            ]);
            $ids = arrFieldForStr($old_list, 'id');
            $model = BaseModel::getInstance('worker_money_adjust_record');

            $wrong_new = $ids ? $model->getList([
                'field' => 'id,worker_order_id',
                'where' => [
                    'id' => ['in', $ids],
                    'worker_order_id' => 0,
                ],
            ]) : [];
            $model->startTrans();
            foreach ($wrong_new as $k => $v) {
                $model->update($v['id'], ['worker_order_id' => $old_list[$v['id']]['order_id']]);
            }
            $model->commit();
            $this->response($old_list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function setAcceWorkeridOrderDefaultWorkerid()
    {
        // 检查脚本
        $order_arr = M()->query('select a.id,a.worker_id,b.worker_id as bworker_id,group_concat(b.id) as bids from worker_order a left join worker_order_apply_accessory b on a.id = b.worker_order_id where a.worker_id != b.worker_id and a.worker_id != 0 and a.worker_id is not null group by a.id');

        $model = BaseModel::getInstance('worker_order_apply_accessory');
        M()->startTrans();
        foreach ($order_arr as $v) {
//            var_dump(count(explode(',', $v['bids'])));
            if (count(explode(',', $v['bids']))) {
                $model->update([
                    'id' => ['in', $v['bids']],
                ], ['worker_id' => $v['worker_id']]);
            }
        }
        M()->commit();
        $this->responseList([]);
    }

    public function workerOrderRecordListOrderat()
    {
        $id_arr = [
            '876',
            '892',
            '541',
            '740',
            '11625',
            '76829',
            '31',
            '608',
            '328',
            '3093',
            '7259',
            '1675',
            '5537',
        ];

        $id = I('get.id');
        !in_array($id, $id_arr) && $this->responseList([]);

        $model = BaseModel::getInstance('worker_money_record');
        $field_arr = [
            'id as "变动记录"',
            'worker_id as "技工id"',
            'type as "变动类型"',
            'data_id as "变动类型对应的id"',
            'money as "变动的金额"',
            'last_money as "变动后的钱包余额"',
            'from_unixtime(`create_time`) as 变动的时间'
        ];
        $opt = [
            'field' => implode(',', $field_arr),
            'where' => ['worker_id' => $id],
            'order' => 'create_time desc,id desc',
        ];
//        $model->getList($opt);
        $this->responseList($model->getList($opt));
    }

	public function workerMoneySetDefault()
	{
		set_time_limit(0);
		$worker_id = I('get.worker_id', 0);
		$model = BaseModel::getInstance('worker_money_record');

		$worker_id && $where['worker_id'] = $worker_id;
		$opt = [
			'field' => 'worker_id,money,worker_telephone,nickname',
			'where' => $where,
			// 'limit' => 20,
		];
		$repair_model = BaseModel::getInstance('worker_repair_money_record');
		$adjust_model = BaseModel::getInstance('worker_money_adjust_record');
		$withdrawcash_model = BaseModel::getInstance('worker_withdrawcash_record');
		$return = [];
		foreach (BaseModel::getInstance('worker')->getList($opt) as $worker) {
			M()->startTrans();
			$up_where = [
				'field' => 'id,type,last_money,money,create_time,transfer_extend_id',
				'where' => [
					'worker_id' => $worker['worker_id']
				],
				'order' => 'create_time asc',
			];
			$pre = [];
			foreach ($model->getList($up_where) as $v) {
				// if ($v['type'] == 3 || $v['type'] == 4) {
					$last_money = $pre['last_money'] + $v['money'];
					// if ($v['last_money'] != $last_money) {
						$v['last_money'] = $last_money;
						$model->update($v['id'], ['last_money' => $last_money]);
						if ($v['transfer_extend_id']) {
							switch ($v['type']) {
								case 1:
									$repair_model->update($v['transfer_extend_id'], [
											'netreceipts_money' => $v['money'],
											'last_money'		=> $last_money,
										]);
									break;

								case 2:
									$adjust_model->update($v['transfer_extend_id'], [
											'adjust_money' => $v['money'],
											'worker_last_money'	=> $last_money,
										]);
									break;
							}
						}

					// }
				// }
				$pre = $v;
			}
			
			$adjust_new_money = number_format($worker['money'] - $pre['last_money'], 2, '.', '');
			if ($adjust_new_money != 0) {
				$adjust_data = [
					'admin_id'		=> 0,
					'worker_id'		=> $worker['worker_id'],
					'worker_order_id'	=> 0,
					'adjust_type'		=> 1,
					'create_time'		=> NOW_TIME,
					'adjust_money'		=> $adjust_new_money,
					'worker_last_money'	=> $worker['money'],
					'adjust_remark'		=> '系统资金检查调整',
					'cp_admin_name'		=> 'V3.0系统自动调整',
				];
				$new_adjust_id = $adjust_model->insert($adjust_data);
				// 系统管理
				$new = [
					'worker_id' 	=> $worker['worker_id'],
					'type' 			=> 6,
					'data_id' 		=> $new_adjust_id,
					'money'			=> $adjust_new_money,
					'last_money'	=> $worker['money'],
					'create_time'	=> NOW_TIME,
					'transfer_extend_id' => $new_adjust_id,
				];
				$model->insert($new);
			}
			M()->commit();
		}
		$this->response($return);
	}

	public function workerMoneySetDefaultTxt()
	{
		set_time_limit(0);
		$worker_id = I('get.worker_id', 0);
		$model = BaseModel::getInstance('worker_money_record');

		$worker_id && $where['worker_id'] = $worker_id;
		$opt = [
			'field' => 'worker_id,money,worker_telephone,nickname',
			'where' => $where,
		];

		$str_url = './worker_system_adjust.txt';
		// file_put_contents($str_url, "=========================================================================================\n", FILE_APPEND);
		// file_put_contents($str_url, '');
		foreach (BaseModel::getInstance('worker')->getList($opt) as $worker) {
			$up_where = [
				'field' => 'id,type,last_money,money,create_time,transfer_extend_id',
				'where' => [
					'worker_id' => $worker['worker_id'],
					'type' => ['neq', 6],
				],
				'order' => 'create_time asc,id asc',
			];
			$pre = [];
			foreach ($model->getList($up_where) as $v) {
				$last_money = number_format($pre['last_money'] + $v['money'], 2, '.', '');
				if (I('get.show_mingxi') == 'mingxi' && $v['last_money'] != $last_money) {
                    echo "{$v['create_time']} || {$v['id']} || {$pre['last_money']} || {$v['money']} || {$v['last_money']} || {$last_money} <br />";
                }
				$v['last_money'] = $last_money;
				$pre = $v;
			}

			$ddd = M()->query("select a.id,a.data_id,b.worker_order_id,a.type,a.money,a.last_money,b.worker_total_fee_modify,b.worker_net_receipts,a.create_time,a.transfer_extend_id from worker_money_record a left join worker_order_fee b on a.data_id = b.worker_order_id where a.type = 1 and a.worker_id = {$worker['worker_id']} and b.worker_net_receipts != a.money order by a.create_time desc");
			foreach ($ddd as $v) {
                echo "<span style='color:red;'>{$v['data_id']}</span> (id) <span style='color:red;'>{$v['money']}</span> (记录变动) <span style='color:red;'>{$v['last_money']}</span> (变动结果) <span style='color:red;'>{$v['worker_total_fee_modify']}</span> (工单应收) <span style='color:red;'>{$v['worker_net_receipts']}</span> (工单实收) ".date('Y-m-d', $v['create_time'])." 时间 <br />";
            }

			$adjust_new_money = number_format($worker['money'] - $pre['last_money'], 2, '.', '');
			if ($adjust_new_money != 0) {
				$string = "{$worker['worker_id']} || {$worker['worker_telephone']} || {$worker['nickname']} || {$worker['money']} || {$pre['last_money']} || {$adjust_new_money}   <br />";
				// file_put_contents($str_url, $string, FILE_APPEND);
				echo $string;
			}
		}
	}
	
	public function workerMoneyCheckout()
	{
		$worker_model = new BaseModel('worker', '', C('DB_CONFIG_OLD_V3'));
		$record_model = BaseModel::getInstance('worker_money_record');

		$is_desc = I('get.desc', 0, 'intval');

		$list = $record_model->getList([
				'field' => 'worker_id,SUM(money) as sum_money,SUM(IF(`type`=1,`money`,0)) as type_1,SUM(IF(`type`=2,`money`,0)) as type_2,SUM(IF(`type`=3,`money`,0)) as type_3,SUM(IF(`type`=4,`money`,0)) as type_4',
				'where' => [
					'worker_id' => ['gt', 0],
					'type' => ['neq', 6],
				],
				'group' => 'worker_id',
				'index' => 'worker_id',
				// 'group' => 'worker_id,type',
			]);

		$worker_ids = arrFieldForStr($list, 'worker_id');
		$return = [];
		$total = 0;
		$get_total = 0;
		$put_total = 0;
		// select a.id,b.worker_order_id,a.type,IF(b.worker_net_receipts != a.money,1111111,0) as is_wrong,a.money,a.last_money,b.worker_total_fee_modify,b.worker_net_receipts,a.create_time from worker_money_record a left join worker_order_fee b on a.data_id = b.worker_order_id where a.type = 1 and a.worker_id = 892 order by a.create_time desc;

		// select a.id as record_id,b.id as withdraw_id,a.type,IF(a.type=3||a.type=4,11111111111111111,0) as is_withdraw,IF((a.type=3||a.type=4)&&a.money!=-b.`out_money`,2222222222222222,0) as is_wrong,a.money,a.last_money,b.status,b.out_money,IF(a.id=63268||a.id=64874||a.id=197132,33333333333,0) as dingweidingweidingwei from worker_money_record a left join worker_withdrawcash_record b on a.data_id = b.id where a.worker_id = 541 order by a.create_time desc;
		if ($worker_ids) {
			$checkout = [
				'field' => 'worker_id,money',
				'where' => [
					'worker_id' => ['in', $worker_ids],
				],
				'order' => 'worker_id '.($is_desc ? 'desc' : 'asc'),
			];
			foreach ($worker_model->getList($checkout) as $k => $v) {
				$sum_money = $list[$v['worker_id']]['sum_money'];
				$result = $v['money'] != $sum_money ? 1 : 0;
				$string = "{$v['worker_id']} => {$v['money']} => {$sum_money} ==== {$result}\n";
				if (I('get.echo')) {
					echo $string;
				}
				if ($result) {
					$total += $ccccc = number_format($sum_money - $v['money'], 2, '.', '');

					if ($ccccc > 0) {
						$get_total += $ccccc;
					} elseif ($ccccc < 0) {
						$put_total += $ccccc;
					}

					$abs = (string)intval(abs($ccccc));
					$key = "{$abs}.{$v['worker_id']}";
					$return[$key] = [
						'worker_id' => $v['worker_id'],
						'money'		=> $v['money'],
						'ccccc'		=> $ccccc,
					] + $list[$v['worker_id']];
				}
			}	
		}

		krsort($return);

		$this->response([
			// 'true_nums' => $llllllllll,
			'count' 	=> count($return),
			'total'		=> number_format($total, 2, '.', ''),
			'get_total'		=> number_format($get_total, 2, '.', ''),
			'put_total'		=> number_format($put_total, 2, '.', ''),
			'data_list' => array_values($return),
		]);
	}
}
