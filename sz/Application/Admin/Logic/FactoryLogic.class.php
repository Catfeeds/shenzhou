<?php
/**
 * File: FactoryLogic.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 11:58
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkbenchEvent;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Library\Common\Util;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\FactoryMoneyRecordService;
use Common\Common\Service\FactoryMoneyChangeRecordService;
use Common\Common\Service\SystemMessageService;

class FactoryLogic extends BaseLogic
{
    const FACTORY_MONEY_CHANGE_TABLE_NAME   = 'factory_money_change_record';
    const FACTORY_TABLE_NAME                = 'factory';
    const FACTORY_ADMIN_TABLE_NAME          = 'factory_admin';
    const FACTORY_ADTAGS_TABLE_NAME         = 'factory_adtags';
    const ADMIN_TABLE_NAME                  = 'admin';
    const FACTORY_FROZEN_TABLE_NAME         = 'factory_money_frozen';
    const ORDER_TABLE_NAME                  = 'worker_order';
    const ORDER_FEE_TABLE_NAME              = 'worker_order_fee';
    const ORDER_PRODUCT_TABLE_NAME          = 'worker_order_product';
    const ORDER_USER_INFO_TABLE_NAME        = 'worker_order_user_info';
    const FACTORY_FEE_CONFIG_TABLE_NAME     = 'factory_fee_config_record';

    public function updateFactoryFeeConfigByFid($fid, $change)
    {
        $check = [
            'service_fee'           => 'service_charge',
            'worker_order_frozen'   => 'default_frozen',
            'date_from'             => 'datefrom',
            'date_to'               => 'dateto',
            'base_distance'         => 'base_distance',
            'base_distance_fee'     => 'base_distance_cost',
            'overrun_distance_fee'  => 'exceed_cost',
            'money_not_enouth'      => 'money_not_enouth',
        ];
        $modify = [
            'service_fee'           => 'service_fee_modify',
            'worker_order_frozen'   => 'worker_order_frozen_modify',
            'date_from'             => 'date_from_modify',
            'date_to'               => 'date_to_modify',
            'base_distance'         => 'base_distance_modify',
            'base_distance_fee'     => 'base_distance_fee_modify',
            'overrun_distance_fee'  => 'overrun_distance_fee_modify',
            'money_not_enouth'      => 'money_not_enouth_modify', 
        ];
        $exchange = array_flip($check);

        $update = array_intersect_key($change, $exchange);

        // foreach ($update as $k => $v) {
        //     switch () {
        //         case '':
                    
        //             break;
                
        //     }
        // }

        $model = BaseModel::getInstance(self::FACTORY_TABLE_NAME);
        $data = $model->getOneOrFail($fid, implode(',', $check));
        $model->update($fid, $update);

        $record = null;
        $change_nuns = 0;
        foreach ($data as $k => $v) {
            isset($update[$k]) && $update[$k] != $v && ++$change_nuns;
            $key = $exchange[$k];
            $record[$key] = $v;
            $record[$modify[$key]] = isset($update[$k]) ? $update[$k] : $v;
        }

        if ($change_nuns || $change['remark']) {
            $record['remark'] = (string)htmlEntityDecode($change['remark']);

            $record['factory_id'] = $fid;
            $record['admin_id'] = AuthService::getAuthModel()->getPrimaryValue();
            $record['create_time'] = NOW_TIME;

            BaseModel::getInstance(self::FACTORY_FEE_CONFIG_TABLE_NAME)->insert($record);
        }
    }

    // $type 1 进行中 2 待结算；3 已结算;
    public function workerOrdersIngMoneyPaginate($type, $fid, &$list, &$count, &$total_money)
    {
        $search = I('get.');

        $s_time = I('get.start_time', 0, 'intval');
        $e_time = I('get.end_time', 0, 'intval');
        $orno   = I('get.orno', '');
        $group_id   = I('get.group_id', 0, 'intval');

        $is_export = I('is_export', 0, 'intval');

        $where = [
            self::ORDER_TABLE_NAME.'.factory_id' => $fid,
            self::ORDER_TABLE_NAME.'.cancel_status' => ['in', OrderService::CANCEL_TYPE_NULL.','.OrderService::CANCEL_TYPE_CS_STOP],
        ];

        $search_time_key = self::ORDER_TABLE_NAME.'.create_time';
        isset($search['orno'])  && $where[self::ORDER_TABLE_NAME.'.orno'] = ['like', "%{$orno}%"];

        if (isset($search['group_id']) && $group_id) {
            $where[self::ORDER_TABLE_NAME.'.origin_type'] = OrderService::ORIGIN_TYPE_FACTORY_ADMIN;
            $factory_admins = BaseModel::getInstance(self::FACTORY_ADMIN_TABLE_NAME)->getList([
                    'field' => 'id',
                    'where' => [
                        'factory_id' => $fid,
                        'tags_id' => $group_id,
                    ],
                    'index' => 'id',
                ]);

            $factory_admin_ids = implode(',', array_keys($factory_admins));            
            if (!$factory_admin_ids) {
                return;
            }
            $where[self::ORDER_TABLE_NAME.'.add_id'] = ['in', $factory_admin_ids];
        } 

        $field = '';
        switch ($type) {
            case 1:
                $where['_string'] = OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE.' <= '.self::ORDER_TABLE_NAME.'.worker_order_status and '.self::ORDER_TABLE_NAME.'.worker_order_status < '.OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT.' OR worker_order_status = '.OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT;
                $where[self::ORDER_TABLE_NAME.'.worker_order_type'] = ['in', implode(',', OrderService::ORDER_TYPE_IN_INSURANCE_LIST)];
//                $ex_where = ['b.type' => 0];
                $ex_where = [];
                // $field = self::ORDER_TABLE_NAME.'.id,'.self::ORDER_TABLE_NAME.'.factory_id,'.self::ORDER_TABLE_NAME.'.orno,'.self::ORDER_TABLE_NAME.'.create_time as type_time,if(b.frozen_money,b.frozen_money,"0.00") as money';
                $field_a = 'id,factory_id,orno,create_time as type_time';
                $field_b = 'worker_order_id,if(frozen_money,frozen_money,"0.00") as money';
                $ex_tablel_name = self::FACTORY_FROZEN_TABLE_NAME;
                $sum_field = 'b.frozen_money';
                break;

            case 2:
//                $where['_string'] = self::ORDER_TABLE_NAME.'.worker_order_status in ('.OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT.','.OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT.')';
                $where['_string'] = self::ORDER_TABLE_NAME.'.worker_order_status in ('.OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT.')';
                $ex_where = ['b.type' => 1];
                $ex_where = [];
                // $field = self::ORDER_TABLE_NAME.'.id,'.self::ORDER_TABLE_NAME.'.factory_id,'.self::ORDER_TABLE_NAME.'.orno,'.self::ORDER_TABLE_NAME.'.create_time as type_time,if(b.frozen_money,b.frozen_money,"0.00") as money';
                $field_a = 'id,factory_id,orno,create_time as type_time';
                $field_b = 'worker_order_id,if(frozen_money,frozen_money,"0.00") as money';
                $ex_tablel_name = self::FACTORY_FROZEN_TABLE_NAME;
                $sum_field = 'b.frozen_money';
                break;

            case 3:
                $where['_string'] = self::ORDER_TABLE_NAME.'.worker_order_status = '.OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED;
                $ex_where = [];
                // $field = self::ORDER_TABLE_NAME.'.id,'.self::ORDER_TABLE_NAME.'.factory_id,'.self::ORDER_TABLE_NAME.'.orno,'.self::ORDER_TABLE_NAME.'.factory_audit_time as type_time,if(b.factory_total_fee_modify,b.factory_total_fee_modify,"0.00") as money';
//                $field_a = 'id,factory_id,orno,create_time as type_time';
                $field_a = 'id,factory_id,orno,factory_audit_time as type_time';
                $field_b = 'worker_order_id,if(factory_total_fee_modify,factory_total_fee_modify,"0.00") as money';
                $ex_tablel_name = self::ORDER_FEE_TABLE_NAME;
                $sum_field = 'b.factory_total_fee_modify';
                $search_time_key = self::ORDER_TABLE_NAME.'.factory_audit_time';
                break;
            
            default:
                return;
                break;
        }

        $search['start_time'] && $where[$search_time_key] = ['egt', $s_time];

            $search['end_time']
        &&  $where[$search_time_key] = isset($where[$search_time_key]) ? ['BETWEEN', "{$s_time},{$e_time}"] : ['elt', $e_time];
        
        if (1 == $is_export) {
            $export_opts = ['where' => $where, 'alias' => self::ORDER_TABLE_NAME];
            $role = AuthService::getModel();
            if (1 == $type || 2 == $type) {
                if (AuthService::ROLE_ADMIN == $role) {
                    (new ExportLogic())->adminOrderFrozen($export_opts, $fid, $type);
                } elseif (
                    AuthService::ROLE_FACTORY == $role ||
                    AuthService::ROLE_FACTORY_ADMIN == $role
                ) {
                    (new ExportLogic())->factoryOrderFrozen($export_opts, $fid, $type);
                }
            } elseif (3 == $type) {
                if (AuthService::ROLE_ADMIN == $role) {
                    (new ExportLogic())->adminOrderSettled($export_opts, $fid);
                } elseif (
                    AuthService::ROLE_FACTORY == $role ||
                    AuthService::ROLE_FACTORY_ADMIN == $role
                ) {
                    (new ExportLogic())->factoryOrderSettled($export_opts, $fid);
                }
            }
        } else {
            $model = BaseModel::getInstance(self::ORDER_TABLE_NAME);
            $count = $model->getNum([
                'alias' => self::ORDER_TABLE_NAME,
                'where' => $where,
            ]);

            $total_money = $model
                ->alias(self::ORDER_TABLE_NAME)
                ->join('left join '.$ex_tablel_name.' b on '.self::ORDER_TABLE_NAME.'.id = b.worker_order_id')
                ->where(array_merge($where, $ex_where))
                ->SUM($sum_field);

            $list = $model->getList([
                'field' => $field_a,
                'alias' => self::ORDER_TABLE_NAME,
                // 'join'  => 'left join '.$ex_tablel_name.' b on '.self::ORDER_TABLE_NAME.'.id = b.worker_order_id',
                'where' => $where,
                'limit' => getPage(),
                'order' => self::ORDER_TABLE_NAME.'.create_time desc',
            ]);

            $worker_order_ids = arrFieldForStr($list, 'id');

            if ($worker_order_ids) {
                $b_list = $worker_order_ids ? BaseModel::getInstance($ex_tablel_name)->getList([
                    'field' => $field_b,
                    'where' => [
                        'worker_order_id' => ['in', $worker_order_ids]
                    ],
                    'index' => 'worker_order_id',
                ]) : [];

                $field_arr = [
                    self::ORDER_TABLE_NAME.'.worker_order_id',
                    'concat('.self::ORDER_TABLE_NAME.'.cp_category_name,"-",'.self::ORDER_TABLE_NAME.'.cp_product_standard_name,"-",'.self::ORDER_TABLE_NAME.'.cp_product_brand_name,"-",cp_product_mode) as product_name',
                    'b.cp_area_names as area_name'
                ];

                $infos = BaseModel::getInstance(self::ORDER_PRODUCT_TABLE_NAME)->getList([
                    'field' => implode(',', $field_arr),
                    'alias' => self::ORDER_TABLE_NAME,
                    'where' => [
                        self::ORDER_TABLE_NAME.'.worker_order_id' => ['in', $worker_order_ids],
                    ],
                    'join'  => 'left join '.self::ORDER_USER_INFO_TABLE_NAME.' b on '.self::ORDER_TABLE_NAME.'.worker_order_id = b.worker_order_id',
//                    'index' => 'worker_order_id',
                    'order' => 'id desc',
                ]);

                $area_name_map =[];
                $product_map = [];
                foreach ($infos as  $key => $item) {
                    $product_map[$item['worker_order_id']][] = $item['product_name'];
                    $area_name_map[$item['worker_order_id']]['area_name'] = $item['area_name'];
                }

                $b_default = [
                    'money' => '0.00',
                ];

                foreach ($list as $k => &$v) {
                    $list[$k]['money'] = $b_list[$v['id']]['money'] ?? '0.00';
                    $list[$k]['area_name'] =$area_name_map[$v['id']]['area_name'] ?? null;
                    $list[$k]['products'] = $product_map[$v['id']] ?? null;
                }
            }
        }
    }

    // 获取厂家 充值记录 列表
    public function getRechargesPaginate($fid, &$list, &$count, &$total_money)
    {
        $search = I('get.');

        $s_time = I('get.start_time', 0, 'intval');
        $e_time = I('get.end_time', 0, 'intval');
        $min_m  = number_format(I('get.min_money', 0, 'intval'), 2, '.', '');
        $max_m  = number_format(I('get.max_money', 0, 'intval'), 2, '.', '');
        $type   = I('get.type', 0, 'intval');
        $is_export = I('is_export', 0, 'intval');

        $where = [
            'status'        => 1,
            'change_type'          => ['in', FactoryMoneyRecordService::CHANGE_TYPE_FOR_RECHARGE_ARR],
        ];

        if (!in_array($type, FactoryMoneyRecordService::CHANGE_TYPE_FOR_RECHARGE_ARR) && $type != 0) {
            return;
        }

        $fid && $where['factory_id'] = $fid;
        $type && isset($search['type']) && $where['change_type'] = $type;
        isset($search['start_time']) && $s_time && $where['create_time'] = ['egt', $s_time];

            isset($search['end_time']) && $e_time  
        &&  $where['create_time'] = isset($where['create_time']) ? ['BETWEEN', "{$s_time},{$e_time}"] : ['elt', $e_time];

        isset($search['min_money']) && $where['change_money'] = ['egt', $min_m];

            isset($search['max_money']) 
        &&  $where['change_money'] = isset($where['change_money']) ? ['BETWEEN', "{$min_m},{$max_m}"] : ['elt', $max_m];

        if (1 == $is_export) {
            //通过厂家id区分是多厂家还是单厂家
            $export_where = ['where' => $where];
            if ($fid > 0) {
                $role = AuthService::getModel();
                if (AuthService::ROLE_ADMIN == $role) {
                    (new ExportLogic())->adminFactoryRechargeOne($export_where, $fid);
                } else {
                    (new ExportLogic())->factoryRechargeOne($export_where, $fid);
                }
            } else {
                (new ExportLogic())->adminFactoryRecharge($export_where);
            }
        } else {
            $model = BaseModel::getInstance(self::FACTORY_MONEY_CHANGE_TABLE_NAME);
            $count = $model->getNum($where);
            $total_money = $model->where($where)->SUM('change_money');

            if (!$count) {
                return;
            }

            $list = $model->getList([
                'field' => 'id,factory_id,operator_id,operator_type,change_type,money,change_money,last_money,operation_remark,create_time',
                'where' => $where,
                'limit' => getPage(),
                'order' => 'create_time desc',
            ]);

            $people = $operators = $factories = $factory_ids = [];
            $check_prink = [
                1 => 'id',
                2 => 'factory_id',
                3 => 'id',
            ];
            $check_name = [
                1 => AuthService::getModel() == AuthService::ROLE_ADMIN ? 'nickout' : '"神州财务" as user_name',
                2 => 'linkman',
                3 => 'nickout',
            ];

            $check = [
                1 => self::ADMIN_TABLE_NAME,
                2 => self::FACTORY_TABLE_NAME,
                3 => self::FACTORY_ADMIN_TABLE_NAME,
            ];
            foreach ($list as $k => $v) {
                $people[$v['operator_type']][$v['operator_id']] = $v['operator_id'];
                $factory_ids[$v['factory_id']] = $v['factory_id'];
            }
            $factory_ids = implode(',', $factory_ids);

            $factories = $factory_ids ? BaseModel::getInstance(self::FACTORY_TABLE_NAME)->getList([
                'field' => 'factory_id,factory_full_name',
                'where' => [
                    'factory_id' => ['in', $factory_ids],
                ],
                'index' => 'factory_id',
            ]) : [];

            foreach ($people as $k => $v) {
                $ids = implode(',', $v);
                if (isset($check[$k]) && isset($check_prink[$k]) && isset($check_name[$k]) && $ids) {
                    $operators[$k] = $ids ? BaseModel::getInstance($check[$k])->getList([
                        'field' => "{$check_prink[$k]},{$check_name[$k]}",
                        'where' => [
                            $check_prink[$k] => ['in', $ids],
                        ],
                        'index' => $check_prink[$k],
                    ]) : [];
                }
            }

            foreach ($list as $k => $v) {
                $operator_type  = $v['operator_type'];
                $operator_id    = $v['operator_id'];

                // 处理 '"神州财务" as user_name' 格式的$field
                $check_name_arr = explode(' ', $check_name[$operator_type]);
                $check_name_arr = empty($check_name_arr)? []: $check_name_arr;
                $check_name[$operator_type] = end($check_name_arr);

                isset($check_name[$operator_type])
                &&  $operator_name = $operators[$operator_type][$operator_id][$check_name[$operator_type]];

                $v['operator_name'] = $operator_name ?? '';
                $v['factory_name']  = $factories[$v['factory_id']]['factory_full_name'] ?? '';
                $operator_type      == 0 && $v['operator_name'] = '系统调整';
                $list[$k] = $v;
            }

            // return [array_values($list), $count];
            return;
        }

    }

    // 厂家财务审核通过 TODO 消息相关
    public function auditedOrder($order_id = 0, $remark = '')
    {
        $model = BaseModel::getInstance('worker_order');
        $field = [
            'orno',
            'factory_id',
            'worker_id',
            'worker_order_status',
            'worker_first_appoint_time',
            'worker_receive_time',
            'worker_first_sign_time',
        ];
        $order = $model->getOneOrFail($order_id, implode(',', $field));

            $order['worker_order_status'] != OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT
        &&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_AUDITED_ORDER_FACTORY);

        $fee_model = BaseModel::getInstance('worker_order_fee');
        $order_fee = $fee_model->getOneOrFail($order_id);

        $f_model = BaseModel::getInstance('factory');
        $f_data = $f_model->getOneOrFail($order['factory_id']);

        $fr_model = BaseModel::getInstance('factory_money_frozen');
        // 当前工单冻结金
        $fr_data = $f_model->getOneOrFail(['worker_order_id' => $order_id], 'frozen_money');
        // 排除改工单后的总冻结金
//        $all_frozen = $fr_model->getList([
//                'field' => 'SUM(frozen_money) as all_frozen',
//                'where' => [
//                    'factory_id'    => $order['factory_id'],
//                    'order_id'  => ['neq', $order_id],
//                    'type'      => 0,
//                ],
//            ]);

        // 厂家资金是否足够结算
        $factory_last_money = $f_data['money'] - $order_fee['factory_total_fee_modify'];
        $factory_last_money < 0 && $this->throwException(ErrorCode::WORKER_ORDER_FACTORY_SETTLEMENT_NOT_MONEY); // 余额不足

        $f_update = [
            // 'frozen_money' => $f_data['frozen_money'] + $fr_data['frozen_money'],
//            'frozen_money' => $all_frozen,
            'money' => $factory_last_money,
        ];

        M()->startTrans();
        $update = [
                'worker_order_status'   => OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
                'factory_audit_remark'  => $remark ?? '',
                'factory_audit_time'    => NOW_TIME,
                'last_update_time'      => NOW_TIME,
            ];
        $model->update($order_id, $update);
        $f_model->update($order['factory_id'], $f_update);
        // 冻结金相关数据
        FactoryMoneyFrozenRecordService::process($order_id, FactoryMoneyFrozenRecordService::TYPE_ORDER_SETTLEMENT);
        // 添加技工信誉记录
        // worker_order_reputation
        (new \Admin\Logic\OrderLogic)->workerOrderCompleteReputation($order_id, $order);
        
        // 厂家-维修金-支付记录
        // factory_repair_pay_record factory_money_change_record
        // BaseModel::getInstance('factory_money_pay_record')->insert([
        BaseModel::getInstance('factory_repair_pay_record')->insert([
                'factory_id'        => $order['factory_id'],
                'worker_order_id'   => $order_id,
                'orno'              => $order['orno'],
                'pay_money'         => $order_fee['factory_total_fee_modify'],
                'last_money'        => $factory_last_money,
                'create_time'       => NOW_TIME,
            ]);
        BaseModel::getInstance(self::FACTORY_MONEY_CHANGE_TABLE_NAME)->insert([
                'factory_id'    => $order['factory_id'],
                'operator_id'   => AuthService::getAuthModel()->getPrimaryValue(),
                'operator_type' => AuthService::getModel() == AuthService::ROLE_FACTORY_ADMIN ? FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN : FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY,
                'operation_remark' => $remark ?? '',
                'change_type'   => FactoryMoneyChangeRecordService::CHANGE_TYPE_SYSTEM_SETTLE,
                'money'         => $f_data['money'],
                'change_money'  => - $order_fee['factory_total_fee_modify'],
                'last_money'    => $factory_last_money,
                'out_trade_number'  => $order['orno'],
                'status'        => FactoryMoneyChangeRecordService::STATUS_SUCCESS,
                'create_time'   => NOW_TIME,
            ]);

        // 操作记录 CS_AUDITED_WORKER_ORDER 
        $extras = ['remark' => $remark];
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::FACTORY_SETTLEMENT_WORKER_ORDER_FEE, $extras);

        // 厂家金额少于 1000 发送警告：【系统消息】尊敬的厂家，您的维修金余额不足{$f_data['money_not_enouth']}元,请注意
        if ($factory_last_money < $f_data['money_not_enouth']) {
            $system_msg = "尊敬的厂家，您的维修金余额不足{$f_data['money_not_enouth']}元,请注意";
            SystemMessageService::create(SystemMessageService::USER_TYPE_FACTORY, $order['factory_id'], $system_msg, 0, SystemMessageService::MSG_TYPE_FACTORY_RECHARGE_REMIND);
        }
        M()->commit();
    }

    // 厂家财务审核不通过，工单待平台财务审核状态 TODO 消息相关
    public function notAuditedOrder($order_id = 0, $remark = '')
    {
        $remark_content = $remark;  //获取备注内容
        $model = BaseModel::getInstance('worker_order');
        $order = $model->getOneOrFail($order_id, 'worker_order_status,auditor_id,orno');

            $order['worker_order_status'] != OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT
        &&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_AUDITED_ORDER_FACTORY);

        M()->startTrans();
        $update = [
                'worker_order_status'   => OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
                'last_update_time'      => NOW_TIME,
                'factory_audit_time'    => NOW_TIME,
                'factory_audit_remark'    => $remark,
            ];
        $model->update($order_id, $update);

        // 冻结金 待结算 转换 进行中
        BaseModel::getInstance(self::FACTORY_FROZEN_TABLE_NAME)->update([
            'worker_order_id' => $order_id
        ], [
            'type' => FactoryMoneyFrozenRecordService::FROZEN_TYPE_ORDER_STATUS_ING,
        ]);

        // 操作记录 CS_AUDITED_WORKER_ORDER 
        $extras = ['remark' => $remark];
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::FACTORY_NOT_SETTLEMENT_WORKER_ORDER_FEE, $extras);
        $system_msg = "工单号 {$order['orno']}，厂家审核不通过。{$remark_content}";
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order['auditor_id'], $system_msg, $order_id, SystemMessageService::MSG_TYPE_FACTORY_ORDER_FACTORY_AUDITOR_FORBIDDEN);
        M()->commit();

        event(new WorkbenchEvent(['worker_order_id' => $order_id, 'event_type' => C('WORKBENCH_EVENT_TYPE.FACTORY_AUDITOR_NOT_PASS')]));
    }

    public function getFactoryGroup()
    {
        $list = FactoryService::FACTORY_GROUP;

        return $list;
    }

    public function getFactoryCanAddOrderNum($factory_id)
    {
        $factory = BaseModel::getInstance('factory')
            ->getOneOrFail($factory_id, 'money,default_frozen');

        return intval($factory['money'] / $factory['default_frozen']);
    }

    public function technology($factory_id)
    {
        $technologies = BaseModel::getInstance('factory_helper')
            ->getList([
                'where' => [
                    'factory_id' => $factory_id,
                ],
                'field' => 'id,name,telephone'
            ]);

        foreach ($technologies as $key => $technology) {
            $technologies[$key]['full'] = $technology['name'] . $technology['telephone'];
        }

        return $technologies;
    }

    public function checkFactoryExpire($factory_id)
    {
        $expire = BaseModel::getInstance('factory')
            ->getFieldVal($factory_id, 'dateto');

        if ($expire < $_SERVER['REQUEST_TIME']) {
            $this->throwException(ErrorCode::FACTORY_DATE_EXPIRE);
        }
    }

    public function checkFactoryExpireByTime($expire)
    {
        if ($expire < $_SERVER['REQUEST_TIME']) {
            $this->throwException(ErrorCode::FACTORY_DATE_EXPIRE, '厂家合同已到期无法下单');
        }
    }

    public function getFrozenMoney($factory_id)
    {
        $sum = BaseModel::getInstance('factory_money_frozen')
            ->getSum([
                'where' => ['factory_id' => $factory_id],
                'lock' => true,
            ], 'frozen_money');

        return floatval($sum);
    }

    public function getFactoryTags($factory)
    {
        $factory_tags = BaseModel::getInstance('factory_adtags')->getList([
            'field' => 'id,name',
            'where' => [
                'factory_id' => $factory['factory_id'],
                'is_delete' => 0,
            ]
        ]);
        $factory_tags  = (array)$factory_tags;

        array_unshift($factory_tags, FactoryService::FACTORY_INTERNAL_GROUP);

        return $factory_tags;
    }

    public function syncFactoryBrand($factory_id, $category_id_brand_map)
    {
        $category_ids = [];
        $should_insert_brand = [];
        foreach ($category_id_brand_map as $category_id => $data) {
            $category_ids[] = $category_id;
            foreach ($data as $brand) {
                $should_insert_brand[$category_id . '_' . $brand] = [
                    'factory_id' => $factory_id,
                    'product_cat_id' => $category_id,
                    'product_brand' => $brand,
                    'sort' => 0,
                ];
            }
        }
        $category_ids = array_unique($category_ids);
        if (!$category_ids) {
            return ;
        }
        $branches = BaseModel::getInstance('factory_product_brand')
            ->getList([
                'where' => [
                    'factory_id' => $factory_id,
                    'product_cat_id' => ['IN', $category_ids],
                ],
                'field' => 'id,product_cat_id,product_brand'
            ]);
        $branch_category_brand_map = [];
        foreach ($branches as $branch) {
            $branch_category_brand_map[$branch['product_cat_id']][] = $branch['product_brand'];
        }

        foreach ($category_id_brand_map as $category_id => $data) {
            foreach ($data as $branch) {
                if (in_array($branch, $branch_category_brand_map[$category_id])) {
                    unset($should_insert_brand[$category_id . '_' . $branch]);
                }
            }
        }
        $should_insert_brand = array_values($should_insert_brand);
        if ($should_insert_brand) {
            BaseModel::getInstance('factory_product_brand')->insertAll($should_insert_brand);
        }
    }

    public function dealerList($factory_id)
    {
        $where = [
            'factory_id' => $factory_id,
        ];
        if ($name = I('name')) {
            $where['factory_product_white_list.name'] = ['LIKE', "%{$name}%"];
        }
        if ($phone = I('phone')) {
            $where['factory_product_white_list.user_name'] = ['LIKE', "%{$phone}%"];
        }
        if ($area_id = I('area_id')) {
            $where['_string'] = "(FIND_IN_SET('{$area_id}', dealer_info.area_ids))";
        }
        if ($status = I('status')) {
            $where['status'] = $status - 1;
        }
        $dealer_list = BaseModel::getInstance('factory_product_white_list')->getList([
            'where' => $where,
            'join' => [
                'LEFT JOIN wx_user ON wx_user.telephone=user_name',
                'LEFT JOIN dealer_info ON wx_user_id=wx_user.id AND user_type=1',
            ],
            'field' => 'factory_product_white_list.id,user_name,factory_product_white_list.name,status,wx_user.id wx_user_id,store_name,dealer_info.area_ids,telephone',
            'order' => 'id DESC',
            'limit' => getPage(),
        ]);

        $count = BaseModel::getInstance('factory_product_white_list')->getNum([
            'where' => $where,
            'join' => [
                'LEFT JOIN wx_user ON wx_user.telephone=user_name',
                'LEFT JOIN dealer_info ON wx_user_id=wx_user.id',
            ],
        ]);

        if ($dealer_list) {

            $user_id_map = [];
            $user_phone_map = [];
            foreach ($dealer_list as &$user) {
                $user_id_map[$user['id']] = &$user;
                $user_phone_map[$user['telephone']] = &$user;
            }


            $area_ids = [];
            foreach ($user_id_map as &$item) {
                if ($item['area_ids']) {
                    $item['area_ids'] = explode(',', $item['area_ids']);
                    [$area_ids[], $area_ids[], $area_ids[]] = $item['area_ids'];
                }
            }

            if ($area_ids) {
                $area_id_name_map = getAreaIdNameMap($area_ids);
                foreach ($user_id_map as &$value) {
                    if ($value['area_ids']) {
                        $area_desc = [
                            $area_id_name_map[$value['area_ids'][0]],
                            $area_id_name_map[$value['area_ids'][1]],
                            $area_id_name_map[$value['area_ids'][2]],
                        ];
                        unset($value['area_ids']);
                        unset($value['telephone']);
                        $value['area_desc'] = implode('-', $area_desc);
                    } else {
                        $value['area_desc'] = '';
                    }
                }
            }

        }

        return ['list' => $dealer_list, 'count' => $count];
    }

    public function showDealer($dealer_id)
    {
        $dealer_info = BaseModel::getInstance('factory_product_white_list')->getOneOrFail($dealer_id, 'id,user_name,name,status,desc');
        $dealer_info['desc'] = $dealer_info['desc'] ?? '';

        $user_info = BaseModel::getInstance('wx_user')->getOne([
            'where' => [
                'telephone' => $dealer_info['user_name'],
                'user_type' => 1,
            ],
            'field' => 'wx_user.id,store_name,dealer_info.area_ids,area_desc,license_image,dealer_images,dealer_product_ids',
            'join' => [
                'LEFT JOIN dealer_info ON wx_user_id=wx_user.id',
            ],
        ]);

        if ($user_info) {
            $area_ids = explode(',', $user_info['area_ids']);
            $area_id_name_map = getAreaIdNameMap($area_ids);
            $user_info['area'] = implode([
                $area_id_name_map[$area_ids[0]],
                $area_id_name_map[$area_ids[1]],
                $area_id_name_map[$area_ids[2]],
            ]);

            if ($user_info['dealer_product_ids']) {
                $dealer_product = BaseModel::getInstance('dealer_product')->getFieldVal([
                    'where' => ['id' => ['IN', $user_info['dealer_product_ids']]],
                ], 'name', true);
            } else {
                $dealer_product = [];
            }

            unset($user_info['dealer_product_ids']);
            unset($user_info['area_ids']);
            $user_info['license_image'] = Util::getServerFileUrl($user_info['license_image']);
            $user_info['dealer_images'] = json_decode($user_info['dealer_images'], true);
            foreach ($user_info['dealer_images'] as &$dealer_image) {
                $dealer_image = Util::getServerFileUrl($dealer_image);
            }

            $user_info['products'] = implode(',', $dealer_product);

            $dealer_info['user'] = $user_info;
        } else {
            $dealer_info['user'] = (Object)[];
        }

        return $dealer_info;
    }

    public function updateDealer($id, $data)
    {
        BaseModel::getInstance('factory_product_white_list')->update(['id' => $id], $data);
    }

    public function getDealerActiveRecord($dealer_id, $factory_id)
    {
        $dealer = BaseModel::getInstance('factory_product_white_list')->getOneOrFail($dealer_id, 'id,user_name');
        $user = BaseModel::getInstance('wx_user')->getOne(['telephone' => $dealer['user_name']], 'id');
        if (!$user) {
            $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '暂时无数据');
        }

        $where = [
            'dealer_id' => $user['id'],
            'factory_id' => $factory_id,
        ];
        $products = BaseModel::getInstance('dealer_bind_products')->getList([
            'field' => 'product_id,factory_id,code',
            'where' => $where,
            'order' => 'id DESC',
            'limit' => getPage(),
        ]);
        $product_num = BaseModel::getInstance('dealer_bind_products')->getNum($where);

        $code_product_map = [];
        $product_id_product_map = [];
        foreach ($products as &$product) {
            $code_product_map[$product['code']] = &$product;
            $product_id_product_map[$product['product_id']] = &$product;
        }
        $yima_table = factoryIdToModelName($factory_id);
        if ($code_product_map) {
            $code_yima_map = BaseModel::getInstance($yima_table)->getFieldVal([
                'code' => ['IN', array_keys($code_product_map)],
                'factory_id' => $factory_id,
            ], 'code,chuchang_time,active_time,zhibao_time,user_name,user_tel,user_address,register_time');
        } else {
            $code_yima_map = [];
        }

        $area_ids = [];
        foreach ($code_yima_map as $code => $item) {
            $code_product_map[$code]['active_time'] = $item['register_time'];
            $code_product_map[$code]['factory_time'] = $item['chuchang_time'];
            $code_product_map[$code]['buy_time'] = $item['active_time'];
            $code_product_map[$code]['warranty_time'] = $item['zhibao_time'];
            $code_product_map[$code]['user_name'] = $item['user_name'];
            $code_product_map[$code]['user_tel'] = $item['user_tel'];

            $user_address = json_decode($item['user_address'], true);
            [$area_ids[], $area_ids[], $area_ids[]] = explode(',', $user_address['ids']);
        }
        $area_ids = array_unique($area_ids);
        if ($area_ids) {
            $area_id_name_map = getAreaIdNameMap($area_ids);
        } else {
            $area_id_name_map = [];
        }

        foreach ($code_yima_map as $code => $item) {
            $user_address = json_decode($item['user_address'], true);

            $areas = explode(',', $user_address['ids']);

            $code_product_map[$code]['address']['province'] = $area_id_name_map[$areas[0]];
            $code_product_map[$code]['address']['city'] = $area_id_name_map[$areas[1]];
            $code_product_map[$code]['address']['district'] = $area_id_name_map[$areas[2]];
            $code_product_map[$code]['address']['address'] = $user_address['address'];
        }

        if ($product_id_product_map) {
            $factory_products = BaseModel::getInstance('factory_product')->getList([
                'where' => [
                    'product_id' => ['IN', array_keys($product_id_product_map)],
                ],
                'field' => 'product_id,product_xinghao,product_category,product_guige,product_brand'
            ]);
        } else {
            $factory_products = [];
        }


        $product_categories = [];
        $product_guides = [];
        $product_brands = [];
        foreach ($factory_products as $factory_product) {
            $product_categories[] = $factory_product['product_category'];
            $product_guides[] = $factory_product['product_guige'];
            $product_brands[] = $factory_product['product_brand'];

            $product_id_product_map[$factory_product['product_id']]['model'] = $factory_product['product_xinghao'];
        }

        $product_category_id_name_map = $product_categories ?BaseModel::getInstance('cm_list_item')->getFieldVal([
            'list_item_id' => ['IN', $product_categories]
        ], 'list_item_id,item_desc', true) : [];
        $product_standard_id_name_map = $product_guides ? BaseModel::getInstance('product_standard')->getFieldVal([
            'standard_id' => ['IN', $product_guides],
        ], 'standard_id,standard_name', true) : [];
        $product_branch_id_name_map = $product_brands ? BaseModel::getInstance('factory_product_brand')->getFieldVal([
            'id' => ['IN', $product_brands],
        ], 'id,product_brand') : [];

        foreach ($factory_products as $item) {
            $product_id_product_map[$item['product_id']]['category'] = $product_category_id_name_map[$item['product_category']];
            $product_id_product_map[$item['product_id']]['standard'] = $product_standard_id_name_map[$item['product_guige']];
            $product_id_product_map[$item['product_id']]['branch'] = $product_branch_id_name_map[$item['product_brand']];
        }

        return [
            'list' => $products,
            'count' => $product_num,
        ];
    }

    public function getFactoryById($factory_id)
    {
        return BaseModel::getInstance('factory')->getOne($factory_id);
    }

    public function factoryMoneyFrozenThawRecord($factory_id, $search = [])
    {
        $return = [
            'num'  => 0,
            'list' => null,
            'total_money' => '0.00',
        ];

        $frozen_record_model = BaseModel::getInstance('factory_money_frozen_record');
        // 先找数量  没有就可以先退出不用找列表

        $where = [
            'factory_id' => $factory_id,
//            'create_time'=> ['egt', strtotime('2018-01-09 18:30:00')],
            '_string' => ' id not in (SELECT `id` from factory_money_frozen_record where type = 11 AND (last_factory_frozen_money  - factory_frozen_money) = 0)',
        ];

        $worker_order_ids = [];
        $orno_list = [];
        if (!empty($search['orno'])) {
            //添加工单号
            $orno_list =  BaseModel::getInstance('worker_order')->getList([
                'field' => 'id,orno,service_type',
                'index' => 'id',
                'where' => [
                    'orno' => ['like', '%'.$search['orno'].'%'],
                ],
            ]);

            $worker_order_ids = array_column($orno_list, 'id');
            if (!$worker_order_ids) {
                return $return;
            }
            $where['worker_order_id'] = ['in', $worker_order_ids];
        }

        if ($search['start_time'] || $search['end_time']) {
            $search['start_time'] && $where['create_time'] = ['egt', $search['start_time']];
            $search['end_time']   && $where['create_time'] = $where['create_time'] ? [$where['create_time'], ['elt', $search['end_time']]] : ['elt', $search['end_time']];
        }

        $where['type'] = ['in', array_keys(FactoryMoneyFrozenRecordService::TYPE_CHANGE_REMARKS_LT_ZERO_KEY_VALUE)];
        $before = $frozen_record_model->getList([
            'fetchSql' => true,
            'field' => 'id,worker_order_id,create_time,-frozen_money as money,1 as money_type,type',
            'where' => $where,
        ]);

        $where['type'] = ['in', array_keys(FactoryMoneyFrozenRecordService::TYPE_CHANGE_REMARKS_EGT_ZERO_KEY_VALUE)];
        $after = $frozen_record_model->getList([
            'fetchSql' => true,
            'field' => 'id,worker_order_id,create_time,last_frozen_money as money,2 as money_type,type',
            'where' => $where,
        ]);

        $all_sal = "select * from ({$after}) a union all select * from ({$before}) b";

        $return['num'] = reset(M()->query("select count(*) as result from ({$all_sal}) C "))['result'];

        if (!$return['num']) {
            return $return;
        }

//        $frozen_thaw_info = $frozen_record_model->getList([
//                'field' => 'id,worker_order_id,create_time,(last_factory_frozen_money  - factory_frozen_money) as frozen_money,type',
//                'where' => $where,
//                'limit' => getPage(),
//                'order' => 'create_time desc',
//            ]);
        $frozen_thaw_info = M()->query("select * from ({$all_sal}) C  order by create_time desc,id,money_type desc limit ".getPage());

        if (!$frozen_thaw_info) {
            return $return;
        }
        //计算合计金额
//        $return['total_money'] = $frozen_record_model->where($where)->Sum('last_factory_frozen_money-factory_frozen_money');
        $return['total_money'] = reset(M()->query("select sum(frozen_money) as result from factory_money_frozen where worker_order_id in (select worker_order_id from(select worker_order_id from ({$after}) a union all select worker_order_id from ({$before}) b) C group by worker_order_id)"))['result'];
        $return['total_money'] = number_format($return['total_money'], 2, '.', '');

        $worker_order_ids = array_column($frozen_thaw_info, 'worker_order_id');
        !$orno_list && $orno_list = BaseModel::getInstance('worker_order')->getList([
            'field' => 'id,orno,service_type',
            'index' => 'id',
            'where' => [
                'id' => ['in', $worker_order_ids],
            ],
        ]);

        //找出type是13的总共记录，然后放到对应的worker_order_id里
        $type_is_fourteen_list = $worker_order_ids ? $frozen_record_model->getList([
            'order' => 'create_time asc,id asc',
            'field' => 'id,worker_order_id,type',
            'where' => [
                'worker_order_id' => ['in', $worker_order_ids],
                'type' => FactoryMoneyFrozenRecordService::TYPE_ADMIN_ORDER_CONFORM_AUDITOR,
            ],
        ]) : [];

        $check_list = [];
        foreach ($type_is_fourteen_list as $k => $v) {
            $check_list[$v['worker_order_id']][$v['id']] = count($check_list[$v['worker_order_id']]) + 1;
        }

        //计算当前工单的冻结金额，添加文案
        foreach ($frozen_thaw_info as $key => $val) {
            $frozen_thaw_info[$key]['frozen_money'] = $val['money'];
            $frozen_thaw_info[$key]['frozen_reason'] = FactoryMoneyFrozenRecordService::getTypeChangeString($val['type'], $val['money'], $val['money_type'],$check_list[$val['worker_order_id']][$val['id']]);
        }

        //添加产品信息
        $product_list =  BaseModel::getInstance('worker_order_product')->getList([
            'field' => 'worker_order_id,cp_category_name,cp_product_standard_name,cp_product_brand_name,cp_product_mode',
            'order' => 'id desc',
            'where' => [
                'worker_order_id' => ['IN', $worker_order_ids]
            ],
        ]);
        $product_list_map = [];
        foreach ($product_list as $key => $val) {
            $product_list_map[$val['worker_order_id']][] = $val;
        }
        //添加工作服务类型
        $service_type_list = OrderService::SERVICE_TYPE;
        foreach ($frozen_thaw_info as $key => $val) {
            $order = $orno_list[$val['worker_order_id']];

            $val['orno'] = $order['orno'];
            $service_type = $order['service_type'];
            $val['service_type'] = $service_type_list[$service_type];

            $val['products'] = $product_list_map[$val['worker_order_id']];

            $return['list'][] = $val;
        }

        return $return;
    }


}
