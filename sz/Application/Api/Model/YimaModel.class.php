<?php
/**
* @User zjz
* @Date 2016/12/12
*/
namespace Api\Model;

use Api\Model\BaseModel;
use Api\Common\ErrorCode;

class YimaModel extends BaseModel
{
	protected $trueTableName = 'yima_0';

	const ACTIVE_JSON_DEFAULT_VALUE = [
            'is_active_type'            => '1,2',                       // 1,2  1消费者， 2经销商
            'is_order_type'             => '1,2',                       // 1,2  1消费者， 2经销商
            'active_credence_day'       => 0,                           // 需要上传发票   单位天
            'cant_active_credence_day'  => 0,                           // 禁止激活产品   单位天
            'active_reward_moth'        => 0,                           // 激活赠送延保   单位月
      	];
	
    public function getYimaInfosByCodes($codes = '', $is_index = false)
    {
		$arr = array_filter(explode(',', $codes));
		if (!$arr) {
			return [];
		}

		$f_code = [];
		foreach ($arr as $k => $v) {
			$f_code[yimaCodeToModelName($v)][] = $v;
		}

		$in_yima = $return = [];
		foreach ($f_code as $k => $v) {
			$opt = [
				'field' => $field,
				'where' => [
					'code' => ['in', implode(',', array_filter($v))]
				]
			];
			if ($is_index) {
				$opt['index'] = 'code';
			}

			foreach (BaseModel::getInstance($k)->getList($opt) as $key => $value) {
				$value['active_json'] = (array)json_decode($value['active_json'], true) + self::ACTIVE_JSON_DEFAULT_VALUE;
				$list[$key] = $value;
				$in_yima[] = $value['code'];
			}

			$return += $list;
		}

		foreach (array_diff($arr, $in_yima) as $k => $v) {
			if ($is_index) {
				$return[$k] = $this->getYimaInfoByCode($v);
			} else {
				$return[] = $this->getYimaInfoByCode($v);
			}
		}
		
		return $return;
    }

	public function getYimaCodeByEnCode($en_code = '')
	{
		$code = '';
		if (strlen($en_code) == 32) {
	        $key = substr($en_code, 0, 1);
	        $model = BaseModel::getInstance('old_yima_code_index_'.$key);
	        $code = $model->getFieldVal(['md5code' => $en_code], 'code');
		} else {
			$code = decryptYima($en_code);
		}
		return $code;
	}

	public function getYimaInfoByEnCode($en_code = '', $return_true = false)
	{
		$code = $this->getYimaCodeByEnCode($en_code);
		if (!$code) {
			$this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
		}
		return $this->getYimaInfoByCode($code, $return_true);
	}

	public function getYimaInfoByCode($code = '', $return_true = false)
	{
		$water = substr($code, 4);
		$f_id = getFidByCode($code);

		$model = BaseModel::getInstance(factoryIdToModelName($f_id));
		$where = [
                  'code' => $code,
                  'factory_id' => $f_id,
            ];
		$data = $model->getOne($where);
		
		$qr_data = [];
		if (!$data) {
	        $qr_data = BaseModel::getInstance('factory_product_qrcode')->getOne([
	                    'qr_first_int' => ['ELT', $water],
	                    'qr_last_int' => ['EGT', $water],
	                    'factory_id' => $f_id,
	              ]);

	       	if ($qr_data) {
	        	$data = [
		            "code"              => $code,
		            "water"             => $water,
		            "factory_product_qrcode_id" => $qr_data['id'],
		            "factory_id"        => $qr_data['factory_id'],
		            "product_id"        => $qr_data['product_id'],
		            "shengchan_time"    => $qr_data['shengchan_time'],
		            "chuchang_time"     => $qr_data['chuchang_time'],
		            "zhibao_time"       => $qr_data['zhibao_time'],
		            "remarks"           => $qr_data['remarks'],
		            "diy_remarks"       => $qr_data['diy_remarks'],
		            "active_json"       => $qr_data['active_json'],
		            "member_id"         => '0',
		            "user_name"         => "",
		            "user_tel"          => "",
		            "user_address"      => "{}",
		            "active_time"       => '0',
		            "register_time"     => '0',
		            "saomiao"           => '0',
		            "is_disable"        => '0',
		        ];

		        // 是否是存在
		        if ($return_true) {
		        	$data['not_true'] = true;
		        }

	        } else {
	        	$this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
	        }
		}
       
	    $data['active_json'] = (array)json_decode($data['active_json'], true) + self::ACTIVE_JSON_DEFAULT_VALUE;
	    $data['chuchuang_time'] = $data['chuchang_time'];

        return $data;
	}

}
