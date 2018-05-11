<?php
/**
 *
 */
namespace Script\Controller;

use Carbon\Carbon;
use Common\Common\Service\FactoryMoneyChangeRecordService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderSettlementService;
use EasyWeChat\Payment\Order;
use Script\Common\ErrorCode;
use Script\Model\BaseModel;
use Common\Common\Service\OrderService;
use Think\Exception;

class FactoryController extends BaseController
{
    public  function factoryFaultPriceBackCheckAndDelete()
    {
        set_time_limit(0);
        try {
            M()->startTrans();
            $ids = M()->query("select ids from (select group_concat(id) as ids,count(id) as nums from factory_product_fault_price where factory_id != 0 and product_id != 0 and fault_id != 0 and standard_id != 0 group by factory_id,product_id,fault_id,standard_id having nums > 1) a");
            $string_ids = implode(',', array_column($ids, 'ids'));
            M()->execute("CREATE TABLE if not exists factory_product_fault_price_table_1 AS select * from factory_product_fault_price where id in ({$string_ids})");

            M()->execute("ALTER TABLE `factory_product_fault_price_table_1` ADD INDEX  if not exists (`factory_id`);");
            M()->execute("ALTER TABLE `factory_product_fault_price_table_1` ADD INDEX  if not exists (`product_id`);");
            M()->execute("ALTER TABLE `factory_product_fault_price_table_1` ADD INDEX  if not exists (`fault_id`);");
            M()->execute("ALTER TABLE `factory_product_fault_price_table_1` ADD INDEX  if not exists (`standard_id`);");


            // 没有问题的数据直接迁移过新 表2
            M()->execute("CREATE TABLE if not exists factory_product_fault_price_table_2 AS SELECT id,factory_id,product_id,fault_id,standard_id,factory_in_price,factory_out_price,worker_in_price,worker_out_price,weihu_time,is_expand from (select *,count(id) as nums from  factory_product_fault_price where factory_id != 0 and product_id != 0 and fault_id != 0 and standard_id != 0 group by factory_id,product_id,fault_id,standard_id having nums = 1) C;");
            M()->execute("ALTER TABLE `factory_product_fault_price_table_2` ADD INDEX  if not exists (`factory_id`);");
            M()->execute("ALTER TABLE `factory_product_fault_price_table_2` ADD INDEX  if not exists (`product_id`);");
            M()->execute("ALTER TABLE `factory_product_fault_price_table_2` ADD INDEX  if not exists (`fault_id`);");
            M()->execute("ALTER TABLE `factory_product_fault_price_table_2` ADD INDEX  if not exists (`standard_id`);");

            $model = BaseModel::getInstance('factory_product_fault_price_table_1');
            $trand = [];
            // 找出有问题的数据 并搜索
            $i = 0;
            $funct2 = M()->query("select fault_id,standard_id,product_id,factory_id,count(id) as nums from factory_product_fault_price_table_1 where factory_id != 0 and product_id != 0 and fault_id != 0 and standard_id != 0 group by factory_id,product_id,fault_id,standard_id having nums > 1");

            foreach ($funct2 as $v) {
                ++$i;
                $where = [
                    'fault_id' => $v['fault_id'],
                    'standard_id' => $v['standard_id'],
                    'product_id' => $v['product_id'],
                    'factory_id' => $v['factory_id'],
                ];
                $data = $model->field('id')->where($where)->find();
                $data['id'] && $trand[$data['id']] = $data['id'];
            }

            $trand_ids = implode(',', $trand);
            if ($trand_ids) {
                M()->execute("insert into factory_product_fault_price_table_2 select * from factory_product_fault_price_table_1 where id in ({$trand_ids})");
            }
            M()->commit();
            // （脚本以外的操作）检验表2数据过后 切换 表2名称 为 factory_product_fault_price，旧表factory_product_fault_price改为factory_product_fault_price_back
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function newCheckoutFactoryMoney()
    {

        try {
            $factory = BaseModel::getInstance('factory')->getList([
                'field' => 'factory_id,money,factory_full_name,linkphone',
                'index' => 'factory_id',
            ]);
            $factory_fees = BaseModel::getInstance('worker_order')->getList([
                'field' => 'worker_order.factory_id,sum(fee.factory_total_fee_modify) as order_pay_money',
                'join'  => ' inner join worker_order_fee fee on worker_order.id = fee.worker_order_id',
                'where' => [
                    'worker_order.worker_order_status' => OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
                    'cancel_status' => ['in', '0,5'],
                ],
                'group' => 'factory_id',
                'index' => 'factory_id',
            ]);

            $factory_change = BaseModel::getInstance('factory_money_change_record')->getList([
                'field' => 'factory_id,sum(change_money) as change_money',
                'where' => [
                    'change_type' => FactoryMoneyChangeRecordService::CHANGE_TYPE_SYSTEM_SETTLE,
                ],
                'group' => 'factory_id',
                'index' => 'factory_id',
            ]);

            $factory_cz = BaseModel::getInstance('factory_money_change_record')->getList([
                'field' => 'factory_id,sum(change_money) as change_money',
                'where' => [
                    'change_type' => ['neq', FactoryMoneyChangeRecordService::CHANGE_TYPE_SYSTEM_SETTLE],
                ],
                'group' => 'factory_id',
                'index' => 'factory_id',
            ]);

//            foreach ($factory_fees as $k => $v) {
//                if ($v['order_pay_money'] != -$factory_change[$k]['change_money'] || $factory[$k]['money'] != ($factory_cz[$k]['change_money'] - $v['order_pay_money'])) {
//                    var_dump($v['factory_id']);
//                }
//            }
            $factory_ids = [];
            foreach ($factory_fees as $k => $v) {
                if ($v['order_pay_money'] != -$factory_change[$k]['change_money'] || $factory[$k]['money'] != ($factory_cz[$k]['change_money'] - $v['order_pay_money'])) {
                    $factory_ids[] = $k;
                }
            }
            $factory_ids = implode(',', $factory_ids);
            if (!$factory_ids) {
                return false;
            }
            $obj = new Carbon(date('Y-m-d H:i:s', NOW_TIME));
            $start_time = $obj->subMonth(1)->startOfDay()->timestamp;
            $end_time = $obj->addMonth(1)->endOfDay()->timestamp;

            $orders = BaseModel::getInstance('worker_order')->getList([
                'field' => 'factory_id,count(id) as nums',
                'where' => [
                    'factory_id' => ['in', $factory_ids],
                    'creat_time' => ['between', "{$start_time},{$end_time}"],
                ],
                'order' => 'nums desc',
                'group' => 'factory_id',
                'index' => 'factory_id',
            ]);

            $index = 0;
            $logic = new \Common\Common\Logic\ExportDataLogic();
            $logic->objPHPExcel->createSheet($index);
            $logic->objPHPExcel->setActiveSheetIndex($index)->setTitle('厂家');
            $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('A' . 1, 'ID');
            $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('B' . 1, '厂家名称');
            $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('C' . 1, '厂家账号');
            $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('D' . 1, '最近一个月的工单量');

            $row = 2;
            foreach ($orders as $k => $v) {
                if ($factory_fees[$k]['order_pay_money'] != -$factory_change[$k]['change_money'] || $factory[$k]['money'] != ($factory_cz[$k]['change_money'] - $factory_fees[$k]['order_pay_money'])) {
                    $column = 'A';
                    $logic->objPHPExcel->setActiveSheetIndex($index)
                        ->setCellValue($column++ . $row, $factory_fees[$k]['factory_id'])
                        ->setCellValue($column++ . $row, $factory[$k]['factory_full_name'])
                        ->setCellValue($column++ . $row, $factory[$k]['linkphone'])
                    ->setCellValue($column++ . $row, $v['nums']);
                    ++$row;
                }
            }
            $name = '厂家资金检索'.date('Ymd', NOW_TIME);
            $logic->putOut($name);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function moneyRecordMoneyEQNextLastMoney()
    {
        $master_pull_time = 1515493800;
        $factory_id = I('get.factory_id', 0);
        if (!$factory_id) {
            $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);
        }
        try {
            $list = BaseModel::getInstance('factory_money_change_record')->getList([
                'field' => 'id,money,change_money,last_money,out_trade_number,change_type,create_time',
                'where' => [
                    'factory_id' => $factory_id,
//                    '_string' => " create_time >= {$master_pull_time} ",
                ],
                'order' => 'create_time asc,id asc',
            ]);

            $cha = 0;
            $pre = [];
            foreach ($list as $k => $v) {
                if ($v['out_trade_number'] == 'A170417180555614' && $v['change_type'] == 4) {
                    $v['last_money'] = $v['last_money'] - 90;
                }
                if ($k != 0 && $pre['last_money'] != $v['money']) {
                    $bad = $pre['last_money'] - $v['money'];
                    echo $v['out_trade_number'].' id '.$v['id'].' next_id '.$pre['id'].'    '.$v['create_time'].'    '.$pre['create_time']. '     '. $bad .'<br />';
                    $cha += $bad;

                }
                $pre = $v;
            }

            var_dump($cha);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public  function factoryAndWorkerAcceReturnFeeBug()
    {
        try {
            $master_pull_time = 1515493800;
            $acce_record = BaseModel::getInstance('worker_order_apply_accessory_record')->getList([
                'field' => 'accessory_order_id',
                'where' => [
                    'user_type' => 5,
                    'type' => 106,
                    'create_time' => ['egt', $master_pull_time],
//                    '_string' => "create_time >= {$master_pull_time}",
                ],
            ]);
            $acce_ids = arrFieldForStr($acce_record, 'accessory_order_id');
            $acce_model = BaseModel::getInstance('worker_order_apply_accessory');
            $acce_list = $acce_ids ? $acce_model->getList([
                'field' => 'worker_order_id',
                'where' => [
                    'id' => ['in', $acce_ids],
                    'is_giveup_return' => 0,
                    'worker_return_pay_method' => 1,
                    'worker_transport_fee' => ['neq', 0],
                ]
            ]) : [];
            $ids = arrFieldForStr($acce_list, 'worker_order_id');
//            $ids = '432577';
            if (!$ids) {
                $this->response();
                exit();
            }

            $list = M()->query("select c.worker_id,c.factory_id,c.worker_order_status,c.worker_order_type,c.orno,a.worker_order_id,a.accessory_return_fee,b.total_worker_transport_fee,b.accessory_numbers,a.quality_fee,a.factory_total_fee,a.factory_total_fee_modify,a.worker_total_fee,a.worker_total_fee_modify,a.worker_net_receipts from worker_order_fee a inner join (select id,group_concat(accessory_number) as accessory_numbers,worker_order_id,sum(worker_transport_fee) as total_worker_transport_fee,worker_return_time from worker_order_apply_accessory where worker_order_id in ({$ids}) and worker_return_pay_method = 1 group by worker_order_id) b on a.worker_order_id = b.worker_order_id inner join worker_order c on a.worker_order_id = c.id where b.total_worker_transport_fee != a.accessory_return_fee and cancel_status in (0,5) and c.id in ({$ids})  order by a.worker_order_id desc;"); // and c.worker_order_type not in (".implode(',', OrderService::ORDER_TYPE_IN_INSURANCE_LIST).")
//            var_dump($list);die;
            $worker_ids = arrFieldForStr($list, 'worker_id');
            $worker_model = BaseModel::getInstance('worker');
            $workers = $worker_ids ? $worker_model->getList([
                'field' => 'worker_id,money',
                'where' => [
                    'worker_id' => ['in', $worker_ids],
                ],
                'index' => 'worker_id',
            ]) : [];
            $factory_ids = arrFieldForStr($list, 'factory_id');
            $factory_model = BaseModel::getInstance('factory');
            $factory = $factory_ids ? $factory_model->getList([
                'field' => 'factory_id,money',
                'where' => [
                    'factory_id' => ['in', $factory_ids],
                ],
                'index' => 'factory_id',
            ]) : [];

            $model = BaseModel::getInstance('worker_order');
            $fee_model = BaseModel::getInstance('worker_order_fee');
            $worker_money_record_model = BaseModel::getInstance('worker_money_record');
            $worker_repair_record_model = BaseModel::getInstance('worker_repair_money_record');

            $change_model = BaseModel::getInstance('factory_money_change_record');
            $pay_model =  BaseModel::getInstance('factory_repair_pay_record');

            // 数据bug脚本查询结果后更改记录文件不允许删除
            $ex_ids = [];
            M()->startTrans();
            // 只处理保内单
            foreach ($list as $v) {
                $record = $fee_update = [];
                $is_in_order = isInWarrantPeriod($v['worker_order_type']); // 是否是保内单: true 保内单; false 保外单;
                if (!$workers[$v['worker_id']] || !$factory[$v['factory_id']] || !$is_in_order) {
                    continue;
                }
                if ($v['worker_order_status'] == 16 || $v['worker_order_status'] == 18) {
                    $feed = OrderSettlementService::orderFeeStatisticsUpdateFee($v['worker_order_id'], ['accessory_return_fee' => $v['total_worker_transport_fee']]);

                    // 质保金不以当前时间算出来为准，以当时工单出错时技工的质保金为准
                    $fee_update['worker_net_receipts'] = $feed['worker_net_receipts'] = $feed['worker_net_receipts'] + $feed['quality_fee'] - $v['quality_fee'];
                    $fee_update['quality_fee'] = $feed['quality_fee'] = $v['quality_fee']; // 不更新 质保金
                    $fee_model->update($v['worker_order_id'], $fee_update);

                    $record[] = "更新技工总返件费用 {$v['accessory_return_fee']} => {$v['total_worker_transport_fee']}";
                    $record[] = "更新技工总工单费用 改前费用{$v['worker_total_fee']} => {$feed['worker_total_fee']},改后费用{$v['worker_total_fee_modify']} => {$feed['worker_total_fee_modify']},技工实收 {$v['worker_net_receipts']} => {$feed['worker_net_receipts']} (质保：{$feed['quality_fee']})";

                    $worker_money = $workers[$v['worker_id']]['money'] - $v['worker_net_receipts'] + $feed['worker_net_receipts'];
                    $record[] = "更新技工钱包 {$workers[$v['worker_id']]['money']} => {$worker_money}";

                    $worker_model->update($v['worker_id'], ['money' => $worker_money]);
                    $a = $worker_repair_record_model->update([
                        'worker_order_id' => $v['worker_order_id'],
                        'worker_id' => $v['worker_id'],
                    ], [
                        'order_money' => $feed['worker_total_fee_modify'],
                        'netreceipts_money' => $feed['worker_net_receipts'],
                        'last_money' => ['exp', ' `last_money` + '.(- $v['worker_net_receipts'] + $feed['worker_net_receipts'])],
                    ]);
                    $worker_money_record_model->update([
                        'data_id' => $v['worker_order_id'],
                        'type' => 1,
                        'worker_id' => $v['worker_id'],
                    ], [
                        'money' => $feed['worker_net_receipts'],
                        'last_money' => ['exp', ' `last_money` + '.(- $v['worker_net_receipts'] + $feed['worker_net_receipts'])],
                    ]);

                    $record[] = "更新厂家总工单费用 改前费用{$v['factory_total_fee']} => {$feed['factory_total_fee']},改后费用{$v['factory_total_fee_modify']} => {$feed['factory_total_fee_modify']}";
                    if ($v['worker_order_status'] == 18) {
                        $factory_money = $factory[$v['factory_id']]['money'] + $v['factory_total_fee_modify'] - $feed['factory_total_fee_modify'];
                        $record[] = "更新厂家资金 {$factory[$v['factory_id']]['money']} => {$factory_money}";

                        $factory_model->update($v['factory_id'], ['money' => $factory_money]);
                        $change_model->update([
                            'factory_id' => $v['factory_id'],
                            'out_trade_number' => $v['orno'],
                            'change_type' => 4,
                        ], [
                            'change_money' => -$feed['factory_total_fee_modify'],
                            'last_money' => ['exp', ' `money` - '.$feed['factory_total_fee_modify']],
                        ]);
                        $pay_model->update([
                            'factory_id' => $v['factory_id'],
                            'worker_order_id' => $v['worker_order_id'],
                        ], [
                            'pay_money' => $feed['factory_total_fee_modify'],
                            'last_money' => ['exp', ' `last_money` + '.($v['factory_total_fee_modify'] - $feed['factory_total_fee_modify'])],
                        ]);

                    }
                } else {
                    $record[] = "更新总费用数据 技工总返件费用 {$v['accessory_return_fee']} => {$v['total_worker_transport_fee']}";
                    $fee_model->update($v['worker_order_id'], ['accessory_return_fee' => $v['total_worker_transport_fee']]);

                }
                if (count($record)) {
                    $record[] = "当前工单状态：".OrderService::WORKER_ORDER_STATUS_CHINESE_NAME_ARR[$v['worker_order_status']];
                    $extras = [
                        'content_replace' => [
                            'content' => implode('；', $record),
                        ],
                        'remark' => '',
                        'operator_id' => 0,
                    ];
                    OrderOperationRecordService::create($v['worker_order_id'],OrderOperationRecordService::SYSTEM_REPAIR_WORKER_REPEAT_COMMIT_RERURNEE_FEE, $extras);
                }
                $ex_ids[] = [
                    'orno' => $v['orno'],
                    'worker_order_id' => $v['worker_order_id'],
                ];
            }
            M()->commit();
            $this->response($ex_ids);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function exportFactoryValidTime()
    {
        $index = 0;
        $logic = new \Common\Common\Logic\ExportDataLogic();
        $logic->objPHPExcel->createSheet($index);
        $logic->objPHPExcel->setActiveSheetIndex($index)->setTitle('厂家');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('A' . 1, '厂家名称');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('B' . 1, '厂家账号');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('C' . 1, '账号状态');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('D' . 1, '厂家有效期开始时间');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('E' . 1, '厂家有效期结束时间');

        $list = BaseModel::getInstance('factory')->getList([
            'field' => 'factory_id,factory_full_name,linkphone,if(factory_status=1,"禁用","正常") as factory_status,datefrom,dateto',
        ]);

        $row = 2;
        foreach ($list as $v) {
            $column = 'A';
            $logic->objPHPExcel->setActiveSheetIndex($index)
                ->setCellValue($column++ . $row, $v['factory_full_name'])
                ->setCellValue($column++ . $row, $v['linkphone'])
                ->setCellValue($column++ . $row, $v['factory_status'])
                ->setCellValue($column++ . $row, date('Y-m-d', $v['datefrom']))
                ->setCellValue($column++ . $row, date('Y-m-d', $v['dateto']));
            ++$row;
        }
        $name = '厂家厂家有效期'.date('Ymd', NOW_TIME);
        $logic->putOut($name);
    }

    public function factoryOrderAgenMoneyChangeSetDefault()
    {
        $factory_id = I('get.id', 0);
        try {
//            !$factory_id && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);
            $factory_id != 564 && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);
            // select substring_index(concat('|','1,2,3,4,5,6,7,8,9'), concat('|',substring_index('1,2,3,4,5,6,7,8,9', ',', 1),','), -1) from factory_money_change_record limit 1;
            $sql = "select count(*) as nums,group_concat(id order by create_time asc) as all_id,substring_index(concat('|',group_concat(id order by create_time asc)), concat('|',substring_index(group_concat(id order by create_time asc), ',', 1),','), -1) as delete_id from factory_money_change_record where factory_id = {$factory_id} and change_type = 4 group by out_trade_number having nums >= 2;";
            $data = M()->query($sql);
            $model = BaseModel::getInstance('factory_money_change_record');

            $remove = arrFieldForStr($data, 'delete_id');
            M()->startTrans();
            $remove && $model->remove(['id' => ['in', $remove]]);
            $opt = [
                'field' => '*',
                'where' => [
                    'factory_id' => $factory_id,
                ],
                'order' => 'create_time desc,id desc',
            ];
            $pre = null;
            $listsss = $model->getList($opt);
            krsort($listsss);
            foreach ($listsss as $v) {
                $last_money = number_format($v['money'] + $v['change_money'], 2, '.', '');
                if (!$pre && $last_money != $v['last_money']) {
                    $model->update($v['id'], ['last_money' => $last_money]);
                    $v['last_money'] = $last_money;
                } else {
                    $update = [];
                    if ($pre['last_money'] != $v['money']) {
                        $update['money'] = $pre['last_money'];
                        $v['money'] = $pre['last_money'];
                    }
                    $last_money = number_format($pre['last_money'] + $v['change_money'], 2, '.', '');
                    if ($last_money != $v['last_money']) {
                        $update['last_money'] = $last_money;
                        $v['last_money'] = $last_money;
                    }
                    $update && $model->update($v['id'], $update);
                }
                $pre = $v;
            }
            M()->commit();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function factoryFronzenSetDefault()
    {
        $f_model = BaseModel::getInstance('factory');
        $fr_model = BaseModel::getInstance('factory_money_frozen');

        try {
            M()->startTrans();
            $removes = M()->query('select a.id from factory_money_frozen a left join worker_order b on a.worker_order_id = b.id where b.worker_order_status = 18 or b.id is null or b.cancel_status not in (0,5);');
            $remove_ids = arrFieldForStr($removes, 'id');
            $remove_ids && $fr_model->remove(['id' => ['in', $remove_ids]]);

            $f_model->update(['_string' => ' 1 '], ['frozen_money' => 0]);
            $list = $fr_model->getList([
                'field' => 'factory_id,sum(frozen_money) as frozen_money',
                'group' => 'factory_id',
            ]);

            foreach ($list as $key => $v) {
                $f_model->update($v['factory_id'], ['frozen_money' => $v['frozen_money']]);
            }
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function setWorkerWithdrawExcelId()
    {
        $excel_model = BaseModel::getInstance('worker_withdrawcash_excel');
        $record_model = BaseModel::getInstance('worker_withdrawcash_record');

        $where = [];
        $nums = 1000;
        try {
            do {
                $data = $excel_model->getList([
                    'field' => 'id,ids',
                    'order' => 'id asc',
                    'limit' => $nums,
                    'where' => $where,
                ]);

                foreach ($data as $k => $v) {
                    $update = [
                        'withdrawcash_excel_id' => $v['id'],
                    ];
                    $v['ids'] && $record_model->update([
                        'id' => ['in', $v['ids']],
                    ], $update);
                }
                $last = end($data);
                $where['_string'] = ' id > '.$last['id'].' ';
            } while ($last);
            // 检查结果
            // select * from worker_withdrawcash_record where withdrawcash_excel_id in (select id from worker_withdrawcash_excel where id = 882);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryMoneyCheckoutAccefee()
    {
        $sql = "select a.id,a.factory_id,factory_total_fee_modify,-`change_money` as change_money,b.factory_total_fee_modify+c.change_money as cha,accessory_return_fee from worker_order a left join  worker_order_fee b on a.id = b.worker_order_id left join factory_money_change_record c on a.orno = c.out_trade_number where a.worker_order_status = 18 and c.change_type = 4 and b.factory_total_fee_modify+c.change_money!=0 and b.factory_total_fee_modify+c.change_money = accessory_return_fee;";
        $list = M()->query($sql);

        $factory_ids = arrFieldForStr($list, 'factory_id');
        $factorys = $factory_ids ? M('factory')->field('factory_id,factory_full_name,linkphone')->where(['factory_id' => ['in', $factory_ids]])->index('factory_id')->select() : [];
        $return = [];
        foreach ($list as $v) {
            $return[$v['factory_id']]['factory'] = $factorys[$v['factory_id']];
            $return[$v['factory_id']]['wrong'][] = "{$v['id']} || {$v['cha']}";
        }
        $this->responseList(array_values($return));
    }

    public function factoryMoneyCheckout()
    {
        $factory_model = new BaseModel('factory', '', C('DB_CONFIG_OLD_V3'));
        $record_model = BaseModel::getInstance('factory_money_change_record');

        $is_desc = I('get.desc', 0, 'intval');

        $list = $record_model->getList([
            'field' => 'factory_id,SUM(change_money) as sum_money,SUM(IF(change_type=1,change_money,0)) as change_type_1,SUM(IF(change_type=2,change_money,0)) as change_type_2,SUM(IF(change_type=3,change_money,0)) as change_type_3,SUM(IF(change_type=4,change_money,0)) as change_type_4,SUM(IF(change_type=5,change_money,0)) as change_type_5',
            'where' => [
                'worker_id' => ['gt', 0],
                'status'    => 1,
            ],
            'order' => 'factory_id '.($is_desc ? 'desc' : 'asc'),
            'group' => 'factory_id',
            'index' => 'factory_id',
        ]);

        $return = $error_ids = [];
        $factory_ids = array_keys($list);
        if ($factory_ids) {
            $checkout = [
                'field' => 'factory_id,money',
                'where' => [
                    'factory_id' => ['in', $factory_ids],
                ],
                'order' => 'factory_id '.($is_desc ? 'desc' : 'asc'),
            ];
            foreach ($factory_model->getList($checkout) as $k => $v) {
                if ($v['money'] != $list[$v['factory_id']]['sum_money']) {
                    $ccccc = $list[$v['factory_id']]['sum_money'] - $v['money'];

                    $abs = (string)intval(abs($ccccc));
                    $key = "{$abs}.{$v['factory_id']}";
                    $return[$key] = [
                            'factory_id' => $v['factory_id'],
                            'money'      => $v['money'],
                            'ccccc'      => $ccccc,
                        ] + $list[$v['factory_id']];

                    $error_ids[] = $v['factory_id'];
                }
            }
        }

        krsort($return);

        $this->response([
            'count' => count($return),
            'data_list' => array_values($return),
        ]);
    }

    public function factoryFaultPriceSetId()
    {

        $fault_model = BaseModel::getInstance('factory_product_fault_price');
        $data = $fault_model->getList([
            'id' => 0
        ]);

        $max_id = $fault_model->getFieldVal([
            'order' => 'id desc'
        ], 'id');

        M()->startTrans();
        foreach ($data as $val) {
            $fault_model->update($val, [
                'id' => ++$max_id
            ]);
        }
        M()->commit();

        echo count($data);
    }

}
