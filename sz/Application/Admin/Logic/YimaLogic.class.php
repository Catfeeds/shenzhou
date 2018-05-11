<?php
/**
* 
*/
namespace Admin\Logic;

use Admin\Logic\BaseLogic;
use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Carbon\Carbon;
use Common\Common\Service\AuthService;
use Illuminate\Support\Arr;

class YimaLogic extends BaseLogic
{
	const NEXT_SPAN_MUNS = 1000;
	const ACTIVE_JSON_DEFAULT_VALUE = [
            'is_active_type'            => '1,2',                       // 1,2  1消费者， 2经销商
            'is_order_type'             => '1,2',                       // 1,2  1消费者， 2经销商
            'active_credence_day'       => 0,                           // 需要上传发票   单位天
            'cant_active_credence_day'  => 0,                           // 禁止激活产品   单位天
            'active_reward_moth'        => 0,                           // 激活赠送延保   单位月
      	];

    // 客服删除指定码段
    public function adminYimaAppliesDeleteByFidAndQrId($f_id = 0, $qr_ids = '')
    {
        $ids = array_unique(array_filter(explode(',', $qr_ids)));

        if (!$f_id || !$ids) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        $where = [
            'factory_id' => $f_id,
            'id' => ['in', implode(',', $ids)],
        ];

        $model = BaseModel::getInstance('factory_product_qrcode');
        $list = $model->getList($where);
        if (count($ids) != count($list)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }


        $add = [];
        foreach ($list as $k => $v) {
            unset($v['id']);
            $v['admin_id'] = AuthService::getAuthModel()->id;
            $v['create_time'] = NOW_TIME;
            $add[] = $v;
        }

        if ($add) {
            M()->startTrans();
            $model->remove($where);
            // 删除未被激活的易码
            BaseModel::getInstance(factoryIdToModelName($f_id))->remove([
                'factory_id' => $f_id,
                'factory_product_qrcode_id' => ['in', $qr_ids],
                'register_time' => 0,
            ]);
            BaseModel::getInstance(factoryIdToModelName($f_id))->update([
                'factory_id' => $f_id,
                'factory_product_qrcode_id' => ['in', $qr_ids],
                'register_time' => ['GT', 0],
            ], ['factory_product_qrcode_id' => 0]);
            BaseModel::getInstance('factory_product_qrcode_delete_record')->insertAll($add);
            M()->commit();
        }
    }

    public function excelYimaApplyYimasBetween($f_data = [], $file_name = '', $start = 0, $end = 0)
    {
        if (strlen($start) < 6 || strlen($end) < 6 || $end < $start) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        $pre_str = $f_data['factory_type'].$f_data['code'];

        $while = ceil(($end - $start + 1) / self::NEXT_SPAN_MUNS);
        $logic = new \Common\Common\Logic\ExportDataLogic();


        $logic->objPHPExcel->setActiveSheetIndex(0)->setCellValue('A' . 1, '厂家名称：'.$f_data['factory_full_name']);
        $logic->objPHPExcel->setActiveSheetIndex(0)->setCellValue('A' . 2, '产品码')->setCellValue('B' . 2, '前端链接');

        $row = 3;
        $i = $start;
        // $end = $start + 100;
        while ($i <= $end) {
            $column = 'A';
            $code = $pre_str.$i;
            $logic->objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($column++ . $row, $code)
                ->setCellValue($column++ . $row, C('YIMA_API_SCC_URL').encryptYimaCode($code));
                ++$row;
                ++$i;
        }
        // $file_name = iconv("UTF-8", "gbk", ($file_name ? $file_name : $start.'至'.$end));
        $logic->putOut($file_name);

    }


    public function excelYimaApplys($list = [])
    {
    	$excel = new \Common\Common\Logic\ExportDataLogic();
        $setSheetData_title = [
            'setWidth' => [
                // 'A' => 20,
                // 'B' => 80
            ],
            'setCellValue' => [
                1 => [
                    'A' => '申请时间',
                    'B' => '申请人',
                    'C' => '标签类型',
                    'D' => '数量',
                    'E' => '申请备注',
                    'F' => '码段',
                    'G' => '印刷状态',
                    'H' => '未使用数量',
                ],
            ],
        ];
        $setSheetData_lines = [
            'A' => 'add_time',
            'B' => 'linkman',
            'C' => 'qr_guige_type',
            'D' => 'nums',
            'E' => 'remarks',
            'F' => 'start_end_code',
            'G' => 'is_check',
            'H' => 'not_bind_nums',
        ];
        $in_arr = [
            'is_check' => [
                0 => '待印刷',
                1 => '已印刷',
                2 => '系统取消',
                3 => '厂家自行取消',
            ],
        ];
        
        $excel->setSheetTitle('二维码申请记录');
        $excel->setExcelForDatas($setSheetData_title, $setSheetData_lines, $list, $in_arr);
        $excel->putOut('二维码申请记录'.date('Y.m.d-H：i'));
        exit();
    }

	// 易码修改，或添加时的数据验证
	public function ruleUpdateYimaData($data = [], $check = [])
	{
		if (!$data['factory_id'] || !$data['code'] || !$data['water'] || !$data['factory_product_qrcode_id']) { //  || !isset($data['shengchan_time'])
			$this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
		} elseif ($data['active_time']) {
			$this->throwException(ErrorCode::YIMA_IS_ACTIVE);
		}

		$check = array_intersect_key($check, [
                    'product_id' => '',
                    'shengchan_time' => '',
                    'chuchang_time' => '',
                    'zhibao_time' => '',
                    'remarks' => '',
                    'diy_remarks' => '',
                    'is_disable' => '',

                    'is_active_type' => '',
                    'is_order_type' => '',
                    'active_credence_day' => '',
                    'cant_active_credence_day' => '',
                    'active_reward_moth' => '',
                ]);

		$return = [];
		$active_json = (array)json_decode($data['active_json'], true) + self::ACTIVE_JSON_DEFAULT_VALUE;

		foreach ($check as $k => $v) {

            // if (isset($active_json[$k]) && $active_json[$k] != $v) {
			if (in_array($k, array_keys($active_json)) && $active_json[$k] != $v) {
				$return['active_json'][$k] = $v;
            // } elseif (isset($data[$k]) && $data[$k] != $v) {
			} elseif (in_array($k, array_keys($data)) && $data[$k] != $v) {
        		$return[$k] = $v;
				switch ($k) {
	        		case 'product_id':
		        		$pro_where = [
	    					'factory_id' => $data['factory_id'],
	    					'product_id' => $v,
	    				];
		        		if (!BaseModel::getInstance('factory_product')->getOne($pro_where)) {
		        			$this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, "绑定产品不属于当前登录厂家或不存在");
		        		}
	        			break;
	        		
	        		case 'chuchang_time':
	        			if (date('Ymd', $v) < date('Ymd', $check['shengchan_time'])) {
	        				$this->throwException(ErrorCode::CHUCHANG_TIME_NOT_LT_SHENGCHAN_TIME);
	        			}
	        			break;

	        		// “已停用”，一旦提交成功，则状态不允许再次修改成”已启用“
	        		case 'is_disable':
	        			if ($v != 1) {
	        				$this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
	        			}
	        			break;
	        	}
			}
        }

        if (count($return)) {
        	$return['active_json'] = isset($return['active_json']) ? $return['active_json'] + $active_json : $active_json;
            $return['active_json']['cant_active_credence_day'] = intval($return['active_json']['cant_active_credence_day']);
            $return['active_json']['active_credence_day'] = intval($return['active_json']['active_credence_day']);
            $return['active_json']['active_reward_moth'] = intval($return['active_json']['active_reward_moth']);
        	$return['active_json'] = json_encode($return['active_json'], JSON_UNESCAPED_UNICODE);
        }
        
        return $return;
	}

	public function updateDetailByCode($code = '', $put = [])
	{
		$f_id = AuthService::getAuthModel()->factory_id;
		$model = BaseModel::getInstance(factoryIdToModelName($f_id));
        $code = AuthService::getAuthModel()->factory_type.AuthService::getAuthModel()->code.mb_strcut($code, -8);

		$where = [
                  'code' => $code,
                  'factory_id' => $f_id,
            ];
		$data = $model->getOne([
                      'code' => $code,
                      'factory_id' => $f_id,
                ]);

		if (!$data) {
			return $this->addDetailByCode($code, $put);
		}
		$update = $this->ruleUpdateYimaData($data, $put);
		if (count($update)) {
			$model->update($where, $update);
		}
	}

	public function addDetailByCode($code, $put = [])
	{
		$f_id = AuthService::getAuthModel()->factory_id;
		$water = substr($code, -8);
		$water_code = AuthService::getAuthModel()->factory_type.AuthService::getAuthModel()->code.$water;
		
		if ($water_code != $code) {
			$this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
		}

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
	            "member_id"         => 0,
	            "user_name"         => "",
	            "user_tel"          => "",
	            "user_address"      => "{}",
	            "active_time"       => 0,
	            "register_time"     => 0,
	            "saomiao"           => 0,
	            "is_disable"        => 0,
	        ];

        } else {
        	$this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
        }
        
        $is_add_arr = $this->ruleUpdateYimaData($data, $put);

        if (count($is_add_arr)) {
        	$add = $is_add_arr + $data;

        	BaseModel::getInstance(factoryIdToModelName($f_id))->insert($add);
        }
	}
	
	// 易码申请记录绑定详情的码段数据
	public function yimaCodeNumsBindInfoCount($qrdata = [], $qrlist = [])
	{
		$pro_ids = arrFieldForStr($qrlist, 'product_id');
		$pro_list = (new \Admin\Logic\ProductLogic())->getProductExendInfoListByIds($pro_ids, true);
		
		$first_code = $qrdata['first_code'];
		$last_code = $qrdata['last_code'];
		$nums = $qrdata['nums'];

        $next_span_nums = $nums < self::NEXT_SPAN_MUNS ? $nums : self::NEXT_SPAN_MUNS;
		$span_arr = [];
		$span_num = ceil($nums/$next_span_nums);

        $active_search = [];
		$used_nums = 0;
		while ($span_num > 0) {
			$list = null;

			--$span_num;
			$one = end($span_arr)['last'] ? end($span_arr)['last'] + 1 : $first_code;

			$two = $one + $next_span_nums - 1;
			$two = $two > $last_code ? $last_code : $two;
			$str = $one.','.$two;

			foreach ($qrlist as $k => $v) {
				if ($one <= $v['qr_first_int'] && $v['qr_first_int'] <= $two) {
					$data = $v;
					unset($qrlist[$k]);
					if ($v['qr_last_int'] > $two) {
						$data['qr_last_int'] = $two;
						$data['nums'] = $two - $data['qr_first_int'] + 1;

						$v['qr_first_int'] = $two + 1;
						$v['nums'] = $v['qr_last_int'] - $v['qr_first_int'] + 1;
						$qrlist[$k] = $v;
					}
                    $data['active_num'] = 0;
					$data['active_json'] = json_decode($data['active_json'], true);
					$data['product_info'] = $pro_list[$data['product_id']];
					$used_nums += $data['nums'];

                    // 分段搜索 已激活数量 的条件
                    $data['qr_last_int'] && $data['qr_first_int'] && $active_search[] = " WHEN {$data['qr_first_int']} <= water AND water <= {$data['qr_last_int']} THEN '".$one.','.$two.'_'.count($list)."' ";

                    // 如果数据完全正确，旧没有可能前面的被覆盖，不正确的时候有可能覆盖($data['qr_first_int']+$data['qr_last_int'])/2 可以减少覆盖的可能
					$list[($data['qr_first_int']+$data['qr_last_int'])/2] = $data;
                    ksort($list);
				}
			}

			$span_arr[$str] = [
				'first' => (string)$one,
				'last'  => (string)$two,
				'nums'  => $next_span_nums,
				'qr_list'  => array_values($list),
			];
		}
        // 分段搜索 已激活数量
        if ($active_search) {
            $sql =  'SELECT '.'CASE'.implode('', $active_search).' END AS text,count(*) as active_nums'.' FROM '.factoryIdToModelName($qrdata['factory_id']).' WHERE active_time > 0 AND factory_id = '.$qrdata['factory_id'].' group by text';
            $active_nums = M()->query($sql);
            
            foreach ($active_nums as $k => $v) {
                $active_nums_arr = explode('_', $v['text']);
                $one = reset($active_nums_arr);
                $two = end($active_nums_arr);
                $qr_ex_codes = $span_arr[$one]['qr_list'][$two];
                isset($qr_ex_codes) && $span_arr[$one]['qr_list'][$two]['active_num'] = $v['active_nums'];
            }   
        }

		$return = [
			'bind_list' => array_values($span_arr),
			'not_bind_nums' => $qrdata['nums'] - $used_nums,
			'bind_nums' => $used_nums,
		];

		return $return;
	}

	public function ruleGroupPCode($p_code = '', $min_nums = 0)
	{
		$p_code = array_filter(explode(',', $p_code));
		// var_dump($p_code);
		$arr = [];
		foreach ($p_code as $k => $v) {
			$next = $p_code[$k+1];

			if (isset($next)) {
				$now_nums = end(explode('_', $v));
                $next_num = reset(explode('_', $next));

                // if ($now_nums == end($p_code) || $next_num == reset($p_code)) {
                if ($now_nums == $next_num) {
                    continue;
                }

                $first_code = $now_nums + ($k == 0 ? 0 : 1);
                $last_code  = $next_num - ($k+1== count($p_code)-1 ? 0 : 1);

				$code_num = $last_code - $first_code + 1;
                
				if ((($min_nums == 0 && $code_num) || ($min_nums > 0 && $code_num >= $min_nums)) && $code_num > 0) {
					$arr[] = [
						'nums' => ''.$code_num,
                        'first_code' => ''.$first_code,
						'last_code' => ''.$last_code,
					];
				}
			}
		}

		return $arr;
	}

    public function ruleGroupPCodeELT($p_code = '')
    {
        $p_code = array_filter(explode(',', $p_code));
        // var_dump($p_code);
        $arr = [];
        foreach ($p_code as $k => $v) {
            $next = $p_code[$k+1];

            if (isset($next)) {
                $now_nums_first = reset(explode('_', $v));
                $now_nums = end(explode('_', $v));
                $next_num = reset(explode('_', $next));
                $next_num_last = end(explode('_', $next));

                // if ($now_nums == end($p_code) || $next_num == reset($p_code)) {
                if ($now_nums == $next_num) {
                    continue;
                }

                $first_code = $now_nums;
                $last_code  = $next_num;

                if ($next_num_last < $last_code) {
                    $last_code = $next_num_last;
                    // $p_code[$k+1] = implode('_', [$next_num, $last_code]);
                }

                $code_num = ($last_code-1) - $first_code;
                
                if ($code_num < 0) {
                    $arr[] = [
                        'nums' => ''.$code_num,
                        'first_code' => ''.$first_code,
                        'last_code' => ''.$last_code,
                    ];
                }
            }
        }

        return $arr;
    }

    public function summary($factory_id)
    {
        $max_code = BaseModel::getInstance('factory_excel')
            ->query("SELECT max(`last_code`) max_code FROM factory_excel WHERE factory_id={$factory_id}");
        $max_code = $max_code[0]['max_code'] - 10000000;   // 默认由10000000开始

        $total_bind = BaseModel::getInstance('factory_product_qrcode')
            ->query("SELECT sum(`nums`) total_bind FROM factory_product_qrcode WHERE factory_id={$factory_id}");
        $total_bind = $total_bind[0]['total_bind'];

        $table_name = factoryIdToModelName($factory_id);
        $active_num = BaseModel::getInstance($table_name)
            ->query("SELECT count(*) active_num FROM {$table_name} WHERE factory_id={$factory_id} AND active_time>0");
        $active_num = $active_num[0]['active_num'];


        return [
            'total_apply' => $max_code > 0 ? $max_code : 0,
            'total_bind' => intval($total_bind),
            'unused_num' => $max_code > 0 ? $max_code - $total_bind : 0,
            'active_num' => intval($active_num),
        ];
	}

    public function monthSummary($factory_id)
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (!$start_time && !$end_time) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择时间');
        }
        if ($end_time < $start_time && $end_time != 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '结束时间不能小于开始时间');
        }
        $start_time = new Carbon(date('Y-m-d', $start_time));
        $end_time = new Carbon(date('Y-m-d', $end_time));

        $start_time->day(1);    // 计算到选择时间的1号开始
        $end_time->day(1)->addMonth(1); // 计算到下个月1号，确保这个月的数据能全部显示

        $type = I('type');

        $factory = AuthService::getAuthModel();
        $is_export = I('export');
        $limit = 12;
        $table_name = factoryIdToModelName($factory_id);
        if ($type == 1) {
            $type_name = '激活';
            $export_template = './Public/统计-月份激活数据导出模板.xls';
            $list_count = BaseModel::getInstance($table_name)->getList([
                'where' => [
                    'factory_id' => $factory_id,
                    'register_time' => [
                        ['EGT', $start_time->timestamp],
                        ['LT', $end_time->timestamp]
                    ],
                ],
                'group' => 'date',
                'order' => 'date ASC',
                'field' => 'from_unixtime(`register_time`, "%Y%m") date, count(*) num',
                'limit' => $limit,
            ]);
        } else {
            $type_name = '售后';
            $export_template = './Public/统计-月份售后数据导出模板.xls';
            $factory_code = $factory['factory_type'] . $factory['code'];

            $list_count = BaseModel::getInstance('worker_order')->getList([
                'where' => [
                    'worker_order.factory_id' => $factory_id,
                    'create_time' => [
                        ['EGT', $start_time->timestamp],
                        ['LT', $end_time->timestamp],
                    ],
                    'register_time' => ['GT', 0],
                    'worker_order_product.yima_code' => ['LIKE', "{$factory_code}%"],
                ],
                'join' => [
                    'LEFT JOIN worker_order_product ON worker_order_product.worker_order_id=worker_order.id',
                    "LEFT JOIN {$table_name} ON {$table_name}.code=worker_order_product.yima_code"
                ],
                'group' => 'date',
                'order' => 'date ASC',
                'field' => 'from_unixtime(`create_time`, "%Y%m") date, count(*) num',
                'limit' => $limit,
            ]);
        }

        $new_list_count = [];
        if (isset($list_count[0])) {
            $first = new Carbon($list_count[0]['date'] . '01');
            while ($start_time->lt($first)) {
                $new_list_count[] = [
                    'date' => $start_time->format('Ym'),
                    'num' => '0',
                ];
                $start_time->addMonth();
            }
        }
        foreach ($list_count as $key => $item) {
            $new_list_count[] = $item;
            $date = new Carbon($item['date'] . '01');
            $new_date = new Carbon(($list_count[$key + 1]['date'] ?? $list_count[$key]['date']) . '01');
            while ($date->addMonth()->lt($new_date)) {
                $new_list_count[] = [
                    'date' => $date->format('Ym'),
                    'num' => '0',
                ];

                $date->addMonth();
            }

            if (count($new_list_count) > $limit) {
                break;
            }

            // 最后一个元素的比较
            if ($key == count($list_count) - 1) {
                while ($date->lt($end_time)) {
                    $new_list_count[] = [
                        'date' => $date->format('Ym'),
                        'num' => '0',
                    ];
                    $date->addMonth();
                }
            }
        }

        $list_count = array_slice($new_list_count, 0, $limit);

        if ($is_export) {
            $objPHPExcel = \PHPExcel_IOFactory::load($export_template);

            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', "{$start_time->format('Y年m月')}—{$end_time->format('Y年m月')}产品激活情况数据表");

            $row = 3;
            foreach ($list_count as $item) {
                $column = 'A';
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue($column++ . $row, $item['date'])
                    ->setCellValue($column++ . $row, $item['num']);

                ++$row;
            }

            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            header('Content-Type: application/vnd.ms-excel');
            // header('Content-Disposition: attachment;filename='.$factory['factory_short_name']. '-易码月' . $type_name . '数据(' . date('Y.m.d H：i') . ')' . '.xls');
            header('Content-Disposition: attachment;filename='.date('Y年m月', $start_time->timestamp).'——'.date('Y年m月', $end_time->timestamp).'-易码月' . $type_name . '情况导出' . '.xls');
            header('Cache-Control: max-age=0');


            $objWriter->save('php://output');
        } else {
            return $list_count;
        }
	}

    public function areaSellSummary($factory_id)
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (!$start_time && !$end_time) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择时间');
        }
        if ($end_time < $start_time && $end_time != 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '结束时间不能小于开始时间');
        }
        $start_time = new Carbon(date('Y-m-d', $start_time));
        $end_time = new Carbon(date('Y-m-d', $end_time));

        $start_time->day(1);    // 计算到选择时间的1号开始
        $end_time->day(1)->addMonth(1); // 计算到下个月1号，确保这个月的数据能全部显示

        $type = I('type');

        $factory = AuthService::getAuthModel();
        $is_export = I('export');
        $limit = $is_export == 1 ? null : 10;
        $table_name = factoryIdToModelName($factory_id);

        // 省市
         $area_field = 'SUBSTRING_INDEX(TRIM(BOTH \'"\' FROM IFNULL(JSON_VALUE(user_address,"$.ids"),"")), \',\', 2)';
        // $area_field = '(SELECT SUBSTRING_INDEX(TRIM(BOTH \'"\' FROM IFNULL(user_address->"$.ids","")), \',\', 2) FROM' . " {$table_name} WHERE {$table_name}.code=worker_order_detail.code LIMIT 1)";
        if ($type == 1) {
            $type_name = '激活';
            $export_template = './Public/统计-销售区域激活数据导出模板.xls';
            $list_count = BaseModel::getInstance($table_name)->getList([
                'where' => [
                    'factory_id' => $factory_id,
                    'register_time' => [
                        ['EGT', $start_time->timestamp],
                        ['LT', $end_time->timestamp]
                    ],
                    // '_string' => $area_field.' != "" ',
                ],
                'group' => $area_field,
                'order' => 'num DESC',
                'field' => $area_field.' as area_ids, count(*) num',
                'limit' => $limit,
            ]);
        } else {
            $type_name = '售后';
            $export_template = './Public/统计-销售区域售后数据导出模板.xls';
            $factory_code = $factory['factory_type'] . $factory['code'];

            $list_count = BaseModel::getInstance('worker_order')->getList([
                'where' => [
                    'worker_order.factory_id' => $factory_id,
                    'create_time' => [
                        ['EGT', $start_time->timestamp],
                        ['LT', $end_time->timestamp],
                    ],
                    'register_time' => ['GT', 0],
                    'worker_order_product.yima_code' => ['LIKE', "{$factory_code}%"],
                    // '_string' => $area_field.' != "" ',
                ],
                'join' => [
                    'LEFT JOIN worker_order_product ON worker_order_product.worker_order_id=worker_order.id',
                    "LEFT JOIN {$table_name} ON {$table_name}.code=worker_order_product.yima_code"
                ],
                'group' => $area_field,
                'order' => 'num DESC',
                'field' => $area_field.' as area_ids, count(*) num',
                'limit' => $limit,
            ]);
        }

        $area_ids = arrFieldForStr($list_count, 'area_ids');
        $area_list = $area_ids ? BaseModel::getInstance('cm_list_item')->getList([
                'where' => [
                    'list_item_id' => ['in', $area_ids],
                ],
                'index' => 'list_item_id',
                'field' => 'list_item_id,item_desc',
            ]) : [];
        
        foreach ($list_count as $k => &$v) {

            $full = [];
            foreach (explode(',', $v['area_ids']) as $value) {
                $full[] =  $area_list[$value]['item_desc'];
            }
            $v['area_ids_full'] = implode('', $full);
            !$v['area_ids_full'] && ($v['area_ids_full'] = '没有填写省市');
            $v['area_ids_ex'] = implode(',', $full);
        }

        if ($is_export) {

            $objPHPExcel = \PHPExcel_IOFactory::load($export_template);
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', "{$start_time->format('Y年m月')}—{$end_time->format('Y年m月')}区域{$type_name}情况数据表");
            // $objPHPExcel->setActiveSheetIndex(0)
            //     ->setCellValue('A2', "省份")
            //     ->setCellValue('B2', "城市")
            //     ->setCellValue('C2', "产品激活数量");
            $row = 3;

            foreach ($list_count as $item) {
                $area = explode(',', $item['area_ids_ex']);
                $column = 'A';
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue($column++ . $row, $area[0] ? $area[0] : '没有填写省')
                    ->setCellValue($column++ . $row, $area[1] ? $area[1] : '没有填写市')
                    ->setCellValue($column++ . $row, $item['num']);

                ++$row;
            }

            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            header('Content-Type: application/vnd.ms-excel');
            // header('Content-Disposition: attachment;filename='.$factory['factory_short_name']. '-易码区域' . $type_name . '数据(' . date('Y.m.d H：i') . ')' . '.xls');
            header('Content-Disposition: attachment;filename='.date('Y年m月', $start_time->timestamp).'——'.date('Y年m月', $end_time->timestamp).'-易码区域' . $type_name . '情况导出' . '.xls');
            header('Cache-Control: max-age=0');


            $objWriter->save('php://output');
        } else {
            return $list_count;
        }
    }


    public function productSummary($factory_id)
    {
        $start_time = I('start_time');
        $end_time = I('end_time');
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        if (!$start_time && !$end_time) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择时间');
        }
        if ($end_time < $start_time && $end_time != 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '结束时间不能小于开始时间');
        }
        $start_time = new Carbon(date('Y-m-d', $start_time));
        $end_time = new Carbon(date('Y-m-d', $end_time));

        $start_time->day(1);    // 计算到选择时间的1号开始
        $end_time->day(1)->addMonth(1); // 计算到下个月1号，确保这个月的数据能全部显示

        $type = I('type');

        $is_export = I('export');
        $limit = $is_export == 1 ? null : 10;

        $table_name = factoryIdToModelName($factory_id);
        // TODO 所有产品都要显示，统计时只显示前10条，导出时所有都要导出
        if ($type == 1) {
            $type_name = '激活';
            $export_template = './Public/统计-产品激活数据导出模板.xls';
            $list_count = BaseModel::getInstance('factory_product')->getList([
                'where' => [
                    'ym.factory_id' => $factory_id,
                    'register_time' => [
                        ['EGT', $start_time->timestamp],
                        ['LT', $end_time->timestamp]
                    ],
                    // 'yima_status' => 0,
                    'factory_product.is_delete' => 0,
                ],
                'field' => 'count(*) num,ym.product_id,product_category,product_guige,product_brand,product_xinghao',
                'join' => "LEFT JOIN {$table_name} ym ON factory_product.product_id=ym.product_id",
                'group' => 'ym.product_id',
                'order' => 'num DESC',
                'limit' => $limit,
            ]);
        } else {
            $type_name = '售后';
            $export_template = './Public/统计-产品售后数据导出模板.xls';

            $list_count = BaseModel::getInstance('worker_order_product')->getList([
                'join' => [
                    'INNER JOIN factory_product ON factory_product.product_id=worker_order_product.product_id',
                    'LEFT JOIN worker_order ON worker_order.id=worker_order_product.worker_order_id',
                ],
                'where' => [
                    'worker_order.factory_id' => $factory_id,
                    'worker_order.create_time' => [
                        ['EGT', $start_time->timestamp],
                        ['LT', $end_time->timestamp],
                    ],
                    'worker_order_product.yima_code' => ['NEQ', ''],
                ],
                'field' => 'count(*) num,factory_product.product_id,product_category,product_guige,product_brand,product_xinghao',
                'group' => 'worker_order_product.yima_code',
                'order' => 'num DESC',
                'limit' => $limit,
            ]);
        }

        $product_id_product_map = [];
        $product_categories = [];
        $product_guides = [];
        $product_brands = [];
        foreach ($list_count as &$factory_product) {
            $product_categories[] = $factory_product['product_category'];
            $product_guides[] = $factory_product['product_guige'];
            $product_brands[] = $factory_product['product_brand'];

            $product_id_product_map[$factory_product['product_id']] = &$factory_product;
        }

        $product_category_id_name_map = $product_categories ? BaseModel::getInstance('cm_list_item')->getFieldVal([
            'list_item_id' => ['IN', $product_categories]
        ], 'list_item_id,item_desc', true) : [];
        $product_standard_id_name_map = $product_guides ? BaseModel::getInstance('product_standard')->getFieldVal([
            'standard_id' => ['IN', $product_guides],
        ], 'standard_id,standard_name', true) : [];
        $product_branch_id_name_map = $product_brands ? BaseModel::getInstance('factory_product_brand')->getFieldVal([
            'id' => ['IN', $product_brands],
        ], 'id,product_brand') : [];

        foreach ($list_count as $item) {
            $product_id_product_map[$item['product_id']]['category'] = $product_category_id_name_map[$item['product_category']];
            $product_id_product_map[$item['product_id']]['standard'] = $product_standard_id_name_map[$item['product_guige']];
            $product_id_product_map[$item['product_id']]['branch'] = $product_branch_id_name_map[$item['product_brand']];
        }

        if ($is_export) {
            $objPHPExcel = \PHPExcel_IOFactory::load($export_template);
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', "{$start_time->format('Y年m月')}—{$end_time->format('Y年m月')}产品{$type_name}情况数据表");
            $row = 3;
            if ($type == 1) {
                $product_ids = Arr::pluck($list_count, 'product_id');
                $product_id_info_map = $product_ids ? BaseModel::getInstance('factory_product_qrcode')->getList([
                    'where' => [
                        'factory_id' => $factory_id,
                        'product_id' => ['IN', $product_ids],
                    ],
                    'group' => 'product_id',
                    'field' => 'product_id,id factory_product_qrcode_id,sum(`nums`) total_num',
                    'index' => 'product_id',
                ]) : [];
                $factory_product_qrcode_ids = Arr::pluck($product_id_info_map, 'factory_product_qrcode_id');
                $exclude_product_id_num_map = $factory_product_qrcode_ids ? BaseModel::getInstance($table_name)->getList([
                    'alias' => 'ym',
                    'join' => 'LEFT JOIN factory_product_qrcode ON factory_product_qrcode.id=ym.factory_product_qrcode_id',
                    'where' => [
                        'factory_product_qrcode.id' => ['IN', $factory_product_qrcode_ids],
                        '_string' => 'ym.product_id!=factory_product_qrcode.product_id',
                    ],
                    'group' => 'ym.product_id',
                    'field' => 'factory_product_qrcode.product_id,count(*) num',
                    'index' => 'product_id',
                ]) : [];
                $include_product_id_num_map = $product_ids ? BaseModel::getInstance($table_name)->getList([
                    'alias' => 'ym',
                    'join' => 'LEFT JOIN factory_product_qrcode ON factory_product_qrcode.id=ym.factory_product_qrcode_id',
                    'where' => [
                        'ym.factory_id' => $factory_id,
                        'ym.product_id' => ['IN', $product_ids],
                        '_string' => 'ym.product_id!=factory_product_qrcode.product_id',
                    ],
                    'group' => 'ym.product_id',
                    'field' => 'ym.product_id,count(*) num',
                    'index' => 'product_id',
                ]) : [];
                $product_id_bind_num_map = [];
                foreach ($product_id_info_map as $product_id => $item) {
                    $product_id_bind_num_map[$product_id] = $item['total_num'] - ($exclude_product_id_num_map[$product_id]['num'] ?? 0) + ($include_product_id_num_map[$product_id]['num'] ?? 0);
                }

                foreach ($list_count as $item) {
                    $column = 'A';
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue($column++ . $row, $item['category'])
                        ->setCellValue($column++ . $row, $item['standard'])
                        ->setCellValue($column++ . $row, $item['branch'])
                        ->setCellValue($column++ . $row, $item['product_xinghao'])
                        ->setCellValue($column++ . $row, $product_id_bind_num_map[$item['product_id']] ?? 0)
                        ->setCellValue($column++ . $row, $item['num']);

                    ++$row;
                }
            } else {
                foreach ($list_count as $item) {
                    $column = 'A';
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue($column++ . $row, $item['category'])
                        ->setCellValue($column++ . $row, $item['standard'])
                        ->setCellValue($column++ . $row, $item['branch'])
                        ->setCellValue($column++ . $row, $item['product_xinghao'])
                        ->setCellValue($column++ . $row, $item['num']);

                    ++$row;
                }
            }


            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            header('Content-Type: application/vnd.ms-excel');
            // header('Content-Disposition: attachment;filename='.$factory['factory_short_name']. '-易码产品' . $type_name . '数据(' . date('Y.m.d H：i') . ')' . '.xls');
            header('Content-Disposition: attachment;filename='.date('Y年m月', $start_time->timestamp).'——'.date('Y年m月', $end_time->timestamp).'-易码产品' . $type_name . '情况导出' . '.xls');
            header('Cache-Control: max-age=0');


            $objWriter->save('php://output');
        } else {
            return $list_count;
        }
    }
}
