<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/09/30
 * PM 14:34
 */
namespace Script\Logic;

use Script\Logic\BaseLogic;
use Script\Model\BaseModel;
use Script\Common\ErrorCode;

class DbLogic extends BaseLogic
{
	protected $rule;
	const WORKER_ORDER_P_STRING = 'worker_order_id';

	public function sqlDataTransfer($key = 'all')
	{
		// $transfers = $key == 'all' ? C('FUNCTION_CONFIG_MODEL') : array_intersect_key(C('FUNCTION_CONFIG_MODEL'), array_flip(explode(',', $key)));
		$return = $function_key = [];
        $sync_time = (array)F(C('SYNC_TIME'), '', C('SYNC_LOG_PATH'));
        $logics = $times = [];
		foreach (C('DATA_TRANSFER_CONFIG') as $k => $v) {
			foreach ($v as $key => $value) {
				$times[$k] = time();
				$function_key[$k] = (array)$function_key[$k];
				
				$rule = $this->isDbStructure($value);
	        	$cp = $rule['rule_logic'];
				
				$logics[$value] = $logic = new $cp($rule);
				$logic->syncTimeCheckAndUpdateWhere($function_key[$k]);
			}
		}

        $function_key = array_filter($function_key);

        M()->startTrans();
		foreach ($function_key as $k => $v) {
			foreach (C('DATA_TRANSFER_CONFIG')[$k] as $table) {
				$time = $times[$k] ?? NOW_TIME;
				$logic = $logics[$table];
				$data = $logic->sqlDataTransfer($v);
				//  更新旧库监控时间
	            // $this->updateSyncTime($table, $time);
			}
		}

		M()->commit();
		return $return;
	}

	public function getSqlDataTransfer()
	{
		$key = I('get.key', '');
		$result = [];
		foreach (explode(',', $key) as $k => $v) {
			$result[$v] = (array)F(C('DATA_REANSFER_F_KEY').$v, '', C('DATA_REANSFER_F_PATH'));
		}
		return $result;
	}

	// ==================================== Model实例化 =====================================================

	protected function setVarThisDbModel()
	{
		$cp = $this->rule['rule_model'];
		return new $cp($this->rule['db_name']);
	}

	protected function setVarCheckModel()
	{
		$cp = $this->rule['rule_model'];
		return new $cp($this->rule['ch_name'], '', C('DB_CONFIG_OLD_V3'));
	}

	protected function setOrderAtModel($table = '', $is_old = true)
	{
	    $conf = $is_old? C('DB_CONFIG_OLD_V3'): '';
		return new \Script\Model\BaseModel($table, '', $conf);
	}

	//=================================结构相关===============================================
	public function dbStructures($key = 'all')
	{
		$structures = $key == 'all' ? C('STRUCTURES_CONFIG_V3') : array_intersect_key(C('STRUCTURES_CONFIG_V3'), array_flip(explode(',', $key)));
		$return = [];

		foreach ($structures as $k => $v) {
			$this->rule[$k] = $rule = $this->isDbStructure($k, $v);
			$logic = new $rule['rule_logic']($rule);
			$logic->structureResult($k);
		}

		foreach ($this->rule as $k => $v) {
			$time = time();
			$logic = new $v['rule_logic']($v);
			list($result, $arr) = $logic->sqlDataTransfer([]);
			// 更新旧库 sync_time 每次成功保存一次，方便查询进度
			// $this->updateSyncTime($k, $time);
		}

		return $return;
	}

	public function updateSyncTime($key, $update_time, $where = [])
	{
		$sync_time = F(C('SYNC_TIME'), '', C('SYNC_LOG_PATH'));
        $sync_time = empty($sync_time)? []: $sync_time;
		$return = [];
		$time = $this->syncTimeWhere($update_time);
		foreach (C('FUNCTION_CONFIG_MODEL')[$key] as $value) {
			$model = M($value, '', C('DB_CONFIG_OLD_V3'));
			$return[$value] = $model->where($where)->save([C('SYNC_TIME') => $time]);
			$sync_time[$value] = $update_time;
		}	
		
		$sync_time = F(C('SYNC_TIME'), $sync_time, C('SYNC_LOG_PATH'));
		return $return;
	}

	public function isDbStructure($key = '')
	{
		$config = C('STRUCTURES_CONFIG_V3')[$key];
		$return = [];
		$return['db_name'] = $config['name'];
		$return['ch_name'] = $key;
		$return['other'] = $config['other'];
		$return['rule_logic'] = $config['logic'] ? '\Script\Logic\\'.ucfirst($config['logic']).'Logic' : '';
        $return['rule_model'] = $config['model'] ? '\Script\Model\\'.ucfirst($config['model']).'Model' : '';
		$return['is_db_name'] = $this->tableShowStatus($config['name']);
		$return['is_ch_name'] = $this->tableShowStatus($key, true);
		return $return;
	}

	public function deleteTable($table = '', $is_other_db = false)
	{
		$return = [];
		if (!empty($table)) {

			if (!$this->tableShowStatus($table)) {
				$return['sql'] = "DROP TABLE BUT NOT TABLE：{$table}";
				$return['last_status'] = false;
				return $return;
			}

			$sql = 'DROP TABLE IF EXISTS '.$table.';';
			$return['sql'] = $this->sqlRunEnd($sql, $is_other_db);
            $return['last_status'] = $this->tableShowStatus($table, $is_other_db);
		}
		return $return;
	}

	public function tableShowStatus($name = '', $is_other_db = false)
	{
		$model = $is_other_db ? M('', '', C('DB_CONFIG_OLD_V3')) : M();
		return $model->query("SHOW TABLES LIKE '{$name}'") ? true : false;
	}

	public function sqlRunEnd($sql = '', $is_other_db = false)
	{
		$model = $is_other_db ? M('', '', C('DB_CONFIG_OLD_V3')) : M();
		if (!empty($sql)) {
			try {
				$arr = [
					'sql' => $sql,
					'result' => $model->execute($sql),
					'time' => time(),
				];
			} catch (\Exception $e) {
				$arr = [
					'sql' => $sql,
					'result' => 'Error',
					'time' => time(),
				];	
			}
			return $arr;
		}
		return null;
	}

	// 非必需
	protected function arrFieldForStr($arr = [], $field = '', $replace_arr = [], $implode = ',', $sande = false)
	{
		$return_arr = [];
	    foreach ($arr as $key => $value) {
	        $k = $value[$field];
	        $k = isset($replace_arr[$k]) ? $replace_arr[$k] : $k;
	        $return_arr[$k] = $k;
	    }
	    return  $sande?
	            ($return = implode($implode, array_filter($return_arr))) ? $implode.$return.$implode : '' :
	            implode($implode, array_filter($return_arr));	
	}

	protected function tableFieldStatus($table, $key, $model_config_field = '')
	{
		if (!$table || !$this->tableShowStatus($table)) {
			return [];
		}
		$model  = M($table, '', C("{$model_config_field}"));
		$data = array_keys($model->find());
		if (!is_array($key)) {
			$key = array_filter(explode(',', $key));
		}
		$in_table = array_intersect($data, $key);
		$return = [];
		foreach ($key as $check) {
			in_array($check, $in_table) && $return[$true] = true;
		};
		return $return;
	}

	protected function syncTimeWhere($int = 0, $key = '')
	{
		if (!$key) {
			return date('Y-m-d H:i:s', $int);
		}

		$sync_time = (array)F(C('SYNC_TIME'), '', C('SYNC_LOG_PATH'));
		if ($key && $sync_time[$key]) {
			return date('Y-m-d H:i:s', $sync_time[$key]);
		}

		return date('Y-m-d H:i:s', NOW_TIME);
	}

	public function deleteDbStructuresSyncTime($key)
	{
		if (!is_array($key)) {
			$key = array_filter(explode(',', $key));
		}
		$field = C('SYNC_TIME');
		$result = [];
		foreach ($key as $table) {
			$check_result = $this->tableFieldStatus($table, $field, 'DB_CONFIG_OLD_V3');
			if ($check_result[$field]) {
				$sql = "ALTER TABLE `{$table}` DROP `{$field}`;";
				$result[$table] = $model->execute($sql);
			}
		}
		return $result;
	}

	public function addDbStructuresSyncTime($key = '')
	{
		$return = [];
		$time_name = C('SYNC_TIME');
		$model = M('', '', C('DB_CONFIG_OLD_V3'));
		$sync_time = (array)F(C('SYNC_TIME'), '', C('SYNC_LOG_PATH'));
		foreach (array_filter(explode(',', $key)) as $table) {
			if ($this->tableShowStatus($table, true)) {
				$model->execute("ALTER TABLE `{$table}` DROP `{$time_name}`");
				$sql = "ALTER TABLE `{$table}` ADD `{$time_name}` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
				$result[$table][] = $model->execute($sql);
				$model->execute("ALTER TABLE `{$table}` ADD INDEX (`{$time_name}`)");
				// $model->where(1)->save([$time_name => time()]);
				$sync_time[$table] = NOW_TIME;
			}
		}
		$sync_time = F(C('SYNC_TIME'), $sync_time, C('SYNC_LOG_PATH'));
		return $return;
	}

}
