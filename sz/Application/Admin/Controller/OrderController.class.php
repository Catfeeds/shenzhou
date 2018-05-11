<?php
/**
 * File: OrderController.class.php
 * User: xieguoqiu
 * Date: 2017/4/5 15:09
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\AccessoryLogic;
use Admin\Logic\AllowanceLogic;
use Admin\Logic\ApplyCostLogic;
use Admin\Logic\OrderLogic;
use Admin\Logic\ProductLogic;
use Admin\Logic\WorkerAddApplyLogic;
use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\UpdateOrderNumberEvent;
use Common\Common\CacheModel\WorkerOrderProductCacheModel;
use Common\Common\CacheModel\WorkerOrderUserInfoCacheModel;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Common\Common\Service\CostService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\OrderMessageService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\FaultTypeService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\SystemMessageService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class OrderController extends BaseController
{

    /**
     * 工单列表
     */
    public function getList()
    {
        try {
            $this->requireAuth();

            $is_export = I('is_export', 0, 'intval');

            $orders = (new OrderLogic())->getList();

            if (1 != $is_export) {
                $this->paginate($orders['list'], $orders['num']);
            }
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getListAll()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            if (!I('orno') && !I('worker_phone') && !I('user_phone')) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $orders = (new OrderLogic())->getList();

            $this->paginate($orders['list'], $orders['num']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getTypeNum()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $admin = AuthService::getAuthModel();

            $type_num_list = [
                'need_checker_receive_orders' => 0,
                'need_checker_check_orders' => 0,
                'need_distributor_receive_orders' => 0,
                'need_distributor_distribute_orders' => 0,
                'need_returnee_receive_orders' => 0,
                'need_returnee_return_orders' => 0,
                'need_auditor_receive_orders' => 0,
                'need_auditor_audit_orders' => 0,
                'worker_in_service_orders' => 0,
                'need_factory_auditor_audit_orders' => 0,
                'factory_auditor_audit_not_pass_orders' => 0,
                'no_follow_over_one_day_orders' => 0,
                'yima_factory_self_process_orders' => 0,
            ];

            $admin_id = AuthService::getAuthModel()->getPrimaryValue();

            $admin_role_ids = AdminCacheModel::getRelation($admin['id'], 'rel_admin_roles', 'admin_id', 'admin_roles_id');
            $admin_type = 0;
            $is_manager = false;
            foreach ($admin_role_ids as $key => $admin_role_id) {
                $admin_role = AdminRoleCacheModel::getOne($admin_role_id, 'level,type,is_disable');
                if ($admin_role['is_disable'] == 1) {
                    continue;
                }
                $admin_type |= $admin_role['type'];
                !$is_manager && ($is_manager = $admin_role['level'] == 2 ? true : false);
            }
            $order_logic = new OrderLogic();
            // 核实工单客服
            if ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER) {
                $need_receive = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE
                ]);
                $need_check = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK
                ], [
                    'checker_id' => $admin_id
                ]);
                $type_num_list['need_checker_receive_orders'] = (int)$need_receive[OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE]['num'];
                $type_num_list['need_checker_check_orders'] = (int)$need_check[OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK]['num'];
            }
            // 派单跟单客服
            if ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR) {
                $need_receive = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
                ]);
                $need_distribute = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
                ], [
                    'distributor_id' => $admin_id
                ]);
                $type_num_list['need_distributor_receive_orders'] = (int)$need_receive[OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE]['num'];
                $type_num_list['need_distributor_distribute_orders'] = (int)$need_distribute[OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE]['num'];


                $recent_update_time = NOW_TIME - 86400;
                $type_num_list['no_follow_over_one_day_orders'] = (int)BaseModel::getInstance('worker_order')->getNum([
                    'where' => [
                        'worker_order_status' => ['NEQ', OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED],
                        'cancel_status' => ['IN', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
                        '_string' => "(checker_id={$admin_id} OR distributor_id={$admin_id} OR returnee_id={$admin_id} OR auditor_id={$admin_id}) AND (SELECT create_time<={$recent_update_time} FROM worker_order_operation_record WHERE worker_order_operation_record.worker_order_id=worker_order.id ORDER BY id DESC LIMIT 1)"
                    ],
                ]);

                $order_in_service_list = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
                    OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
                    OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
                ], [
                    'distributor_id' => $admin_id
                ]);
                $type_num_list['worker_in_service_orders'] = $order_in_service_list[OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]['num'] + $order_in_service_list[OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE]['num'] + $order_in_service_list[OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE]['num'];
            }
            // 回访客服
            if ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE) {
                $need_receive = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
                ]);
                $data = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
                ], [
                    'returnee_id' => $admin_id,
                ]);
                $type_num_list['need_returnee_receive_orders'] = (int)$need_receive[OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE]['num'];
                $type_num_list['need_returnee_return_orders'] = (int)($data[OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT]['num'] + $data[OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT]['num']);
            }
            // 财务客服/财务主管
            if ($admin_type & AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR) {
                $meed_receive = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
                ]);
                $data = $order_logic->getOrderStatusNumMap([
                    OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
                    OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
                    OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
                ], [
                    'auditor_id' => $admin_id,
                ]);
                $type_num_list['need_auditor_receive_orders'] = (int)$meed_receive[OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE]['num'];
                $type_num_list['need_auditor_audit_orders'] = (int)($data[OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT]['num'] + $data[OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT]['num']);
                $type_num_list['need_factory_auditor_audit_orders'] = (int)$data[OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT]['num'];
                $type_num_list['factory_auditor_audit_not_pass_orders'] = (int)$data[OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT]['num'];
            }
            // 客服主管、超级管理员
//            if (in_array($admin['role_id'], [AdminRoleService::ROLE_CUSTOMER_SERVICE_SUPERVISOR, AdminRoleService::ROLE_SUPER_ADMIN])) {
            if ($is_manager) {
                $type_num_list['yima_factory_self_process_orders'] = (int)BaseModel::getInstance('worker_order')->getNum([
                    'worker_order_status' => OrderService::STATUS_FACTORY_SELF_PROCESSED,
                    'worker_order_type' => OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE,
                ]);
            }

            $this->response($type_num_list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 工单详情
     */
    public function getDetail()
    {
        $id = I('get.id');
        try {
            $this->requireAuth();
            $order = (new OrderLogic())->show($id);

            $this->response($order);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    /**
     * 修改工单用户信息
     */
    public function confirmOrderUserInfo()
    {
        $id = I('get.id');
        try {
            $this->requireAuth();
            $user_info = [
                'province_id' => I('province_id'),
                'city_id' => I('city_id'),
                'area_id' => I('area_id'),
                'street_id'=> I('street_id',0),
                'address' => I('address'),
                'real_name' => I('real_name'),
                'phone' => I('phone'),
            ];
            if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
//                checkAdminOrderPermission($id);
                $user_info['lat'] = I('lat');
                $user_info['lon'] = I('lon');
            }

            $this->checkEmpty($user_info);
            $area_id_map = AreaService::getAreaNameMapByIds([$user_info['province_id'], $user_info['city_id'], $user_info['area_id'],$user_info['street_id']]);
            $user_info['cp_area_names'] = implode('-', Arr::pluck($area_id_map, 'name'));
            $worker_order_model = BaseModel::getInstance('worker_order');
            $worker_order_status = $worker_order_model->getFieldVal($id, 'worker_order_status');
            // 判断工单状态
            if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
                if ($worker_order_status >= OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '维修商已预约无法修改,请确认');
                }
            } else {
                if ($worker_order_status >= OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '技工已接单午饭修改');
                }
            }


            if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
                $operation_type = OrderOperationRecordService::CS_MODIFY_USER_INFO;
            } else {
                $operation_type = OrderOperationRecordService::FACTORY_MODIFY_USER_INFO;
            }

            $worker_order_model->startTrans();
            BaseModel::getInstance('worker_order_user_info')->update($id, $user_info);
            OrderOperationRecordService::create($id, $operation_type);
            $worker_order_model->commit();

            //自动接单缓存
            WorkerOrderUserInfoCacheModel::addWorkerOrderUserInfo($id);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    public function workerHandleOrderTest()
    {
        $id = I('get.id');
        try {
            $order = BaseModel::getInstance('worker_order')->getOneOrFail($id);
            if ($order['worker_order_status'] != 7) {
                exit('该工单不是待技工预约状态，请确认');
            }
            $worker_order_model = BaseModel::getInstance('worker_order');
            $worker_order_model->startTrans();
            BaseModel::getInstance('worker_order_appoint_record')->insert([
                'worker_id' => $order['worker_id'],
                'worker_order_id' => $order['id'],
                'appoint_status' => 4,
                'appoint_time' => NOW_TIME + 600,
                'factory_appoint_fee' => 10,
                'factory_appoint_fee_modify' => 10,
                'worker_appoint_fee' => 10,
                'worker_appoint_fee_modify' => 10,
                'create_time' => NOW_TIME,
                'is_sign_in' => 1,
                'sign_in_time' => NOW_TIME + 300,
                'is_over' => 1,
            ]);
            $worker_order_model->update($id, [
                'worker_order_status' => 10,
                'worker_first_appoint_time' => NOW_TIME,
                'worker_first_sign_time' => NOW_TIME + 300,
                'worker_repair_time' => NOW_TIME + 400,
            ]);
            $worker_order_model->commit();

            exit('ok');
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function cancel()
    {
        $id = I('get.id');
        try {
            $this->requireAuth();

            $reason_type = I('reason_type');
            $cancel_remark = I('cancel_remark', '');
            if (!$reason_type) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择取消原因');
            }

            $worker_order_model = BaseModel::getInstance('worker_order');
            $order = $worker_order_model->getOneOrFail($id, 'id,orno,factory_check_order_id,factory_check_order_type,factory_id,distributor_id,checker_id,worker_order_type,worker_order_status,worker_group_id,worker_id,children_worker_id');

            if ($order['worker_order_status'] == OrderService::STATUS_FACTORY_SELF_PROCESSED) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '自行处理工单无需取消');
            }
            if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
                $operation_type = OrderOperationRecordService::CS_CANCEL_ORDER;
                $reason = OrderService::CS_CANCEL_REASON[$reason_type];
                if ($order['worker_order_status'] >= OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE && $order['worker_order_status'] != OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单只能在待客服回访之前取消');
                }
                (new AccessoryLogic())->checkAllCompleted($id);
                $cancel_status = OrderService::CANCEL_TYPE_CS;

                $message_receiver_type = $order['factory_check_order_type'] == 1 ? SystemMessageService::USER_TYPE_FACTORY : SystemMessageService::USER_TYPE_FACTORY_ADMIN;
                $message = "工单号{$order['orno']}，已被客服放弃";
                $receiver_id = $order['factory_check_order_id'];
                $message_type = SystemMessageService::MSG_TYPE_FACTORY_ORDER_ADMIN_STOP;

            } else {
                $operation_type = OrderOperationRecordService::FACTORY_CANCEL_ORDER;
                $reason = OrderService::FACTORY_CANCEL_REASON[$reason_type];
                if ($order['worker_order_status'] >= OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单已有技工接单，不能取消');
                }
                $cancel_status = AuthService::getModel() == AuthService::ROLE_FACTORY ? OrderService::CANCEL_TYPE_FACTORY : OrderService::CANCEL_TYPE_FACTORY_ADMIN;

                $message_receiver_type = SystemMessageService::USER_TYPE_ADMIN;
                $message = "工单号{$order['orno']}，厂家已经取消工单";
                $receiver_id = $order['distributor_id'] ? : $order['checker_id'];
                $message_type = SystemMessageService::MSG_TYPE_ADMIN_ORDER_FACTORY_STOP;
            }
            $worker_order_model->startTrans();
            $worker_order_model->update($order['id'], [
                'cancel_status' => $cancel_status,
                'cancel_time' => NOW_TIME,
                'cancel_type' => $reason_type,
                'cancel_remark' => $cancel_remark,
            ]);
            OrderOperationRecordService::create($id, $operation_type, [
                'remark' => $reason . '-' . $cancel_remark,
            ]);
            //群内工单修改数量
            event(new UpdateOrderNumberEvent([
                'worker_order_id'              => $id,
                'operation_type'               => $operation_type,
                'original_worker_id'           => $order['worker_id'],
                'original_children_worker_id'  => $order['children_worker_id'],
                'original_worker_order_status' => $order['worker_order_status']
            ]));
            // 保内单则取消冻结金
            if (OrderService::isInsurance($order['worker_order_type'])) {
                OrderSettlementService::unfreezeOrderMoneyAndOtherOrder($id, $order['factory_id']);
            }

            SystemMessageService::create($message_receiver_type, $receiver_id, $message, $order['id'], $message_type);

            $worker_order_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getOrderProductFaults()
    {
        $id = I('get.id');
        try {
            $this->requireAuth();
            [$factory_id, $service_type, $worker_order_type] = array_values(BaseModel::getInstance('worker_order')->getOneOrFail($id, 'factory_id,service_type,worker_order_type'));
            $order_products = $id ? BaseModel::getInstance('worker_order_product')->getList([
                'where' => ['worker_order_id' => $id],
                'field' => 'product_category_id,product_standard_id,cp_product_standard_name product_standard_name,cp_category_name category_name,concat(product_category_id,"_",product_standard_id) as fautl_keys',
                'index' => 'fautl_keys',
            ]) : [];
            
            $category_ids = Arr::pluck($order_products, 'product_category_id');
            $where = [
                'where' => [
                    'product_id' => ['IN', $category_ids]
                ],
            ];
            $category_id_fault_list_map = $category_ids ? BaseModel::getInstance('product_miscellaneous')->getFieldVal($where, 'product_id,product_faults', true) : [];

            $fault_type = (string)OrderService::SERVICE_TYPE_FRO_FAULT_TYPE_ARR[$service_type];
            $product_logic = new ProductLogic();
            foreach ($order_products as &$order_product) {
                unset($order_product['fautl_keys']);
                $order_product['fault_type'] = $fault_type;
                $order_product['fault_type_name'] = FaultTypeService::FAULT_TYPE_NAME_ARR[$fault_type];
                $order_product['faults'] = $product_logic->getFactoryFaultPriceByCategoryIdAndStandardId($factory_id, $order_product['product_category_id'], $order_product['product_standard_id'], $category_id_fault_list_map[$order_product['product_category_id']], $fault_type);
            }

            //工单保内外类型  0是保内  1是保外
            $order_product['is_insurance'] = in_array($worker_order_type,OrderService::ORDER_TYPE_IN_INSURANCE_LIST) ? 0 :1;

            $this->responseList(array_values($order_products));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function operateOrderProducts()
    {
        try {
            $this->requireAuth();
            $worker_order_id = I('get.id');
            $add = array_filter(I('add'));
            $update = array_filter(I('update'));
            $delete = array_filter(I('delete'));
            if (!$add && !$update && !$delete) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少请求参数,请作改动后再提交');
            }
            $order = BaseModel::getInstance('worker_order')->getOneOrFail($worker_order_id, 'orno,worker_order_type,factory_id,worker_order_status,service_type,distributor_id');
            if ($order['worker_order_status'] > OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE && $order['worker_order_status'] != OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '只有技工未完成服务前才能修改工单产品');
            }

            $is_insured = in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST);

            $auth_model = AuthService::getModel();
            $worker_order_product_model = BaseModel::getInstance('worker_order_product');
            $worker_order_product_model->startTrans();
            $factory = BaseModel::getInstance('factory')->getOneOrFail([
                'where' => ['factory_id' => $order['factory_id']],
                'field' => 'factory_id,money,default_frozen',
                'lock' => true,
            ]);
            if ($add) {
                foreach ($add as $key => $item) {
                    if (!$item['product_category_id']) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品类别');
                    } elseif (!$item['product_standard_id']) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品规格');
                    }
                    $add[$key]['product_nums'] = 1;
                    $add[$key]['worker_order_id'] = $worker_order_id;

                    $product_frozen_money = $is_insured ? FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($order['service_type'], $order['factory_id'], $item['product_category_id'], $item['product_standard_id'], $factory['default_frozen']) : 0;
                    $add[$key]['frozen_money'] = $product_frozen_money;
                    $add[$key]['factory_repair_fee'] = $product_frozen_money;
                    $add[$key]['factory_repair_fee_modify'] = $product_frozen_money;
                }
                $product_logic = new ProductLogic();
                $product_logic->loadProductCpDetailInfo($add);

                $add_products = [];
                foreach ($add as $item) {
                    $add_products[] = "{$item['cp_product_brand_name']}-{$item['cp_product_standard_name']}-{$item['cp_product_mode']}-{$item['cp_category_name']}";
                }
                $worker_order_product_model->insertAll($add);
                $operation_type = $auth_model == AuthService::ROLE_ADMIN ? OrderOperationRecordService::CS_ORDER_ADD_PRODUCT : OrderOperationRecordService::FACTORY_ORDER_ADD_PRODUCT;
                OrderOperationRecordService::create($worker_order_id, $operation_type, [
                    'remark' => '添加工单产品：' . implode(';', $add_products),
                ]);
            }
            if ($update) {
                $product_logic = new ProductLogic();
                $product_logic->loadProductCpDetailInfo($update);

                $update_products = [];
                foreach ($update as $key => $item) {
                    $product_frozen_money = $is_insured ? FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($order['service_type'], $order['factory_id'], $item['product_category_id'], $item['product_standard_id'], $factory['default_frozen']) : 0;
                    $update_set = $item;
                    $update_set['frozen_money']                 = $update[$key]['frozen_money'] = $product_frozen_money;
                    $update_set['factory_repair_fee']           = $update[$key]['factory_repair_fee'] = $product_frozen_money;
                    $update_set['factory_repair_fee_modify']    = $update[$key]['factory_repair_fee_modify'] = $product_frozen_money;
                    $update_set['fault_id']                     = $update[$key]['fault_id'] = 0;
                    $update_set['cp_fault_name']                = $update[$key]['cp_fault_name'] = '';
                    $update_products[] = "{$item['cp_product_brand_name']}-{$item['cp_product_standard_name']}-{$item['cp_product_mode']}-{$item['cp_category_name']}";
                    $worker_order_product_model->update($item['id'], $update_set);
                }

                $operation_type = $auth_model == AuthService::ROLE_ADMIN ? OrderOperationRecordService::CS_ORDER_MODIFY_PRODUCT : OrderOperationRecordService::FACTORY_ORDER_MODIFY_PRODUCT;
                OrderOperationRecordService::create($worker_order_id, $operation_type, [
                    'remark' => '工单产品修改为：' . implode(';', $update_products),
                ]);
            }
            if ($delete) {
                $worker_order_products = $worker_order_product_model->getList([
                    'where' => [
                        'id' => ['IN', $delete],
                    ],
                    'field' => 'id,worker_order_id,is_complete,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode',
                    'index' => 'id'
                ]);
                $delete_products = [];

                foreach ($worker_order_products as $worker_order_product) {
                    if ($worker_order_id != $worker_order_product['worker_order_id']) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '产品不同属于一个工单,删除失败');
                    }
                    if ($worker_order_product['is_complete'] == 1) {
                        $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, "{$worker_order_product['cp_product_brand_name']} {$worker_order_product['cp_product_standard_name']} {$worker_order_product['cp_product_mode']} {$worker_order_product['cp_category_name']} 已上传完成服务报告,无法删除");
                    }
                    $delete_products[] = "{$worker_order_product['cp_product_brand_name']}-{$worker_order_product['cp_product_standard_name']}-{$worker_order_product['cp_product_mode']}-{$worker_order_product['cp_category_name']}";
                }
                $leave_num = $worker_order_product_model->getNum([
                    'worker_order_id' => $worker_order_id,
                    'id' => ['NOT IN', $delete],
                ]);
                if ($leave_num < 1) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '最少需要剩余1个产品不能删除');
                }
                $accessory = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
                    'where' => [
                        'worker_order_product_id' => ['IN', $delete],
                        'cancel_status' => ['NEQ', AccessoryService::CANCEL_STATUS_NORMAL],
                        'accessory_status' => ['IN', [AccessoryService::STATUS_WORKER_APPLY_ACCESSORY, AccessoryService::STATUS_ADMIN_CHECKED, AccessoryService::STATUS_FACTORY_CHECKED, AccessoryService::STATUS_FACTORY_SENT, AccessoryService::STATUS_WORKER_TAKE, AccessoryService::STATUS_WORKER_SEND_BACK, AccessoryService::STATUS_COMPLETE]]
                    ],
                    'field' => 'worker_order_product_id'
                ]);
                if ($accessory) {
                    $has_accessory_product = $worker_order_products[$accessory['worker_order_product_id']];
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, "{$has_accessory_product['cp_product_brand_name']} {$has_accessory_product['cp_product_standard_name']} {$has_accessory_product['cp_product_mode']} {$has_accessory_product['cp_category_name']} 有配件单不能删除");
                }
                $cost = BaseModel::getInstance('worker_order_apply_cost')->getOne([
                    'where' => [
                        'worker_order_product_id' => ['IN', $delete],
                        'status' => ['IN', [CostService::STATUS_APPLY, CostService::STATUS_ADMIN_PASS, CostService::STATUS_FACTORY_PASS]]
                    ],
                    'field' => 'worker_order_product_id'
                ]);
                if ($cost) {
                    $has_cost_product = $worker_order_products[$cost['worker_order_product_id']];
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, "{$has_cost_product['cp_product_brand_name']} {$has_cost_product['cp_product_standard_name']} {$has_cost_product['cp_product_mode']} {$has_cost_product['cp_category_name']} 有配件单不能删除");
                }
                $worker_order_product_model->remove(['id' => ['IN', $delete]]);

                $operation_type = $auth_model == AuthService::ROLE_ADMIN ? OrderOperationRecordService::CS_ORDER_DELETE_PRODUCT : OrderOperationRecordService::FACTORY_ORDER_DELETE_PRODUCT;
                OrderOperationRecordService::create($worker_order_id, $operation_type, [
                    'remark' => '删除工单产品：' . implode(';', $delete_products),
                ]);

                //自动接单缓存
                foreach ($delete as $product_id) {
                    WorkerOrderProductCacheModel::removeCache($product_id);
                }
            }

            $order_fee = BaseModel::getInstance('worker_order_fee')->getOne($worker_order_id, 'service_fee_modify');
            $product_sum = $worker_order_product_model->getOne([
                'worker_order_id' => $worker_order_id,
            ], 'sum(frozen_money*product_nums) sum_product_money');
            $total_frozen = $product_sum['sum_product_money'] + $order_fee['service_fee_modify'];


            $frozen_type = AuthService::getModel() == AuthService::ROLE_ADMIN ? FactoryMoneyFrozenRecordService::TYPE_CS_MODIFY_PRODUCT : FactoryMoneyFrozenRecordService::TYPE_FACTORY_MODIFY_PRODUCT;
            FactoryMoneyFrozenRecordService::process($worker_order_id, $frozen_type, $total_frozen);

            // 全部完成维修
            $complete_num = BaseModel::getInstance('worker_order_product')->getNum([
                'worker_order_id' => $worker_order_id,
                'is_complete' => ['not in', '1,2']
            ]);
            if (!$complete_num) {
                $update_data['worker_repair_time'] = NOW_TIME;
                $update_data['worker_order_status'] = OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE;
                $update_data['last_update_time'] = NOW_TIME;
                //更新订单状态
                BaseModel::getInstance('worker_order')->update([
                    'id' => $worker_order_id
                ], $update_data);
                //添加操作记录
                OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::SYSTEM_DELETE_PRODUCT_AUTH_FINISH_REPAIR);
                //后台推送
                SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order['distributor_id'], '工单号'.$order['orno'].'，完成维修', $worker_order_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_WORKER_UPLOAD_REPORT);
                // 完成服务结算
//        if ($worker_order_status == 10 && in_array($order_info['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
//            OrderSettlementService::autoSettlement();
//        }
                OrderSettlementService::autoSettlement();
            }


            // TODO 发送修改通知
            $worker_order_product_model->commit();

            $product_ids = WorkerOrderProductCacheModel::addWorkerOrderProductIds($worker_order_id);
            foreach ($product_ids as $product_id) {
                WorkerOrderProductCacheModel::addWorkerOrderProduct($product_id);
            }

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function detailsServices()
    {
        try {
            $this->requireAuth();
            $id = I('get.id', 0);
            $worker_order_products = BaseModel::getInstance('worker_order_product')->getList([
                'where' => ['worker_order_id' => $id],
                'field' => 'id,fault_id,product_category_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode',
            ]);

            $product_faults = BaseModel::getInstance('product_miscellaneous')->getList([
                'where' => ['product_id' => ['IN', Arr::pluck($worker_order_products, 'product_category_id')]],
                'field' => 'id,product_id,product_faults',
                'index' => 'product_id'
            ]);

            $order = BaseModel::getInstance('worker_order')->getOne($id, 'service_type');
            $cond = [];
            switch($order['service_type']){
                case 107:
                    $cond['fault_type'] = 0;
                    break;
                case 108:
                    $cond['fault_type'] = 2;
                    break;
                case 106:
                    $cond['fault_type'] = 1;
                    break;
                case 109:
                    $cond['fault_type'] = 0;
                    break;
                case 110:
                    $cond['fault_type'] = 1;
                    break;
            }

            foreach ($worker_order_products as $key => $worker_order_product) {
                $product_fault = $product_faults[$worker_order_product['product_category_id']];
                $cond['id'] = ['IN', explode(',', $product_fault['product_faults'])];
                $worker_order_products[$key]['fault_list'] = BaseModel::getInstance('product_fault')->getList([
                    'where' => $cond,
                    'field' => 'id,fault_name name',
                ]);
            }

            $this->response($worker_order_products);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updateOrdersProductsServices()
    {
        $this->requireAuth(AuthService::ROLE_ADMIN);
        $id = I('get.id', 0, 'intval');
        // $data = array_filter(I('update'));
        $data = array_filter((array)htmlEntityDecodeAndJsonDecode(I('put.update', '[]')));
        try {
//            checkAdminOrderPermission($id);
            !$data && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            D('Order', 'Logic')->updateOrdersProductsServices($id, $data);
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
