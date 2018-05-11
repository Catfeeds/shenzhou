<?php
/**
 * File: YimaController.class.php
 * User: zjz
 * Date: 2017/7/20
 */

namespace Admin\Controller;

use Admin\Logic\ProductLogic;
use Common\Common\Service\AuthService;
use Admin\Common\ErrorCode;
use Illuminate\Support\Arr;
use Library\Common\Util;
use Admin\Model\BaseModel;
// use Admin\Controller\BaseController;

class YimaController extends BaseController
{
      public function disableDetail()
      {
            $code = I('get.code', '');
            try {
                $this->requireAuthFactoryGetFid();
                  $logic = new \Admin\Logic\YimaLogic();
                  $logic->updateDetailByCode($code, ['is_disable' => 1]);

                  $this->okNull();
            } catch (\Exception $e) {
                  $this->getExceptionError($e);
            }
      }

      public function updateDetail()
      {
            $code = I('get.code', '');
            try {
                  $this->requireAuthFactoryGetFid();

                  $logic = new \Admin\Logic\YimaLogic();
                  $logic->updateDetailByCode($code, I('put.', []));

                  $this->okNull();
            } catch (\Exception $e) {
                  $this->getExceptionError($e);
            }
      }

      public function yimaDetail()
      {     
            $f_id = I('get.factory_id', 0);
            $code = I('get.code', '');
      
            try {
                  if ($f_id) {
                        $factoty = BaseModel::getInstance('factory')->getOneOrFail($f_id);

                        // $pre_code = $factoty['factory_type'].$factoty['code'];
                        // if (substr($code, -8) == $code) {
                        //       $code = $pre_code.$code;
                        // }
                        $pre_code = $factoty['factory_type'].$factoty['code'];
                        $str_num = strlen($pre_code);
                        $cut_code = substr($code, $str_num);
                        $code = str_replace($pre_code, '', $code) == $cut_code ? $pre_code.$cut_code : $pre_code.$code;
                        $data = (new \Api\Model\YimaModel())->getYimaInfoByCode($code);
                        // $data = BaseModel::getInstance(factoryIdToModelName($f_id))->getOne([
                        //       'code' => $code,
                        //       'factory_id' => $f_id,
                        // ]);
                        // isset($data['active_json']) && $data['active_json'] = (array)json_decode($data['active_json'], true);
                        // var_dump($code);die;
                  } else {
                        $f_id = getFidByCode($code);
                        $data = (new \Api\Model\YimaModel())->getYimaInfoByCode($code);
                        $factoty = BaseModel::getInstance('factory')->getOneOrFail($data['factory_id']);
                  }
                  
                  //     $factoty['factory_type'].$factoty['code'] != substr($code, 0,4)
                  // &&  $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR  , '易码参数错误,不符合规范');

                  if ($data) {
                        $data['bind_time'] = BaseModel::getInstance('factory_product_qrcode')->getFieldVal($data['factory_product_qrcode_id'], 'datetime');
                  }
                  
                  // (!$data['code'] || !$data['factory_product_qrcode_id']) && $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
                  // 厂家信息
                  $data['factory_full_name'] = $factoty['factory_full_name'];
                  $data['factory_short_name'] = $factoty['factory_short_name'];

                  (!$data['code'] || (!$data['factory_product_qrcode_id'] && $data['factory_product_qrcode_id'] != 0) ) && $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);

                  // 产品信息
                  $product_data = $data['product_id'] ? BaseModel::getInstance('factory_product')->getOne($data['product_id'], 'product_id,product_xinghao,product_category,product_guige,product_brand') : [];
                  $cm_data = $product_data['product_category'] ? BaseModel::getInstance('cm_list_item')->getOne([
                      'list_item_id' => $product_data['product_category'],
                  ]) : [];
                  $guige_data = $product_data['product_guige'] ? BaseModel::getInstance('product_standard')->getOne([
                      'standard_id' => $product_data['product_guige'],
                  ]) : [];
                  $brand_data = $product_data['product_brand'] ? BaseModel::getInstance('factory_product_brand')->getOne([
                      'id' => $product_data['product_brand']
                  ]) : [];
                  $product_data['product_category_desc'] = $cm_data['item_desc'];
                  $product_data['product_guige_desc'] = $guige_data['standard_name'];
                  $product_data['product_brand_desc'] = $brand_data['product_brand'];
                  $data['product_info'] = $product_data;

                  // 激活人信息
                  $data['bill'] = null;
                  $data['register_user'] = null;

                  if ($data['member_id'] && $data['register_time']) {
                        $data['register_user'] = BaseModel::getInstance('wx_user')->getOne($data['member_id'], 'id,telephone,user_type,nickname');
                        
                        $bill = null;
                        if ($data['register_user']['user_type'] == 1) {
                              $bill = BaseModel::getInstance('dealer_bind_products')->getFieldVal([
                                          'code' => $code,
                                    ], 'bill');
                        }
                        if (!$bill) {
                              $bill = BaseModel::getInstance('wx_user_product')->getFieldVal([
                                          '_complex' => [
                                                '_logic' => 'or',
                                                'code' => $code,
                                                'md5code' => md5($code),
                                          ],
                                    ], 'bill');
                        }

                        $data['bill'] = $bill ? Util::getServerFileUrl($bill) : $bill;
                  }


                  // 用户地址
                  if ($data['user_address']) {
                        $user_address = json_decode($data['user_address'], true);
                        $data['user_address'] = is_array($user_address) ? $user_address : [
                              'ids' => '',
                              'names' =>  '',
                              'address' => $data['user_address'],
                        ];
                  } else {
                        $data['user_address'] = null;
                  }

                  // 质保策略
                  // $data['active_json'] = (array)json_decode($data['active_json'], true);
                  $active_arr = [
                        'is_active_type'            => '1,2',                       // 1,2  1消费者， 2经销商
                        'is_order_type'             => '1,2',                       // 1,2  1消费者， 2经销商
                        'active_credence_day'       => 0,                           // 需要上传发票   单位天
                        'cant_active_credence_day'  => 0,                           // 禁止激活产品   单位天
                        'active_reward_moth'         => 0,                           // 激活赠送延保   单位天
                  ];
                  $data['active_json'] += $active_arr;

                  // 质保时间
                  $data['active_end_time'] = '0';
                  if ($data['active_time'] && $data['zhibao_time']) {
                        $data['active_end_time'] = $data['active_time'] ? 
                                                   get_limit_date($data['active_time'], $data['zhibao_time'] + $data['active_json']['active_reward_moth']) : 
                                                   null;      
                  
                  }

                  // 售后次数
                  $data['about_worker_order_nums'] = 0;
                  $data['about_worker_order'] = null;
                  if ($data['register_time']) {
                        $data['about_worker_order'] = BaseModel::getInstance('worker_order')->getList([
                                    'alias' => 'WO',
                                    'where' => [
                                          'WOP.yima_code' => $code,
                                    ],
                                    'join'  => 'LEFT JOIN worker_order_product WOP ON WO.id = WOP.worker_order_id',
                                    'field' => 'WO.id order_id,WO.orno,WO.create_time datetime',
                                    'group' => 'WO.id',
                              ]);
                        $data['about_worker_order_nums'] = count($data['about_worker_order']);
                  }


                  $this->response($data);
            } catch (\Exception $e) {
                  $this->getExceptionError($e);
            }
      }

	public function searchBetweenCode()
	{
		$search_nums = I('get.nums', 0, 'intval');
		try {
			$token_id = $this->requireAuthFactoryGetFid();
                  $model = new \Admin\Model\FactoryExcelModel();
                  $list = $model->groupPCodeByFactoryIdAndNums($token_id, $search_nums);
			$this->response($list);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}
	
	public function yimasForQr()
	{
            $page_code        = I('get.page_code', 0);      //
            $page_type        = I('get.page_type', 'next'); //

		$ex_id 	      = I('get.excel_id',		0, 'intval');  	// 申请记录id
		$qr_id 	      = I('get.qr_id',			0, 'intval');  	// 绑定码段id
		$qr_start 		= I('get.qr_start', 		0, 'intval');	// 码段开始
		$qr_end 		= I('get.qr_end', 	      0, 'intval');  	// 码段结束
		$chuchang_start   = I('get.chuchang_start', 	0, 'intval');  	// 出场开始时间
		$chuchang_end 	= I('get.chuchang_end', 	0, 'intval');  	// 出场结束时间
		$active_start 	= I('get.active_start', 	0, 'intval');  	// 激活开始时间
		$active_end 	= I('get.active_end', 		0, 'intval');  	// 激活结束时间
		$yima_status 	= I('get.yima_status', 		0, 'intval');  	// 二维码状态  0全部 1启用  2停用
		$zhibao_status 	= I('get.zhibao_status', 	0, 'intval');  	// 质保状态 0全部 1未过保 2已过保
		$user_type 		= I('get.user_type', 		0, 'intval');  	// 激活角色 0全部 1经销商 2普通用户高
		$active_phone 	= I('get.active_phone', 	'');  		// 激活人手机号码
		$user_phone 	= I('get.user_phone', 		'');  		// 用户手机号码
		$user_area 		= I('get.user_area', 		''); 			// 用户地区
		$cate_id		= I('get.cate_id', 		0, 'intval'); 	// 品类id
		$brand_id		= I('get.brand_id', 		0, 'intval'); 	// 品牌id
		$guige_id		= I('get.guige_id', 		0, 'intval'); 	// 规格id
		$product_id		= I('get.product_id', 		0, 'intval'); 	// 产品id
		try {
            $this->requireAuthFactoryGetFid();
            $token_id = 1;
            //     AuthService::getModel() != 'factory'
            // &&  $this->throwException(ErrorCode::NOT_FACTORY);
            $yima_where = $where = [
                  'factory_id' => $token_id,
            ];

            $yima_name = factoryIdToModelName($token_id);
            $yima_model = BaseModel::getInstance($yima_name);
		$model = BaseModel::getInstance('factory_product_qrcode');

		// TODO 需要将 搜索条件处理 封装起来
		// 码段id 条件
		if ($qr_id) {
                  $yima_where['factory_product_qrcode_id'] = $qr_id;
            }

            // 申请记录id条件 转换 码段id 条件
            if ($ex_id && !isset($yima_where['factory_product_qrcode_id'])) {
            	$where['factory_excel_id'] = $ex_id;
            	$qr_list = $model->getList($where);
            	$factory_product_qrcode_ids = arrFieldForStr($qr_list, 'id');
            	!$factory_product_qrcode_ids && $this->response();

            	$where['id'] = count($qr_list) == 1 ? $factory_product_qrcode_ids : ['in', $factory_product_qrcode_ids];
            	$yima_where['factory_product_qrcode_id'] = $where['id'];

            	unset($where['factory_excel_id']);
            }

            // 二维码码段
            if ($qr_start) {
            	$yima_where['water'] = $where['qr_last_int'] = ['EGT', $qr_start];
            }
            if ($qr_end) {
            	$where['qr_first_int'] = ['ELT', $qr_end];
            	$yima_where['water'] = isset($yima_where['water']) ? ['between', "{$qr_start},{$qr_end}"] : $where['qr_first_int'];
            }

            // 出厂时间
            if ($chuchang_start) {
            	$yima_where['chuchang_titme'] = $where['chuchang_titme'] = ['EGT', $chuchang_start];
            }
            if ($chuchang_end) {
            	$where['chuchang_titme'] = 	isset($where['chuchang_titme']) ? 
            								['between', "{$chuchang_start},{$chuchang_end}"] : 
            								['ELT', $chuchang_end];
            	$yima_where['chuchang_titme'] = $where['chuchang_titme'];
            }

            // 激活时间 应该是 购买时间
            if ($active_start) {
            	$yima_where['active_time'] = ['EGT', $active_start];
            }
            if ($active_end) {
            	$yima_where['active_time'] = 	isset($where['active_time']) ? 
            									['between', "{$active_start},{$active_end}"] : 
            									['ELT', $active_end];
            }

            // 质保状态 1未过保 2已过保 未激活 TODO 质保期算的不对
            if (in_array($zhibao_status, [1, 2, 3])) {
            	$yima_where['_string'] .= isset($yima_where['_string']) ? ' AND ' : '';
            	switch ($zhibao_status) {
            		case 1:
            			$yima_where['_string'] .= ' (active_time > 0 AND '.NOW_TIME.' <= active_time +  zhibao_time * 24 * 3600 ) ';
            			break;
            		
            		case 2:
            			$yima_where['_string'] .= ' (active_time > 0 AND '.NOW_TIME.' > active_time +  (zhibao_time * 24 * 3600)) ';
            			break;

                        case 3:
                              $yima_where['active_time'] = 0;
                              break;
            	}
            }

            // 激活角色 1经销商 2普通用户高
            if (in_array($user_type, [1, 2])) {
            	$yima_where['_string'] .= isset($yima_where['_string']) ? ' AND ' : '';
            	$yima_where['_string'] .= ' (member_id > 0 AND (SELECT user_type FROM wx_user WHERE id = member_id) = '.($user_type - 1).') ';
            }

            // 激活人手机号码
            if (!empty($active_phone)) {
            	$yima_where['_string'] .= isset($yima_where['_string']) ? ' AND ' : '';
            	$yima_where['_string'] .= ' (member_id > 0 AND (SELECT user_type FROM wx_user WHERE id = member_id) = 1) AND (SELECT user_type FROM wx_user WHERE id = member_id) LIKE "%'.$active_phone.'%") ';
            }

            // 用户手机号码
            if (!empty($user_phone)) {
            	$yima_where['user_tel'] = ['like', '%'.$user_phone.'%'];
            }

            // 用户地区
            $user_area_ids = !empty($user_area) ? implode(',', array_filter(explode(',', $user_area))) : '';
            if ($user_area_ids) {
            	$cms = BaseModel::getInstance('cm_list_item')->getList([
            			'list_item_id' => ['in', $user_area_ids]
            		]);
            	$area_names = arrFieldForStr($cms, 'item_desc', '');
            	$yima_where['user_address'] = ['like', '%'.$area_names.'%'];
            }

            // 产品id
            if ($product_id) {
                  $yima_where['product_id'] = $where['product_id'] = $product_id;
            } elseif ($cate_id || $brand_id || $guige_id) {
                  $fp_where = [
                        'product_category' => $cate_id,
                        'product_guige' => $guige_id,
                        'product_brand' => $brand_id,
                  ];
                  $pro_ids = arrFieldForStr(BaseModel::getInstance('factory_product')->getList(array_filter($fp_where)), 'product_id');
                  !$pro_ids && $this->response();

                  $yima_where = $where['product_id'] = ['in', $pro_ids];
            }

            $has_pre = $has_next = false;
            $first_qr_code = $last_qr_code = 0;

            $order = 'DESC';
            if ($page_code) {
                  if ($page_type == 'pre') {
                        $where['qr_first_int'] = $yima_where['water'] = ['GT', $page_code];

                        $first_qr_one = $model->getOne(array_merge($where, ['order' => "water {$order}"]));
                        $first_yima_one = $yima_model->getOne(array_merge($yima_where, ['order' => "water {$order}"]));

                        if (!$first_qr_one && !$first_yima_one) {
                              $this->response();
                        } elseif ($first_yima_one['water'] > $first_qr_one['qr_last_int']) {
                              $first_qr_code = $first_yima_one['water'];
                        } elseif ($first_yima_one['water'] < $first_qr_one['qr_last_int']) {
                              $first_qr_code = $first_qr_one['qr_last_int'];
                        } else {
                              $first_qr_code = $first_qr_one['qr_last_int'];
                        }

                  } else {
                        $where['qr_last_int'] = $yima_where['water'] = ['LT', $page_code];
                  }

            }

            $page_no = I('page_no', 1, 'intval');
            $page_size = I('page_size', 10, 'intval');
            $limit_page = getPage($page_no, $page_size + 1);

            $yima_opt = [
                  'where' => $yima_where,
                  'limit' => $limit_page,
                  'order' => "water {$order}",
            ];
            $opt = [
                  'where' => $where,
                  'field' => 'id',
                  'index' => 'id',
                  'order' => "qr_first_int {$order}",
                  'limit' => $limit_page,
            ];

            $list = $yima_list = [];

            $qr_count_data = reset($model->getList([
                        'field' => 'COUNT(id) as count,SUM(nums) as code_nums',
                        'where' => $opt['where'],
                  ]));
            // var_dump($qr_count_data);die;
            $qr_list_ids = '';
            if ($qr_count_data['count']) {
                  $qr_list = $model->getList($opt);
                  $qr_list_ids = implode(',', array_keys($qr_list));
            }

            $list = null;
            $nums = $not_qr_in_yima = $not_yima_in_qr = 0;
            if ($qr_list_ids) {
                  // $yima_list = $yima_model->getList($yima_opt);
                  // $one =  M()->_sql();

                  // $not_yima_list_sql = str_replace('`', ' ', reset(explode(' LIMIT ', reset(explode(' limit ', $one)))));
                  // $cut_str = reset(explode('FROM  '.$yima_name.'  WHERE', $not_yima_list_sql));
                  // $not_yima_list_sql = str_replace($cut_str, 'SELECT water ', $not_yima_list_sql);
                  
                  // // 不在申请记录里面又符合查询条件的易码
                  // $in_qr_not_yima_where = $not_yima_where = [
                  //       'factory_product_qrcode_id' => ['not in', $qr_list_ids],
                  // ];
                  // $not_yima_where['_string'] .= " water IN ({$not_yima_list_sql}) ";
                  // $not_qr_in_yima = $yima_model->getNum($not_yima_where);
                  
                  // // 在申请记录范围却不在申请条件的易码
                  // $not_yima_where = [
                  //       'factory_product_qrcode_id' => ['in', $qr_list_ids],
                  // ];
                  // $not_yima_where['_string'] .= " water NOT IN ({$not_yima_list_sql}) ";
                  // $not_yima_in_qr = $yima_model->getNum($not_yima_where);

                  // //  符合条件的总数 = 申请记录的nums的和 + 不在申请记录里面又符合查询条件的数量 - 在申请记录范围却不在查询条件的数量
                  // $nums = $qr_count_data['code_nums'] + $not_qr_in_yima + $not_yima_in_qr;

                  // $last_qr_code

                  // $yima_list = $yima_model->getList($yima_opt);

            } else {
                  $nums = $yima_model->getNum($yima_opt['where']);
                  !$nums && $this->response();
                  $list = $yima_model->getList($yima_opt);
            }

            $response = [
                  'has_pre'   => reset($list)['water'] != $first_qr_code ? true : false,
                  'data_list' => $list,
                  'has_next'  => $list == $page_size + 1 ? true : false,
            ];

            if ($response['has_next']) {
                  unset($response['data_list'][count($response['data_list'])]);
            }

            $this->response($response);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

    /**
     * 易码列表：
     * 1.获取在易码详情(yima_x)中且符合条件的前10条
     * 2.查询所有分段列表（factory_product_qrcode）
     * 3.查询本来符合条件但修改过后变为不符合条件的易码
     * 4.将2中查询到的按顺序循环，并与1和3中的对比获取正确的列表
     */
    public function getList()
    {
        try {
            $factory = $this->requireAuthFactoryGetFactory();

            $last_code = I('last_code');
            // 1下一页，2上一页
            $order_type = I('order_type', 1);

            if ($order_type == 1) {
                $order = 'DESC';
                $last_code && --$last_code;
            } else {
                $order = 'ASC';
                $last_code && ++$last_code;
            }

            $code_start = I('code_start');
            $code_end = I('code_end');
            // 去除厂家code
            if ($code_start && strlen($code_start) > 8) {
                $code_start = substr($code_start, -8);
            }
            if ($code_end && strlen($code_end) > 8) {
                $code_end = substr($code_end, -8);
            }


            $qrcode_where['factory_product_qrcode.factory_id'] = $factory['factory_id'];
            $yima_where['ym.factory_id'] = $factory['factory_id'];
            $yima_changed_not_in_where['ym.factory_id'] = $factory['factory_id'];
            // 计算code的范围
            if ($code_end && $code_start) {
                if ($code_start && $code_end) {
                    if ($code_start > $code_end) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '二维码尾码不能小于首码，请重新输入后再搜索');
                    }
                }

                if ($order_type == 1) {
                    $code_end = $last_code ? ($last_code > $code_end ? $code_end : $last_code) : $code_end;
                } else {
                    $code_start = $last_code ? ($last_code > $code_start ? $last_code : $code_start) : $code_start;
                }

                $qrcode_where['_string'] = "(qr_first_int<={$code_start} AND qr_last_int>={$code_start} OR qr_first_int<={$code_end} AND qr_last_int>={$code_end} OR qr_first_int>={$code_start} AND qr_last_int<={$code_end})";
                $yima_where['water'] = [['EGT', $code_start], ['ELT', $code_end]];
                $yima_changed_not_in_where['water'] = [['EGT', $code_start], ['ELT', $code_end]];
            } elseif ($code_end) {
                if ($order_type == 1) {
                    $code_end = $last_code ? ($last_code > $code_end ? $code_end : $last_code) : $code_end;
                    $qrcode_where['_string'] = "(qr_first_int<={$code_end} AND qr_last_int>={$code_end})";
                } else {
                    $code_start = $last_code;
                    $qrcode_where['_string'] = "(qr_first_int<={$code_start} AND qr_last_int>={$code_start} OR qr_first_int<={$code_end} AND qr_last_int>={$code_end})";
                }


                $yima_where['water'] = ['ELT', $code_end];
                $yima_changed_not_in_where['water'] = ['ELT', $code_end];
            } elseif ($code_start && $last_code) {
                if ($order_type == 1) {
                    $code_end = $last_code;
                    $qrcode_where['_string'] = "(qr_first_int<={$code_start} AND qr_last_int>={$code_start} OR qr_first_int<={$code_end} AND qr_last_int>={$code_end})";
                } else {
                    $code_start = $last_code ? ($last_code > $code_start ? $last_code : $code_start) : $code_start;
                    $qrcode_where['_string'] = "(qr_first_int<={$code_start} AND qr_last_int>={$code_start})";
                }


                $yima_where['water'] = [['EGT', $code_start], ['ELT', $code_end]];
                $yima_changed_not_in_where['water'] = [['EGT', $code_start], ['ELT', $code_end]];
            } elseif ($code_start) {
                $qrcode_where['_string'] = "(qr_last_int>={$code_start})";
                $yima_where['water'] = ['EGT', $code_start];
                $yima_changed_not_in_where['water'] = ['EGT', $code_start];
            } elseif ($last_code) {
                if ($order_type == 1) {
                    $code_end = $last_code;
                    $qrcode_where['qr_first_int'] = ['ELT', $code_end];
//                    $qrcode_where['_string'] = "(qr_first_int<={$code_end} AND qr_last_int>={$code_end})";
                    $yima_where['water'] = ['ELT', $code_end];
                    $yima_changed_not_in_where['water'] = ['ELT', $code_end];
                } else {
                    $code_start = $last_code;
//                    $qrcode_where['_string'] = "(qr_first_int<={$code_start} AND qr_last_int>={$code_start})";
                    $qrcode_where['qr_last_int'] = ['EGT', $code_start];
                    $yima_where['water'] = ['EGT', $code_start];
                    $yima_changed_not_in_where['water'] = ['EGT', $code_start];
                }
            }



            // 出厂时间
            $chuchang_start   = I('get.chuchang_start');  	// 出厂开始时间
            $chuchang_end 	= I('get.chuchang_end');  	// 出厂结束时间
            $chuchang_start = strtotime($chuchang_start);
            $chuchang_end = strtotime($chuchang_end);
            if ($chuchang_start && $chuchang_end) {
                $qrcode_where['chuchang_time'] = [
                    ['EGT', $chuchang_start],
                    ['ELT', $chuchang_end],
                ];
                $yima_where['ym.chuchang_time'] = [
                    ['EGT', $chuchang_start],
                    ['ELT', $chuchang_end],
                ];
                $yima_changed_not_in_where['ym.chuchang_time'] = [['LT', $chuchang_start], ['GT', $chuchang_end], '_logic' => 'or'];
            } elseif ($chuchang_start) {
                $qrcode_where['chuchang_time'] = ['EGT', $chuchang_start];
                $yima_where['ym.chuchang_time'] = ['EGT', $chuchang_start];
                $yima_changed_not_in_where['ym.chuchang_time'] = ['LT', $chuchang_start];
            } elseif ($chuchang_end) {
                $qrcode_where['chuchang_time'] = ['ELT', $chuchang_end];
                $yima_where['ym.chuchang_time'] = ['ELT', $chuchang_end];
                $yima_changed_not_in_where['ym.chuchang_time'] = ['GT', $chuchang_end];
            }

            $need_actived = false;

            // 购买时间
            $active_start 	= I('get.active_start');
            $active_end 	= I('get.active_end');
            $active_start = strtotime($active_start);
            $active_end = strtotime($active_end);
            if ($active_start && $active_end) {
                $need_actived = true;
                $yima_where['active_time'] = [
                    ['EGT', $active_start],
                    ['ELT', $active_end],
                ];
            } elseif ($active_start) {
                $need_actived = true;
                $yima_where['active_time'][] = ['EGT', $active_start];
            } elseif ($active_end) {
                $need_actived = true;
                $yima_where['active_time'][] = ['ELT', $active_end];
            }

            // 易码启用状态
            $yima_status 	= I('yima_status', 		0, 'intval');  	// 二维码状态  0全部 1启用  2停用
            if ($yima_status) {
                if ($yima_status == 1) {
                    $yima_where['is_disable'] = 0;
                    $yima_changed_not_in_where['is_disable'] = 1;
                } else {
                    $yima_where['is_disable'] = 1;
                    $yima_changed_not_in_where['is_disable'] = 0;
                    $need_actived = true;   // qrcode中默认都是启用的，如果是要筛选禁用的只可能再列表中了
                }
            }

            $register_status = I('register_status');        // 激活状态 0全部 1已激活 2未激活
            if ($register_status) {
                if ($register_status == 1) {
                    $need_actived = true;
                    $yima_where['active_time'][] = ['GT', 0];
                    $yima_changed_not_in_where['active_time'] = ['GT', 0];
                } else {
                    $yima_where['active_time'] = 0;
                    $yima_changed_not_in_where['active_time'][] = ['GT', 0];    // 已激活的也是不符合条件的
                }
            }

            // 质保状态，只有已激活的才需要搜索
            $zhibao_status 	= I('zhibao_status', 	0, 'intval');  	// 质保状态 0全部 1未过保 2已过保
            if ($zhibao_status) {
                $now = NOW_TIME;
                if ($zhibao_status == 1) {
                    $yima_where['_string'] = "(`active_time`+(ym.zhibao_time+ifnull(json_extract(ym.active_json, '$.active_reward_moth'),0))*2592000)>{$now} AND active_time>0";
                    $need_actived = true;
                } else {
                    $yima_where['_string'] = "(`active_time`+(ym.zhibao_time+ifnull(json_extract(ym.active_json, '$.active_reward_moth'),0))*2592000)<{$now} AND active_time>0";
                    $need_actived = true;
                }
            }

            // 激活角色
            $user_type 		= I('get.user_type', 		0, 'intval');  	// 激活角色 0全部 1经销商 2普通用户高
            if ($user_type) {
                $need_actived = true;
                $yima_where['user_type'] = $user_type == 2 ? 0 : 1;
            }

            // 激活人手机号码
            $active_phone 	= I('get.active_phone');  		// 激活人手机号码
            if ($active_phone) {
                $need_actived = true;
                $yima_where['telephone'] = ['LIKE', "%{$active_phone}%"];
            }

            // 用户手机号码
            $user_phone 	= I('get.user_phone');  		// 用户手机号码
            if ($user_phone) {
                $need_actived = true;
                $yima_where['user_tel'] = ['LIKE', "%{$user_phone}%"];
            }

            // 用户地区
            $user_area 		= I('get.user_area'); 			// 用户地区
            if ($user_area) {
                $need_actived = true;
                $area = BaseModel::getInstance('cm_list_item')->getFieldVal($user_area, 'item_desc');
                $yima_where['user_address'] = ['LIKE', "%{$area}%"];
//                $yima_where['_string'] .= 'and FIND_IN_SET(json_extract(active_json, "$.active_reward_moth"), )';
            }

            // 分类
            $category_id		= I('get.cate_id', 		0, 'intval'); 	// 品类id
            if ($category_id) {
                $qrcode_where['product_category'] = $category_id;
                $yima_where['product_category'] = $category_id;
                $yima_changed_not_in_where['product_category'] = ['NEQ', $category_id];
            }

            // 品牌
            $brand_id		= I('get.brand_id', 		0, 'intval'); 	// 品牌id
            if ($brand_id) {
                $qrcode_where['product_brand'] = $brand_id;
                $yima_where['product_brand'] = $brand_id;
                $yima_changed_not_in_where['product_brand'] = ['NEQ', $brand_id];
            }

            // 规格
            $guige_id		= I('get.guige_id', 		0, 'intval'); 	// 规格id
            if ($guige_id) {
                $qrcode_where['product_guige'] = $guige_id;
                $yima_where['product_guige'] = $guige_id;
                $yima_changed_not_in_where['product_guige'] = ['NEQ', $guige_id];
            }

            // 规格——产品
            $product_id		= I('get.product_id', 		0, 'intval'); 	// 产品id
            if ($product_id) {
                $qrcode_where['factory_product.product_id'] = $product_id;
                $yima_where['factory_product.product_id'] = $product_id;
                $yima_changed_not_in_where['factory_product.product_id'] = ['NEQ', $product_id];
            }

            $export = I('export');
            $skip_export_code = explode(',', I('skip_export_code'));
            rsort($skip_export_code);
            if ($export) {
                $limit = 20000;
            } else {
                $limit = 10;
            }

            // 找出符合查找条件的在易码详情中的易码（无论是修改后符合还是本身就符合条件）
            $yima_model = factoryIdToModelName($factory['factory_id']);
            // 只有有搜索条件时才需要进行查找，不合符条件的也是一样
            $changed_site = BaseModel::getInstance($yima_model)->getList([
                'alias' => 'ym',
                'where' => $yima_where,
                'order' => "water {$order}",
                'join' => [
                    'LEFT JOIN wx_user ON wx_user.id=ym.member_id',
                    'LEFT JOIN dealer_info ON dealer_info.wx_user_id=wx_user.id',
                    'LEFT JOIN factory_product ON factory_product.product_id=ym.product_id',
                    'LEFT JOIN factory_product_qrcode ON factory_product_qrcode.id=ym.factory_product_qrcode_id',
                ],
                'field' => 'water,factory_product.product_id,product_xinghao,product_category,product_guige,user_name nickname,product_brand,ym.shengchan_time,ym.chuchang_time,ym.zhibao_time,ym.active_time,user_tel,user_address,json_extract(ym.active_json, "$.active_reward_moth") extra_zhibao_time,user_type,telephone,register_time,ym.remarks,ym.diy_remarks,is_disable,factory_product_qrcode.datetime',
                'limit' => $limit,
            ]);

            $has_active_code_list = [];
            $result_list = [];
            $factory_code = $factory['factory_type'] . $factory['code'];
            $qrcode_sequence_num = 0;
            $factory_product_ids = [];

            if (!$need_actived) {
                // TODO 将上面$changed_site查询到的结尾码作为开始/结尾，优化查询速度（不合符的也是）
                // 找出所有该厂家符合条件的二维码段
                $qrcode_sequence_list = BaseModel::getInstance('factory_product_qrcode')->getList([
                    'where' => $qrcode_where,
                    'order' => "qr_first_int {$order}",
                    'join' => [
                        'LEFT JOIN factory_product ON factory_product.product_id=factory_product_qrcode.product_id',
                    ],
                    'field' => 'factory_product_qrcode.id,factory_product_qrcode.nums,qr_first_int,qr_last_int,factory_product.product_id,product_xinghao,product_category,product_guige,product_brand,shengchan_time,chuchang_time,zhibao_time,json_extract(active_json, "$.active_reward_moth") extra_zhibao_time,remarks,diy_remarks,datetime',
                ]);

                $qrcode_length = count($qrcode_sequence_list);
                // 设置查询的最大最小值
                if ($qrcode_sequence_list) {
                    if ($code_start && $code_end) {
                        $qrcode_sequence_list[0]['qr_last_int'] = $qrcode_sequence_list[0]['qr_last_int'] > $code_end ? $code_end : $qrcode_sequence_list[0]['qr_last_int'];
                        $qrcode_sequence_list[$qrcode_length - 1]['qr_first_int'] = $qrcode_sequence_list[$qrcode_length - 1]['qr_first_int'] > $code_start ? $qrcode_sequence_list[$qrcode_length - 1]['qr_first_int'] : $code_start;
                    } elseif ($code_start) {
                        $qrcode_sequence_list[$qrcode_length - 1]['qr_first_int'] = $qrcode_sequence_list[$qrcode_length - 1]['qr_first_int'] > $code_start ? $qrcode_sequence_list[$qrcode_length - 1]['qr_first_int'] : $code_start;
                    } elseif ($code_end) {
                        $qrcode_sequence_list[0]['qr_last_int'] = $qrcode_sequence_list[0]['qr_last_int'] > $code_end ? $code_end : $qrcode_sequence_list[0]['qr_last_int'];
                    }
                }

                foreach ($qrcode_sequence_list as $item) {
                    $qrcode_sequence_num += $item['qr_last_int'] - $item['qr_first_int'] + 1;
                    $factory_product_ids[] = $item['id'];
                }

                if (count($yima_changed_not_in_where) > 1) {
                    if ($register_status != 2) {
                        // 查找修改后不符合条件的
                        $yima_changed_not_in_where['_string'] = "(factory_product_qrcode.product_id!=ym.product_id OR factory_product_qrcode.shengchan_time!=ym.shengchan_time OR factory_product_qrcode.chuchang_time!=ym.chuchang_time OR factory_product_qrcode.zhibao_time!=ym.zhibao_time OR factory_product_qrcode.active_json!=ym.active_json OR is_disable=1)";
                    }
                    $yima_changed_not_in_where['factory_product_qrcode_id'] = $factory_product_ids ? ['IN', $factory_product_ids] : null;
                    $change_not_site = BaseModel::getInstance($yima_model)->getFieldVal([
                        'alias' => 'ym',
                        'where' => $yima_changed_not_in_where,
                        'order' => "water {$order}",
                        'join' => [
                            'LEFT JOIN factory_product ON factory_product.product_id=ym.product_id',
                            'LEFT JOIN factory_product_qrcode ON factory_product_qrcode.id=ym.factory_product_qrcode_id'
                        ],
                    ], 'water', true);
                } else {
                    $change_not_site = [];
                }

                $overflow = false;
                foreach ($qrcode_sequence_list as $item) {
                    // TODO 根据搜索的code_end和code_start决定for的开始和结束
                    $i = $item['qr_last_int'];
                    $j = $item['qr_first_int'];
//                    if ($order_type == 1) { // 降序
//                        $i = $code_end ? ($item['qr_last_int'] > $code_end ? $code_end : $item['qr_last_int']) : $item['qr_last_int'];
//                        $j = $code_start ? ($item['qr_first_int'] > $code_start ? $item['qr_first_int'] : $code_start) : $item['qr_first_int'];
//                    } else {
//                        $i = $code_end ? ($item['qr_last_int'] > $code_end ? $code_end : $item['qr_last_int']) : $item['qr_last_int'];
//                        $j = $code_start ? ($item['qr_first_int'] > $code_start ? $item['qr_first_int'] : $code_start) : $item['qr_first_int'];
//                    }
                    for (; $i >= $j; $order_type == 1 ? --$i : ++ $j) {
                        if ($limit && count($result_list) >= $limit) {
                            $overflow = true;
                            break;
                        }

                        $value_equal = false;
                        $sequence = $order_type == 1 ? $i : $j;
                        // 先检查符合条件的易码，有则先插入符合条件列表
                        for ($site_last_index = 0; $site_last_index < count($changed_site); ++$site_last_index) {
                            $value = $changed_site[$site_last_index];
                            if (($order_type == 1 && $value['water'] >= $sequence) || ($order_type == 2 && $value['water'] <= $sequence)) {
                                if (!in_array($value['water'], $skip_export_code)) {
                                    if ($value['active_time'] > 0) {
                                        $has_active_code_list[] = $factory_code . $value['water'];
                                    }
                                    $user_address = json_decode($value['user_address'], true);
                                    $result_list[] = [
                                        'code' => $value['water'],
                                        'product_id' => $value['product_id'],
                                        'product_xinghao' => $value['product_xinghao'],
                                        'product_category' => $value['product_category'],
                                        'product_guige' => $value['product_guige'],
                                        'product_brand' => $value['product_brand'],
                                        'chuchang_time' => $value['chuchang_time'],
                                        'shengchan_time' => $value['shengchan_time'],
                                        'zhibao_time' => $value['active_time'] ? $value['zhibao_time'] + trim($value['extra_zhibao_time'], '"') : $value['zhibao_time'],
                                        'buy_time' => $value['active_time'] ? : 0,
                                        'register_time' => $value['register_time'] ? : 0,
                                        'is_zhibao_valid' => $value['active_time'] > 0 && $value['active_time'] + ($value['zhibao_time'] + $value['extra_zhibao_time']) * 2592000 > NOW_TIME,
                                        'user_phone' => $value['user_tel'] ?? '',
                                        'user_address' => $user_address ? $user_address['names'] : '',
                                        'user_detail' => $user_address ? $user_address['address'] : '',
                                        'active_user_name' => $value['nickname'],
                                        'active_user_phone' => $value['telephone'],
                                        'active_user_type' => isset($value['user_type']) ? $value['user_type'] + 1 : 0,
                                        'remarks' => $value['remarks'],
                                        'diy_remarks' => $value['diy_remarks'],
                                        'is_disable' => $value['is_disable'],
                                        'datetime' => $value['datetime'],
                                    ];
                                    if ($value['water'] == $sequence) {
                                        $value_equal = true;
                                    }
                                }
                                array_splice($changed_site, $site_last_index, 1);
                            } else {
                                break;
                            }
                        }
                        // 防止重复添加
                        if ($value_equal) {
                            continue;
                        }
                        // 不在不符合列表的才能添加
                        if (!in_array($sequence, $change_not_site) && !in_array($sequence, $skip_export_code)) {
                            $result_list[] = [
                                'code' => $sequence,
                                'product_id' => $item['product_id'],
                                'product_xinghao' => $item['product_xinghao'],
                                'product_category' => $item['product_category'],
                                'product_guige' => $item['product_guige'],
                                'product_brand' => $item['product_brand'],
                                'chuchang_time' => $item['chuchang_time'],
                                'zhibao_time' => $item['zhibao_time'],
                                'shengchan_time' => $item['shengchan_time'],
                                'buy_time' => 0,
                                'register_time' => 0,
                                'is_zhibao_valid' => isset($changed_site[$site_last_index]['active_time']) ? $changed_site[$site_last_index]['active_time'] + ($changed_site[$site_last_index]['zhibao_time'] + $changed_site[$site_last_index]['extra_zhibao_time']) * 2592000 > NOW_TIME : true,
                                'user_phone' => '',
                                'user_address' => '',
                                'user_detail' => '',
                                'active_user_name' => '',
                                'active_user_phone' => '',
                                'active_user_type' => 0,
                                'remarks' => $item['remarks'],
                                'diy_remarks' => $item['diy_remarks'],
                                'datetime' => $item['datetime'],
                                'is_disable' => 0,
                            ];
                        }
                    }
                    if ($overflow) {
                        if ($export) {
                            exit("<script>alert('每次最多导出{$limit}个，本次导出数量已超过限制')</script>");
                        }
                        break;
                    }
                }
            } else {
                $change_not_site = 0;
                foreach ($changed_site as $value) {
                    if (in_array($value['water'], $skip_export_code)) {
                        continue;
                    }
                    $user_address = json_decode($value['user_address'], true);
                    if ($value['active_time'] > 0) {
                        $has_active_code_list[] = $factory_code . $value['water'];
                    }
                    $result_list[] = [
                        'code' => $value['water'],
                        'product_id' => $value['product_id'],
                        'product_xinghao' => $value['product_xinghao'],
                        'product_category' => $value['product_category'],
                        'product_guige' => $value['product_guige'],
                        'product_brand' => $value['product_brand'],
                        'chuchang_time' => $value['chuchang_time'],
                        'shengchan_time' => $value['shengchan_time'],
                        'zhibao_time' => $value['active_time'] ? $value['zhibao_time'] + trim($value['extra_zhibao_time'], '"') : $value['zhibao_time'],
                        'buy_time' => $value['active_time'] ? : 0,
                        'register_time' => $value['register_time'] ? : 0,
                        'is_zhibao_valid' => $value['active_time'] + ($value['zhibao_time'] + $value['extra_zhibao_time']) * 2592000 > NOW_TIME,
                        'user_phone' => $value['user_tel'] ?? '',
                        'user_address' => $user_address ? $user_address['names'] : '',
                        'user_detail' => $user_address ? $user_address['address'] : '',
                        'active_user_name' => $value['nickname'],
                        'active_user_phone' => $value['telephone'],
                        'active_user_type' => isset($value['user_type']) ? $value['user_type'] + 1 : 0,
                        'remarks' => $value['remarks'],
                        'diy_remarks' => $value['diy_remarks'],
                        'is_disable' => $value['is_disable'],
                        'datetime' => $value['datetime'],
                    ];
                }
            }

            $result_list && (new ProductLogic())->loadProductExtraInfo($result_list, 'code');

            if ($order_type == 2) {
                $result_list = array_reverse($result_list);
            }

            if ($export) {
                if ($has_active_code_list) {
                    // $code_order_times_map = BaseModel::getInstance('worker_order_detail')->getFieldVal([
                    $code_order_times_map = BaseModel::getInstance('worker_order_product')->getFieldVal([
                        'where' => [
                            'yima_code' => ['IN', $has_active_code_list],
                        ],
                        'group' => 'yima_code',
                    ], 'yima_code as code,count(*) num', true);
                } else {
                    $code_order_times_map = [];
                }
                

                $filePath = './Public/二维码信息查询导出模板.xls';
                $objPHPExcel = \PHPExcel_IOFactory::load($filePath);
                $row = 2;
                foreach ($result_list as $item) {
                    $order_times = $code_order_times_map[$factory_code . $item['code']] ?? 0;
                    $column = 'A';
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue($column++ . $row, $factory_code . $item['code'])
                        ->setCellValue($column++ . $row, $item['category'])
                        ->setCellValue($column++ . $row, $item['standard'])
                        ->setCellValue($column++ . $row, $item['branch'])
                        ->setCellValue($column++ . $row, $item['product_xinghao'])
                        ->setCellValue($column++ . $row, $item['datetime'] ? date('Y-m-d', $item['datetime']) : '————')
                        ->setCellValue($column++ . $row, $item['shengchan_time'] ? date('Y-m-d', $item['shengchan_time']) : '————')
                        ->setCellValue($column++ . $row, $item['chuchang_time'] ? date('Y-m-d', $item['chuchang_time']) : '————')
                        ->setCellValue($column++ . $row, $item['zhibao_time'])
                        ->setCellValue($column++ . $row, $item['buy_time'] ? '已激活' : '未激活')
                        ->setCellValue($column++ . $row, $item['buy_time'] ? date('Y-m-d', $item['buy_time']) : '————')
                        ->setCellValue($column++ . $row, $item['buy_time'] ? date('Y-m-d', $item['buy_time'] + $item['zhibao_time'] * 2592000) : '————')
                        ->setCellValue($column++ . $row, $item['buy_time'] ? ($item['is_zhibao_valid'] ? '未过保' : '已过保') : '————')
                        ->setCellValue($column++ . $row, $item['active_user_name'] ? : '————')
                        ->setCellValue($column++ . $row, $item['user_phone'], 'str')->getWorksheet()
                        ->setCellValue($column++ . $row, $item['user_address'] ? : '————')
                        ->setCellValue($column++ . $row, $item['user_detail'] ? : '————')
                        ->setCellValue($column++ . $row, $item['is_disable'] == 0 ? '启用' : '禁用')
                        ->setCellValue($column++ . $row, $item['active_user_type'] ? ($item['active_user_type'] == 1 ? '用户' : '经销商') : '————')
                        ->setCellValue($column++ . $row, $item['active_user_phone'], 'str')->getWorksheet()
                        ->setCellValue($column++ . $row, $order_times)
                        ->setCellValue($column++ . $row, str_replace('=', ' ', $item['remarks']))
                        ->setCellValue($column++ . $row, str_replace('=', ' ', $item['diy_remarks']));

                    ++$row;
                }

                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename='.$factory['factory_short_name'] . ' 易码详情 (' . date('Y.m.d H：i') . ')' . '.xls');
                header('Cache-Control: max-age=0');
                $objWriter->save('php://output');
            } else {
                if (!$last_code) {
                    $change_not_site_num = 0;
                    if (!$need_actived) {
                        $change_not_site_num = count($change_not_site);
                    }
                    if (count($yima_where) > 1) {
                        $yima_where_ext = $yima_where;
                        if (!$need_actived) {
                            $yima_where_ext['_string'] .= $yima_where_ext['_string'] ? ' AND ' : '';
                            $yima_where_ext['_string'] .= "(factory_product_qrcode.product_id!=ym.product_id OR factory_product_qrcode.shengchan_time!=ym.shengchan_time OR factory_product_qrcode.chuchang_time!=ym.chuchang_time OR factory_product_qrcode.zhibao_time!=ym.zhibao_time OR factory_product_qrcode.active_json!=ym.active_json OR is_disable=1)";
                        }
                        $factory_product_ids && $yima_where_ext['factory_product_qrcode_id'] = ['NOT IN', $factory_product_ids];
                        $changed_site_num = BaseModel::getInstance($yima_model)->getNum([
                            'alias' => 'ym',
                            'where' => $yima_where_ext,
                            'join' => [
                                'LEFT JOIN wx_user ON wx_user.id=ym.member_id',
                                'LEFT JOIN factory_product ON factory_product.product_id=ym.product_id',
                                'LEFT JOIN factory_product_qrcode ON factory_product_qrcode.id=ym.factory_product_qrcode_id'
                            ],
                        ]);
                    } else {
                        $changed_site_num = 0;
                    }

                    $total_num = $qrcode_sequence_num + $changed_site_num - $change_not_site_num;
                } else {
                    $total_num = 0;
                }
                $this->response(['list' => $result_list, 'total' => $total_num, 'factory_code' => $factory_code]);
            }


        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function exportYimaData()
    {
        try {
            $excel_id = I('get.id');

            $excel_info = BaseModel::getInstance('factory_excel')->getOneOrFail($excel_id, 'factory_id,first_code,last_code,is_check');

            // 是否审核通过  0 未审核 1 审核通过 2 审核不通过
            if ($excel_info['is_check'] != 1) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '易码未通过审核,无法导出');
            }

            $factory = BaseModel::getInstance('factory')->getOneOrFail($excel_id['factory_id'], 'factory_full_name,factory_type,code');

            $save_path = './Uploads/yima_excels/' . date('ym') . '/';
            if (!is_dir($save_path)) {
                mkdir($save_path, 0755, true);
            }
            $excel_name = $factory['factory_full_name'] . '-易码' . $excel_info['first_code'] . '至' . $excel_info['last_code'];
            $excel_file = $save_path . $excel_name . '.xls';

            // 文件已存在则直接导出，不存在则先生成
            if (file_exists($excel_file)) {
                $objPHPExcel = \PHPExcel_IOFactory::load($excel_file);

                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
            } else {
                $filePath = './Public/客服审核通过后导出模板' . '.xls';
                $objPHPExcel = \PHPExcel_IOFactory::load($filePath);

                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('A1', '厂家名称：' . $factory['factory_full_name']);

                $row = 3;
                for ($i = $excel_info['first_code']; $i <= $excel_info['last_code']; ++$i) {
                    $code = $factory['factory_type'] . $factory['code'] . $i;
                    $entry = Util::getServerUrl() . C('YIMA_DETAIL_ROUTE') . encryptYimaCode($code);

                    $column = 'A';
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue($column++ . $row, $code)
                        ->setCellValue($column++ . $row, $entry);

                    ++$row;
                }

                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
                $objWriter->save($excel_file);
            }



            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename='.$excel_name . '(' . date('Y.m.d H：i') . ')' . '.xls');
            header('Cache-Control: max-age=0');


            $objWriter->save('php://output');
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
	}

    public function repairYimaDate()
    {
        try {
            M()->startTrans();
            // 填写的购买时间2016年6月1号之前的，购买时间&激活时间就置为生成时间+60天
            for ($i = 0; $i <=15; ++$i) {
                $list = BaseModel::getInstance('yima_' . $i)->getList([
                    'where' => [
                        'active_time' => [
                            ['LT', 1464710400],
                            ['NEQ', 0]
                        ],
//                        'register_time' => 0,
                    ],
                    'field' => 'code,datetime',
                    'join' => [
                        'LEFT JOIN factory_product_qrcode ON factory_product_qrcode.id=factory_product_qrcode_id',
                    ],
                ]);

                $day_60 = 86400 * 60;
                foreach ($list as $item) {
                    BaseModel::getInstance('yima_' . $i)->update([
                        'code' => $item['code']
                    ], [
                        'active_time' => $item['datetime'] + $day_60,
                        'register_time' => $item['datetime'] + $day_60,
                    ]);
                }


                // 购买时间在2016年6月1号之后，且没有激活时间数据的，就购买时间=激活时间
                $sql = "UPDATE yima_{$i} SET `register_time`=`active_time` WHERE active_time>=1464710400 AND register_time=0";
                BaseModel::getInstance('yima_' . $i)->execute($sql);

                // 质保期为0的，改为12个月
                BaseModel::getInstance('yima_' . $i)->update([
                    'zhibao_time' => 0,
                ], ['zhibao_time' => 12]);
            }

            // 码的申请日期、绑定日期都是正常的，如果生产日期、出厂日期早于2016年4月1日，将生产日期置为绑定日期+15天，出厂日期置为绑定日期+30天
            $date_time = 1459440000;
            $product_qrcode = BaseModel::getInstance('factory_product_qrcode')->getList([
                'where' => [
                    'shengchan_time' => ['ELT', $date_time],    // 生成时间小于2016.04.01的
                    'chuchang_time' => ['ELT', $date_time],    // 出厂时间小于2016.04.01的
                    '_logic' => 'or',
                ],
                'field' => 'id,factory_id,datetime,qr_first_int,qr_last_int,shengchan_time,chuchang_time',
            ]);
            foreach ($product_qrcode as $item) {
                $update = [
                    'shengchan_time' => $item['datetime'] + 86400 * 15,
                    'chuchang_time' => $item['datetime'] + 86400 * 30,
                ];
                if ($update) {
                    BaseModel::getInstance('factory_product_qrcode')->update($item['id'], $update);
                    $table_name = factoryIdToModelName($item['factory_id']);
                    BaseModel::getInstance($table_name)->update([
                        'factory_id' => $item['factory_id'],
                        'water' => [
                            ['EGT', $item['qr_first_int']],
                            ['ELT', $item['qr_last_int']],
                        ]
                    ], $update);
                }
            }
            // 质保期为0的，改为12个月
            BaseModel::getInstance('factory_product_qrcode')->update([
                'zhibao_time' => 0,
            ], ['zhibao_time' => 12]);

            M()->commit();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
	}

    public function summary()
    {
        try {
            $this->requireAuthFactoryGetFid();

            $factory = AuthService::getAuthModel();

            $summary = D('Yima', 'Logic')->summary($factory['factory_id']);

            $this->response($summary);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
	}

    public function monthSummary()
    {
        try {
            $this->requireAuthFactoryGetFid();

            $factory = AuthService::getAuthModel();

            $summary = D('Yima', 'Logic')->monthSummary($factory['factory_id']);

            $this->response($summary);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
	}

      public function areaSellSummary()
      {
            try {
                $this->requireAuthFactoryGetFid();

            $factory = AuthService::getAuthModel();

            $summary = D('Yima', 'Logic')->areaSellSummary($factory['factory_id']);

            $this->response($summary);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
      }

    public function productSummary()
    {
        try {
            $this->requireAuthFactoryGetFid();

            $factory = AuthService::getAuthModel();

            $summary = D('Yima', 'Logic')->productSummary($factory['factory_id']);

            $this->response($summary);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
	}

	
}
