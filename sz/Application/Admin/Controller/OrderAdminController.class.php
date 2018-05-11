<?php
/*
 * User: xieguoqiu
 * Date: 2017/4/5 15:09
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\OrderLogic;
use Admin\Logic\WorkbenchLogic;
use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkbenchEvent;
use Admin\Model\WorkerOrderApplyAccessoryModel;
use Carbon\Carbon;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\OrderMessageService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\OrderUserService;
use Common\Common\Service\SMSService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use Common\Common\Service\WorkerService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class OrderAdminController extends BaseController
{
	public function auditedOrder()
	{
		$order_id  		= I('get.id', 0, 'intval');
		$type  			= I('post.type', 0, 'intval');
		$remark  		= I('post.remark', '');
		try {
			$this->requireAuth(AuthService::ROLE_ADMIN);
//			checkAdminOrderPermission($order_id);

			// 1 (与维修商结算)财务审核通过 ；2 (与维修商结算)平台财务客服审核财务不通过(退回工单)
			!in_array($type, [1, 2]) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);

			$logic = new \Admin\Logic\OrderAdminLogic();
			switch ($type) {
				case 1:
					$logic->auditedOrder($order_id);
					break;
				
				case 2:
					$logic->notAuditedOrder($order_id, $remark);
					break;
			}

			$this->okNull();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function isPayForWorker()
	{
		$order_id  		= I('get.id', 0, 'intval');
		$type  			= I('post.type', 0, 'intval');
		$remark  		= I('post.remark', '');
		$go_sign_nums  	= I('post.nums', 0, 'intval');
		try {
			$id = $this->requireAuth(AuthService::ROLE_ADMIN);
//			checkAdminOrderPermission($order_id);

			// 1 确认与维修商结算（并可以更改技工上门次数）；2 确认不维修商结算
			!in_array($type, [1, 2]) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);

			$logic = new \Admin\Logic\OrderAdminLogic();
			switch ($type) {
				case 1:
					$logic->payForWorkerById($order_id, $remark, $go_sign_nums);
					break;
				
				case 2:
					$logic->notPayForWorkerById($order_id, $remark);
					break;
			}

			$this->okNull();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	// 财务客服接单
	public function auditorReceiveOrder()
	{
		$order_id  = I('get.id', 0, 'intval');
		try {
			$admin_id = $this->requireAuth(AuthService::ROLE_ADMIN);
//			checkAdminOrderPermission($order_id);
			$model = BaseModel::getInstance('worker_order');
			$data = $model->getOneOrFail($order_id, 'worker_order_status');
				$data['worker_order_status'] != OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE
			&&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_AUDITOR_RECEIVE);
			$update = [
				'worker_order_status' 	=> OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
				'auditor_id'			=> $admin_id,
				'auditor_receive_time' 	=> NOW_TIME,
				'last_update_time' 	=> NOW_TIME,
			];
            M()->startTrans();
			$model->update($order_id, $update);
            
            $extras = [
                'content_replace' => [
                    'admin_name' => AuthService::getAuthModel()->user_name,
                ],
            ];
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_AUDITOR_RECEIVED, $extras);
            M()->commit();
			$this->okNull();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	// 回访客服接单
	public function returneeReceiveOrder()
	{
		$order_id  = I('get.id', 0, 'intval');
		try {
			$admin_id = $this->requireAuth(AuthService::ROLE_ADMIN);
//			checkAdminOrderPermission($order_id);
			$model = BaseModel::getInstance('worker_order');
			$data = $model->getOneOrFail($order_id, 'worker_order_status');
				$data['worker_order_status'] != OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE
			&&  $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_RETURNEE_RECEIVE);
			$update = [
				'worker_order_status' 	=> OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
				'returnee_id'			=> $admin_id,
				'returnee_receive_time' => NOW_TIME,
				'last_update_time' => NOW_TIME,
			];
			M()->startTrans();
			$model->update($order_id, $update);

            $extras = [
                'content_replace' => [
                    'admin_name' => AuthService::getAuthModel()->user_name,
                ],
            ];
            OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_RETURNEE_RECEIVED, $extras);
            M()->commit();
			$this->okNull();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function receivers()
	{
		$order_id   = I('get.id', 0, 'intval');         	  // 订单id
        $address_id = I('get.worker_area_id', 0, 'intval');   // 地址id
        $sort_type  = I('get.order_type', 0, 'intval');       // 查询模式 （排序模式）  1,签约优先;2,里程优先;
        $phone      = I('get.worker_telephone', 0, 'intval'); // 手机号码
        $name       = htmlEntityDecode(I('get.name', ''));    // 技工名称 
        $reset      = I('get.reset', 1, 'intval');            // 重新获取数据

        $order = BaseModel::getInstance('worker_order')->getOne([
        	'field' => 'a.id,b.lat,b.lon,a.service_type',
        	'alias' => 'a',
        	'join'  => 'left join worker_order_user_info b on a.id = b.worker_order_id',
        	'where' => [
        		'a.id' => $order_id,
        	],
        ]);
        
        !$order && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

        $area_data = BaseModel::getInstance('area')->getOne($address_id);
        if (!$area_data || !in_array($sort_type, [1, 2])) {
            $this->paginate();
        } elseif ($area_data['parent_id'] == 0) {
            $this->fail(ErrorCode::MUST_SELECT_CITY);
        }

        // empty($address_id) && $this->fail(ErrorCode::AT_ADDRESS_NOT_EMPTY); // 归属地址不能为空

        $order['details'] = BaseModel::getInstance('worker_order_product')->getList([
                'where' => [
                    'worker_order_id' => $order_id
                ],
                'field' => 'id,worker_order_id,product_category_id',
                'order' => 'id ASC',
            ]);

        $where = [
            '_string' => ' FIND_IN_SET('.$address_id.', worker_area_ids) ',
            'receivers_status' => ['neq', 0],
            'is_check' => 1,
//            '_complex' => [
//                '_logic' => 'or',
//                'type' => ['not in', implode(',', WorkerService::ADMIN_DISTRIBUTE_TYPE_ARR)],
//                'group_apply_status' => ['in', implode(',', WorkerService::ADMIN_NOT_DISTRIBUTE_GROUP_APPLY_STATUS_ARR)],
//            ],
            'type' => ['in', implode(',', WorkerService::ADMIN_DISTRIBUTE_TYPE_ARR)],
            'group_apply_status' => ['not in', implode(',', WorkerService::ADMIN_NOT_DISTRIBUTE_GROUP_APPLY_STATUS_ARR)],
        ];

        if (strlen($phone) > 5) {
            $where['worker_telephone'] = ['like', '%'.$phone.'%'];
        }

        if (!empty($name)) {
            $where['nickname'] = ['like', '%'.$name.'%'];
        }   

        $cash_data = $score = $workers = [];

        $s_key 		 = $sort_type.'_'.C('S_KEY_PRE').$order['id'].'_'.$address_id;
        $s_score_key = $sort_type.'_'.C('S_SCORE_KEY_PRE').$order['id'];

        // 是否重新计算数据
        if ($reset) {
            S($s_key, null);
        } else {
            $cash_data = S($s_key);
        }

        if ($order['lat'] != $cash_data['lat'] || $order['lon'] != $cash_data['lon'] || !count($cash_data['scores'])) {
            
            $workers = BaseModel::getInstance('worker')->getList([
                    'where' => $where, 
                    'field' => 'worker_id,nickname,worker_telephone,worker_area_ids,worker_address,lat,lng as lon,is_qianzai,receivers_status,notes,worker_detail_address',
                    'index' => 'worker_id',
                ]);
            $worker_ids = arrFieldForStr($workers, 'worker_id');

            // 进入计算
            $logic = new \Admin\Logic\WorkerLogic();
            $data = $logic->getCashDataReceivers($order, $sort_type, $workers, $worker_ids, $address_id);
            // $score = $data[0];
            // $sort_list = array_intersect_key($score, $workers);
            $sort_list = $data[0];
        } else {
            
            $search_result = BaseModel::getInstance('worker')->getList([
                    'where' => $where, 
                    'field' => 'worker_id',
                    'index' => 'worker_id',
                ]);
            $sort_list = array_intersect_key($cash_data['scores'], $search_result);
        }

        if (!count($sort_list)) {
            $this->paginate();
        }

        $cp_sort_list = $sort_list;

        $is_top_where = [
            'where' => [
                'worker_id' => ['in', implode(',', array_keys($sort_list))],
                'receivers_status' => 2,
            ],
            'index' => 'worker_id',
            'field' => 'worker_id',
        ];
        $tops = BaseModel::getInstance('worker')->getList($is_top_where);

        $worker_complete_order_nums_arr = RedisPool::getInstance()->hMGet(C('SKEY_COMPLETE_ORDER_NUMS'));
//        $worker_complete_order_nums_arr = RedisPool::getInstance()->get(C('SKEY_COMPLETE_ORDER_NUMS'));

        //  千万
        $sprintf_d = 10000000;
        foreach ($sort_list as $k => $v) {
            if ($tops[$k]['worker_id'] == $k) {
                $v += C('IS_TOP_SCORE');
            }

            $all_score = $v*$sprintf_d;
            if ($worker_complete_order_nums_arr[$k] > 0) {
                $all_score += $worker_complete_order_nums_arr[$k];
            }

            $sort_list[$k] = $all_score;
        }

        arsort($sort_list);

        // 根据分页获取排序后的数据
        $count = count($sort_list);
        $page = explode(',', getPage());
        $sort_list = array_slice($sort_list, $page[0], $page[1], true);
        // 分页后的数据
        $worker_ids = implode(',', array_keys($sort_list));

        if (!$worker_ids) {
            $this->paginate();
        }

        if (!count($workers)) {
            $workers = BaseModel::getInstance('worker')->getList([
                    'where' => [
                        'worker_id' => ['in', $worker_ids],
                    ], 
                    'field' => 'worker_id,nickname,worker_telephone,worker_area_ids,worker_address,lat,lng as lon,is_qianzai,receivers_status,notes,worker_detail_address',
                    'index' => 'worker_id',
                ]);
        }

        $order_num_list = BaseModel::getInstance('worker_order_reputation')
                ->field('worker_id,COUNT(worker_id) AS all_order_nums')
                ->where([
                        'worker_id' => ['in', $worker_ids],
                        'is_return' => 0,
                    ])
                ->group('worker_id')
                ->index('worker_id')
                ->select();

        $labels = BaseModel::getInstance('worker_label')->getList([
                'field' => 'worker_id,count(id) as nums,name',
                'where' => [
                    'worker_id' => ['in', $worker_ids],
                ],
                'group' => 'label_id,worker_id',
            ]);

        $coop_list  = BaseModel::getInstance('worker_coop_busine')
                    ->getList([
                        'where' => [
                            'worker_id' => ['IN', $worker_ids]
                        ],
                        'field' => 'worker_id,coop_level',
                        'index' => 'worker_id',
                    ]);

        $scores_totals = S($s_score_key);

        $labels_worker = [];
        foreach ($labels as $k => $v) {
            $labels_worker[$v['worker_id']][] = $v;
        }

        // 保持排序后的顺序返回数据 并组装 其他数据
        $list = $all_worker_area_arr = [];
        foreach ($sort_list as $key => $v) {
            $v = $cp_sort_list[$key];
            $value = $workers[$key];
            $mi = 0;        // 米
            $li = 0.0;      // 里（千米）
            $is_top = $value['receivers_status'] == 2 ? 1 : 0;

            if ($order['lat'] != null && $order['lon'] != null && $value['lat'] != null && $value['lon'] != null) {
                $mi = Util::distanceSimplify($order['lat'], $order['lon'], $value['lat'], $value['lon']) * 1.5;
                $li = number_format($mi / 1000, 2, '.', '');
            }

            $label 			= $labels_worker[$key];
            $order_nums 	= $order_num_list[$key]['all_order_nums'];
            $coop_level 	= $coop_list[$key]['coop_level'] ?? '0';
            $scores_total 	= $scores_totals[$key];
            
            if (!isset($scores_total['paid_order'])) {
                $scores_total['paid_order'] = '0.00';
            }
            if (!isset($scores_total['no_complaint'])) {
                $scores_total['no_complaint'] = '0.00';
            }
            if (!isset($scores_total['no_cancel'])) {
                $scores_total['no_cancel'] = '0.00';
            }
            if (!isset($scores_total['on_work_time'])) {
                $scores_total['on_work_time'] = '0.00';
            }
            if (!isset($scores_total['return_time'])) {
                $scores_total['return_time'] = '0.00';
            }
            if (!isset($scores_total['appoint_time'])) {
                $scores_total['appoint_time'] = '0.00';
            }
            if (!isset($scores_total['contract_qualification'])) {
                $scores_total['contract_qualification'] = '0.00';
            }

            $v = number_format($v/100, 2, '.', '');

            $list_key = count($list);
            if ($workers[$key]) {
                $workers[$key]['distances'] = $li;
                $workers[$key]['order_nums'] = $order_nums ? $order_nums : '0';
                $workers[$key]['coop_level'] = $coop_level;
                $workers[$key]['scores_total'] = $v;
                $workers[$key]['scores'] = $scores_total;
                $workers[$key]['label'] = $label;

                    $workers[$key]['worker_area_ids']
                &&  !$workers[$key]['worker_address']
                &&  $all_worker_area_arr[$list_key] = $workers[$key]['worker_area_ids'];

                $list[$list_key] = $workers[$key];
            } else {
                $list[$list_key] = [
                    'worker_id' => $key,
                    'order_nums' => $order_nums ? $order_nums : '0',
                    'coop_level' => $coop_level,
                    'distances' => $li,
                    'scores_total' => $v,
                    'scores' => $scores_total,
                    'label' => $label,
                ];
            }
        }

        // 补充地址信息
        if ($all_worker_area_arr) {
            $all_worker_area_ids = implode(',', $all_worker_area_arr);
            $all_worker_area_ids = implode(',', array_unique(explode(',', $all_worker_area_ids)));
            $areas = $all_worker_area_ids ? AreaService::getAreaNameMapByIds($all_worker_area_ids) : [];
            foreach ($list as $k => &$list_data) {
                if (!$all_worker_area_arr[$k]) {
                    continue;
                }
                $worker_address = [];
                foreach (explode(',', $list_data['worker_area_ids']) as $area_id) {
                    $worker_address[] = $areas[$area_id]['name'] ?? '';
                }
                $worker_address = implode('-', $worker_address);
                $list_data['worker_address'] = $worker_address ?? '——';
            }
        }

        // 返回结果
        $this->paginate($list, $count);
	}


    /**
     * 核实客服接单
     * @param $id
     */
    public function checkerReceive()
    {
        $id = I('get.id');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $admin = AuthService::getAuthModel();
//            checkAdminOrderPermission($id);

            $worker_order_model = BaseModel::getInstance('worker_order');
            $worker_order_status = $worker_order_model->getFieldVal($id, 'worker_order_status');
            if ($worker_order_status != OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单不是待接单状态,请重试');
            }

            $worker_order_model->startTrans();
            $worker_order_model->update($id, [
                'checker_id' => $admin['id'],
                'worker_order_status' => OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
                'checker_receive_time' => NOW_TIME,
                'last_update_time' => NOW_TIME,
            ]);
            OrderOperationRecordService::create($id, OrderOperationRecordService::CS_CHECKER_RECEIVED, [
                'content_replace' => [
                    'admin_name' => $admin['user_name']
                ]
            ]);
            $worker_order_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 核实工单信息
     * @param $id
     */
    public function confirmOrderInfo()
    {
        $id = I('get.id');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
//            checkAdminOrderPermission($id);

            $worker_order_model = BaseModel::getInstance('worker_order');
            $order_info = $worker_order_model->getOneOrFail($id, 'worker_order_status,checker_id');
            $worker_order_status = $order_info['worker_order_status'];
            $checker_id = $order_info['checker_id'];
            // 判断工单状态
            if ($worker_order_status != OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单不是待核实状态,请确认');
            }
            // 获取consumer信息
            $user_info = BaseModel::getInstance('worker_order_user_info')->getOne($id);
            if (!$user_info['lat'] || !$user_info['lon']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请先在用户信息中选取用户坐标');
            }
            $consumer_info = BaseModel::getInstance('consumer')->getOne(['tell' => $user_info['phone']], 'id,cons_orders,order_times');

            $worker_order_model->startTrans();
            $worker_order_model->update($id, [
                'worker_order_status' => OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
                'check_time' => NOW_TIME,
                'last_update_time' => NOW_TIME,
            ]);
            OrderOperationRecordService::create($id, OrderOperationRecordService::CS_CHECKER_CHECKED);
            if ($consumer_info) {
                BaseModel::getInstance('consumer')->update($consumer_info['id'], [
                    'cons_orders' => $consumer_info['cons_orders'] . ",{$id}",
                    'order_times' => $consumer_info['order_times'] + 1,
                    'area_full' => "{$user_info['province_id']},{$user_info['city_id']},{$user_info['area_id']}",
                    'cons_name' => $user_info['real_name'],
                    'tell' => $user_info['phone'],
                    'cons_area' => $user_info['cp_area_names'],
                    'cons_address' => $user_info['address'],
                ]);
            } else {
                BaseModel::getInstance('consumer')->insert([
                    'cons_orders' => $id,
                    'order_times' => 1,
                    'area_full' => "{$user_info['province_id']},{$user_info['city_id']},{$user_info['area_id']}",
                    'cons_name' => $user_info['real_name'],
                    'tell' => $user_info['phone'],
                    'cons_area' => $user_info['cp_area_names'],
                    'cons_address' => $user_info['address'],
                    'lat' => $user_info['lat'],
                    'lng' => $user_info['lon'],
                    'addtime' => NOW_TIME,
                ]);
            }
            $worker_order_model->commit();

            event(new WorkbenchEvent(['worker_order_id' => $id, 'event_type' => C('WORKBENCH_EVENT_TYPE.ADMIN_CHECKER_CHECK')]));

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function distributorReceive()
    {
        $id = I('get.id');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $admin = AuthService::getAuthModel();
//            checkAdminOrderPermission($id);

            $worker_order_model = BaseModel::getInstance('worker_order');
            $order = $worker_order_model->getOneOrFail($id, 'worker_order_status');
            if ($order['worker_order_status'] != OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单不是待派单状态,请确认');
            }

            $worker_order_model->startTrans();
            $worker_order_model->update($id, [
                'distributor_id' => $admin['id'],
                'worker_order_status' => OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
                'distributor_receive_time' => NOW_TIME,
                'last_update_time' => NOW_TIME,
            ]);

            $extras = [
                'content_replace' => [
                    'admin_name' => AuthService::getAuthModel()->user_name,
                ],
            ];
            OrderOperationRecordService::create($id, OrderOperationRecordService::CS_DISTRIBUTOR_RECEIVED, $extras);
            $worker_order_model->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 首选技工列表
     * @param $id
     */
    public function receiversFirst()
    {
        $id = I('get.id');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
//            checkAdminOrderPermission($id);

            $worker_order_user_info = BaseModel::getInstance('worker_order_user_info')
                ->getOneOrFail($id, 'area_id,lat,lon');
            $product_category_ids = BaseModel::getInstance('worker_order_product')
                ->getFieldVal(['worker_order_id' => $id], 'product_category_id', true);
            $top_category_ids = BaseModel::getInstance('product_category')
                ->getFieldVal(['id' => ['IN', $product_category_ids]], 'parent_id', true);
            $where_filter = [];
            // repairable_pros 为不可维修分类，数据库备注有误
            foreach ($top_category_ids as $item) {
                $where_filter[] = ' !FIND_IN_SET(' . $item . ', repairable_pros) ';
            }
            $filter_string = implode('AND', $where_filter) . ' AND (first_distribute_areas_path LIKE "%,' . $worker_order_user_info['area_id'] . '" OR first_distribute_areas_path LIKE "%,' . $worker_order_user_info['area_id'] . '-%" OR first_distribute_areas_path LIKE "%,' . $worker_order_user_info['area_id'] . ',%") ';
            $list_in_where = [
                '_string' => $filter_string,
            ];
            // 事先将不算分数的技工ID赛选出来
            $not_worker_ids = array_keys(BaseModel::getInstance('worker')->getList([
                'field' => 'worker_id',
                'where' => [
                    '_complex' => [
                        '_logic' => 'or',
                        'type' => ['not in', implode(',', WorkerService::ADMIN_DISTRIBUTE_TYPE_ARR)],
                        'group_apply_status' => ['in', implode(',', WorkerService::ADMIN_NOT_DISTRIBUTE_GROUP_APPLY_STATUS_ARR)],
                    ],
                ],
                'index' => 'worker_id',
            ]));
            $not_worker_ids && $list_in_where['worker_id'] = ['not in', implode(',', $not_worker_ids)];
            $list_in = BaseModel::getInstance('worker_coop_busine')->getList([
                'where' => $list_in_where,
                'field' => 'worker_id,first_distribute_areas,first_distribute_areas_path,first_distribute_pros',
                'index' => 'worker_id'
            ]);

            $worker_ids = $search_areas = $search_areas_arr = [];

            foreach ($list_in as $k => $v) {
                foreach (explode('-', $v['first_distribute_areas_path']) as $value) {
                    $aids = array_filter(explode(',', $value));
                    $aid  = end($aids);
                    if (!isset($search_areas[$aid])) {
                        $search_areas_arr = array_merge($aids, $search_areas_arr);
                        $search_areas[$aid] = implode(',', $aids);
                    }
                }
                $worker_ids[$k] = $k;
            }
            $worker_ids = implode(',', $worker_ids);

            if (!$worker_ids) {
                $this->paginate();
            }

            $search_areas_ids = implode(',', array_unique($search_areas_arr));
            $area_datas = $search_areas_ids ? BaseModel::getInstance('cm_list_item')->getList([
                'where' => [
                    'list_item_id' => ['in', $search_areas_ids]
                ],
                'index' => 'list_item_id',
                'field' => 'list_item_id,item_desc',
            ]) : [];
            // var_dump($area_datas);die;
            $field = 'worker_id,worker_telephone,nickname,lat,lng,is_check,is_qianzai,is_complete_info';
            $list = BaseModel::getInstance('worker')->getList([
                'where' => [
                    'worker_id' => ['in', $worker_ids],

                ],
                'field' => $field,
                'index' => 'worker_id',
            ]);
            $sort_list = [];
            foreach ($list as $k => $v) {
                $mi = Util::distanceSimplify($worker_order_user_info['lat'], $worker_order_user_info['lon'], $v['lat'], $v['lng']) * 1.5;

                $li = number_format($mi / 1000, 2, '.', '');
                $sort_list[$v['worker_id']] = $li;
            }

            ksort($sort_list);
            arsort($sort_list);

            // 根据分页获取排序后的数据
            $count = count($list);
            $page = explode(',', getPage());
            $sort_list = array_slice($sort_list, $page[0], $page[1], true);

            $return = [];
            foreach ($sort_list as $k => $v) {
                $data = $list[$k];

                $data['first_distribute_pros'] = $list_in[$k]['first_distribute_pros'];
                $data['is_first_distribute_pros'] = array_intersect($top_category_ids, explode(',', $list_in[$k]['first_distribute_pros'])) ? '1' : '0';
                $data['first_distribute_areas'] = $list_in[$k]['first_distribute_areas'];

                foreach (explode(',', $data['first_distribute_areas']) as $value) {
                    $first_distribute_areas_full = [];
                    foreach (explode(',', $search_areas[$value]) as $area_v) {
                        $first_distribute_areas_full[] = $area_datas[$area_v] ?  $area_datas[$area_v]['item_desc'] : '????';
                    }
                    $data['first_distribute_areas_full'][] = implode('-', $first_distribute_areas_full);
                }
                $data['first_distribute_areas_full'] = implode(';', $data['first_distribute_areas_full']);

                $data['li'] = $v;
                $return[]   = $data;
            }

            $this->paginate($return, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function distribute2Worker()
    {
        $id = I('get.id');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
//            checkAdminOrderPermission($id);

            $data = [
                'distribute_mode' => intval(I('distribute_mode')),
                'worker_id' => intval(I('worker_id')),
                'homefee_mode' => intval(I('homefee_mode')),
                'est_miles' => I('est_miles'),
                'straight_miles' => I('straight_miles'),
                'is_send_user_message' => intval(I('is_send_user_message')),
                'user_message' => I('user_message'),
                'is_send_worker_message' => intval(I('is_send_worker_message')),
                'worker_message' => I('worker_message'),
                'desc' => I('desc'),
            ];

            if (!$data['worker_id']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择派单技工');
            } elseif (!$data['homefee_mode']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择上门计费模式');
            } elseif (!in_array($data['distribute_mode'], [0, 2, 3])) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择派单模式');
            }

            (new OrderLogic())->distribute2Worker($id, $data);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function modifyServiceType()
    {
        $id = I('get.id');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $admin = AuthService::getAuthModel();
//            if (!in_array($admin['role_id'], [AdminRoleService::ROLE_SUPER_ADMIN, AdminRoleService::ROLE_CUSTOMER_SERVICE_SUPERVISOR, AdminRoleService::ROLE_CUSTOMER_SERVICE_TEAM_LEADER, AdminRoleService::ROLE_CHECKER, AdminRoleService::ROLE_DISTRIBUTOR, AdminRoleService::ROLE_RETURNEE, AdminRoleService::ROLE_CHECKER_AND_DISTRIBUTOR, AdminRoleService::ROLE_CHECKER_AND_RETURNEE, AdminRoleService::ROLE_CHECKER_AND_DISTRIBUTOR_AND_RETURNEE, AdminRoleService::ROLE_DISTRIBUTOR_AND_RETURNEE])) {
//                $this->fail(ErrorCode::WORKER_ORDER_ADMIN_NO_PERMISSION, '您无权限进行该操作');
//            }
            $worker_order_model = BaseModel::getInstance('worker_order');
            $order = $worker_order_model->getOneOrFail($id, 'worker_order_status,service_type,worker_order_type');
            if ($order['service_type'] == OrderService::TYPE_PRE_RELEASE_INSTALLATION) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预发件安装单无法修改服务类型');
            } elseif ($order['worker_order_status'] >= OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE && $order['worker_order_status'] != OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单当前状态无法修改服务类型');
            }
            $service_type = I('service_type');
            $allow_order_types = Arr::except(OrderService::SERVICE_TYPE, OrderService::TYPE_PRE_RELEASE_INSTALLATION);
            if (!isset($allow_order_types[$service_type])) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择核实的工单类型');
            }
            $remark = I('remark', '');
            $worker_order_model->startTrans();
            $worker_order_model->update($id, [
                'service_type' => $service_type,
            ]);
            OrderOperationRecordService::create($id, OrderOperationRecordService::CS_MODIFY_SERVICE_TYPE, [
                'remark' => '服务类型修改为:' . OrderService::SERVICE_TYPE[$service_type] . ' ' . $remark,
            ]);

            $is_insured = in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST) ? true : false;
            // 冻结金变动 TYPE_CS_SERVICE_TYPE
            $befroe_is_installation = in_array($order['service_type'], OrderService::SERVICE_TYPE_INSTALLATION_TYPE_LIST);
            $after_is_installation = in_array($service_type, OrderService::SERVICE_TYPE_INSTALLATION_TYPE_LIST);
            if ($is_insured && $after_is_installation != $befroe_is_installation) {
                $order_product = BaseModel::getInstance('worker_order_product')->getOne([
                    'field' => 'product_category_id,product_standard_id',
                    'order' => 'id asc',
                    'where' => [
                        'worker_order_id' => $id,
                    ],
                ]);
                $default_factory_frozen = BaseModel::getInstance('factory')->getFieldVal($order['factory_id'], 'default_frozen');
                $frozen_price = FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($service_type, $order['factory_id'], $order_product['product_category_id'], $order_product['product_standard_id'], $default_factory_frozen);
                $frozen_price  = number_format($frozen_price, 2, '.', '');
                FactoryMoneyFrozenRecordService::process($id, FactoryMoneyFrozenRecordService::TYPE_CS_SERVICE_TYPE, $frozen_price);
            }

            $worker_order_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addAuditRemark()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $admin = AuthService::getAuthModel();

//            if (!in_array($admin['role_id'], [AdminRoleService::ROLE_SUPER_ADMIN, AdminRoleService::ROLE_CUSTOMER_SERVICE_SUPERVISOR, AdminRoleService::ROLE_CUSTOMER_SERVICE_TEAM_LEADER, AdminRoleService::ROLE_DISTRIBUTOR, AdminRoleService::ROLE_RETURNEE, AdminRoleService::ROLE_CHECKER_AND_DISTRIBUTOR, AdminRoleService::ROLE_CHECKER_AND_RETURNEE, AdminRoleService::ROLE_CHECKER_AND_DISTRIBUTOR_AND_RETURNEE, AdminRoleService::ROLE_DISTRIBUTOR_AND_RETURNEE])) {
//                $this->fail(ErrorCode::WORKER_ORDER_ADMIN_NO_PERMISSION, '您无权限进行该操作');
//            }

            $id = I('get.id');
            $content = I('content');
            if (!$content) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写结算备注');
            }
            $order = BaseModel::getInstance('worker_order')->getOneOrFail($id, 'worker_order_status');
            if (($order['worker_order_status'] < OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT || $order['worker_order_status'] >= OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT) && $order['worker_order_status'] != OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单当前状态不能添加结算备注');
            }

            BaseModel::getInstance('worker_order_audit_remark')->insert([
                'worker_order_id' => $id,
                'admin_id' => $admin['id'],
                'content' => $content,
                'create_time' => NOW_TIME,
            ]);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getAuditRemark()
    {
        $id = I('get.id');
        $this->requireAuth(AuthService::ROLE_ADMIN);
        try {
            $remarks = BaseModel::getInstance('worker_order_audit_remark')->getList([
                'where' => ['worker_order_id' => $id],
                'order' => 'id DESC',
                'field' => 'id,admin_id,content,create_time',
            ]);
            foreach ($remarks as $key => $remark) {
                $remarks[$key]['content'] = htmlEntityDecode($remark['content']);
            }
            BaseModel::getInstance('admin')->attachField2List($remarks, 'nickout name');

            $this->responseList($remarks);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addOrderOperationRecord()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $id = I('get.id');
            $see_auth = I('see_auth');
            $content = I('content');
            $remark = I('remark', '');
            $images = I('images', '');
            if (!$id) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择所属工单');
            }
            if (!$see_auth) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择可查看角色');
            } elseif (!$content) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写记录内容');
            }
            if ($images) {
                $image_list = explode(',', $images);
                $images = '';
                if (count($image_list) > 8) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '最多只能添加8张图片');
                }
                foreach ($image_list as $image) {
                    $images .= "<img src='{$image}' />";
                }
            }
            OrderOperationRecordService::create($id, OrderOperationRecordService::CS_ADD_ORDER_OPERATION_RECORD, [
                'content_replace' => [
                    'content' => $content,
                ],
                'remark' => $remark . $images,
                'see_auth' => $see_auth,
            ]);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getOrderOperationRecord()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $id = I('get.id');
            $record = BaseModel::getInstance('worker_order_operation_record')->getOneOrFail($id, 'id,content,remark,see_auth');
            $this->response($record);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updateOrderOperationRecord()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $id = I('get.id');
            $see_auth = I('see_auth');
            if (!$see_auth) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择可查看角色');
            }

            BaseModel::getInstance('worker_order_operation_record')->update($id, ['see_auth' => $see_auth]);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function adjustOrderFee()
    {
//        $url = urldecode("factory_product%5B0%5D%5Bworker_order_product_id%5D=726162&factory_product%5B0%5D%5Bfactory_repair_fee_modify%5D=70.00&factory_product%5B0%5D%5Bfactory_repair_reason%5D=&factory_product%5B0%5D%5Bservice_fee_modify%5D=0.00&factory_product%5B0%5D%5Bservice_reason%5D=&factory_appoint_record%5B0%5D%5Bappoint_record_id%5D=651935&factory_appoint_record%5B0%5D%5Bfactory_appoint_fee_modify%5D=14.00&factory_appoint_record%5B0%5D%5Bfactory_appoint_reason%5D=&worker_product%5B0%5D%5Bworker_order_product_id%5D=726162&worker_product%5B0%5D%5Bworker_repair_fee_modify%5D=60.00&worker_product%5B0%5D%5Bworker_repair_reason%5D=&worker_appoint_record%5B0%5D%5Bappoint_record_id%5D=651935&worker_appoint_record%5B0%5D%5Bworker_appoint_fee_modify%5D=1.50&worker_appoint_record%5B0%5D%5Bworker_appoint_reason%5D=&factory_transport_fee%5B0%5D%5Baccessory_id%5D=151899&factory_transport_fee%5B0%5D%5Bfactory_transport_fee_modify%5D=0.00&factory_transport_fee%5B0%5D%5Bfactory_transport_fee_reason%5D=&factory_transport_fee%5B1%5D%5Baccessory_id%5D=151902&factory_transport_fee%5B1%5D%5Bfactory_transport_fee_modify%5D=10.00&factory_transport_fee%5B1%5D%5Bfactory_transport_fee_reason%5D=%E4%BF%AE%E6%94%B913&worker_transport_fee%5B0%5D%5Baccessory_id%5D=151899&worker_transport_fee%5B0%5D%5Bworker_transport_fee_modify%5D=0.00&worker_transport_fee%5B0%5D%5Bworker_transport_fee_reason%5D=&worker_transport_fee%5B1%5D%5Baccessory_id%5D=151902&worker_transport_fee%5B1%5D%5Bworker_transport_fee_modify%5D=12.00&worker_transport_fee%5B1%5D%5Bworker_transport_fee_reason%5D=%E4%BF%AE%E6%94%B9%E6%88%9012&modify_type=1");
//        $url = str_replace('&', "\n", $url);
//        $url = str_replace('=', ":", $url);
//        die($url);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $worker_order_id = I('get.id');
            if (!$worker_order_id) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择工单');
            }

            $order = BaseModel::getInstance('worker_order')->getOneOrFail($worker_order_id, 'worker_id,worker_order_type');
            // 是否是保内单
            $is_insurance = in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST);

            if (!$is_insurance) {
                // 保外单费用更改
                $put = I('put.', []);

                $order_fee = BaseModel::getInstance('worker_order_fee')->getOne($worker_order_id, 'accessory_out_fee_modify,worker_repair_fee_modify');
                $accessory_out_fee_modify = number_format(I('put.worker_accessory_fee_modify', 0), 2, '.', '');
                $worker_repair_fee_modify = number_format(I('put.worker_repair_fee_modify', 0), 2, '.', '');

                $fee_update_data = [];
                isset($put['worker_accessory_fee_modify']) && $accessory_out_fee_modify != $order_fee['accessory_out_fee_modify'] && $fee_update_data['accessory_out_fee_modify'] =  $accessory_out_fee_modify;
                isset($put['worker_repair_fee_modify']) && $worker_repair_fee_modify != $order_fee['worker_repair_fee_modify'] && $fee_update_data['worker_repair_fee_modify'] = $worker_repair_fee_modify;

                M()->startTrans();
                (isset($put['worker_repair_fee_reason']) || isset($put['worker_accessory_fee_reason'])) && BaseModel::getInstance('worker_order_ext_info')->update($worker_order_id, [
                    'worker_repair_out_fee_reason' => $put['worker_repair_fee_reason'] ?? '',
                    'accessory_out_fee_reason' => $put['worker_accessory_fee_reason'] ?? '',
                ]);

                if (!$fee_update_data) {
                    M()->commit();
                    $this->response();
                    exit;
                }


                $fee_update_data += [
                    'accessory_out_fee_modify' => $order_fee['accessory_out_fee_modify'],
                    'worker_repair_fee_modify' => $order_fee['worker_repair_fee_modify'],
                ];

                $fee_update_data['worker_total_fee_modify'] = $fee_update_data['accessory_out_fee_modify'] + $fee_update_data['worker_repair_fee_modify'];
                $fee_update_data['worker_total_fee_modify'] < $order_fee['accessory_out_fee_modify'] + $order_fee['worker_repair_fee_modify']  && $this->fail(ErrorCode::NOTINSUREANCE_FEE_MODIFY_NOT_LT);


                $start = strtotime(date('Y-m-d', NOW_TIME));
                $end = $start + 3600*24-1;

                OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, $fee_update_data);

                // 判断是否发送短信
                $tody_modify_nums = BaseModel::getInstance('worker_order_operation_record')->getNum([
                    'worker_order_id' => $worker_order_id,
                    'operation_type' => OrderOperationRecordService::CS_MODIFY_NOTINRUANCE_WORKER_FEE,
                    'create_time' => ['between', $start.','.$end],
                ]);

                $pay_type_detail = BaseModel::getInstance('worker_order_out_worker_add_fee')->order('create_time asc,id asc')->getFieldVal(['worker_order_id' => $worker_order_id], 'pay_type');
                OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_MODIFY_NOTINRUANCE_WORKER_FEE, [
                    'remark' =>  in_array($pay_type_detail, WorkerOrderOutWorkerAddFeeService::PAY_TYPE_PLATFORM_GET_MONEY_LIST) ? '微信支付的资金填少了，现补上' : '',
                    'content_replace' => [
                        'total_fee' => $fee_update_data['accessory_out_fee_modify'] + $fee_update_data['worker_repair_fee_modify'],
                    ],
                ]);

                $worker = BaseModel::getInstance('worker')->getOne($order['worker_id'] ?? 0, 'worker_telephone');
                // 发送短信（队列，内容未定）
//                !$tody_modify_nums && $worker['worker_telephone'] && sendSms($worker['worker_telephone'], SMSService::TMP_ORDER_ACCESSORY_PROMPT_WORKER_SEND_BACK, []);
                M()->commit();
                $this->response();
                exit();
            }

            $modify_type = I('modify_type');
            $put = I('put.', []);
            // 以下为保内单
            if ($modify_type != 1 && $modify_type != 2) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '费用修改类型错误');
            }

            $worker_order_product_model = BaseModel::getInstance('worker_order_product');
//            $order_logic = new OrderLogic();

            $is_modify_worker_fee = false;
            $is_modify_factory_fee = false;
            $order_fee = [];
            $worker_order_product_model->startTrans();
            if ($modify_type == 1) {
// 厂家产品费用修改
                $factory_product = array_filter(I('factory_product', []));
                $last_money_data = [];
                if ($factory_product) {
                    $factory_repair_fee_modify_total = 0;
                    $service_fee_modify_total = 0;
                    foreach ($factory_product as $item) {
                        if (!$item['worker_order_product_id']) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少产品参数id,请检查~');
                        } elseif (!isset($item['factory_repair_fee_modify'])) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写厂家修改维修费');
                        } elseif (!isset($item['service_fee_modify'])) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写厂家修改服务费');
                        }
                        $worker_order_product_model->update([
                            'id' => $item['worker_order_product_id'],
                            'worker_order_id' => $worker_order_id,
                        ], [
                            'factory_repair_fee_modify' => $item['factory_repair_fee_modify'],
                            'factory_repair_reason' => $item['factory_repair_reason'] ?? '',
                            'service_fee_modify' => $item['service_fee_modify'],
                            'service_reason' => $item['service_reason'] ?? '',
                        ]);
                        $factory_repair_fee_modify_total += $item['factory_repair_fee_modify'];
                        $service_fee_modify_total += $item['service_fee_modify'];
                    }
                    $last_money_data = OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, [
                        'factory_repair_fee_modify' => $factory_repair_fee_modify_total,
                        'service_fee_modify' => $service_fee_modify_total,
                    ]);
                    $is_modify_factory_fee = true;
//                $order_logic->updateOrderFee($worker_order_id, [
//                    'factory_repair_fee_modify' => $factory_repair_fee_modify_total,
//                    'service_fee_modify' => $service_fee_modify_total,
//                ]);
//                $order_logic->recalculateOrderFactoryFee($worker_order_id);
                }
                // 厂家预约费用修改
                $factory_appoint_record = array_filter(I('factory_appoint_record', []));
                if ($factory_appoint_record) {
                    $factory_appoint_fee_modify_total = 0;
                    foreach ($factory_appoint_record as $item) {
                        if (!$item['appoint_record_id']) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少预约记录id,请检查~');
                        } elseif (!isset($item['factory_appoint_fee_modify'])) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写厂家预约修改费用');
                        }
                        BaseModel::getInstance('worker_order_appoint_record')->update([
                            'id' => $item['appoint_record_id'],
                            'worker_order_id' => $worker_order_id,
                        ], [
                            'factory_appoint_fee_modify' => $item['factory_appoint_fee_modify'],
                            'factory_appoint_reason' => $item['factory_appoint_reason'] ?? '',
                        ]);
                        $factory_appoint_fee_modify_total += $item['factory_appoint_fee_modify'];
                    }
                    $last_money_data = OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, [
                        'factory_appoint_fee_modify' => $factory_appoint_fee_modify_total,
                    ]);
                    $is_modify_factory_fee = true;
//                $order_logic->updateOrderFee($worker_order_id, [
//                    'factory_appoint_fee_modify' => $factory_appoint_fee_modify_total,
//                ]);
//                $order_logic->recalculateOrderFactoryFee($worker_order_id);
                }

                // zjz
                if (isset($put['factory_transport_fee'])) {
                    $factory_transport_fee = I('put.factory_transport_fee', []);
                    $update_acce = [];
                    $acce_model = BaseModel::getInstance('worker_order_apply_accessory');
                    $acce_list = $acce_model->getList([
                        'field' => 'id,factory_transport_fee,factory_transport_fee_modify,worker_return_pay_method,is_giveup_return',
                        'where' => [
//                            'worker_return_pay_method' => AccessoryService::PAY_METHOD_NOW_PAY,
//                            'is_giveup_return' => AccessoryService::RETURN_ACCESSORY_PASS,
                            'worker_order_id' => $worker_order_id,
                        ],
                        'index' => 'id',
                    ]);
                    
                    foreach ($factory_transport_fee as $key => $item) {
                        !$item['accessory_id'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少配件单id,请检查~');
                        !isset($item['factory_transport_fee_modify']) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写厂家配件运费修改费用');
                        !isset($acce_list[$item['accessory_id']]) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '不存在配件单id,请检查~');
                        $item['factory_transport_fee_modify'] = number_format($item['factory_transport_fee_modify'], 2, '.', '');
                        $update_acce[$item['accessory_id']] = $item;
                        unset($factory_transport_fee[$key]);
                    }

                    $factory_accessory_return_fee_modify = 0;
                    foreach ($acce_list as $v) {
                        $fee_modify = $update_acce[$v['id']]['factory_transport_fee_modify'];
                        if (isset($update_acce[$v['id']]) && $v['worker_return_pay_method'] == AccessoryService::PAY_METHOD_NOW_PAY && $v['is_giveup_return'] == AccessoryService::RETURN_ACCESSORY_PASS) {
                            $factory_accessory_return_fee_modify += $fee_modify;

                            $acce_model->update([
                                'id' => $v['id'],
                                'worker_order_id' => $worker_order_id,
                            ], [
                                'factory_transport_fee_modify' => $fee_modify,
                                'factory_transport_fee_reason' => $update_acce[$v['id']]['factory_transport_fee_reason'] ?? '',
                            ]);

                        } elseif ($v['worker_return_pay_method'] == AccessoryService::PAY_METHOD_NOW_PAY && $v['is_giveup_return'] == AccessoryService::RETURN_ACCESSORY_PASS) {
                            $factory_accessory_return_fee_modify += $v['factory_transport_fee_modify'];
                        }
                    }

                    $order_fee = OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, [
                        'factory_accessory_return_fee_modify' => $factory_accessory_return_fee_modify,
                    ]);
                    $is_modify_factory_fee = true;
                }

            } else {
                // 技工产品费用修改
                $worker_product = array_filter(I('worker_product', []));
                if ($worker_product) {
                    $worker_repair_fee_modify_total = 0;
                    foreach ($worker_product as $item) {
                        if (!$item['worker_order_product_id']) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少产品参数id,请检查~');
                        } elseif (!isset($item['worker_repair_fee_modify'])) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写技工修改维修费');
                        }
                        $worker_order_product_model->update([
                            'id' => $item['worker_order_product_id'],
                            'worker_order_id' => $worker_order_id,
                        ], [
                            'worker_repair_fee_modify' => $item['worker_repair_fee_modify'],
                            'worker_repair_reason' => $item['worker_repair_reason'] ?? '',
                        ]);
                        $worker_repair_fee_modify_total += $item['worker_repair_fee_modify'];
                    }
                    $order_fee = OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, [
                        'worker_repair_fee_modify' => $worker_repair_fee_modify_total,
                    ]);
                    $is_modify_worker_fee = true;
//                $order_logic->updateOrderFee($worker_order_id, [
//                    'worker_repair_fee_modify' => $worker_repair_fee_modify_total,
//                ]);
//                $order_logic->recalculateWorkerOrderFee($worker_order_id);
                }
                // 技工预约费用修改
                $worker_appoint_record = array_filter(I('worker_appoint_record', []));
                if ($worker_appoint_record) {
                    $worker_appoint_fee_modify_total = 0;
                    foreach ($worker_appoint_record as $item) {
                        if (!$item['appoint_record_id']) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少预约记录id,请检查~');
                        } elseif (!isset($item['worker_appoint_fee_modify'])) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写技工预约修改费用');
                        }
                        BaseModel::getInstance('worker_order_appoint_record')->update([
                            'id' => $item['appoint_record_id'],
                            'worker_order_id' => $worker_order_id,
                        ], [
                            'worker_appoint_fee_modify' => $item['worker_appoint_fee_modify'],
                            'worker_appoint_reason' => $item['worker_appoint_reason'] ?? '',
                        ]);
                        $worker_appoint_fee_modify_total += $item['worker_appoint_fee_modify'];
                    }
                    $order_fee = OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, [
                        'worker_appoint_fee_modify' => $worker_appoint_fee_modify_total,
                    ]);
                    $is_modify_worker_fee = true;
//                $order_logic->updateOrderFee($worker_order_id, [
//                    'worker_appoint_fee_modify' => $worker_appoint_fee_modify_total,
//                ]);
//                $order_logic->recalculateWorkerOrderFee($worker_order_id);
                }
                $worker_apply_allowance = array_filter(I('worker_apply_allowance'));
                if ($worker_apply_allowance) {
                    $apply_fee_modify_total = 0;
                    foreach ($worker_apply_allowance as $item) {
                        if (!$item['apply_allowance_id']) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少补贴单记录id,请检查~');
                        } elseif (!isset($item['apply_fee_modify'])) {
                            $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写技工补贴单修改费用');
                        }
                        BaseModel::getInstance('worker_order_apply_allowance')->update($item['apply_allowance_id'], [
                            'apply_fee_modify' => $item['apply_fee_modify'],
                            'modify_reason' => $item['modify_reason'] ?? '',
                        ]);
                        $apply_fee_modify_total += $item['apply_fee_modify'];
                    }
                    $order_fee = OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, [
                        'worker_allowance_fee_modify' => $apply_fee_modify_total,
                    ]);
                    $is_modify_worker_fee = true;
//                $order_logic->updateOrderFee($worker_order_id, [
//                    'worker_allowance_fee_modify' => $apply_fee_modify_total,
//                ]);
//                $order_logic->recalculateWorkerOrderFee($worker_order_id);
                }

                // zjz
                if (isset($put['worker_transport_fee'])) {
                    $worker_transport_fee = I('put.worker_transport_fee', []);
                    $update_acce = [];
                    $acce_model = BaseModel::getInstance('worker_order_apply_accessory');
                    $acce_list = $acce_model->getList([
                        'field' => 'id,worker_transport_fee,worker_transport_fee_modify,worker_return_pay_method,is_giveup_return',
                        'where' => [
//                            'worker_return_pay_method' => AccessoryService::PAY_METHOD_NOW_PAY,
//                            'is_giveup_return' => AccessoryService::RETURN_ACCESSORY_PASS,
                            'worker_order_id' => $worker_order_id,
                        ],
                        'index' => 'id',
                    ]);

                    foreach ($worker_transport_fee as $key => $item) {
                        !$item['accessory_id'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少配件单id,请检查~');
                        !isset($item['worker_transport_fee_modify']) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写技工配件运费修改费用');
                        !isset($acce_list[$item['accessory_id']]) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '不存在配件单id,请检查~');
                        $item['worker_transport_fee_modify'] = number_format($item['worker_transport_fee_modify'], 2, '.', '');
                        $update_acce[$item['accessory_id']] = $item;
                        unset($worker_transport_fee[$key]);
                    }

                    $worker_accessory_return_fee_modify = 0;
                    foreach ($acce_list as $v) {
                        $fee_modify = $update_acce[$v['id']]['worker_transport_fee_modify'];
                        if (isset($update_acce[$v['id']]) && $v['worker_return_pay_method'] == AccessoryService::PAY_METHOD_NOW_PAY && $v['is_giveup_return'] == AccessoryService::RETURN_ACCESSORY_PASS) {
                            $worker_accessory_return_fee_modify += $fee_modify;

                            $acce_model->update([
                                'id' => $v['id'],
                                'worker_order_id' => $worker_order_id,
                            ], [
                                'worker_transport_fee_modify' => $fee_modify,
                                'worker_transport_fee_reason' => $update_acce[$v['id']]['worker_transport_fee_reason'] ?? '',
                            ]);
                        } elseif ($v['worker_return_pay_method'] == AccessoryService::PAY_METHOD_NOW_PAY && $v['is_giveup_return'] == AccessoryService::RETURN_ACCESSORY_PASS) {
                            $worker_accessory_return_fee_modify += $v['worker_transport_fee_modify'];
                        }
                    }
                    $order_fee = OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, [
                        'worker_accessory_return_fee_modify' => $worker_accessory_return_fee_modify,
                    ]);
                    $is_modify_worker_fee = true;
                }

            }

//            var_dump($order_fee);die;
            $order_fee = BaseModel::getInstance('worker_order_fee')->getOne($worker_order_id, 'factory_total_fee_modify,worker_total_fee_modify');
            $is_modify_worker_fee && OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_MODIFY_WORKER_FEE, [
                'content_replace' => [
                    'total_fee' => $order_fee['worker_total_fee_modify']
                ]
            ]);
            $is_modify_factory_fee && OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_MODIFY_FACTORY_FEE, [
                'content_replace' => [
                    'total_fee' => $order_fee['factory_total_fee_modify']
                ]
            ]);
//            $is_modify_factory_fee && isset($order_fee['factory_total_fee_modify']) &&
//            FactoryMoneyFrozenRecordService::process($worker_order_id, FactoryMoneyFrozenRecordService::TYPE_CS_EDIT_FACTORY_FEE, $order_fee['factory_total_fee_modify']);
            $worker_order_product_model->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function userPaid()
    {
        try {
            $admin_id = $this->requireAuth(AuthService::ROLE_ADMIN);

            $id = I('get.id');
            $put = I('put.', []);

            !isset($put['it_is_ture']) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '未选择是否属实');

            $it_is_ture = I('put.it_is_ture', '');
            $worker_repair_fee = I('put.worker_repair_fee', '');
            $accessory_fee = I('put.accessory_fee', '');

            !in_array($it_is_ture, ['0', '1'], true) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择是否属实');

            $logic = new \Qiye\Logic\OrderLogic();
            M()->startTrans();
            if ($it_is_ture == '0') {
                !isset($put['worker_repair_fee']) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写保外单技工维修金费用');
                !isset($put['accessory_fee']) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写保外单配件费用');
                $worker_repair_fee + $accessory_fee < 1 && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保外单支付金额不能小于1元');
                $request = [
                    'worker_repair_fee' => number_format($worker_repair_fee, 2, '.', ''),
                    'accessory_out_fee' => number_format($accessory_fee, 2, '.', ''),
                ];
                $logic->cashPaySuccess($id, $admin_id, $request);
            } else {
                $logic->cashPaySuccess($id, $admin_id);
            }
            M()->commit();
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
