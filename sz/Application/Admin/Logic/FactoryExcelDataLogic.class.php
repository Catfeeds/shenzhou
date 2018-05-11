<?php
/**
 * File: FactoryExcelDataLogic.class.php
 * User: zjz
 * Date: 2017/7/12
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;

class FactoryExcelDataLogic extends BaseLogic
{

	public $nums = 0;

	public function yimaApplyAndExcel()
	{
		// BaseModel::getInstance('factory_excel')->update(['_string' => 'check_time is null'], ['is_check' => 1,'check_time' => ['exp', '`add_time`']]);
		$factory_product_qrcode = BaseModel::getInstance('factory_product_qrcode');

		$list = $factory_product_qrcode->getList([
				// 'where' => ['factory_id' => 858],
				'field' => 'id,product_id,factory_id,qr_first_int,qr_last_int',
				// 'order' => 'id DESC',
			]);

		var_dump(count($list));

		$i = 0;
		$factory_index = [];
		foreach ($list as $k => $v) {
			// if (!isset($factory_index[$v['factory_id']])) {
			// 	$factory_index[$v['factory_id']] =   BaseModel::getInstance('factory')->getOne($v['factory_id'], 'factory_type,code');
			// }
			// $factory = $factory_index[$v['factory_id']];
			$update =[];

			$where = [
				'factory_id' => $v['factory_id'],
				'water' => ['BETWEEN', $v['qr_first_int'].','.$v['qr_last_int']],
			];

			// if ($v['product_id']) {
			// 	BaseModel::getInstance('factory_excel_datas_0')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_1')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_2')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_3')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_4')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_5')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_6')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_7')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_8')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_9')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_a')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_b')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_c')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_d')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_e')->update($where, ['product_id' => $v['product_id']]);
			// 	BaseModel::getInstance('factory_excel_datas_f')->update($where, ['product_id' => $v['product_id']]);	
			// }

			// $field = 'count(id) as nums,concat_ws("_",`shengchan_time`,`chuchang_time`,`zhibao_time`) as value';
			// $da = BaseModel::getInstance('factory_excel_datas_z')->getList([
			// 		'where' => $where,
			// 		'group' => 'value',
			// 		'field' => $field,
			// 	]);
			$sql = M()->field('id,shengchan_time , chuchuang_time, zhibao_time')
				->table('factory_excel_datas_0')
				->union([
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_1 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_2 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_3 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_4 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_5 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_6 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_7 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_8 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_9 WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_a WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_b WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_c WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_d WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_e WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
					"SELECT id,shengchan_time , chuchuang_time, zhibao_time FROM factory_excel_datas_f WHERE `factory_id` = {$v['factory_id']} AND `water` BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']}",
				], true)
				->fetchSql(true)
				->where(['factory_id' => $v['factory_id'],'water' => ['BETWEEN', $v['qr_first_int'].','.$v['qr_last_int']],])
				->select();

       		$da = M()->query("SELECT count(id) as nums, concat_ws('_',`shengchan_time`,`chuchuang_time`,`zhibao_time`) as value FROM ( {$sql} ) C GROUP BY value ");
			
			$index = [];
			foreach ($da as $key => $value) {
				$index[$value['value']] = $value['nums'];
			}

			arsort($index);
			$update_data = explode('_', reset(array_flip($index)));
			
			if ($update_data[0] && $update_data[0] != $v['shengchan_time']) {
				$update['shengchan_time'] = $update_data[0];
			}

			if ($update_data[1] && $update_data[1] != $v['chuchang_time']) {
				$update['chuchang_time'] = $update_data[1];
			}

			if ($update_data[2] != $v['zhibao_time']) {
				$update['zhibao_time'] = $update_data[2];
			}
			
			$data = BaseModel::getInstance('factory_excel')->getOne([
					'factory_id' => $v['factory_id'],
					'first_code' => ['ELT', $v['qr_first_int']],
					'last_code' => ['EGT', $v['qr_last_int']],
				], 'excel_id');

			if ($data) {
				$update['factory_excel_id'] = $data['excel_id'];
			}

			if (count($update)) {
				$factory_product_qrcode->update($v['id'], $update);
				// var_dump(M()->_sql());
				var_dump($v['id']);
			}

		}
		// php /Users/seanzen/Desktop/3NCTO/Shenzhou-Server/test.php common/yimaApplyAndExcel/co/cocococo/api_url_secret/0A1B0c2D0e3F0G
	}

	// public function factoryExcelToYima($name = 'factory_excel_datas_0', $name_key = '')
	// {
	// 	if (empty($name)) {
	// 		return;
	// 	}

	// 	$model = BaseModel::getInstance($name);
	// 	// 4984579
	// 	$count = $model->getNum();
	// 	$limit = 10000;
	// 	$i = ceil($count/$limit);
	// 	$start = 1;
	// 	$field = '*';

	// 	do {
	// 		$list = $model->getList(['field' => $field,'limit' => getPage($start, $limit)]);

	// 		$yima_model = null;
	// 		foreach ($list as $k => $v) {

	// 			if (!$v['factory_id']) {
	// 				continue;
	// 			}

	// 			$qr_data = BaseModel::getInstance('factory_product_qrcode')->getOne([
	// 					'factory_id' => $v['factory_id'],
	// 					'qr_first_int' => ['ELT', $v['water']],
	// 					'qr_last_int' => ['EGT', $v['water']],
	// 				]);

	// 			if (!$qr_data['id']) {
	// 				continue;
	// 			}

	// 			$old_new_model = BaseModel::getInstance('old_yima_code_index_'.str_replace('factory_excel_datas_', '', $name));
	// 			$yima_model = BaseModel::getInstance(factoryIdToModelName($v['factory_id']));

	// 			$old_new_model->insert([
	// 					'code' => $v['code'],
	// 					'md5code' => $v['md5code'],
	// 					'url' => $v['url'],
	// 				]);


	// 			if ($v['saomiao'] || $v['active_time'] || $v['register_time'] || intval($v['shengchan_time']) != intval($qr_data['shengchan_time']) || intval($v['chuchang_time']) != intval($qr_data['chuchang_time']) || intval($v['zhibao_time']) != intval($qr_data['zhibao_time'])) {

	// 				$yima_model->insert([
	// 					'code' => $v['code'],
	// 					'water' => $v['water'],
	// 					'factory_product_qrcode_id' => $qr_data['id'] ? $qr_data['id'] : 0,
	// 					'factory_id' => $v['factory_id'],
	// 					'product_id' => $v['product_id'],
	// 					'shengchan_time' => $v['shengchan_time'],
	// 					'chuchang_time' => $v['chuchang_time'],
	// 					'zhibao_time' => $v['zhibao_time'],
	// 					'remarks' => $v['remarks'],
	// 					'diy_remarks' => $v['diy_remarks'],
	// 					'member_id' => $v['member_id'],
	// 					'user_name' => $v['user_name'],
	// 					'user_tel' => $v['user_tel'],
	// 					'user_address' => $v['user_address'],
	// 					'active_time' => $v['active_time'],
	// 					'register_time' => $v['register_time'],
	// 					'saomiao' => $v['saomiao'],
	// 				]);
	// 			}
	// 			++$this->nums;
	// 			var_dump($this->nums);
	// 		}
	// 		--$i;
	// 		++$start;
	// 		unset($list);
	// 	} while ($i);

	// 	// php /Users/seanzen/Desktop/3NCTO/Shenzhou-Server/test.php common/factoryExcelToYima/co/cocococo/api_url_secret/0A1B0c2D0e3F0G
	// }

	public function factoryExcelToYima2($name = 'factory_excel_datas_0', $name_key = '')
	{
		$model = BaseModel::getInstance($name);
		$opt = [
			'where' => [
				'factory_id' => ['GT', 0],
				'_string' => '`code` IS NOT NULL',
			],
			'field' => '`factory_id`,GROUP_CONCAT(`id`) as ids,GROUP_CONCAT(`code`) as codes,COUNT(`id`) as nums',
			'group' => 'factory_id',
		];
		$data = $model->getList($opt);

		// var_dump($data);die;

		$all_count = $model->getNum();
		$count = $model->getNum($opt['where']);
		$not_count = $all_count - $count;
		$factory_ids = [];

		$excel_name = 'factory_excel_datas_'.$name_key;

		$sql = $sql_2 = '';

		// M()->query("DELETE FROM `old_yima_code_index_{$name_key}`");
		$sql .= "DELETE FROM `old_yima_code_index_{$name_key}`;\n";
		// M()->query("INSERT INTO `old_yima_code_index_{$name_key}` (SELECT `code`,`md5code`,`url` FROM `{$excel_name}`)");
		$where = ' `factory_id` > 0 AND `code` IS NOT NULL ';
		$sql .= "INSERT INTO `old_yima_code_index_{$name_key}`(`md5code`,`code`,`url`) (SELECT `md5code`,`code`,`url` FROM `{$excel_name}` WHERE {$where});\n";

		$ocunt_str = "all_count {$all_count}  count {$count}  not_count {$not_count} \n";

		$sql_arr = $code_count_arr = [];
		$group_num = 0;
		foreach ($data as $k => $v) {
			$group_num += $v['nums'];

			$ids = explode(',', $v['ids']);
			$ids = implode('","', array_unique(array_filter($ids)));

			$sql_arr[factoryIdToModelName($v['factory_id'])][] = $codes = array_unique(array_filter(explode(',', $v['codes'])));
			$code_count = count($codes);
			$codes = implode('","', $codes);

			if (!$ids || !$codes) {
				continue;
			}
			$ids = "\"{$ids}\"";
			$codes = "\"{$codes}\"";
			$factory_ids[$v['factory_id']] = $v['factory_id'];
			$table_name = factoryIdToModelName($v['factory_id']);

			if (!isset($code_count_arr[$table_name])) {
				$code_count_arr[$table_name] = $code_count;
			} else {
				$code_count_arr[$table_name] += $code_count;
			}

			// M()->query("DELETE FROM `{$table_name}` WHERE `id` IN({$ids})");
			// $sql .= "DELETE FROM `{$table_name}` WHERE `code` IN ({$codes});\n";

			$field = '`code`,`water`,"0" as `factory_product_qrcode_id`,`factory_id`,`product_id`,`shengchan_time`,`chuchuang_time` as chuchang_time,`zhibao_time`,"" as `remarks`,"" as `diy_remarks`,"{\"is_active_type\":\"1,2\",\"is_order_type\":\"1,2\",\"active_credence_day\":0,\"cant_active_credence_day\":0,\"active_reward_moth\":0}" as `active_json`,`member_id`,`user_name`,`user_tel`,`user_address`,`active_time`,IFNULL(`register_time`,0),`saomiao`,0 as `is_disable`';
			// M()->query("INSERT INTO `{$table_name}` (SELECT {$field} FROM `{$excel_name}`) WHERE `id` IN({$ids})");
			// $sql .= "INSERT INTO `{$table_name}` (SELECT {$field} FROM `{$excel_name}` WHERE `id` IN ({$ids}));\n";
			$sql_2 .= "INSERT INTO `{$table_name}` (SELECT {$field} FROM `{$excel_name}` WHERE `code` IN ({$codes}));\n";
		}

		$code_all_code = 0;
		foreach ($code_count_arr as $k => $v) {
			$code_all_code += $v;
			$ocunt_str .= " {$k} code_count {$v} \n";
		}
		$ocunt_str .= " group_num {$group_num} \n code_all_code {$code_all_code}\n";

		// $factory_ids = implode(',', array_filter($factory_ids));
		// if ($factory_ids) {
		// }

		// $file_name = "./sql_backup/yima_v2.0/{$name}.sql";
		// file_put_contents($file_name, $sql);

		file_put_contents("./sql_backup/yima_v2.0/all.sql", $sql, FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/all_2.sql", $sql_2, FILE_APPEND);

		file_put_contents("./sql_backup/yima_v2.0/count.sql", "{$name}  {$ocunt_str}", FILE_APPEND);
	}

	public function factoryExcelForyima()
	{
		$lists = BaseModel::getInstance('factory_product_qrcode')->getList([
				'factory_id' => ['GT', 0],
			]);
		$models = [];
		$sql = $sql_2 = '';
		$delete_sql = [];
		foreach ($lists as $k => $v) {
			if (!$v['factory_id']) {
				continue;
			}

			if (!isset($models[$v['factory_id']])) {
				// $models[$v['factory_id']] = BaseModel::getInstance(factoryIdToModelName($v['factory_id']));
				$models[$v['factory_id']] = factoryIdToModelName($v['factory_id']);
			}

			$model = $models[$v['factory_id']];

			$where = [
				'water' => ['BETWEEN', "{$v['qr_first_int']},{$v['qr_last_int']}"],
				'saomiao' => 0,
				'register_time' => 0,
				'active_time' => 0,
				'product_id' => intval($v['product_id']),
				'shengchan_time' => intval($v['shengchan_time']),
				'chuchang_time' => intval($v['chuchang_time']),
				'zhibao_time' => intval($v['zhibao_time']),
				'active_json' => $v['active_json'],
			];
			// BaseModel::getInstance(factoryIdToModelName($v['factory_id']))->fetchSql(true)->where($where)->delete();
			// die(M()->_sql());

			// $delete_sql[$model][] = " ( `water` BETWEEN '{$v['qr_first_int']}' AND '{$v['qr_last_int']}' AND `saomiao` = 0 AND `register_time` = 0 AND `active_time` = 0 AND `product_id` = ".intval($v['product_id'])." AND `shengchan_time` = ".intval($v['shengchan_time'])." AND `chuchang_time` = ".intval($v['chuchang_time'])." AND `zhibao_time` = ".intval($v['zhibao_time'])." AND `active_json` = '{$v['active_json']}' AND `factory_id` = {$v['factory_id']} ) ";
			$delete_sql[$model][] = " ( `factory_product_qrcode_id` = {$v['id']} AND `saomiao` = 0 AND `register_time` = 0 AND `active_time` = 0 AND `product_id` = ".intval($v['product_id'])." AND `shengchan_time` = ".intval($v['shengchan_time'])." AND `chuchang_time` = ".intval($v['chuchang_time'])." AND `zhibao_time` = ".intval($v['zhibao_time'])."  ) ";
			// AND `active_json` = '{$v['active_json']}'

			// $sql .= ";\n".$model->fetchSql(true)->where([
			// 		'water' => ['BETWEEN', "{$v['qr_first_int']},{$v['qr_last_int']}"],
			// 		'factory_id' => $v['factory_id'],
			// 	])->save([
			// 		'factory_product_qrcode_id' => $v['id'],
			// 	]).";\n";
			$sql_2 .= "UPDATE `{$model}` SET `factory_product_qrcode_id`={$v['id']} WHERE `water` BETWEEN '{$v['qr_first_int']}' AND '{$v['qr_last_int']}' AND `factory_id` = {$v['factory_id']};\n";
			// die(M()->_sql());

			// $sql .= $model->fetchSql(true)->where([
			// 		'factory_product_qrcode_id' => $v['id'],
			// 		'_string' => 'product_id IS NULL  OR product_id = 0',
			// 		'factory_id' => $v['factory_id'],
			// 	])->save([
			// 		'product_id' => $v['product_id'],
			// 	]);
			$sql .= "UPDATE `{$model}` SET `product_id`='{$v['product_id']}' WHERE `factory_product_qrcode_id` = {$v['id']} AND ( `product_id` IS NULL OR `product_id` = 0 );\n";

			// var_dump($v['id']);
		}
		$delete = './sql_backup/yima_v2.0/factory_excel_delete.sql';
		// $delete = "./sql_backup/yima_v2.0/factory_excel_update_all.sql";
		file_put_contents($delete, '');

		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all.sql", $sql);
		// file_put_contents($delete, $sql, FILE_APPEND);

		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", $sql_2);




		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_0 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_1 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_2 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_3 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_4 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_5 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_6 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_7 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_8 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_9 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_10 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_11 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_12 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_13 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_14 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		file_put_contents("./sql_backup/yima_v2.0/factory_excel_update_all_2.sql", "DELETE FROM yima_15 WHERE `factory_product_qrcode_id` = 0;\n", FILE_APPEND);
		
		foreach ($delete_sql as $key => $value) {
			$string = "DELETE FROM `{$key}` WHERE ".implode(' OR ', $value).";\n";
			file_put_contents($delete, $string, FILE_APPEND);
		}

		die("\nis ok");
	}


	public function yimaareaids()
	{
		$arr = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15];
		foreach ($arr as $k => $v) {
			$model = BaseModel::getInstance('yima_'.$v);
			$list = $model->getList([
					'_string' => " `user_address` NOT LIKE '{%' AND `user_address` NOT LIKE '%}' AND user_address is not null  AND user_address != '' ",
				], 'code,user_address');
			
			foreach ($list as $key => $value) {
				$json = [
					'ids' => '',
					'names' => '',
					'address' => $value['user_address'],
				];
				
				$model->update(['code' => $value['code']], ['user_address' => json_encode($json, JSON_UNESCAPED_UNICODE)]);
			}
		}
		die("\nis ok");
	}

	public function factoryoldqr()
	{
		// $model = BaseModel::getInstance('factory_product_qrcode');
		// $not_eid_list = $model->getList(['_string' => 'factory_excel_id is null']);

		// $errors = [];
		// $field = 'id,product_id,factory_id,qr_first_int,qr_last_int,nums,factory_excel_id,datetime';
		// foreach ($not_eid_list as $key => $value) {
		// 	$sql = "select {$field} from factory_product_qrcode where factory_id = 1 and  ( (qr_first_int <= {$value['qr_first_int']} AND qr_last_int >= {$value['qr_first_int']}) OR (qr_first_int <= {$value['qr_last_int']} AND qr_last_int >= {$value['qr_last_int']})  ) and factory_excel_id is not null AND datetime > 1462032000 order by datetime desc;";
		// 	$list = M()->query($sql);
		// 	if ($list) {

		// 		foreach ($list as $k => $v) {
		// 			$v['error_excel_id'] = $value['id'];
		// 			$v['datetime_'] = date('Y-m-d H:i:s', $v['datetime']);
		// 			$list[$k] = $v;
		// 		}
		// 		$errors[$value['factory_id']] = $list;
		// 	}
		// }
		// return $errors;
		$field = "factory_id,GROUP_CONCAT(CONCAT_WS('_',first_code,last_code) order by first_code ASC) as p_codes,MIN(first_code) as min_code,MAX(last_code) as max_code";
		$model = BaseModel::getInstance('factory_excel');
		$list = $model->getList([
				'field' => $field,
				'where' => [
					'is_check' => 1,
				],
				'group' => 'factory_id'
			]);

		$logic = new \Admin\Logic\YimaLogic();
		$rule_arr = $arr = $error = [];
		$add = [];
		foreach ($list as $k => $v) {
			// $p_code = $v['first_code'].','.($v['p_codes'] ? $v['p_codes'] : '_').','.$v['last_code'];
			$p_code = 	$v['p_codes'] ?
						$v['first_code'].','.$v['p_codes'].','.$v['last_code'] : 
						$v['first_code'].','.$v['last_code'];

			$p_code = $v['min_code'].$p_code.$v['max_code'];
			$d = (array)$logic->ruleGroupPCode($p_code, $nums);
			if ($d) {
				foreach ($d as $ks => $vs) {
					$error[$v['factory_id']] = $model->getList([
							'_string' => "factory_id = {$v['factory_id']} AND ( (first_code <= {$vs['first_code']} AND {$vs['first_code']} <= last_code) OR (first_code <= {$vs['last_code']} AND {$vs['last_code']} <= last_code) ) ",
						]);

					$add_time = $this->getOldAddTime([
							'first_code' => $vs['first_code'],
							'last_code' => $vs['last_code'],
						]);
					$add[] = [
						'factory_id' => $v['factory_id'],
						'add_time' => $add_time,
						'first_code' => $vs['first_code'],
						'last_code' => $vs['last_code'],
						'nums' => $vs['nums'],
						'qr_type' => 0,
						'qr_guige' => 0,
						'is_check' => 1,
						'check_time' => $add_time, 
					];
				}
			}

		}

		M()->startTrans();
		if (I('get.co') == 'co') {
			
			$add && $model->insertAll($add) && file_put_contents('./sql_backup/yima_V2_excel_qr_update_sql.log', M()->_sql()."\n", FILE_APPEND);
			$this->factoryProductQrcodeEidIsNullRule();
		}
		M()->commit();
		return $add;
		// return $rule_arr;
	}

	public function factoryProductQrcodeEidIsNullRule()
	{
		$qr_model = BaseModel::getInstance('factory_product_qrcode');
		$nulls = $qr_model->getList([
				'where' => [
					'_string' => 'factory_excel_id is null',
					// 'id' => 1736,
				],
				'field' => '*',
			]);

		$model = BaseModel::getInstance('factory_excel');
		$save = [];
		foreach ($nulls as $k => $v) {
			$where = [
				// '_string' => " (first_code <= {$v['qr_first_int']} AND {$v['qr_first_int']} <= last_code) OR (first_code <= {$v['qr_last_int']} AND {$v['qr_last_int']} <= last_code) ",
				// 'first_code|last_code' => ['BETWEEN', "{$v['qr_first_int']},{$v['qr_last_int']}"],
				'factory_id' => $v['factory_id'],
				'_string' => " first_code BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']} OR last_code BETWEEN {$v['qr_first_int']} AND {$v['qr_last_int']} OR (first_code <= {$v['qr_first_int']} AND {$v['qr_first_int']} <= last_code) ",
			];

			$li = $model->getList([
					'where' => $where,
					'field' => 'excel_id,first_code,last_code',
					'order' => 'first_code ASC'
				]);

			// var_dump($li);die;

			if ($v['qr_last_int'] == $v['qr_first_int'] && $li) {
				$qr_model->update($v['id'], ['factory_excel_id' => reset($li)['excel_id']]);
				file_put_contents('./sql_backup/yima_V2_excel_qr_update_sql.log', M()->_sql()."\n", FILE_APPEND);
				continue;
			}

			foreach ($li as $ks => $vs) { 
				$arr = array_unique([
					$vs['first_code'],
					$vs['last_code'],
					$v['qr_first_int'],
					$v['qr_last_int'],
				]);
				sort($arr);

				// var_dump($arr, '======================================================');

				// 交集新数据
				$first = max($vs['first_code'], $v['qr_first_int']);
				$last = min($vs['last_code'], $v['qr_last_int']);
				$new = $v;
				unset($new['id']);
				$new['qr_first_int'] = $first;
				$new['qr_last_int'] = $last;
				$new['nums'] = $new['qr_last_int'] - $new['qr_first_int'] + 1;
				$new['factory_excel_id'] = $vs['excel_id'];


				// $ks == (count($li)-2): $ks 最小值为0 
				if (count($arr) == 3 && $v['qr_last_int'] <= $vs['last_code']) {

					$qr_model->update($v['id'], [
							'factory_excel_id' => $vs['excel_id'],
							'qr_first_int' => $first,
							'qr_last_int' => $last,
							'nums' => $last - $first + 1,
						]);
					file_put_contents('./sql_backup/yima_V2_excel_qr_update_sql.log', M()->_sql()."\n", FILE_APPEND);
				} elseif (count($arr) == 2 || ($first == $v['qr_first_int'] AND $last == $v['qr_last_int'])) {

					$qr_model->update($v['id'], [
							'factory_excel_id' => $vs['excel_id'],
							'qr_first_int' => $first,
							'qr_last_int' => $last,
							'nums' => $last - $first + 1,
						]);
					file_put_contents('./sql_backup/yima_V2_excel_qr_update_sql.log', M()->_sql()."\n", FILE_APPEND);
				}  elseif ($first == $v['qr_first_int']) {

					$v['qr_first_int'] = $vs['last_code'] + 1;
					$qr_model->insert($new);
					file_put_contents('./sql_backup/yima_V2_excel_qr_update_sql.log', M()->_sql()."\n", FILE_APPEND);
				} elseif ($last == $v['qr_last_int']) {

					$v['qr_last_int'] = $vs['first_code'] - 1;
					$qr_model->insert($new);
					file_put_contents('./sql_backup/yima_V2_excel_qr_update_sql.log', M()->_sql()."\n", FILE_APPEND);
				}
			}
		}
		// die;
		
	}

	protected function getOldAddTime($where = [])
	{
		$where = array_filter($where);
		if (!isset($where['first_code']) || !isset($where['last_code'])) {
			$this->throwException(-1, '数据恢复失败请检查数据');
		}

		$one = BaseModel::getInstance('factory_product_qrcode')->getOne([
				'where' => [
					'_string' => " (qr_first_int BETWEEN  {$where['first_code']} AND {$where['last_code']}) OR (qr_last_int BETWEEN  {$where['first_code']} AND {$where['last_code']}) ",
				],
				'field' => 'datetime',
				'order' => 'datetime asc',
			]);
		
		if (!$one) {
			
			$start = BaseModel::getInstance('factory_excel')->getOne([
					'where' => [
						'last_code' => ['ELT', $where['first_code']],
					],
					'order' => 'last_code DESC',
					'field' => 'add_time as datetime'
				]);

			$end = BaseModel::getInstance('factory_excel')->getOne([
					'where' => [
						'first_code' => ['EGT', $where['last_code']],
					],
					'order' => 'first_code ASC',
					'field' => 'add_time as datetime'
				]);

			$one['datetime'] = number_format(($start['datetime'] + $end['datetime'])/2, 0, '.', '');

		} 

		return $one['datetime'];
	}

	public function factoryqrbindagen()
	{
		$model = BaseModel::getInstance('factory_product_qrcode');
		
		$field = "factory_id,GROUP_CONCAT(CONCAT_WS('_',qr_first_int,qr_last_int) order by qr_first_int ASC,qr_last_int ASC) as p_codes,MIN(qr_first_int) as min_code,MAX(qr_last_int) as max_code";

		$list = $model->getList([
				'field' => $field,
				'group' => 'factory_id',
			]);

		$f_ids = $return = [];
		$logic = new \Admin\Logic\YimaLogic();
		foreach ($list as $k => $v) {
			$p_code = 	$v['p_codes'] ?
						$v['first_code'].','.$v['p_codes'].','.$v['last_code'] : 
						$v['first_code'].','.$v['last_code'];

			$d = (array)$logic->ruleGroupPCodeELT($p_code);
			if ($d) {
				$data = $v;
				$data['errors'] = $d;
				if (I('get.co') == 'co') {
					unset($data['p_codes']);
				}
				$f_ids[$v['factory_id']] = $v['factory_id'];
				$return[$v['factory_id']] = $data;
			}
		}

		$f_ids = implode(',', array_unique(array_filter($f_ids)));

		if ($f_ids) {
			foreach (BaseModel::getInstance('factory')->getList(['factory_id' => ['in', $f_ids]], 'factory_id,factory_full_name,linkman,linkphone') as $k => $v) {
				$return[$v['factory_id']]['factory_name'] = $v['factory_full_name'];
				$return[$v['factory_id']]['linkman'] = $v['linkman'];
				$return[$v['factory_id']]['linkphone'] = $v['linkphone'];
			}

		}
		return array_values($return);
	}

}

