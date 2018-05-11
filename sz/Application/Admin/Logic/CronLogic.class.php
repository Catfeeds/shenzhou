<?php
/**
 * File: CronLogic.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/14
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Repositories\Events\OrderSettlementFatalErrorEvent;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\OrderSendNotificationEvent;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\FactoryMoneyChangeRecordService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SMSService;
use Common\Common\Service\SystemMessageService;

class CronLogic extends BaseLogic
{

    protected $tableName = 'worker_order';

    const SETTLE_LIMIT        = 10000;
    const SETTLE_DEFAULT_DAYS = 5;

    const LIST_TYPE_FACTORY                = 1;
    const LIST_TYPE_ORDER_FEE              = 2;
    const LIST_TYPE_FACTORY_MONEY_FROZEN   = 3;
    const LIST_TYPE_REPUTATION             = 5;
    const LIST_TYPE_WORKER_ORDER_EXT_INFO  = 6;
    const LIST_TYPE_WORKER_ORDER_ACCESSORY = 7;
    const LIST_TYPE_ADMIN_CONFIG           = 8;
    const LIST_TYPE_WORKER                 = 9;
    const LIST_TYPE_ORDER_USER_INFO        = 10;
    const LIST_TYPE_ORDER_PRODUCT          = 11;
    const LIST_TYPE_ORDER                  = 12;
    const LIST_TYPE_ORDER_ACCESSORY_ITEM   = 13;
    const LIST_TYPE_MESSAGE_STATS          = 14;

    const PROMPT_LIMIT = 3;

    const PROMPT_WORKER_UPLOAD_ACCESSORY_REPORT = 1;
    const PROMPT_WORKER_APPOINT_TOMORROW        = 2;
    const PROMPT_FACTORY_ACCESSORY_CONFIRM_SEND = 3;
    const PROMPT_WORKER_ACCESSORY_SEND_BACK     = 4;
    const PROMPT_WORKER_UPLOAD_REPORT           = 5;

    protected $list_name
        = [
            self::LIST_TYPE_FACTORY                => '厂家',
            self::LIST_TYPE_ORDER_FEE              => '工单费用',
            self::LIST_TYPE_FACTORY_MONEY_FROZEN   => '厂家冻结金记录',
            self::LIST_TYPE_REPUTATION             => '技工工单信誉',
            self::LIST_TYPE_WORKER_ORDER_EXT_INFO  => '工单额外信息',
            self::LIST_TYPE_WORKER_ORDER_ACCESSORY => '配件单',
            self::LIST_TYPE_WORKER                 => '技工',
            self::LIST_TYPE_ORDER_USER_INFO        => '工单用户信息',
            self::LIST_TYPE_ORDER_PRODUCT          => '工单产品',
            self::LIST_TYPE_ORDER                  => '工单',
            self::LIST_TYPE_ORDER_ACCESSORY_ITEM   => '工单详情',
            self::LIST_TYPE_MESSAGE_STATS          => '消息统计',

        ];

    protected $param = [];

    protected $config = [];

    protected function flush()
    {
        $this->param = [];
    }

    protected function setList($key, $list)
    {
        $this->param[$key] = $list;
    }

    protected function getListInfo($param_key, $list_key, $is_allow_null = false)
    {
        if (
            !array_key_exists($param_key, $this->param) ||
            !array_key_exists($list_key, $this->param[$param_key])
        ) {
            if ($is_allow_null) {
                return null;
            }
            $name = $this->list_name[$param_key]?? '';
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, $name . '数据不存在');
        }

        return $this->param[$param_key][$list_key];
    }

    protected function setListInfo($param_key, $list_key, $key, $val)
    {
        if (
            !array_key_exists($param_key, $this->param) ||
            !array_key_exists($list_key, $this->param[$param_key])
        ) {
            $name = $this->list_name[$param_key]?? '';
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, $name . '数据不存在');
        }

        return $this->param[$param_key][$list_key][$key] = $val;
    }


    /**
     * 工单自动结算
     */
    public function orderSettle()
    {
        $order_model = BaseModel::getInstance('worker_order');

        $last_id = 0;

        $where = [
            'worker_order_status' => OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
            'cancel_status'       => ['in', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
            'id'                  => ['gt', &$last_id],
        ];

        //根据厂家自己设置的结算时长
        $factory_ids_setting = $this->getFactorySetting();
        $complex = [];
        $today = strtotime(date('Ymd', NOW_TIME));
        foreach ($factory_ids_setting as $days => $factory_ids) {
            $deadline = $today - $days * 86400;
            $factory_ids = implode(',', $factory_ids);
            $complex[] = "(factory_id in ({$factory_ids}) and audit_time<{$deadline})";
        }

        $where['_string'] = implode(' or ', $complex);

        $opts = [
            'field' => 'id,factory_id,orno,worker_receive_time,worker_first_appoint_time,worker_first_sign_time',
            'where' => $where,
            'order' => 'id',
            'limit' => self::SETTLE_LIMIT,
        ];

        do {
            $order = $order_model->getList($opts);

            if (empty($order)) {
                break;
            }

            $factory_ids = [];
            $worker_order_ids = [];

            foreach ($order as $val) {
                $factory_id = $val['factory_id'];
                $worker_order_id = $val['id'];

                $factory_ids[] = $factory_id;
                $worker_order_ids[] = $worker_order_id;
            }

            $factory_ids = array_unique($factory_ids);
            $worker_order_ids = array_unique($worker_order_ids);

            $this->collectWorkerOrderFee($worker_order_ids);
            $this->collectFactory($factory_ids);
            $this->collectFactoryMoneyFrozen($worker_order_ids);
            $this->collectReputation($worker_order_ids);
            $this->collectAccessory($worker_order_ids);
            $this->collectWorkerOrderExtInfo($worker_order_ids);
            $this->collectAdminConf();

            $fatal_error = []; // 记录异常

            $repair_record_insert_data = []; // 待插入维修收入记录
            $change_record_insert_data = []; // 待插入资金变动记录
            $worker_reputation_update_data = []; // 技工信誉更新数据
            $worker_order_ids_update = []; // 工单状态变动id列表
            $factory_ids_update = []; // 厂家信息变动厂家id列表
            $factory_ids_unsettle = []; // 厂家金额不足不能结算厂家id列表
            $factory_ids_remind_money_not_enough = []; // 厂家金额不足提醒厂家id列表
            $order_operations = []; // 工单日志
            $frozen_record_insert_data = []; // 冻结日志记录

            //检查工单及组织更新数据
            foreach ($order as $order_info) {
                $worker_order_id = $order_info['id'];
                $factory_id = $order_info['factory_id'];
                $orno = $order_info['orno'];

                try {
                    $order_fee = $this->getListInfo(self::LIST_TYPE_ORDER_FEE, $worker_order_id);
                    $factory_total_fee_modify = $order_fee['factory_total_fee_modify'];

                    //厂家信息
                    $factory = $this->getListInfo(self::LIST_TYPE_FACTORY, $factory_id);
                    $factory_money = $factory['money'];
                    $last_frozen_money = $factory['frozen_money'];
                    $auto_settlement_days = $factory['auto_settlement_days'];

                    //厂家冻结金
                    $factory_frozen = $this->getListInfo(self::LIST_TYPE_FACTORY_MONEY_FROZEN, $worker_order_id);
                    $frozen_money = $factory_frozen['frozen_money'];

                    //检查余额
                    $last_money = bcsub($factory_money, $factory_total_fee_modify, 2);
                    if ($last_money < 0) {
                        $this->throwException(ErrorCode::WORKER_ORDER_FACTORY_SETTLEMENT_NOT_MONEY);
                    }
                    if ($last_money < FactoryService::FACTORY_FEE_MONEY_ERROR) {
                        $factory_ids_remind_money_not_enough[] = $factory_id;
                    }

                    //技工信誉
                    $worker_reputation_update_data[] = $this->getWorkerOrderCompleteReputation($order_info);

                    //这里代码不组织厂家余额和冻结金额数组是因为这些数据属于累计结果,不是单纯的记录,必须汇总

                    $worker_order_ids_update[] = $worker_order_id;
                    $factory_ids_update[] = $factory_id;

                    //厂家维修金支付记录
                    $repair_record_insert_data[] = [
                        'factory_id'      => $factory_id,
                        'worker_order_id' => $worker_order_id,
                        'orno'            => $orno,
                        'pay_money'       => $factory_total_fee_modify,
                        'last_money'      => $last_money,
                        'create_time'     => NOW_TIME,
                    ];


                    //厂家资金变动记录
                    $change_record_insert_data[] = [
                        'factory_id'       => $factory_id,
                        'operator_type'    => 0,
                        'operation_remark' => '',
                        'change_type'      => FactoryMoneyChangeRecordService::CHANGE_TYPE_SYSTEM_SETTLE,
                        'money'            => $factory_money,
                        'change_money'     => - $factory_total_fee_modify,
                        'last_money'       => $last_money,
                        'out_trade_number' => $orno,
                        'status'           => FactoryMoneyChangeRecordService::STATUS_SUCCESS,
                        'create_time'      => NOW_TIME,
                    ];

                    //厂家解冻后金额
                    $last_frozen_money = bcsub($last_frozen_money, $frozen_money, 2);
                    //厂家解冻前金额
                    $factory_frozen_money = bcadd($last_frozen_money, $frozen_money, 2);

                    $frozen_record_insert_data[] = [
                        'type'                      => FactoryMoneyFrozenRecordService::TYPE_SYSTEM_ORDER_SETTLEMENT,
                        'worker_order_id'           => $worker_order_id,
                        'factory_id'                => $factory_id,
                        'factory_frozen_money'      => $factory_frozen_money,
                        'frozen_money'              => $frozen_money,
                        'last_frozen_money'         => '0.00',
                        'last_factory_frozen_money' => $last_frozen_money,
                        'create_time'               => NOW_TIME,
                    ];

                    $order_operations[] = [
                        'worker_order_id' => $worker_order_id,
                        'extras'          => ['remark' => '', 'operator_id' => 0, 'content_replace' => ['day' => $auto_settlement_days]],
                    ];

                    //批量获取的厂家余额和冻结金额是当前批次的快照,每循环一条必须要相对应减去金额,保证日志记录能够对应
                    $this->setListInfo(self::LIST_TYPE_FACTORY, $factory_id, 'money', $last_money);
                    $this->setListInfo(self::LIST_TYPE_FACTORY, $factory_id, 'frozen_money', $last_frozen_money);

                } catch (\Exception $e) {
                    $fatal_error[] = [
                        'worker_order_id' => $worker_order_id,
                        'fatal_error_msg' => $e->getMessage(),
                    ];
                    $factory_ids_unsettle[] = $factory_id;
                }

            }

            $factory_ids_update = array_unique($factory_ids_update);
            M()->startTrans();
            //更新工单
            if (!empty($worker_order_ids_update)) {
                $where = ['id' => ['in', $worker_order_ids_update]];
                $update_data = [
                    'worker_order_status'  => OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
                    'factory_audit_remark' => '',
                    'factory_audit_time'   => NOW_TIME,
                    'last_update_time'     => NOW_TIME,
                ];
                $order_model->update($where, $update_data);
            }

            //更新厂家
            $factory_model = BaseModel::getInstance('factory');
            foreach ($factory_ids_update as $factory_id) {
                $factory = $this->getListInfo(self::LIST_TYPE_FACTORY, $factory_id);
                $factory_money = $factory['money'];
                $frozen_money = $factory['frozen_money'];

                $factory_update_data = [
                    'frozen_money' => $frozen_money,
                    'money'        => $factory_money,
                ];
                $factory_model->update($factory_id, $factory_update_data);
            }

            //删除厂家冻结记录
            if (!empty($worker_order_ids_update)) {
                BaseModel::getInstance('factory_money_frozen')
                    ->remove(['worker_order_id' => ['in', $worker_order_ids_update]]);
            }

            //更新技工信誉
            $worker_reputation_model = BaseModel::getInstance('worker_order_reputation');
            foreach ($worker_reputation_update_data as $worker_reputation) {
                $where = $worker_reputation['where'];
                $data = $worker_reputation['update_data'];
                $worker_reputation_model->update($where, $data);
            }

            //厂家-维修金-支付记录
            if (!empty($repair_record_insert_data)) {
                BaseModel::getInstance('factory_repair_pay_record')
                    ->insertAll($repair_record_insert_data);
            }

            //冻结金额变动日志
            if (!empty($frozen_record_insert_data)) {
                BaseModel::getInstance('factory_money_frozen_record')
                    ->insertAll($frozen_record_insert_data);
            }

            //厂家资金变动记录
            if (!empty($change_record_insert_data)) {
                BaseModel::getInstance('factory_money_change_record')
                    ->insertAll($change_record_insert_data);
            }

            //日志
            if (!empty($order_operations)) {
                OrderOperationRecordService::createMany($order_operations, OrderOperationRecordService::SYSTEM_SETTLEMENT_WORKER_ORDER_FEE);
            }

            //记录异常报错
            foreach ($fatal_error as $error) {
                event(new OrderSettlementFatalErrorEvent($error));
            }

            //余额不足提醒
            if (!empty($factory_ids_unsettle)) {
                $factory_ids_unsettle_total = array_count_values($factory_ids_unsettle);
                $factory_ids_unsettle = array_unique($factory_ids_unsettle);
                $log_opts = [];
                foreach ($factory_ids_unsettle as $factory_id) {
                    $total_unsettle = $factory_ids_unsettle_total[$factory_id]?? 0;
                    $content = "您好，由于您的可用余额不足，有{$total_unsettle}张工单无法自动结算，请及时充值！";
                    $log_opts[] = [
                        'receiver_id' => $factory_id,
                        'content'     => $content,
                        'data_id'     => 0,
                    ];
                }
                SystemMessageService::createMany(SystemMessageService::USER_TYPE_FACTORY, SystemMessageService::MSG_TYPE_FACTORY_RECHARGE_REMIND, $log_opts);
            }

            //厂家金额少于 1000 发送警告
            if (!empty($factory_ids_remind_money_not_enough)) {
                $factory_ids_remind_money_not_enough = array_unique($factory_ids_remind_money_not_enough);
                $content = "您的维修金已不足1000元，请及时充值";
                $log_opts = [];
                foreach ($factory_ids_remind_money_not_enough as $factory_id) {
                    $log_opts[] = [
                        'receiver_id' => $factory_id,
                        'content'     => $content,
                        'data_id'     => 0,
                    ];
                }
                SystemMessageService::createMany(SystemMessageService::USER_TYPE_FACTORY, SystemMessageService::MSG_TYPE_FACTORY_RECHARGE_REMIND, $log_opts);
            }

            M()->commit();

            $last_id = end($worker_order_ids);
            $this->flush();

        } while (true);
    }

    public function clearFatalErrorLog()
    {
        $key = 'settle_fatal_error';
        $fatal = F($key);

        if (empty($fatal)) {
            return 0;
        }

        $worker_order_ids = array_keys($fatal);

        $opts = [
            'field' => 'worker_order_status,id',
        ];

        $model = BaseModel::getInstance('worker_order');

        do {
            $sub_worker_order_ids = array_splice($worker_order_ids, 0, self::SETTLE_LIMIT);
            if (empty($sub_worker_order_ids)) {
                break;
            }

            $opts['where'] = ['id' => ['in', $sub_worker_order_ids]];

            $orders = $model->getList($opts);

            foreach ($orders as $order) {
                $worker_order_status = $order['worker_order_status'];
                $worker_order_id = $order['id'];

                if (OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED == $worker_order_status) {
                    unset($fatal[$worker_order_id]);
                }
            }

        } while (true);

        F($key, $fatal);

        return 0;

    }

    protected function getFactorySetting()
    {
        $model = BaseModel::getInstance('factory');
        $data = $model->getList([
            'field' => 'factory_id,auto_settlement_days',
        ]);

        $list = [];

        foreach ($data as $val) {
            $factory_id = $val['factory_id'];
            $auto_settlement_days = $val['auto_settlement_days'];

            $list[$auto_settlement_days][] = $factory_id;
        }

        return $list;
    }


    protected function collectWorkerOrderFee($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单id为空');
        }

        $data = [];
        $opts = [
            'where' => ['worker_order_id' => ['in', $worker_order_ids]],
        ];
        $list = BaseModel::getInstance('worker_order_fee')->getList($opts);

        foreach ($list as $key => $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        $this->setList(self::LIST_TYPE_ORDER_FEE, $data);
    }

    protected function collectFactory($factory_ids)
    {
        if (empty($factory_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '厂家ID为空');
        }

        $data = [];
        $opts = [
            'where' => ['factory_id' => ['in', $factory_ids]],
            'field' => 'factory_id,money,frozen_money,auto_settlement_days',
        ];
        $list = BaseModel::getInstance('factory')->getList($opts);

        foreach ($list as $key => $val) {
            $factory_id = $val['factory_id'];

            $data[$factory_id] = $val;
        }

        $this->setList(self::LIST_TYPE_FACTORY, $data);
    }

    protected function collectFactoryMoneyFrozen($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $data = [];
        $opts = [
            'where' => [
                'worker_order_id' => ['in', $worker_order_ids],
            ],
        ];
        $list = BaseModel::getInstance('factory_money_frozen')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        $this->setList(self::LIST_TYPE_FACTORY_MONEY_FROZEN, $data);
    }

    protected function collectReputation($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $data = [];
        $opts = [
            'where' => ['worker_order_id' => ['in', $worker_order_ids]],
        ];
        $list = BaseModel::getInstance('worker_order_reputation')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        $this->setList(self::LIST_TYPE_REPUTATION, $data);
    }

    protected function collectAccessory($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $data = [];
        $opts = [
            'where' => [
                'worker_order_id'  => ['in', $worker_order_ids],
                'is_giveup_return' => AccessoryService::RETURN_ACCESSORY_PASS,
            ],
            'order' => 'worker_return_time DESC',
        ];
        $list = BaseModel::getInstance('worker_order_apply_accessory')
            ->getList($opts);

        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id][] = $val;
        }

        $this->setList(self::LIST_TYPE_WORKER_ORDER_ACCESSORY, $data);
    }

    protected function collectAdminConf()
    {
        $c_arr = array_keys(C('WORKER_REPUTATION_CONFING_TIME'));
        $c_where = ['name' => ['in', implode(',', $c_arr)]];
        $config = BaseModel::getInstance('admin_config')->getList([
            'field' => 'name,value,type',
            'where' => $c_where,
            'index' => 'name',
        ]);

        $this->config = $config;
    }

    protected function collectWorkerOrderExtInfo($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $data = [];
        $opts = [
            'where' => ['worker_order_id' => ['in', $worker_order_ids]],
        ];
        $list = BaseModel::getInstance('worker_order_ext_info')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        $this->setList(self::LIST_TYPE_WORKER_ORDER_EXT_INFO, $data);
    }

    protected function getWorkerOrderCompleteReputation($order)
    {
        //获取参数
        $worker_order_id = $order['id'];

        $worker_reputation = $this->getListInfo(self::LIST_TYPE_REPUTATION, $worker_order_id);

        $accessory = $this->getListInfo(self::LIST_TYPE_WORKER_ORDER_ACCESSORY, $worker_order_id, true);
        $last_return_accessory = empty($accessory) ? [] : $accessory[0];
        $worker_return_time = empty($last_return_accessory) ? 0 : $last_return_accessory['worker_return_time'];

        $worker_receive_time = $order['worker_receive_time'];
        $worker_first_appoint_time = $order['worker_first_appoint_time'];
        $worker_first_sign_time = $order['worker_first_sign_time'];
        $factory_audit_time = NOW_TIME;

        // 快速预约 第一次预约时间 - 接单时间
        $first_appoint_min = timediff($worker_receive_time, $worker_first_appoint_time);
        // 快速上门时间差   第一次上门时间 - 接单时间
        $first_arrive_hour = timediff($worker_receive_time, $worker_first_sign_time, 'hour');
        // 按时上门   第一次上门时间 - 第一次预约上门时间
        $is_ontime_min = timediff($worker_first_appoint_time, $worker_first_sign_time);
        // 按时返件   完成时间 - 最后返件时间`
        $return_acce_hour = $worker_return_time > 0 ? timediff($worker_return_time, $factory_audit_time, 'hour') : 0;
        $time_comparison = [
            'appiont_time' => $first_appoint_min,
            'arrive_time'  => $first_arrive_hour,
            'ontime_time'  => $is_ontime_min,
            'return_time'  => $return_acce_hour,
        ]; // 技工当前工单每个环节使用时长

        $reputation_update = [
            'totals'      => 0,
            'is_complete' => 1,
            'is_return'   => 0,
        ];
        $evaluate_standards = $this->config; // 评价基准 - 获取评比的分数
        $evaluate_score = C('WORKER_REPUTATION_CONFING_TIME'); // 评价得分 - 获取评比的需要修改的字段名称
        foreach ($evaluate_standards as $graded => $standard) {
            $standard_value = $standard['value'];
            $score_time_field = $evaluate_score[$graded]['time_field'];

            if (isset($reputation_update[$score_time_field])) {
                continue;
            }
            $comparison = $time_comparison[$score_time_field];
            if ($comparison < $standard_value) {
                $score_score_field = $evaluate_score[$graded]['score_field'];

                $reputation_update[$score_time_field] = $comparison;
                $score = $evaluate_score[$graded]['score'];
                $reputation_update[$score_score_field] = $score;
                $reputation_update['totals'] += $score;
            }
        }

        // 服务码得分
        $service_evaluate_fraction = 0;
        $service_evaluate_score = ['A' => 10, 'B' => 5];
        $ext_info = $this->getListInfo(self::LIST_TYPE_WORKER_ORDER_EXT_INFO, $worker_order_id);
        if (isset($service_evaluate_score[$ext_info['service_evaluate']])) {
            $service_evaluate_fraction = $service_evaluate_score[$ext_info['service_evaluate']];
        }

        // 服务质量   服务码得分 + 回访服务得分 + 服务规范统计 + 维修质量统计(维修次数得分)
        $service_fraction = $service_evaluate_fraction +
            $worker_reputation['revcode_fraction'] +
            $worker_reputation['quality_standard_fraction'] +
            $worker_reputation['repair_nums_fraction'];

        //总分 = 快速预约得分 + 快速上门得分 + 按时上门得分 + 配件返还得分 + 服务质量分
        $reputation_update['totals'] += $service_fraction;

        return [
            'where'       => ['id' => $worker_reputation['id']],
            'update_data' => $reputation_update,
        ];
    }

    /**
     * 配件单需要返还的旧件在工单上传完成服务3天后，还未上传返件报告
     */
    public function promptWorkerUploadAccessoryReport()
    {
        //配件单需要返还的旧件在工单上传完成服务3天后，还未上传返件报告

        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');

        $last_id = 0;

        $days = 3;
        $deadline = strtotime(date('Ymd', NOW_TIME)) - $days * 86400;

        $valid_order_status = [OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE];
        $valid_order_status_str = implode(',', $valid_order_status);
        $where = [
            'is_giveup_return' => AccessoryService::RETURN_ACCESSORY_PASS,
            'accessory_status' => AccessoryService::STATUS_WORKER_TAKE,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
            'worker_order_id'  => ['exp', "in (select id from worker_order where worker_repair_time<{$deadline} and worker_order_status in ({$valid_order_status_str}) )"],
            'id'               => ['gt', &$last_id],
        ];

        $opts = [
            'field' => 'id,worker_id,worker_order_id,worker_order_product_id',
            'where' => $where,
            'order' => 'id',
            'limit' => self::SETTLE_LIMIT,
        ];

        do {
            $list = $accessory_model->getList($opts);

            if (empty($list)) {
                break;
            }

            $worker_order_ids = [];
            $worker_order_product_ids = [];
            $worker_ids = [];
            $accessory_ids = [];
            foreach ($list as $val) {
                $id = $val['id'];
                $worker_order_id = $val['worker_order_id'];
                $worker_order_product_id = $val['worker_order_product_id'];
                $worker_id = $val['worker_id'];

                $worker_order_ids[] = $worker_order_id;
                $worker_order_product_ids[] = $worker_order_product_id;
                $worker_ids[] = $worker_id;
                $accessory_ids[] = $id;
            }

            $this->collectWorker($worker_ids);
            $this->collectOrder($worker_order_ids);
            $this->collectOrderProduct($worker_order_product_ids);
            $this->collectOrderUserInfo($worker_order_ids);
            $this->collectAccessoryItem($accessory_ids);
            $this->collectMessageStats(self::PROMPT_WORKER_UPLOAD_ACCESSORY_REPORT);
            $update_stats_ids = [];
            $insert_stats = [];
            $data_type = self::PROMPT_WORKER_UPLOAD_ACCESSORY_REPORT;
            $sms_list = [];

            foreach ($list as $val) {
                $id = $val['id'];
                $worker_order_id = $val['worker_order_id'];
                $worker_order_product_id = $val['worker_order_product_id'];
                $worker_id = $val['worker_id'];

                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_name = $worker_info['nickname'];
                $phone = $worker_info['worker_telephone'];

                $worker_order_product_info = $this->getListInfo(self::LIST_TYPE_ORDER_PRODUCT, $worker_order_product_id);
                $brand = $worker_order_product_info['cp_product_brand_name'];
                $category = $worker_order_product_info['cp_category_name'];

                $worker_order_info = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id);
                $orno = $worker_order_info['orno'];
                $service_type_str = OrderService::getServiceType($worker_order_info['service_type']);

                $worker_order_user_info = $this->getListInfo(self::LIST_TYPE_ORDER_USER_INFO, $worker_order_id);
                $detail_address = $worker_order_user_info['address'];

                $accessory_item_info = $this->getListInfo(self::LIST_TYPE_ORDER_ACCESSORY_ITEM, $id);
                $accessory_name = $accessory_item_info['name'];

                $key = $data_type . '_' . $id;
                $message_stats = $this->getListInfo(self::LIST_TYPE_MESSAGE_STATS, $key, true);

                if (!empty($message_stats)) {
                    $times = $message_stats['times'];
                    $id = $message_stats['id'];

                    if ($times >= 1) {
                        continue;
                    }
                    $update_stats_ids[] = $id;
                } else {
                    $insert_stats[] = [
                        'data_id'   => $id,
                        'data_type' => $data_type,
                        'times'     => 1,
                    ];
                }

                $param = [
                    'workername'    => $worker_name,
                    'accessoryname' => $accessory_name,
                    'orno'          => $orno,
                    'detailaddress' => $detail_address,
                    'brand'         => $brand,
                    'category'      => $category,
                    'servicetype'   => $service_type_str,
                ];
                $sms_list[] = [
                    'phone' => $phone,
                    'param' => $param,
                ];
            }

            M()->startTrans();

            $message_stat_model = BaseModel::getInstance('message_statistic');
            if (!empty($insert_stats)) {
                $message_stat_model->insertAll($insert_stats);
            }
            if (!empty($update_stats_ids)) {
                $where = [
                    'id' => ['in', $update_stats_ids],
                ];
                $update_data = [
                    'times' => ['exp', 'times+1'],
                ];
                $message_stat_model->update($where, $update_data);
            }

            M()->commit();

            foreach ($sms_list as $sms) {
                $phone = $sms['phone'];
                $param = $sms['param'];

                sendSms($phone, SMSService::TMP_ORDER_ACCESSORY_PROMPT_WORKER_SEND_BACK, $param);
            }

            $last_id = end($worker_order_ids);
            $this->flush();

        } while (true);
    }

    protected function collectWorker($worker_ids)
    {
        if (empty($worker_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '技工ID为空');
        }

        $data = [];
        $opts = [
            'field' => 'nickname,worker_telephone,worker_id',
            'where' => ['worker_id' => ['in', $worker_ids]],
        ];
        $list = BaseModel::getInstance('worker')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $worker_id = $val['worker_id'];

            $data[$worker_id] = $val;
        }
        $this->setList(self::LIST_TYPE_WORKER, $data);
    }

    protected function collectOrderProduct($worker_order_product_ids)
    {
        if (empty($worker_order_product_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '产品ID为空');
        }

        $data = [];
        $opts = [
            'field' => 'cp_product_brand_name,id,cp_category_name',
            'where' => ['id' => ['in', $worker_order_product_ids]],
        ];
        $list = BaseModel::getInstance('worker_order_product')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $worker_order_product_id = $val['id'];

            $data[$worker_order_product_id] = $val;
        }

        $this->setList(self::LIST_TYPE_ORDER_PRODUCT, $data);
    }

    protected function collectOrderUserInfo($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $data = [];
        $opts = [
            'field' => 'worker_order_id,address',
            'where' => ['worker_order_id' => ['in', $worker_order_ids]],
        ];
        $list = BaseModel::getInstance('worker_order_user_info')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        $this->setList(self::LIST_TYPE_ORDER_USER_INFO, $data);
    }

    protected function collectOrder($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $data = [];
        $opts = [
            'field' => 'id,service_type,orno,worker_id,distributor_id,origin_type,add_id,factory_id',
            'where' => ['id' => ['in', $worker_order_ids]],
        ];
        $list = BaseModel::getInstance('worker_order')
            ->getList($opts);

        $worker_ids = [];

        foreach ($list as $key => $val) {
            $worker_id = $val['worker_id'];
            $worker_order_id = $val['id'];

            $data[$worker_order_id] = $val;
            $worker_ids[] = $worker_id;
        }

        $this->setList(self::LIST_TYPE_ORDER, $data);
    }

    protected function collectAccessoryItem($accessory_ids)
    {
        if (empty($accessory_ids)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '配件单ID为空');
        }

        $data = [];
        $opts = [
            'field' => 'accessory_order_id,name',
            'where' => ['accessory_order_id' => ['in', $accessory_ids]],
        ];
        $list = BaseModel::getInstance('worker_order_apply_accessory_item')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $accessory_order_id = $val['accessory_order_id'];

            $data[$accessory_order_id] = $val;
        }

        $this->setList(self::LIST_TYPE_ORDER_ACCESSORY_ITEM, $data);
    }

    protected function collectMessageStats($data_type)
    {
        if (empty($data_type)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '类型为空');
        }

        $data = [];
        $opts = [
            'field' => 'id,data_id,times',
            'where' => ['data_type' => $data_type],
        ];
        $list = BaseModel::getInstance('message_statistic')
            ->getList($opts);

        foreach ($list as $key => $val) {
            $data_id = $val['data_id'];

            $key = $data_type . '_' . $data_id;
            $data[$key] = $val;
        }

        $this->setList(self::LIST_TYPE_MESSAGE_STATS, $data);
    }

    /**
     * 明天需上门工单
     */
    public function promptWorkerAppointTomorrow()
    {
        $today = strtotime(date('Ymd'));
        $tomorrow_begin = $today + 86400;
        $tomorrow_end = $tomorrow_begin + 86400;

        $order_model = BaseModel::getInstance('worker_order');
        $opts = [
            'where' => [
                'worker_order_status' => ['in', [
                    OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
                    OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                    OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
                ]],
                'cancel_status'       => OrderService::CANCEL_TYPE_NULL,
            ],
            'field' => 'id',
        ];
        $orders = $order_model->getList($opts);

        if (empty($orders)) {
            return 0;
        }

        $worker_order_ids = array_column($orders, 'id');

        $appoint_model = BaseModel::getInstance('worker_order_appoint_record');

        $sub_query = $appoint_model->field('max(id)')
            ->where(['worker_order_id' => ['in', $worker_order_ids]])
            ->group('worker_order_id')->buildSql();

        $opts = [
            'field'  => 'id,worker_order_id,is_over,appoint_time,appoint_status,worker_id',
            'where'  => ['_string' => "id in ({$sub_query}) "],
            'group'  => 'worker_order_id',
            'having' => "is_over=0 and appoint_status in (1,2,5) and appoint_time>={$tomorrow_begin} and appoint_time<{$tomorrow_end}",
        ];
        $appoints = $appoint_model->getList($opts);
        if (empty($appoints)) {
            return 0;
        }

        $worker_order_ids = [];
        $worker_ids = [];
        $worker_cnt = [];

        foreach ($appoints as $appoint) {
            $worker_order_id = $appoint['worker_order_id'];
            $worker_id = $appoint['worker_id'];

            $worker_order_ids[] = $worker_order_id;
            $worker_ids[] = $worker_id;

            $worker_cnt[$worker_id][] = $worker_order_id;
        }

        $this->collectOrder($worker_order_ids);
        $this->collectWorker($worker_ids);

        foreach ($worker_cnt as $worker_id => $worker_order_ids) {

            $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id, true);
            if (empty($worker_info)) {
                continue;
            }

            $cnt = count($worker_order_ids);
            $worker_order_id = $worker_order_ids[0];

            event(new OrderSendNotificationEvent([
                'type'    => AppMessageService::TYPE_APPOINT_MASSAGE,
                'data_id' => $worker_order_id,
                'num'     => $cnt,
            ]));

        }

        return 0;
    }

    /**
     * 已到厂家预估发货时间，但厂家还未发件
     */
    public function promptFactoryAccessoryConfirmSend()
    {
        $last_id = 0;

        $model = BaseModel::getInstance('worker_order_apply_accessory');
        $where = [
            'factory_estimate_time' => ['lt', NOW_TIME],
            'accessory_status'      => AccessoryService::STATUS_FACTORY_CHECKED,
            'cancel_status'         => AccessoryService::CANCEL_STATUS_NORMAL,
            'id'                    => ['gt', &$last_id],
        ];
        $opts = [
            'field' => 'worker_order_id,id',
            'where' => $where,
            'order' => 'id',
            'limit' => self::SETTLE_LIMIT,
        ];

        do {

            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $worker_order_ids = array_column($list, 'worker_order_id');

            $this->collectOrder($worker_order_ids);
            $data_type = self::PROMPT_FACTORY_ACCESSORY_CONFIRM_SEND;
            $this->collectMessageStats($data_type);
            $update_stats_ids = [];
            $insert_stats = [];

            $sys_factory_opts = [];
            $sys_factory_admin_opts = [];
            $sys_admin_opts = [];

            foreach ($list as $val) {
                $id = $val['id'];
                $worker_order_id = $val['worker_order_id'];

                $order = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id, true);
                if (empty($order)) {
                    continue;
                }

                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                $key = $data_type . '_' . $id;
                $message_stats = $this->getListInfo(self::LIST_TYPE_MESSAGE_STATS, $key, true);

                if (!empty($message_stats)) {
                    $times = $message_stats['times'];
                    $stats_id = $message_stats['id'];

                    if ($times >= self::PROMPT_LIMIT) {
                        continue;
                    }
                    $update_stats_ids[] = $stats_id;
                } else {
                    $insert_stats[] = [
                        'data_id'   => $id,
                        'data_type' => $data_type,
                        'times'     => 1,
                    ];
                }

                $order = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id);
                $distributor_id = $order['distributor_id'];
                $orno = $order['orno'];

                $content = "工单号{$orno}的配件没按时发件";
                $sys_admin_opts[] = ['receiver_id' => $distributor_id, 'data_id' => $id, 'content' => $content];

                $content = "工单号{$orno}的配件，已到预估发件时间，请发件";
                if (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
                    $sys_factory_opts[] = ['receiver_id' => $add_id, 'data_id' => $id, 'content' => $content];
                } elseif (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $sys_factory_admin_opts[] = ['receiver_id' => $add_id, 'data_id' => $id, 'content' => $content];
                }
            }

            $last_id = end($worker_order_ids);

            M()->startTrans();

            $message_stat_model = BaseModel::getInstance('message_statistic');
            if (!empty($insert_stats)) {
                $message_stat_model->insertAll($insert_stats);
            }
            if (!empty($update_stats_ids)) {
                $where = [
                    'id' => ['in', $update_stats_ids],
                ];
                $update_data = [
                    'times' => ['exp', 'times+1'],
                ];
                $message_stat_model->update($where, $update_data);
            }

            if (!empty($sys_admin_opts)) {
                //客服
                SystemMessageService::createMany(SystemMessageService::USER_TYPE_ADMIN, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_DELAY, $sys_admin_opts);
            }

            if (!empty($sys_factory_opts)) {
                //厂家系统消息
                SystemMessageService::createMany(SystemMessageService::USER_TYPE_FACTORY, SystemMessageService::MSG_TYPE_FACTORY_ACCESSORY_SYSTEM_FOUND_NOT_SEND, $sys_factory_opts);
            }

            if (!empty($sys_factory_admin_opts)) {
                //厂家子账号系统消息
                SystemMessageService::createMany(SystemMessageService::USER_TYPE_FACTORY_ADMIN, SystemMessageService::MSG_TYPE_FACTORY_ACCESSORY_SYSTEM_FOUND_NOT_SEND, $sys_factory_opts);
            }

            M()->commit();

        } while (true);

    }

    /**
     * 工单完结7天后，配件还未返还
     */
    public function promptWorkerAccessorySendBack()
    {
        $last_id = 0;

        //这个查询不能把子查询单独弄出来查,因为子查询单独查的数据量会越来越多(工单完结量随着时间递增)
        $model = BaseModel::getInstance('worker_order_apply_accessory');
        $deadline = strtotime(date('Ymd')) - 7 * 86400;
        $where = [
            'accessory_status' => AccessoryService::STATUS_FACTORY_SENT,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
            'is_giveup_return' => AccessoryService::RETURN_ACCESSORY_PASS,
            'id'               => ['gt', &$last_id],
            'worker_order_id'  => ['exp', "in (select id from worker_order where factory_audit_time<{$deadline} and worker_order_status=" . OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED . ")"],
        ];
        $opts = [
            'field' => 'worker_order_id,id',
            'where' => $where,
            'order' => 'id',
            'limit' => self::SETTLE_LIMIT,
        ];

        do {

            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $worker_order_ids = array_column($list, 'worker_order_id');

            $this->collectOrder($worker_order_ids);
            $data_type = self::PROMPT_WORKER_ACCESSORY_SEND_BACK;
            $this->collectMessageStats($data_type);
            $update_stats_ids = [];
            $insert_stats = [];

            $sys_opts = [];

            foreach ($list as $val) {
                $id = $val['id'];
                $worker_order_id = $val['worker_order_id'];

                $key = $data_type . '_' . $worker_order_id;
                $message_stats = $this->getListInfo(self::LIST_TYPE_MESSAGE_STATS, $key, true);

                if (!empty($message_stats)) {
                    $times = $message_stats['times'];
                    $stats_id = $message_stats['id'];

                    if ($times >= self::PROMPT_LIMIT) {
                        continue;
                    }
                    $update_stats_ids[] = $stats_id;
                } else {
                    $insert_stats[] = [
                        'data_id'   => $worker_order_id,
                        'data_type' => $data_type,
                        'times'     => 1,
                    ];
                }

                $order = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id, true);
                if (empty($order)) {
                    continue;
                }

                $distributor_id = $order['distributor_id'];
                $orno = $order['orno'];

                $content = "工单号{$orno}的配件，维修商没按时返还";
                $sys_opts[] = ['receiver_id' => $distributor_id, 'data_id' => $id, 'content' => $content];

            }

            M()->startTrans();
            $message_stat_model = BaseModel::getInstance('message_statistic');
            if (!empty($insert_stats)) {
                $message_stat_model->insertAll($insert_stats);
            }
            if (!empty($update_stats_ids)) {
                $where = [
                    'id' => ['in', $update_stats_ids],
                ];
                $update_data = [
                    'times' => ['exp', 'times+1'],
                ];
                $message_stat_model->update($where, $update_data);
            }

            //系统消息
            if (!empty($sys_opts)) {
                SystemMessageService::createMany(SystemMessageService::USER_TYPE_ADMIN, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_SYSTEM_FOUND_NOT_BACK, $sys_opts);
            }

            M()->commit();

            $last_id = end($worker_order_ids);

        } while (true);

    }

    /**
     * 到了预约的时间4小时，师傅还未提交服务报告/签到记录
     */
    public function promptWorkerUploadReport()
    {
        $order_model = BaseModel::getInstance('worker_order');
        $opts = [
            'where' => [
                'worker_order_status' => ['in', [
                    OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
                    OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                    OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
                ]],
                'cancel_status'       => OrderService::CANCEL_TYPE_NULL,
            ],
            'field' => 'id',
        ];
        $orders = $order_model->getList($opts);

        if (empty($orders)) {
            return 0;
        }

        $worker_order_ids = array_column($orders, 'id');

        $appoint_model = BaseModel::getInstance('worker_order_appoint_record');

        $sub_query = $appoint_model->field('id,worker_order_id,is_over,appoint_time,appoint_status,worker_id')
            ->where(['worker_order_id' => ['in', $worker_order_ids]])
            ->order('worker_order_id, create_time desc')->buildSql();

        $deadline = NOW_TIME - 4 * 3600;
        $opts = [
            'table'  => $sub_query . ' as tmp',
            'group'  => 'worker_order_id',
            'having' => "is_over=0 and appoint_time<{$deadline}",
        ];
        $appoints = $appoint_model->getList($opts);

        $worker_order_ids = array_column($appoints, 'worker_order_id');

        $this->collectOrder($worker_order_ids);
        $data_type = self::PROMPT_WORKER_UPLOAD_REPORT;
        $this->collectMessageStats($data_type);
        $update_stats_ids = [];
        $insert_stats = [];
        $sys_opts = [];

        foreach ($appoints as $val) {
            $id = $val['id'];
            $worker_order_id = $val['worker_order_id'];

            $key = $data_type . '_' . $id;
            $message_stats = $this->getListInfo(self::LIST_TYPE_MESSAGE_STATS, $key, true);

            if (!empty($message_stats)) {
                $times = $message_stats['times'];
                $stats_id = $message_stats['id'];

                if ($times >= self::PROMPT_LIMIT) {
                    continue;
                }
                $update_stats_ids[] = $stats_id;
            } else {
                $insert_stats[] = [
                    'data_id'   => $worker_order_id,
                    'data_type' => $data_type,
                    'times'     => 1,
                ];
            }

            $order = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id, true);
            if (empty($order)) {
                continue;
            }
            $distributor_id = $order['distributor_id'];
            $orno = $order['orno'];

            $content = "工单号{$orno}，师傅未按时上门";
            $sys_opts[] = ['receiver_id' => $distributor_id, 'data_id' => $worker_order_id, 'content' => $content];
        }

        $message_stats_model = BaseModel::getInstance('message_statistic');
        if (!empty($insert_stats)) {
            $message_stats_model->insertAll($insert_stats);
        }
        if (!empty($update_stats_ids)) {
            $where = [
                'id' => ['in', $update_stats_ids],
            ];
            $update_data = [
                'times' => ['exp', 'times+1'],
            ];
            $message_stats_model->update($where, $update_data);
        }

        //系统消息
        if (!empty($sys_opts)) {
            SystemMessageService::createMany(SystemMessageService::USER_TYPE_ADMIN, SystemMessageService::MSG_TYPE_ADMIN_ORDER_SYSTEM_FOUND_NOT_SIGN_IN, $sys_opts);
        }

        return 0;
    }
}