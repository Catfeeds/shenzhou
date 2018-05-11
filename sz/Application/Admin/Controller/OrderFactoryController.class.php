<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/3
 * Time: 11:27
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\FactoryLogic;
use Admin\Logic\OrderLogic;
use Admin\Model\BaseModel;
use Carbon\Carbon;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\Repositories\Events\OrderSendNotificationEvent;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\AreaService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\AuthService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class OrderFactoryController extends BaseController
{

    // 厂家财务审核工单
    public function auditedOrder()
    {
        $order_id       = I('get.id', 0, 'intval');
        $type           = I('post.type', 0, 'intval');
        $remark         = I('post.remark', '');

        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);
            checkFactoryOrderPermission($order_id);

            // 1 (厂家与平台结算)财务审核通过 ；2 (与维修商结算)平台财务客服审核财务不通过(待平台财务客服审核)
            !in_array($type, [1, 2]) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);

            $logic = new \Admin\Logic\FactoryLogic();
            switch ($type) {
                case 1:
                    $logic->auditedOrder($order_id, $remark);
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

    /**
     * 厂家下单
     */
    public function add()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();


            // 服务类型
            $data['service_type'] = I('service_type');
            if (!isset(OrderService::SERVICE_TYPE[$data['service_type']])) {
                $this->fail(ErrorCode::SYS_REQUEST_METHOD_ERROR, '请选择正确的服务类型');
            }
            // 订单类型
            $data['is_insured'] = I('is_insured');
            // 用户信息
            $user['phone'] = I('phone');
            if (!preg_match('/\\d{8,}/', $user['phone'])) {
                $this->fail(ErrorCode::SYS_REQUEST_METHOD_ERROR, '请输入正确的手机号码格式');
            }
            $user['real_name'] = I('real_name');
            if (!$user['real_name']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请输入客户姓名');
            }
            $user['province_id'] = I('province_id');
            $user['city_id'] = I('city_id');
            $user['area_id'] = I('area_id');
            if (!$user['province_id'] || !$user['city_id'] || !$user['area_id']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择用户所属省市区', 400);
            }
            $user['address'] = I('address');
            if (!$user['address']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, ' 请填写用户详细地址');
            }
            // 产品信息
            $products = I('products');
            foreach ($products as $product) {
                if (!$product['product_category_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品分类');
                }
                if (!$product['product_standard_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产品规格');
                }
                if ($product['product_nums'] < 1) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '产品数量必须大于等于1');
                }
            }
            // 技术支持人
            $ext_info['factory_helper_id'] = I('factory_helper_id');
            if (!$ext_info['factory_helper_id']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择技术支持人');
            }
            // 物流信息
            if ($data['service_type'] == OrderService::TYPE_PRE_RELEASE_INSTALLATION) {
                $data['express']['express_number'] = I('express_number');
                $data['express']['express_code'] = I('express_code');
                if (!$data['express']['express_number'] || !$data['express']['express_code']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写物流信息');
                }
            }
            $data['worker_order_status'] = OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE;
            $data['factory_check_order_time'] = NOW_TIME;
            $data['factory_check_order_id'] = AuthService::getAuthModel()->getPrimaryValue();
            $data['factory_check_order_type'] = AuthService::getModel() == AuthService::ROLE_FACTORY ? 1 : 2;

            $create_order_service = new OrderService\CreateOrderService($factory_id);
            $create_order_service->setOrderUser($user);
            $create_order_service->setOrderExtInfo($ext_info);
            $create_order_service->setOrderProducts($products);
            $create_order_service->setOrder($data);
            M()->startTrans();
            $worker_order_id = $create_order_service->create();
            M()->commit();

            $this->response([
                'worker_order_id' => $worker_order_id,
                'orno' => BaseModel::getInstance('worker_order')->getFieldVal($worker_order_id, 'orno'),
            ]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 加载下过单的用户信息
     */
    public function customerInfo()
    {
        $factory = $this->requireAuthFactoryGetFactory();
        $phone = I('phone');
        try {
            $order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne([
                'where' => [
                    'factory_id' => $factory['factory_id'],
                    'phone' => $phone,
                ],
                'join' => 'LEFT JOIN worker_order ON id=worker_order_id',
                'order' => 'worker_order_id desc',
                'field' => 'real_name,phone,province_id,city_id,area_id,address,cp_area_names area_names'
            ]);

            $this->response($order_user_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 厂家工单自行处理或下单给神州联保
     * @param $id
     */
    public function factoryOrderOrNot()
    {
        $id = I('get.id');
        try {
            $this->requireAuthFactoryGetFid();

            $check_type = I('check_type');
            checkFactoryOrderPermission($id);

            if (!in_array($check_type, [1, 2])) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请求参数错误');
            }

            $order = BaseModel::getInstance('worker_order')->getOne($id, 'worker_order_type,service_type,factory_id,worker_order_status');
            if (!in_array($order['worker_order_status'], [OrderService::STATUS_FACTORY_SELF_PROCESSED, OrderService::STATUS_CREATED])) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请求状态错误,请检查');
            }

            $is_frozen = false;
            $frozen = 0;
            if ($check_type == OrderService::STATUS_FACTORY_SELF_PROCESSED) {
                $order_status = OrderService::STATUS_FACTORY_SELF_PROCESSED;
                $operation_type = OrderOperationRecordService::FACTORY_ORDER_SELF_PROCESSING;
            } else {
                $order_status =  OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE;
                $operation_type = OrderOperationRecordService::FACTORY_ORDER_ADD_TO_PLATFORM;

                if (in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
                    $order_products = BaseModel::getInstance('worker_order_product')->getList([
                        'where' => ['worker_order_id' => $id],
                        'field' => 'product_category_id,product_standard_id,product_nums'
                    ]);
                    $is_frozen = true;
                    $factory = BaseModel::getInstance('factory')->getOne($order['factory_id'], 'default_frozen');
                    foreach ($order_products as $product) {
                        $frozen += FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($order['service_type'], $order['factory_id'], $product['product_category_id'], $product['product_standard_id'], $factory['default_frozen']) * $product['product_nums'];
                    }

                    if (in_array($order['service_type'], [OrderService::TYPE_WORKER_INSTALLATION, OrderService::TYPE_PRE_RELEASE_INSTALLATION])) {
                        $frozen += C('ORDER_INSURED_SERVICE_FEE');
                    }

                }
            }

            BaseModel::getInstance('worker_order')->startTrans();
            BaseModel::getInstance('worker_order')->update($id, [
                'worker_order_status' => $order_status,
                'factory_check_order_type' => AuthService::ROLE_FACTORY == AuthService::getModel() ? 1 : 2,
                'factory_check_order_id' => AuthService::getAuthModel()->getPrimaryValue(),
                'factory_check_order_time' => NOW_TIME,
                'last_update_time' => NOW_TIME,
            ]);

            $is_frozen && FactoryMoneyFrozenRecordService::process($id, FactoryMoneyFrozenRecordService::TYPE_FACTORY_ADD_ORDER, $frozen);
            OrderOperationRecordService::create($id, $operation_type);
            BaseModel::getInstance('worker_order')->commit();


            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function reAdd()
    {
        $id = I('get.id');
        try {
            $factory_id = $this->requireAuthFactoryGetFid();

            $order = BaseModel::getInstance('worker_order')->getOneOrFail($id, 'id,orno,worker_id,worker_order_type,worker_order_status,cancel_status,cancel_time,factory_check_order_time,parent_id');

            if ($order['worker_order_status'] != OrderService::STATUS_FACTORY_SELF_PROCESSED && !OrderService::isCanceledOrder($order['cancel_status'])) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该工单状态不可重新下单');
            }

            if ($order['worker_order_status'] == OrderService::STATUS_FACTORY_SELF_PROCESSED) {
                $cancel_time = new Carbon(date('y-m-d', $order['factory_check_order_time']));
            } else {
                $cancel_time = new Carbon(date('y-m-d', $order['cancel_time']));
            }
            if ($cancel_time->addDays(7) <= Carbon::now()) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '取消超过7天，不允许重新下单');
            }
            if ($order['parent_id'] != 0) {
                $rework_order_id = BaseModel::getInstance('worker_order')->getFieldVal([
                    'where' => ['orno' => ['LIKE', 'F%'], 'parent_id' => $order['parent_id'], 'cancel_status' => OrderService::CANCEL_TYPE_NULL],
                ], 'id');
                if ($rework_order_id) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '下单失败，母工单已存在其他进行中返修单');
                }
            }

            $worker_order_model = BaseModel::getInstance('worker_order');
            $worker_order_model->startTrans();
            if (OrderService::isInsurance($order['worker_order_type'])) {
                $factory = BaseModel::getInstance('factory')->getOneOrFail([
                    'where' => ['factory_id' => $factory_id],
                    'field' => 'factory_id,money,default_frozen',
                    'lock' => true,
                ]);
                // 计算冻结金额
                $order_product_sum = BaseModel::getInstance('worker_order_product')->getSum([
                    'where' => [
                        'worker_order_id' => $id,
                    ],
                    'lock' => true,
                ], 'factory_repair_fee_modify');
                $order_fee = BaseModel::getInstance('worker_order_fee')->getOne($id, 'service_fee_modify');
                $total_frozen = $order_product_sum + $order_fee['service_fee_modify'];
                $factory_frozen_money = (new FactoryLogic())->getFrozenMoney($factory_id);
                if ($factory['money'] < $total_frozen + $factory_frozen_money) {
                    $this->fail(ErrorCode::FACTORY_MONEY_NOT_ENOUGH_ADD_ORDER);
                }
                // 方法里面更新厂家总冻结金
                FactoryMoneyFrozenRecordService::process($id, FactoryMoneyFrozenRecordService::TYPE_ORDER_READD, $total_frozen);
//                BaseModel::getInstance('factory')->setNumDec($factory_id, 'frozen_money', $total_frozen);
            }

            $worker_order_update_data = [
                'worker_id' => 0,
                'checker_id' => 0,
                'auditor_id' => 0,
                'distributor_id' => 0,
                'returnee_id' => 0,
                'worker_order_status' => OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE,
                'cancel_status' => 0,
                'cancel_time' => 0,
                'cancel_type' => 0,
                'extend_appoint_time' => 0,
                'checker_receive_time' => 0,
                'check_time' => 0,
                'distributor_receive_time' => 0,
                'distribute_time' => 0,
                'worker_receive_time' => 0,
                'worker_first_appoint_time' => 0,
                'worker_first_sign_time' => 0,
                'worker_repair_time' => 0,
                'returnee_receive_time' => 0,
                'return_time' => 0,
                'auditor_receive_time' => 0,
                'audit_time' => 0,
                'factory_audit_time' => 0,
                'last_update_time' => NOW_TIME,
            ];
            $order_classification = OrderService::getClassificationByOrno($order['orno']);
            if ($order_classification == OrderService::CLASSIFICATION_REWORK_ORDER_TYPE) {
                unset($worker_order_update_data['checker_id'], $worker_order_update_data['distributor_id'], $worker_order_update_data['checker_receive_time'], $worker_order_update_data['check_time'], $worker_order_update_data['distributor_receive_time'], $worker_order_update_data['distribute_time']);
                $worker_order_update_data['worker_order_status'] = OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE;
            }
            $worker_order_model->update($id, $worker_order_update_data);
            OrderOperationRecordService::create($id, OrderOperationRecordService::FACTORY_ORDER_READD);
            $worker_order_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function applyRework()
    {
        $this->requireAuthFactoryGetFid();
        try {
            $worker_order_id = I('worker_order_id');
            $remark = I('remark');
            if (!$worker_order_id || !$remark) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $order = BaseModel::getInstance('worker_order')->getOneOrFail($worker_order_id, 'id,orno,worker_id,children_worker_id,factory_id,checker_id,distributor_id,worker_order_status,audit_time,service_type');

            $classification = OrderService::getClassificationByOrno($order['orno']);
            $order['rework_order_id'] = '0';

            if ($classification != OrderService::CLASSIFICATION_COMMON_ORDER_TYPE) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '当前工单不允许申请返修');
            }
            if ((new OrderLogic())->canOrderRework($order['worker_order_status'], $classification, $order['audit_time']) != 1) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '当前工单不允许申请返修');
            }
            $rework_order_id = BaseModel::getInstance('worker_order')->getFieldVal([
                'where' => ['orno' => ['LIKE', 'F%'], 'parent_id' => $order['id'], 'cancel_status' => OrderService::CANCEL_TYPE_NULL],
            ], 'id');
            if ($rework_order_id) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '当前工单已有返修单不允许再次申请返修');
            }

            $worker_order = Arr::only($order, ['checker_id', 'distributor_id', 'service_type']);
            $worker_order['origin_type'] = AuthService::getModel() == AuthService::ROLE_FACTORY ? 1 : 2;
            $worker_order['add_id'] = AuthService::getAuthModel()->getPrimaryValue();
            $worker_order['parent_id'] = $order['id'];
            $worker_order['is_insured'] = false;
            $worker_order['worker_order_status'] = OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE;
            $worker_order['create_time'] = $worker_order['checker_receive_time'] = $worker_order['distributor_receive_time'] = $worker_order['check_time'] = NOW_TIME;
            $worker_order['order_classification'] = OrderService::CLASSIFICATION_REWORK_ORDER_PREFIX;
            $worker_order['worker_order_type'] = OrderService::ORDER_TYPE_REWORK_OUT_INSURANCE;
            $worker_order_products = BaseModel::getInstance('worker_order_product')->getList([
                'where' => [
                    'worker_order_id' => $order['id'],
                ],
                'field' => 'product_brand_id,product_category_id,product_standard_id,product_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,fault_label_ids,product_nums,cp_product_mode,yima_code,user_service_request',
            ]);
            $worker_order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne($order['id'], 'area_id,province_id,wx_user_id,city_id,real_name,phone,cp_area_names,address,lat,lon');
            $worker_order_ext_info = BaseModel::getInstance('worker_order_ext_info')->getOne($order['id'], 'factory_helper_id,is_send_user_message,user_message,is_send_worker_message,worker_message,est_miles,straight_miles');

            $create_order_service = new OrderService\CreateOrderService($order['factory_id'], true);
            $create_order_service->setOrder($worker_order);
            $create_order_service->setOrderProducts($worker_order_products);
            $create_order_service->setOrderUser($worker_order_user_info);
            $create_order_service->setOrderExtInfo($worker_order_ext_info);
            M()->startTrans();
            $new_rework_order_id = $create_order_service->create();
            OrderOperationRecordService::create($order['id'], OrderOperationRecordService::FACTORY_APPLY_ORDER_REWORK, [
                'remark' => $remark,
            ]);
            // 信誉信息
            BaseModel::getInstance('worker_order_quality')->insert([
                'worker_id' => $order['worker_id'],
                'worker_order_id' => $order['id'],
                'is_fault' => 1,
                'addtime' => NOW_TIME,
            ]);
            if ($order['children_worker_id']) {
                BaseModel::getInstance('worker_order_quality')->insert([
                    'worker_id' => $order['children_worker_id'],
                    'worker_order_id' => $order['id'],
                    'is_fault' => 1,
                    'addtime' => NOW_TIME,
                ]);
            }
            // 操作记录
            OrderOperationRecordService::create($new_rework_order_id,OrderOperationRecordService::FACTORY_ADD_ORDER_REWORK, [
                'remark' => $remark,
                'content_replace' => [
                    'orno' => $order['orno']
                ]
            ]);
            $admin_distributor = AdminCacheModel::getOne($order['distributor_id'], 'user_name');
            OrderOperationRecordService::create($new_rework_order_id, OrderOperationRecordService::SYSTEM_REWORK_ORDER_DISTRIBUTOR_AUTO_RECEIVE, [
                'remark' => '接单客服：' . $admin_distributor['user_name'],
                'content_replace' => [
                    'orno' => $order['orno']
                ]
            ]);
            $worker_name = BaseModel::getInstance('worker')->getFieldVal($order['worker_id'], 'nickname');
            OrderOperationRecordService::create($new_rework_order_id, OrderOperationRecordService::SYSTEM_REWORK_ORDER_ORIGIN_WORKER, [
                'content_replace' => [
                    'orno' => $order['orno'],
                    'worker_name' => $worker_name,
                ]
            ]);
            M()->commit();

            event(new OrderSendNotificationEvent([
                'data_id' => $order['id'],
                'type'    => AppMessageService::TYPE_ORIGIN_ORDER_REWORK
            ]));

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


    public function getBatchImportData()
    {
        // excel_in_order
        $factory = $this->requireAuthFactoryGetFactory();

        if ($factory['excel_in_order'] != 1) {
            $this->fail(ErrorCode::ORDER_IMPORT_FACTORY_NO_PERMISSION);
        }
//        $factory = M('factory')->find(833);
        try {
            $config['exts'] = ['xls', 'xlsx'];
            $file_info = Util::upload($config);

            $objPHPExcelReader = \PHPExcel_IOFactory::load($file_info['file_path']);

            $sheet = $objPHPExcelReader->getSheet();


            $max_num = C('ORDER_IMPORT_MAX_NUM');

            $excel_data = [];
            $highest_row = $sheet->getHighestRow();
            for ($i = 2; $i <= $highest_row; ++$i) {
                $ed = $sheet->rangeToArray('A' . $i . ':N' . $i, null, true, false)[0];
                if ($ed[0] && $ed[1] && $ed[2]) {
                    $excel_data[] = $sheet->rangeToArray('A' . $i . ':N' . $i, null, true, false)[0];
                } else {
                    break;
                }
            }
            if (count($excel_data) > $max_num) {
                $this->fail(ErrorCode::ORDER_IMPORT_EXCEL_DATA_NUM_ERROR, ['num' => $max_num]);
            }


            // 省市区
            $provinces = AreaService::index(0);
            $province_ids = Arr::pluck($provinces, 'id');
            $province_name_id_map = Arr::pluck($provinces, 'id', 'name');
            $city_list = AreaService::getChildrenByParentIds($province_ids);
            $city_id_name_map = [];
            $city_ids = [];
            foreach ($city_list as $item) {
                $city_id_name_map[$item['parent_id'] . $item['name']] = $item['id'];
                $city_ids[] = $item['id'];
            }
            $district_list = AreaService::getChildrenByParentIds($city_ids);
            $district_id_name_map = [];
            $city_id_district_map = [];
            foreach ($district_list as $item) {
                $district_id_name_map[$item['parent_id'] . $item['name']] = $item['id'];
                $city_id_district_map[$item['parent_id']][] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                ];
            }

            // 产品分类
            $category_name_id_map = BaseModel::getInstance('product_category')
                ->getFieldVal([
                    'id' => ['IN', $factory['factory_category']]
                ], 'name,id', true);

            // 产品规格
            $standards = BaseModel::getInstance('product_standard')
                ->getList([
                    'field' => 'standard_id,product_id,standard_name',
                    'where' => ['product_id' => ['IN', array_values($category_name_id_map)]],
                ]);
            $categoty_id_standard_map = [];
            foreach ($standards as $key => $standard) {
                $categoty_id_standard_map[$standard['product_id'] . $standard['standard_name']] = $standard['standard_id'];
            }

            // 服务类型
            $service_name_id_map = array_flip(OrderService::SERVICE_TYPE);

            // 技术支持
            $technology = BaseModel::getInstance('factory_helper')
                ->getList([
                    'field' => 'id,name,telephone',
                    'where' => ['factory_id' => $factory['factory_id']]
                ]);
            $factory_technology_map = [];
            foreach ($technology as $item) {
                $factory_technology_map[$item['name'] . $item['telephone']] = $item['id'];
            }

            // 物流公司
            $express_name_code_map = BaseModel::getInstance('express_com')
                ->getFieldVal([], 'name,comcode');

            $excel_data_complete = [];
            $count = 0;
            foreach ($excel_data as $key => $row) {
                $excel_data_complete[$count]['no'] = $count;
                $excel_data_complete[$count]['real_name'] = strval($row[0]); // 客户姓名
                $excel_data_complete[$count]['phone'] = strval($row[1]); // 客户手机

                $area_des = str_replace(' ', '', $row[2]);
                // 5个自治区、4个直辖市特殊匹配
                if (preg_match('/^(河北|山西|辽宁|吉林|黑龙江|江苏|浙江|安徽|福建|江西|山东|河南|湖北|湖南|广东|海南|四川|贵州|云南|陕西|甘肃|青海|台湾)[^省]/u', $area_des, $area_match)) {
                    $area_des = $area_match[1] . '省' . mb_substr($area_des, mb_strlen($area_match[1]));
                } elseif (mb_substr($area_des, 0, 8) == '新疆维吾尔自治区') {
                    $area_des = '新疆' . mb_substr($area_des, 8);
                } elseif (mb_substr($area_des, 0, 7) == '宁夏回族自治区') {
                    $area_des = '宁夏' . mb_substr($area_des, 7);
                } elseif (mb_substr($area_des, 0, 5) == '西藏自治区') {
                    $area_des = '西藏' . mb_substr($area_des, 5);
                } elseif (mb_substr($area_des, 0, 7) == '广西壮族自治区') {
                    $area_des = '广西' . mb_substr($area_des, 7);
                } elseif (mb_substr($area_des, 0, 6) == '内蒙古自治区') {
                    $area_des = '内蒙古' . mb_substr($area_des, 6);
                } elseif (preg_match('/^(北京|重庆|上海|天津)/u', $area_des, $area_match)) {
                    $area_len = mb_strlen($area_match[1]);
                    if (preg_match('/^(北京|重庆|上海|天津)市/u', $area_des, $area_match_1)) {
                        // 匹配北京市海淀区 转为 北京市北京市海淀区
                        if (mb_substr($area_des, $area_len + 1, $area_len) != $area_match[1]) {
                            $area_des = $area_match[1] . '市' . $area_match[1] . '市' . mb_substr($area_des, $area_len + 1);
                        }
                    } else {
                        // 匹配北京海淀区 转为 北京市北京市海淀区
                        if (mb_substr($area_des, $area_len, $area_len) != $area_match[1]) {
                            $area_des = $area_match[1] . '市' . $area_match[1] . '市' . mb_substr($area_des, $area_len);
                        } else {    // 匹配北京北京市海淀区 转为 北京市北京市海淀区
                            $area_des = $area_match[1] . '市' . mb_substr($area_des, $area_len);
                        }
                    }
                }

                // 匹配城市
                preg_match('/^(?:(内蒙古|西藏|新疆|广西|宁夏)|(.+?)(?:省|市))?(?:(.+?)(市|自治州|自治县|地区|县|盟))?(.+)$/u', $area_des, $match);

                /**
                 * XXX省或XX市（直辖市）匹配成功则在2中，
                 * 如果是特殊省份则在1中，
                 */
                $province = trim($match[2]) ? : trim($match[1]);
                $city = trim($match[3]) . $match[4];
                $district = trim($match[5]);

                $province_id = intval($province_name_id_map[$province]);
                $city_id = $province_id ?
                    (isset($city_id_name_map[$province_id . $city]) ? intval($city_id_name_map[$province_id . $city]) : 0) : 0;

                $district_id = 0;
                $real_district = '';
                foreach ($city_id_district_map[$city_id] as $dt) {
                    if (mb_substr($district, 0, mb_strlen($dt['name'])) == $dt['name']) {
                        $real_district = $dt['name'];
                        $district_id = intval($dt['id']);
                        break;
                    }
                }
                $pcd = $province . $city . $real_district;
                $excel_data_complete[$count]['area'] = [
                    'origin' => $province_id && $city_id && $district_id ? $pcd : $row[2],
                    'match' => 0,
                    'province_id' => $province_id,
                    'city_id' => $city_id,
                    'district_id' => $district_id,
                ];
                $area = &$excel_data_complete[$count]['area'];
                if ($area['province_id'] && $area['city_id'] && $area['district_id']) {
                    $area['match'] = 1;
                }
                !$area['province_id'] && $area['city_id'] = 0;
                !$area['city_id'] && $area['area_id'] = 0;
                $excel_data_complete[$count]['area_desc'] = $row[2];


                // 匹配分类
                $category_id = intval($category_name_id_map[$row[3]]);
                $excel_data_complete[$count]['category'] = [
                    'origin' => $row[3],
                    'match' => $category_id ? 1 : 0,
                    'category_id' => $category_id,
                ];

                // 匹配规格
                if ($excel_data_complete[$count]['category']['match'] == 0) {
                    $excel_data_complete[$count]['standard'] = [
                        'origin' => $row[4],
                        'match' => 0,
                        'standard_id' => 0,
                    ];
                } else {
                    $standard_id = intval($categoty_id_standard_map[$excel_data_complete[$count]['category']['category_id'] . $row[4]]);
                    $excel_data_complete[$count]['standard'] = [
                        'origin' => $row[4],
                        'match' => $standard_id ? 1 : 0,
                        'standard_id' => $standard_id,
                    ];
                }

                $excel_data_complete[$count]['product_brand'] = strval($row[5]);
                $excel_data_complete[$count]['model'] = strval($row[6]);
                $excel_data_complete[$count]['nums'] = intval($row[7]) ? : 1;

                // 匹配服务类型
                $service_type_id = $service_name_id_map[$row[8]];
                $excel_data_complete[$count]['service_type'] = [
                    'origin' => $row[8],
                    'match' => $service_type_id ? 1 : 0,
                    'service_type' => $service_type_id,
                ];

                $excel_data_complete[$count]['user_service_request'] = strval($row[9]);

                $technology_id = intval($factory_technology_map[$row[10] . $row[11]]);
                $excel_data_complete[$count]['factory_helper'] = [
                    'origin' => $row[10] . $row[11],
                    'match' => $technology_id ? 1 : 0,
                    'factory_helper_id' => $technology_id
                ];

                // 有填写物流公司或物流单号则把服务类型改为上门安装
                if ($row[12] || $row[13]) {
                    $excel_data_complete[$count]['service_type']['match'] = 1;
                    $excel_data_complete[$count]['service_type']['service_type'] = OrderService::TYPE_PRE_RELEASE_INSTALLATION;
                }
                if (in_array($row['12'], ['顺丰隔日', '顺丰速递', '顺丰快递'])) {
                    $row[13] = '顺丰';
                } elseif (in_array($row['12'], ['申通快递'])) {
                    $row[13] = '申通';
                } elseif (in_array($row['12'], ['圆通快递', '圆通速递'])) {
                    $row[13] = '圆通';
                } elseif (in_array($row['12'], ['中通快递',])) {
                    $row[13] = '中通';
                } elseif (in_array($row['12'], ['韵达速递', '韵达快递'])) {
                    $row[13] = '韵达';
                } elseif (in_array($row['12'], ['德邦快递', '德邦物流'])) {
                    $row[13] = '德邦';
                } elseif (in_array($row['12'], ['百世物流', '百世快递', '百世汇通'])) {
                    $row[13] = '百世';
                } elseif (in_array($row['12'], ['安能物流'])) {
                    $row[13] = '安能';
                } elseif (in_array($row['12'], ['天天快递'])) {
                    $row[13] = '天天';
                }
                $comcode = $express_name_code_map[$row['12']];
                $excel_data_complete[$count]['express_com'] = [
                    'origin' => $row[12],
                    'match' => $comcode ? 1 : 0,
                    'comcode' => $comcode ? : '',
                ];
                $excel_data_complete[$count]['express_number'] = strval($row['13']);

                ++$count;

            }

            $this->responseList($excel_data_complete);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function batchImportOrders()
    {
        try {
            $orders = I('orders');
//            var_export($orders);exit;
            if (!$orders) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择订单');
            }
            $factory = $this->requireAuthFactoryGetFactory();
            if ($factory['excel_in_order'] != 1) {
                $this->fail(ErrorCode::ORDER_IMPORT_FACTORY_NO_PERMISSION);
            }

//            D('Factory', 'Logic')->checkFactoryExpire($factory['factory_id']);

            $provinces = AreaService::index(0);
            $province_ids = Arr::pluck($provinces, 'id');
            $province_id_name_map = Arr::pluck($provinces, 'name', 'id');

            $cities = AreaService::getChildrenByParentIds($province_ids);
            $city_id_name_map = Arr::pluck($cities, 'name', 'id');
            $city_ids = array_keys($city_id_name_map);

            $districts = AreaService::getChildrenByParentIds($city_ids);
            $district_id_name_map = Arr::pluck($districts, 'name', 'id');

//        $technologies = D('Factory', 'Logic')->technology($factory['factory_id']);
//        $technology_id_map = [];
//        foreach ($technologies as $item) {
//            $technology_id_map[$item['id']] = $item;
//        }

            $category_ids = [];
            $standard_ids = [];
            $category_id_brand_map = [];
            foreach ($orders as $order) {
                $category_ids[] = $order['category_id'];
                $standard_ids[] = $order['product_standard_id'];
                $order['product_brand'] && $category_id_brand_map[$order['category_id']][] = $order['product_brand'];
            }

            $category_id_name_map = BaseModel::getInstance('product_category')
                ->getFieldVal([
                    'id' => ['IN', $category_ids],
                ], 'id,name', true);
            $standard_ids = array_unique($standard_ids);
            $standard_id_name_map = BaseModel::getInstance('product_standard')
                ->getFieldVal([
                    'standard_id' => ['IN', $standard_ids],
                ], 'standard_id,standard_name');

            D('Factory', 'Logic')->syncFactoryBrand($factory['factory_id'], $category_id_brand_map);
            $brands = BaseModel::getInstance('factory_product_brand')
                ->getList([
                    'field' => 'id,product_cat_id,product_brand',
                    'where' => [
                        'factory_id' => $factory['factory_id'],
                        'product_cat_id' => ['IN', $category_ids],
                    ]
                ]);

            $brand_map = [];
            foreach ($brands as $brand) {
                $brand_map[$brand['product_cat_id'] . $brand['product_brand']] = $brand['id'];
            }


            $total_num = 0;
            $data = [];
            $count = 0;
            foreach ($orders as $order) {
                if (!$order['real_name']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写用户姓名');
                }
                if (!$order['phone']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写用户手机号码');
                }
                if (!$order['district_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择所在地区');
                }
                if (!$order['province_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择所在省份');
                }
                if (!$order['city_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择所在城市');
                }
                if (!$order['address']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写详细地址');
                }
                if (!$order['factory_helper_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择服技术支持');
                }
                if (!$order['service_type']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择服务类型');
                }
                if ($order['nums'] < 1) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写正确的商品数量');
                }
                if (!$order['category_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择产品类型');
                }
                if (!$order['product_standard_id']) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择产品规格');
                }
                if ($order['service_type'] == OrderService::TYPE_PRE_RELEASE_INSTALLATION && (!$order['comcode'] || !$order['express_number'])) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预发件安装单，物流公司与单号必填');
                }

                $data[$count]['user']['real_name'] = $order['real_name'];
                $data[$count]['user']['phone'] = $order['phone'];
                $data[$count]['user']['area_id'] = $order['district_id'];
                $data[$count]['user']['province_id'] = $order['province_id'];
                $data[$count]['user']['city_id'] = $order['city_id'];
                $data[$count]['user']['address'] = $order['address'];
                $data[$count]['user']['cp_area_names'] = "{$province_id_name_map[$order['province_id']]}-{$city_id_name_map[$order['city_id']]}-{$district_id_name_map[$order['district_id']]}";
                $data[$count]['ext_info']['factory_helper_id'] = $order['factory_helper_id'];
                $data[$count]['service_type'] = $order['service_type'];
                $data[$count]['products'][] = [
                    'product_nums' => $order['nums'],
                    'product_category_id' => $order['category_id'],
                    'cp_category_name' => $category_id_name_map[$order['category_id']],
                    'product_standard_id' => $order['product_standard_id'],
                    'cp_product_standard_name' => $standard_id_name_map[$order['product_standard_id']],
                    'product_brand_id' => $order['product_brand'] ? $brand_map[$order['category_id'] . $order['product_brand']] : 0,
                    'cp_product_brand_name' => $order['product_brand'],
                    'cp_product_mode' => $order['model'],
                    'user_service_request' => $order['user_service_request'],
                ];

                $data[$count]['express']['express_code'] = $order['comcode'];
                $data[$count]['express']['express_number'] = $order['express_number'];

                $total_num += $order['nums'];

                ++$count;
            }

            if ($total_num < 1) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写正确的商品数量');
            }

//            // 检查余额是否足够下单
//            $frozen = D('Factory', 'Logic')->getFrozenMoney($factory['factory_id']);
//            if ($factory['money'] - $frozen - ($total_num * $factory['default_frozen']) < 0) {
//                $rest = intval(($factory['money'] - $frozen) / $factory['default_frozen']);
//                $this->fail(ErrorCode::ORDER_IMPORT_NO_ENOUGH_MONEY, ['total' => $total_num, 'num' => $rest > 0 ? $rest : 0]);
//            }

            M()->startTrans();
            $worker_order_ids = (new OrderLogic())->batchAddOrder($data);
            M()->commit();

            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}