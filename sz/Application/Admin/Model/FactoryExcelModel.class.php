<?php
/**
* 
*/
namespace Admin\Model;

use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;

class FactoryExcelModel extends BaseModel
{

	protected $trueTableName = 'factory_excel';
	
	// 获取厂家可用码段
	public function groupPCodeByFactoryIdAndNums($factory_id = 0, $nums = 0)
	{
		// (FE.nums - SUM(FPQ.nums)) as not_bind_nums
        // $sql = "select CONCAT_WS('_',FPQ.qr_first_int,FPQ.qr_last_int) as p_codes_one order by FPQ.qr_first_int";
        // $field = "FE.excel_id,GROUP_CONCAT(FPQ.id) as ids,FE.nums as fe_nums,SUM(FPQ.nums) as fpq_nums,GROUP_CONCAT({$sql}) as p_codes";
		$code = '';
		if (AuthService::getModel() == 'factory' && $factory_id == AuthService::getAuthModel()->factory_id) {
			$code = AuthService::getAuthModel()->factory_type.AuthService::getAuthModel()->code;
		} else {
			$f_data = BaseModel::getInstance('factory')->getOne($factory_id, 'factory_type,code');
			$f_data && ($code = $f_data['factory_type'].$f_data['code']);
		}

        $field = "FE.excel_id,GROUP_CONCAT(FPQ.id) as ids,FE.nums as fe_nums,SUM(IF(FPQ.nums>0,FPQ.nums,0)) as fpq_nums,GROUP_CONCAT(CONCAT_WS('_',FPQ.qr_first_int,FPQ.qr_last_int) order by FPQ.qr_first_int ASC) as p_codes,FE.first_code,FE.last_code,FE.add_time,FE.check_time";

		$list = BaseModel::getInstance('factory_excel')->getList([
				'alias' => 'FE',
				'join'  => 'LEFT JOIN factory_product_qrcode FPQ ON FE.excel_id = FPQ.factory_excel_id',
				'field' => $field,
				'where' => [
					'FE.factory_id' => $factory_id,
					// 'FE.nums' => ['EGT', $nums],
					'FE.is_check' => 1,

				],
				'group' => 'FE.excel_id',
				// 'order' => 'FPQ.qr_first_int ASC',
				'having' => '(fe_nums - fpq_nums) >= '.$nums.' AND fe_nums > fpq_nums',
				// 'having' => '(fe_nums - fpq_nums) >= '.$nums.' AND fe_nums >= '.$nums.' AND fe_nums > fpq_nums',
			]);

		$logic = new \Admin\Logic\YimaLogic();
		$arr = [];
		
		foreach ($list as $k => $v) {
			// $p_code = $v['first_code'].','.($v['p_codes'] ? $v['p_codes'] : '_').','.$v['last_code'];
			$p_code = 	$v['p_codes'] ?
						$v['first_code'].','.$v['p_codes'].','.$v['last_code'] : 
						$v['first_code'].','.$v['last_code'];
			
			$rule_arr = (array)$logic->ruleGroupPCode($p_code, $nums);
			
			foreach ($rule_arr as $key => $value) {
				$value['excel_id'] = $v['excel_id'];
				$value['add_time'] = $v['add_time'];
				$value['check_time'] = $v['check_time'];
				$value['first_code_full'] = $value['first_code'] && $code ? $code.$value['first_code'] : $code.$value['first_code'];
				$value['last_code_full'] = $value['last_code'] && $code ? $code.$value['last_code'] : $code.$value['last_code'];
				$rule_arr[$key] = $value;
			}

			$arr = array_merge($arr, $rule_arr);
		}
		return $arr;
	}

	// 获取厂家可用码段
	public function groupPCodeByFactoryIdAndNumsNotExcelId($factory_id = 0, $nums = 0)
	{
		// (FE.nums - SUM(FPQ.nums)) as not_bind_nums
        // $sql = "select CONCAT_WS('_',FPQ.qr_first_int,FPQ.qr_last_int) as p_codes_one order by FPQ.qr_first_int";
        // $field = "FE.excel_id,GROUP_CONCAT(FPQ.id) as ids,FE.nums as fe_nums,SUM(FPQ.nums) as fpq_nums,GROUP_CONCAT({$sql}) as p_codes";
		$code = '';
		if (AuthService::getModel() == 'factory' && $factory_id == AuthService::getAuthModel()->factory_id) {
			$code = AuthService::getAuthModel()->factory_type.AuthService::getAuthModel()->code;
		} else {
			$f_data = BaseModel::getInstance('factory')->getOne($factory_id, 'factory_type,code');
			$f_data && ($code = $f_data['factory_type'].$f_data['code']);
		}

        $field = "FE.excel_id,GROUP_CONCAT(FPQ.id) as ids,FE.nums as fe_nums,SUM(IF(FPQ.nums>0,FPQ.nums,0)) as fpq_nums,GROUP_CONCAT(CONCAT_WS('_',FPQ.qr_first_int,FPQ.qr_last_int) order by FPQ.qr_first_int ASC) as p_codes,FE.first_code,FE.last_code,FE.add_time,FE.check_time";

		$excel_list = BaseModel::getInstance('factory_excel')->getList([
				'field' => '*',
				'where' => [
					'factory_id' => $factory_id,
					'is_check' => 1,
				],
				'index' => 'excel_id',
			]);

		$list = BaseModel::getInstance('factory_product_qrcode')->getList([
				'field' => 'GROUP_CONCAT(CONCAT_WS(\'_\',qr_first_int,qr_last_int) order by qr_first_int ASC) as p_codes,factory_excel_id',
				'where' => [
					'factory_id' => $factory_id,
				],
				'group' => 'factory_excel_id',
			]);

		var_dump($list);die;


		$logic = new \Admin\Logic\YimaLogic();
		$arr = [];
		
		foreach ($list as $k => $v) {
			// $p_code = $v['first_code'].','.($v['p_codes'] ? $v['p_codes'] : '_').','.$v['last_code'];
			$p_code = 	$v['p_codes'] ?
						$v['first_code'].','.$v['p_codes'].','.$v['last_code'] : 
						$v['first_code'].','.$v['last_code'];

			$rule_arr = (array)$logic->ruleGroupPCode($p_code, $nums);
			
			foreach ($rule_arr as $key => $value) {
				$value['excel_id'] = $v['excel_id'];
				$value['add_time'] = $v['add_time'];
				$value['check_time'] = $v['check_time'];
				$value['first_code_full'] = $value['first_code'] && $code ? $code.$value['first_code'] : $code.$value['first_code'];
				$value['last_code_full'] = $value['last_code'] && $code ? $code.$value['last_code'] : $code.$value['last_code'];
				$rule_arr[$key] = $value;
			}

			$arr = array_merge($arr, $rule_arr);
		}
		return $arr;
	}
		
}
