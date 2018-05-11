<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/09/30
 * PM 12:42
 */

namespace Script\Controller;

use Script\Model\BaseModel;

class CommonController extends BaseController {

    public function workerFactoryMoneyExcel()
    {
        ini_set('memory_limit', '500m');
        $index = 0;
        $logic = new \Common\Common\Logic\ExportDataLogic();
        $logic->objPHPExcel->createSheet($index);
        $logic->objPHPExcel->setActiveSheetIndex($index)->setTitle('厂家');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('A' . 1, '厂家id');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('B' . 1, '厂家名称');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('C' . 1, '厂家账号');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('D' . 1, '厂家当前余额');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('E' . 1, '厂家当前可下单余额');

        $list = BaseModel::getInstance('factory')->getList([
            'field' => 'factory_id,factory_full_name,linkphone,money,(money - frozen_money) as can_order_money',
        ]);
        $row = 3;
        foreach ($list as $v) {
            $column = 'A';
            $logic->objPHPExcel->setActiveSheetIndex($index)
                ->setCellValue($column++ . $row, $v['factory_id'])
                ->setCellValue($column++ . $row, $v['factory_full_name'])
                ->setCellValue($column++ . $row, $v['linkphone'])
                ->setCellValue($column++ . $row, $v['money'])
                ->setCellValue($column++ . $row, $v['can_order_money']);
            ++$row;
        }
        unset($list);

        $where = [];
//        $data_num = 20000;
        ++$index;
        $logic->objPHPExcel->createSheet($index);
        $logic->objPHPExcel->setActiveSheetIndex($index)->setTitle('技工');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('A' . 1, '技工id');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('B' . 1, '技工名称');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('C' . 1, '技工账号');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('D' . 1, '技工当前钱包余额');
        $logic->objPHPExcel->setActiveSheetIndex($index)->setCellValue('E' . 1, '技工当前质保金余额');
        $wlist = BaseModel::getInstance('worker')->getList([
            'field' => 'worker_id,nickname,worker_telephone,money,quality_money',
//            'limit' => $data_num,
            'where' => $where,
            'order' => 'worker_id asc',
        ]);
        $row = 3;
        foreach ($wlist as $v) {
            $column = 'A';
            $logic->objPHPExcel->setActiveSheetIndex($index)
                ->setCellValue($column++ . $row, $v['worker_id'])
                ->setCellValue($column++ . $row, $v['nickname'])
                ->setCellValue($column++ . $row, $v['worker_telephone'])
                ->setCellValue($column++ . $row, $v['money'])
                ->setCellValue($column++ . $row, $v['quality_money']);
            ++$row;
        }
        $end = end($wlist);
        $where['_string'] = ' worker_id > '.$end['worker_id'];
        unset($wlist);

//        do {
//
//            $wlist = BaseModel::getInstance('worker')->getList([
//                'field' => 'worker_id,nickname,worker_telephone,money,(quality_money_need - quality_money) as olny_quality_money',
//                'limit' => $data_num,
//                'where' => $where,
//                'order' => 'worker_id asc',
//            ]);
//            $row = 3;
//            foreach ($wlist as $v) {
//                $column = 'A';
//                $logic->objPHPExcel->setActiveSheetIndex($index)
//                    ->setCellValue($column++ . $row, $v['worker_id'])
//                    ->setCellValue($column++ . $row, $v['nickname'])
//                    ->setCellValue($column++ . $row, $v['worker_telephone'])
//                    ->setCellValue($column++ . $row, $v['money'])
//                    ->setCellValue($column++ . $row, $v['olny_quality_money']);
//                ++$row;
//            }
//            $end = end($wlist);
//            $where['_string'] = ' worker_id > '.$end['worker_id'];
//            unset($wlist);
//        } while ($end);
        // $file_name = iconv("UTF-8", "gbk", ($file_name ? $file_name : $start.'至'.$end));
        $logic->putOut('数据迁移后厂家与技工资金导出时间戳'.NOW_TIME);
    }

	public function sqldumpv3()
	{
		$DB_NAME = C('DB_NAME');
		$string = '';
		foreach (C('FUNCTION_CONFIG_MODEL') as $value) {
			if (is_array($value) || $value) {
				foreach ($value as $v) {
					$string .= " --ignore-table {$DB_NAME}.{$v} ";
				}
			} else {
				if (!$value) {
					continue;
					$string .= " --ignore-table {$DB_NAME}.{$value} ";
				}
			}
		}
		if (I('get.is_test')) {
			$string .= " --ignore-table {$DB_NAME}.wx_user ";
		}
		die($string);
	}

	public function addDbStructuresSyncTime()
	{
		// "ALTER TABLE `{$table}` ADD `{C('SYNC_TIME')}` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
		$key = I('get.key', '');
		if ($key == 'all') {
			$key = [];
			foreach (C('FUNCTION_CONFIG_MODEL') as $value) {
				$key = array_merge((array)$value, $key);
			}
			$key = implode(',', array_unique(array_filter($key)));
		}
		try {
			$logic = new \Script\Logic\DbLogic();
			$result = $logic->addDbStructuresSyncTime($key);
			$this->response($result);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function deleteDbStructuresSyncTime()
	{
		// "ALTER TABLE `{$table}` DROP `{C('SYNC_TIME')}`";
		$key = I('get.key', '');
		try {
			$logic = new \Script\Logic\DbLogic();
			$result = $logic->deleteDbStructuresSyncTime($key);
			$this->response($result);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}

	}
    
	public function dbStructures()
	{
		try {
			$result = (new \Script\Logic\DbLogic())->dbStructures(I('get.key'));
            $this->response($result);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function sqlDataTransfer()
	{
		try {
			$result = (new \Script\Logic\DbLogic())->sqlDataTransfer(I('get.key'));
            $this->response($result);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function getSqlDataTransfer()
	{
		try {
			$result = (new \Script\Logic\DbLogic())->getSqlDataTransfer();
            $this->response($result);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function resetSqlDataTransfer()
	{
		$key = I('get.key', '');
		$result = [];
		try {
			$f_data = (array)F(C('STRUCTURES_F_KEY'), '', C('STRUCTURES_F_PATH'));
			if ($key === 'all') {
				foreach ($f_data as $k => $v) {
					$logic = new $v['structure']['rule_logic']();
					$result[$k] = $logic->resetSqlDataTransfer($k);
				}
			} elseif (isset($f_data[$key])) {
				$v = $f_data[$key];
				$logic = new $v['structure']['rule_logic']();
				$result = $logic->resetSqlDataTransfer($key);
			}
            $this->response($result);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

}
