<?php
/**
 * File: WorkerMoneyController.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/22
 */

namespace Script\Controller;


use Common\Common\Service\WorkerMoneyRecordService;
use Script\Model\BaseModel;

class WorkerMoneyController extends BaseController
{

    const QUERY_LIMIT = 2500;

    public function checkWorker()
    {
        $last_id = 0;

        $begin = time();

        $bug_worker_ids = $this->getBugWorkerIds();

        $opts = [
            'field' => 'worker_id,money',
            'where' => [
                'worker_id' => [
                    ['gt', &$last_id],
                    ['in', $bug_worker_ids],
                ],
            ],
            'order' => 'worker_id',
            'limit' => self::QUERY_LIMIT,
        ];

        $worker_model = BaseModel::getInstance('worker');

        $error_worker_ids = [];

        do {
            $workers = $worker_model->getList($opts);

            if (empty($workers)) {
                break;
            }

            $worker_ids = [];
            foreach ($workers as $worker) {
                $worker_id = $worker['worker_id'];

                $worker_ids[] = $worker_id;
            }

            $worker_money_record = $this->getWorkerMoneyRecord($worker_ids);
            $worker_money_sum = $this->getWorkerMoneySum($worker_ids);

            foreach ($workers as $worker) {
                $worker_id = $worker['worker_id'];
                $money = $worker['money'];

                $records = $worker_money_record[$worker_id]?? [];
                $sum_info = $worker_money_sum[$worker_id]?? 0;

                $sum = $sum_info['sum'];

                if ($sum != $money) {
                    $error_worker_ids[] = $worker_id;
                    continue;
                }

                if (!self::checkMoneyRecord($records)) {
                    $error_worker_ids[] = $worker_id;
                    continue;
                }
            }

            $last_id = end($worker_ids);

        } while (true);

        $worker_money_record = $this->getWorkerMoneyRecord($error_worker_ids);

        M()->startTrans();

        $money_model = BaseModel::getInstance('worker_money_record');
        $adjust_model = BaseModel::getInstance('worker_money_adjust_record');
        foreach ($error_worker_ids as $worker_id) {
            $records = $worker_money_record[$worker_id]?? [];

            $cur_money = 0; // 技工修正余额

            foreach ($records as $record) {
                $id = $record['id'];
                $record_money = $record['money'];
                $record_last_money = $record['last_money'];
                $type = $record['type'];
                $data_id = $record['data_id'];

                $cur_money = round($cur_money + $record_money, 2, PHP_ROUND_HALF_UP);
                if ($cur_money != $record_last_money) {
                    $money_model->update($id, [
                        'last_money' => $cur_money,
                    ]);

                    //奖惩修复
                    if (WorkerMoneyRecordService::TYPE_REWARD_AND_PUNISH == $type) {
                        $adjust_model->update($data_id, [
                            'worker_last_money' => $cur_money,
                        ]);
                    }
                }
            }

            $worker_model->update($worker_id, [
                'money' => $cur_money
            ]);

        }

        M()->commit();

        var_dump(implode(',', $error_worker_ids));

        $end = time();

        date_default_timezone_set('GMT');
        echo date('i:s', $end - $begin);
    }

    protected function getBugWorkerIds()
    {
        $iteration_time = 1515493800;
        $opts = [
            'field' => 'distinct worker_id',
            'where' => [
                'type'        => WorkerMoneyRecordService::TYPE_REWARD_AND_PUNISH,
                'create_time' => ['gt', $iteration_time],
            ],
        ];

        $model = BaseModel::getInstance('worker_money_record');
        $list = $model->getList($opts);

        if (empty($list)) {
            return '-1';
        }

        return array_column($list, 'worker_id');
    }

    protected static function checkMoneyRecord(&$records)
    {
        $cur_sum = 0;
        foreach ($records as $record) {
            $id = $record['id'];
            $worker_id = $record['worker_id'];
            $record_money = $record['money'];
            $record_last_money = $record['last_money'];

            $cur_sum = round($cur_sum + $record_money, 2, PHP_ROUND_HALF_UP);
            if ($cur_sum != $record_last_money) {
                //var_dump($worker_id, $id, $cur_sum, $record_last_money);
                //echo '<br>';

                return false;
            }
        }

        return true;
    }

    protected function getWorkerMoneyRecord($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('worker_money_record');

        $opts = [
            'where' => [
                'worker_id' => ['in', $worker_ids],
            ],
            'order' => 'worker_id,create_time',
        ];

        $list = $model->getList($opts);

        $data = [];

        foreach ($list as $val) {
            $worker_id = $val['worker_id'];

            $data[$worker_id][] = $val;
        }

        return $data;
    }

    protected function getWorkerMoneySum($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('worker_money_record');

        $opts = [
            'field' => 'sum(money) as sum,worker_id',
            'where' => [
                'worker_id' => ['in', $worker_ids],
            ],
            'group' => 'worker_id',
        ];

        $list = $model->getList($opts);

        $data = [];

        foreach ($list as $val) {
            $worker_id = $val['worker_id'];

            $data[$worker_id] = $val;
        }

        return $data;
    }


}