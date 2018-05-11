<?php
/**
* 
*/
namespace Api\Model;

use Api\Model\BaseModel;

class QueueMessageModel extends BaseModel
{
	protected $trueTableName = 'queue_message';

	public function anxinjie_sms_success($datas)
	{
		$insert = [];
		foreach ($datas as $k => $v) {
			$one = null;
			// 	$v['table_id']
			// && 	
			$v['content']
			&& 	$v['phone']
			&&  $one = $insert[] = [
				'phone_id' 	=> $v['table_id'],
				'content' 	=> $v['content'],
				'type' 		=> $v['type'],
				'state'		=> 3,
				'phone_number' 	=> $v['phone'],
				'create_time' 	=> time(),
			];
			$one && $this->insert($one);
		}
		// if (count($insert)) {
		// 	$this->insertAll($insert);
		// }
	}

	public function anxinjie_sms_error($datas)
	{
		$insert = [];
		foreach ($datas as $k => $v) {
			$one = null;
			// 	$v['table_id']
			// && 	
			$v['content']
			&& 	$v['phone']
			&&  $one = $insert[] = [
				'phone_id' 	=> $v['table_id'],
				'content' 	=> $v['content'],
				'type' 		=> $v['type'],
				'state'		=> 4,
				'phone_number' 	=> $v['phone'],
				'create_time' 	=> time(),
			];
			$one && $this->insert($one);
		}
		// if (count($insert)) {
		// 	$this->insertAll($insert);
		// }
	}
}