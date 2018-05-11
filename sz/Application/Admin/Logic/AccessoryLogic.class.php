<?php
/**
 * Function:配件单
 * File: FactoryAccessoryLogic.class.php
 * User: sakura
 * Date: 2017/11/7
 */


namespace Admin\Logic;
use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Logic\ExpressTrackingLogic;
use Common\Common\Repositories\Events\AccessoryCheckEvent;
use Common\Common\Service\AccessoryRecordService;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\AuthService;
use Common\Common\Service\ExpressTrackingService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SystemMessageService;
use Common\Common\Service\WorkerOrderProductService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class AccessoryLogic extends BaseLogic
{

    protected $tableName = 'worker_order_apply_accessory';

    public function add($order_id, $product_id, $data)
    {
        !$product_id && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择产信息');

        $order_info = BaseModel::getInstance('worker_order')->getOneOrFail($order_id);
        $order_info['worker_id'] = $order_info['worker_id'] ?? 0;
        if (
            in_array($order_info['worker_order_type'], OrderService::ORDER_TYPE_OUT_INSURANCE_LIST) &&
            !in_array($order_info['worker_order_type'], OrderService::ORDER_TYPE_OUT_ACCESSORY_LIST)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '异常保外单不能申请配件单');
        }

        !in_array($order_info['worker_order_status'], OrderService::CS_APPLY_ACCESSORY_WORKER_ORDER_STATUS_LIST) && $this->throwException(ErrorCode::WORKER_ORDER_STATUS_NOT_CS_APPLY_ACCESSORY);

        $order_product = BaseModel::getInstance('worker_order_product')->getOneOrFail([
            'worker_order_product_id' => $product_id,
            'worker_order_id' => $order_id,
        ]);

        $order_product['is_complete'] != WorkerOrderProductService::IS_COMPLETE_NO && $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '已完成的产品不能申请配件！');

        (empty($data['name']) || $data['num'] < 1) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写完整的配件信息');

        $area_arr = array_filter([
            $data['province_id'],
            $data['city_id'],
            $data['area_id']
        ]);

        (
            !in_array($data['receive_address_type'], AccessoryService::RECEIVE_ADDRESS_TYPE_LIST) ||
            !$area_arr ||
            !$data['phone'] ||
            !$data['user_name'] ||
            !$data['address']
        ) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写完整的收件人信息');

        $areas = BaseModel::getInstance('area')->getList([
            'where' => [
                'id' => ['in', $area_arr]
            ],
            'field' => 'id,parent_id,name',
            'order' => 'field(id,'.implode(',', $area_arr).')',
        ]);

        !$areas && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '收件人省市区错误');
        $area_pre = [];
        foreach ($areas as $v) {
            $v['parent_id'] && $area_pre['id'] != $v['parent_id'] && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '收件人省市区错误');
            $data['area_ids'][] = $v['id'];
            $data['area_names'][] = $v['name'];
            $area_pre = $v;
        }

        // 确认发件
        $express_number = $data['express_number'];
        $express_code = $data['express_code'];
        $is_giveup_return = $data['is_giveup_return'];
        $remark = $data['remark'] ?? '';

        //检查参数
        empty($express_number) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '物流单号为空');
        empty($express_code) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '物流公司为空');
        // 是否需要返件 1-需要返件 2-放弃返件
        !in_array($is_giveup_return, [1, 2]) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请选择是否需要返件');

        //是否放弃配件返还
        $is_giveup_return_str = '旧件不需返还';
        if (1 == $is_giveup_return) {
            $data['is_giveup_return'] = AccessoryService::RETURN_ACCESSORY_PASS;
            $is_giveup_return_str = '旧件需返还';
        } else {
            $data['is_giveup_return'] = AccessoryService::RETURN_ACCESSORY_FORBIDDEN;
        }

        $data['cs_apply_imgs'] = !empty($data['cs_apply_imgs']) ? htmlEntityDecode($data['cs_apply_imgs']) : '';
        $acce_number = AccessoryService::genArNo();
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::CS_APPLY_ACCESSORY, [
            'content_replace' => [
                'acce_number' => $acce_number,
                'acce_name' => $data['name'],
                'remark'      => '',
            ],
        ]);

        $accessory_data = [
            'worker_order_product_id'=> $product_id,
            'accessory_number'       => $acce_number,
            'factory_id'             => $order_info['factory_id'],
            'worker_order_id'        => $order_info['id'],
            'worker_id'              => $order_info['worker_id'],
//            'apply_reason'           => !empty($data['apply_reason']) ? $data['apply_reason'] : '',
            'addressee_name'         => $data['user_name'],
            'addressee_phone'        => $data['phone'],
            'addressee_address'      => $data['address'],
            'cs_apply_imgs'          => !empty($data['cs_apply_imgs']) ? html_entity_decode(html_entity_decode($data['cs_apply_imgs'])) : '',
            'addressee_area_ids'     => implode(',', $data['area_ids']),
            'cp_addressee_area_desc' => implode(',', $data['area_names']),
            'receive_address_type'   => $data['receive_address_type'] ?? AccessoryService::RECEIVE_ADDRESS_TYPE_default,
            'cancel_status'          => AccessoryService::CANCEL_STATUS_NORMAL,
            'create_time'            => NOW_TIME,
            'last_update_time'       => NOW_TIME,
            // 发件
            'factory_send_time'      => NOW_TIME,
            'admin_check_time'       => NOW_TIME,
            'factory_check_time'     => NOW_TIME,
            'accessory_status'       => AccessoryService::STATUS_FACTORY_SENT,
            'is_giveup_return'       => $data['is_giveup_return'],
        ];
        $accessory_id = BaseModel::getInstance('worker_order_apply_accessory')->insert($accessory_data);
        $item_data = [
            'accessory_order_id' => $accessory_id,
            'worker_id'          => $order_info['worker_id'],
            'name'               => $data['name'],
            'nums'               => $data['num'],
            'code'               => !empty($data['code']) ? $data['code'] : '',
            'remark'             => $remark
        ];
        $item_id = BaseModel::getInstance('worker_order_apply_accessory_item')->insert($item_data);

        $accessory_record = [];
        // 客服申请配件
        $acce_record_common = [
            'accessory_order_id' => $accessory_id,
            'create_time' => NOW_TIME,
            'user_id' => AuthService::getAuthModel()->getPrimaryValue(),
            'user_type' => AccessoryRecordService::ROLE_CS,
            'remark' => '',
        ];
        $accessory_record[] = [
            'type' => AccessoryRecordService::OPERATE_TYPE_CS_APPLY,
            'content' => "工单号{$order_info['orno']}申请了配件单",
            'remark' => '客服新增配件单',
        ] + $acce_record_common;
        $accessory_record[] = [
            'type' => AccessoryRecordService::OPERATE_TYPE_CS_CHECKED,
            'content' => '审核配件单（审核通过)',
            'remark' => '',
        ] + $acce_record_common;
        $accessory_record[] = [
            'type' => AccessoryRecordService::OPERATE_TYPE_CS_ACT_FACTORY_CHECKED,
            'content' => '客服代厂家审核配件单（审核通过）',
            'remark' => '',
        ] + $acce_record_common;

        $company = BaseModel::getInstance('express_com')->getFieldVal(['comcode' => $express_code], 'name');
        $content = "客服代确认发件, {$company}：{$express_number} ($is_giveup_return_str)";
        $accessory_record[] = [
                'type' => AccessoryRecordService::OPERATE_TYPE_CS_ACT_FACTORY_CONFIRM_SEND,
                'content' => $content,
                'remark' => $data['remark'].($data['cs_apply_imgs'] && $data['remark'] ? '：' : '').$this->handleImage($data['cs_apply_imgs']),
            ] + $acce_record_common;
        BaseModel::getInstance('worker_order_apply_accessory_record')->insertAll($accessory_record);

        //获取配件单
        $distributor_id = $order_info['distributor_id'];
        $orno = $order_info['orno'];

        //物流跟踪

        expressTrack($express_code, $express_number, $accessory_id, ExpressTrackingService::TYPE_ACCESSORY_SEND);

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => AppMessageService::TYPE_CS_SEND_ACCESSORY_MASSAGE, 'data_id' => $accessory_id, 'express_number' => $express_number]));

        //系统消息
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, "工单号{$orno}的配件已发送", $accessory_id, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_SEND);

        //统计
        $where = [
            'worker_order_id'      => $order_id,
            'accessory_unsent_num' => ['egt', 1],
        ];
        $update_data = [
            'accessory_order_num' => ['exp', 'accessory_order_num + 1'],
            'total_accessory_num' => ['exp', 'total_accessory_num + 1'],
//            'accessory_worker_unreceive_num' => ['exp', 'accessory_worker_unreceive_num + 1'],
        ];
        // 未返件数量
        1 == $is_giveup_return && $update_data['accessory_worker_unreceive_num'] = ['exp', 'accessory_worker_unreceive_num + 1'];
        $statistics_model = BaseModel::getInstance('worker_order_statistics');
        $statistics_model->update($where, $update_data);

    }

    public function getList($param)
    {
        //获取参数
        $status = $param['status'];
        $accessory_number = $param['accessory_number'];
        $is_giveup_return = $param['is_giveup_return'];
        $orno = $param['orno'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $is_confirm_send_back = $param['is_confirm_send_back'];
        $limit = $param['limit'];
        $send_back_express_no = $param['send_back_express_no'];

        //获取管理员信息
        $admin = AuthService::getAuthModel();
        $role = AuthService::getModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();

        //权限
        $tag_id = $param['tag_id']; //子账号组
        $admin_ids = $param['admin_ids']; // 工单客服
        $admin_ids = Util::filterIdList($admin_ids);

        $factory_ids = $param['factory_ids']; //所属厂家id
        $factory_ids = Util::filterIdList($factory_ids);

        $factory_group_ids = $param['factory_group_ids']; // 厂家组别
        $factory_group_ids = Util::filterIdList($factory_group_ids);

        //组合查询
        $condition = [];
        if (AuthService::ROLE_FACTORY == $role) {
            //厂家
            $factory_id = $admin_id;
            $condition['woaa.factory_id'] = $factory_id;
            //厂家不显示配件单申请和客服审核不通过数据
            $condition['accessory_status'][] = ['not in', [AccessoryService::STATUS_WORKER_APPLY_ACCESSORY, AccessoryService::STATUS_ADMIN_FORBIDDEN]];
            if (strlen($tag_id) > 0 && '0' != $tag_id) {
                //非系统默认组
                $where = [
                    'factory_id' => $factory_id,
                    'tags_id'    => $tag_id,
                ];
                $factory_admin_ids = BaseModel::getInstance('factory_admin')
                    ->getFieldVal($where, 'id', true);
                $in = empty($factory_admin_ids) ? '-1' : implode(',', array_unique($factory_admin_ids));
                $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where add_id in ({$in}) and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY_ADMIN . ')'];
            }
        } elseif (AuthService::ROLE_FACTORY_ADMIN == $role) {
            //子账号
            $factory_id = $admin['factory_id'];
            $condition['woaa.factory_id'] = $factory_id;
            //厂家子账号不显示配件单申请和客服审核不通过数据
            $condition['accessory_status'][] = ['not in', [AccessoryService::STATUS_WORKER_APPLY_ACCESSORY, AccessoryService::STATUS_ADMIN_FORBIDDEN]];
            if (strlen($tag_id) > 0) {
                if ('0' != $tag_id) {
                    $where = [
                        'factory_id' => $factory_id,
                        'tags_id'    => $tag_id,
                    ];
                    $factory_admin = BaseModel::getInstance('factory_admin')
                        ->getFieldVal($where, 'id', true);
                    $in = empty($factory_admin) ? '-1' : implode(',', array_unique($factory_admin));
                    $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where add_id in ({$in}) and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY_ADMIN . ')'];
                } else {
                    $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where (add_id={$factory_id} and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY . ") or (add_id={$admin_id} and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY_ADMIN . "))"];
                }
            }
        } elseif (AuthService::ROLE_ADMIN == $role) {
            $reveive_types = (new AdminLogic())->getAdminReveiceType(AuthService::getAuthModel()->getPrimaryValue());
            //客服
            if (!empty($admin_ids)) {
                $admin_in = implode(',', $admin_ids);
            } elseif (!in_array(AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR, $reveive_types)) {
                $group_admin_ids = (new AdminGroupLogic())->getManageGroupAdmins($param['admin_group_id'] ? [$param['admin_group_id']] : []);
                $admin_in = implode(',', $group_admin_ids);
            }
            $admin_in && $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where distributor_id in ({$admin_in}))"];

            //厂家列表
            if (!empty($factory_ids)) {
                $condition['woaa.factory_id'][] = ['in', $factory_ids];
            }

            if (!empty($factory_group_ids)) {
                $where = ['group_id' => ['in', $factory_group_ids]];
                $factory_ids = BaseModel::getInstance('factory')
                    ->getFieldVal($where, 'factory_id', true);
                $in = empty($factory_ids) ? '-1' : $factory_ids;
                $condition['woaa.factory_id'][] = ['in', $in];
            }
        }

        //状态
        if (!empty($status)) {
            if (-1 == $status) {
                //已终止
                $condition['woaa.cancel_status'] = ['gt', AccessoryService::CANCEL_STATUS_NORMAL];
            } else {
                $condition['accessory_status'][] = ['eq', $status];
                $condition['woaa.cancel_status'][] = ['eq', AccessoryService::CANCEL_STATUS_NORMAL];
            }
        }
        if (strlen($send_back_express_no) > 0) {
            $condition['woaa.id'][] = ['exp', "in (select data_id from express_tracking where express_number like '%{$send_back_express_no}%' and type=2)"];
        }

        //配件单号
        if (!empty($accessory_number)) {
            $condition['accessory_number'][] = ['like', '%' . $accessory_number . '%'];
        }

        //是否需要返件
        if ($is_giveup_return >= 0) {
            $condition['accessory_status'][] = ['egt', AccessoryService::STATUS_FACTORY_SENT];
            if (1 == $is_giveup_return) {
                //需要返件
                $condition['is_giveup_return'][] = AccessoryService::RETURN_ACCESSORY_PASS;
            } elseif (2 == $is_giveup_return) {
                //不需要返件
                //1-默认放弃返件；2-配件单申请后续放弃返件；
                $condition['is_giveup_return'][] = ['in', [AccessoryService::RETURN_ACCESSORY_FORBIDDEN, AccessoryService::RETURN_ACCESSORY_GIVE_UP]];
            }
        }
        //工单号
        if (!empty($orno)) {
            $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where orno = '{$orno}')"];
        }
        //申请时间
        if ($date_from > 0) {
            $condition['woaa.create_time'][] = ['egt', $date_from];
        }
        if ($date_to > 0) {
            $condition['woaa.create_time'][] = ['lt', $date_to];
        }

        if (1 == $is_confirm_send_back) {
            $deadline = strtotime(date('Ymd')) - 7 * 86400;
            $operation_type = OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT;
            $condition['_string']
                = "(select create_time from worker_order_operation_record where worker_order_id=woaa.worker_order_id
and operation_type={$operation_type} order by id desc limit 1) < {$deadline}";
            $condition['accessory_status'][] = ['eq', AccessoryService::STATUS_WORKER_TAKE];
            $condition['woaa.cancel_status'][] = ['eq', AccessoryService::CANCEL_STATUS_NORMAL];
            $condition['is_giveup_return'][] = AccessoryService::RETURN_ACCESSORY_PASS;
            $worker_order_status_str = implode(',', [
                OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
                OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
            ]);
            $condition['worker_order_id'][] = ['exp', "in (select id from worker_order where worker_order_status in ({$worker_order_status_str}))"];
        }

        $model = BaseModel::getInstance($this->tableName);

        //获取数据总量,分页使用
        $opts = [
            'alias' => 'woaa',
            'where' => $condition,
        ];
        $cnt = $model->getNum($opts);

        //获取列表数据
        $field = 'woaa.id as accessory_id,accessory_number,addressee_name,'
            . 'addressee_phone,cp_addressee_area_desc,addressee_address,'
            . 'woaa.create_time,woaa.cancel_status,accessory_status,worker_order_id,'
            . 'worker_order_product_id,is_giveup_return,apply_reason,wo.origin_type,'
            . 'wo.add_id,woaa.factory_id,wo.distributor_id';
        $opts = [
            'alias' => 'woaa',
            'field' => $field,
            'join'  => ['left join worker_order as wo on wo.id=woaa.worker_order_id'],
            'where' => $condition,
            'order' => 'woaa.id desc',
            'limit' => $limit,
        ];
        $list = $model->getList($opts);

        $accessory_ids = [];
        $worker_order_ids = [];
        $worker_order_product_ids = [];
        $factory_ids = [];
        $factory_admin_ids = [];
        $wx_user_ids = [];
        $admin_ids = [];

        foreach ($list as $val) {
            $accessory_id = $val['accessory_id'];
            $worker_order_id = $val['worker_order_id'];
            $worker_order_product_id = $val['worker_order_product_id'];
            $factory_id = $val['factory_id'];
            $origin_type = $val['origin_type'];
            $add_id = $val['add_id'];
            $distributor_id = $val['distributor_id'];

            $accessory_ids[] = $accessory_id;
            $worker_order_ids[] = $worker_order_id;
            $worker_order_product_ids[] = $worker_order_product_id;
            $factory_ids[] = $factory_id;
            $admin_ids[] = $distributor_id;

            if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                $factory_admin_ids[] = $add_id;
            } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                $wx_user_ids[] = $add_id;
            } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                $wx_user_ids[] = $add_id;
            }
        }

        $factories = $this->getFactories($factory_ids);
        $factory_admins = $this->getFactoryAdmins($factory_admin_ids);
        $worker_orders = $this->getWorkerOrders($worker_order_ids);
        $wx_users = $this->getWxUsers($wx_user_ids);
        $worker_products = $this->getWorkerOrderProducts($worker_order_product_ids);
        $accessory_items = $this->getAccessoryItems($accessory_ids);
        $admins = $this->getAdmins($admin_ids);
        //整理数据
        foreach ($list as $key => $val) {
            $accessory_id = $val['accessory_id'];
            $worker_order_id = $val['worker_order_id'];
            $worker_order_product_id = $val['worker_order_product_id'];
            $origin_type = $val['origin_type'];
            $add_id = $val['add_id'];
            $factory_id = $val['factory_id'];
            $distributor_id = $val['distributor_id'];

            //获取工单
            $order = $worker_orders[$worker_order_id] ?? null;
            $val['order'] = $order;

            $factory = $factories[$factory_id] ?? null;

            //获取工单下单人
            $creator = null;
            if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                //子账号
                $factory_admin = $factory_admins[$add_id] ?? null;
                $creator = [
                    'factory_id'         => $factory_id,
                    'factory_short_name' => isset($factory) ? $factory['factory_short_name'] : '',
                    'factory_full_name'  => isset($factory) ? $factory['factory_full_name'] : '',
                    'group_name'         => isset($factory) ? $factory['group_name'] : '',
                    'tag_name'           => isset($factory_admin) ? $factory_admin['tag_name'] : '',
                    'linkman'            => isset($factory_admin) ? $factory_admin['nickout'] : '',
                ];
            } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                //普通用户
                $wx_user = $wx_users[$add_id] ?? null;
                $creator = [
                    'factory_id'         => $factory_id,
                    'factory_short_name' => isset($factory) ? $factory['factory_short_name'] : '',
                    'factory_full_name'  => isset($factory) ? $factory['factory_full_name'] : '',
                    'group_name'         => '',
                    'tag_name'           => '',
                    'linkman'            => isset($wx_user) ? $wx_user['real_name'] : '',
                ];
            } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                //经销商
                $wx_dealer = $wx_users[$add_id] ?? null;
                $creator = [
                    'factory_id'         => $factory_id,
                    'factory_short_name' => isset($factory) ? $factory['factory_short_name'] : '',
                    'factory_full_name'  => isset($factory) ? $factory['factory_full_name'] : '',
                    'group_name'         => '',
                    'tag_name'           => '',
                    'linkman'            => isset($wx_dealer) ? $wx_dealer['real_name'] : '',
                ];
            } elseif (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
                //厂家
                $creator = [
                    'factory_id'         => $factory_id,
                    'factory_short_name' => isset($factory) ? $factory['factory_short_name'] : '',
                    'factory_full_name'  => isset($factory) ? $factory['factory_full_name'] : '',
                    'group_name'         => isset($factory) ? $factory['group_name'] : '',
                    'tag_name'           => isset($factory) ? $factory['tag_name'] : '',
                    'linkman'            => isset($factory) ? $factory['linkman'] : '',
                ];
            }
            $val['creator'] = $creator;

            //获取配件
            $val['accessory_item'] = $accessory_items[$accessory_id] ?? null;

            //获取维修产品
            $val['product'] = $worker_products[$worker_order_product_id] ?? null;
            $val['cs'] = $admins[$distributor_id] ?? null;

            $list[$key] = $val;
        }
//        dump($list);exit;
        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];
    }

    protected function getFactories($factory_ids)
    {
        if (empty($factory_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory');
        $where = ['factory_id' => ['in', $factory_ids]];
        $filed = 'factory_id,factory_short_name,linkman,group_id,factory_full_name';
        $list = $model->getList($where, $filed);

        $factory_group = (new FactoryLogic())->getFactoryGroup();
        $factory_group_map = Arr::pluck($factory_group, 'name', 'id');

        $data = [];

        foreach ($list as $val) {
            $factory_id = $val['factory_id'];
            $group_id = $val['group_id'];

            $val['group_name'] = $factory_group_map[$group_id] ?? '';
            $val['tag_name'] = '系统默认组';

            $data[$factory_id] = $val;
        }

        return $data;
    }

    protected function getFactoryAdmins($factory_admin_ids)
    {
        if (empty($factory_admin_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory_admin');
        $opts = [
            'field' => 'fa.id,fat.name as tag_name,fa.nickout',
            'alias' => 'fa',
            'where' => ['fa.id' => ['in', $factory_admin_ids]],
            'join'  => ['left join factory_adtags as fat on fat.id=fa.tags_id'],
        ];
        $list = $model->getList($opts);

        $data = [];

        foreach ($list as $val) {
            $id = $val['id'];
            $tag_name = $val['tag_name'];

            $val['tag_name'] = empty($tag_name) ? '' : $tag_name;

            $data[$id] = $val;
        }

        return $data;

    }

    protected function getWorkerOrders($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $filed = 'id,orno,origin_type,add_id,factory_id,distributor_id,worker_order_type';
        $where = ['id' => ['in', $worker_order_ids]];
        $model = BaseModel::getInstance('worker_order');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $worker_order_id = $val['id'];

            $data[$worker_order_id] = $val;
        }

        return $data;
    }

    protected function getWxUsers($wx_user_ids)
    {
        if (empty($wx_user_ids)) {
            return [];
        }

        $filed = 'id,real_name';
        $where = ['id' => ['in', $wx_user_ids]];
        $model = BaseModel::getInstance('wx_user');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $id = $val['id'];

            $data[$id] = $val;
        }

        return $data;
    }

    protected function getWorkerOrderProducts($worker_order_product_ids)
    {
        if (empty($worker_order_product_ids)) {
            return [];
        }

        $filed = 'id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,user_service_request,cp_fault_name';
        $where = ['id' => ['in', $worker_order_product_ids]];
        $model = BaseModel::getInstance('worker_order_product');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $worker_order_product_id = $val['id'];

            $data[$worker_order_product_id] = $val;
        }

        return $data;
    }

    protected function getAccessoryItems($accessory_ids)
    {
        if (empty($accessory_ids)) {
            return [];
        }

        $filed = 'accessory_order_id,name,nums,remark,code,cp_accessory_brand_desc,cp_accessory_type_desc,mode';
        $where = ['accessory_order_id' => ['in', $accessory_ids]];
        $model = BaseModel::getInstance('worker_order_apply_accessory_item');
        $list = $model->getList($where, $filed);

        $data = [];
        foreach ($list as $val) {
            $accessory_order_id = $val['accessory_order_id'];

            $data[$accessory_order_id][] = $val;
        }

        return $data;
    }

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,user_name,nickout';
        $where = ['id' => ['in', $admin_ids]];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($where, $filed);

        $data = [];

        $role = AuthService::getModel();

        foreach ($list as $val) {
            $admin_id = $val['id'];

            if (AuthService::ROLE_ADMIN == $role) {
                $val['user_name'] = $val['nickout']; //客服显示真名
            }
            unset($val['nickout']);

            $data[$admin_id] = $val;
        }

        return $data;
    }

    public function getInfo($param)
    {
        //获取参数
        $accessory_id = $param['accessory_id'];

        //检查参数
        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取配件单
        $field = 'id as accessory_id,factory_id,worker_order_id,worker_id,worker_order_product_id,accessory_number,addressee_name,addressee_phone,cp_addressee_area_desc,addressee_address,accessory_imgs,apply_reason,accessory_status,create_time,cancel_status,factory_estimate_time,is_giveup_return,receive_address_type';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);

        $accessory_id = $info['accessory_id'];
        $worker_order_id = $info['worker_order_id'];
        $worker_order_product_id = $info['worker_order_product_id'];
        $factory_id = $info['factory_id'];

        //旧件图片
        $img = $info['accessory_imgs'];
        $info['accessory_imgs'] = null;
        if (!empty($img)) {
            $img = json_decode($img, true);
            $img = array_map(function ($image) {
                return Util::getServerFileUrl($image['url']);
            }, $img);
            $info['accessory_imgs'] = $img ?? null;
        }

        $order = $this->getWorkerOrders([$worker_order_id]); // 工单

        $field = 'id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,user_service_request,cp_fault_name,fault_label_ids';
        $product = BaseModel::getInstance('worker_order_product')
            ->getOneOrFail($worker_order_product_id, $field);
        $fault_label_ids = $product['fault_label_ids'];
        $fault_label_ids = Util::filterIdList($fault_label_ids);

        $cp_fault_name = '';
        if (!empty($fault_label_ids)) {
            $label_model = BaseModel::getInstance('product_fault_label');
            $label_names = $label_model->getFieldVal(['id' => ['in', $fault_label_ids]], 'label_name', true);
            $cp_fault_name = implode(',', $label_names);
        }

        $product['cp_fault_name'] = $cp_fault_name;


        $item = $this->getAccessoryItems([$accessory_id]); // 配件详情
        $factory_db = BaseModel::getInstance('factory');
        $field = 'receive_address,receive_tell,receive_person';
        $factory = $factory_db->getOneOrFail($factory_id, $field);
        //获取日志
        $accessory_service = new AccessoryRecordService();
        $accessory_service->searchRecord($accessory_id);

        $info['order'] = $order[$worker_order_id] ?? null;
        $info['factory'] = $factory;
        $info['product'] = $product;
        $info['accessory_item'] = $item[$accessory_id] ?? null;
        $info['record'] = $accessory_service->getRecord();
        $info['schedule'] = $accessory_service->getSchedule();

        return $info;
    }

    public function getStatusCnt($param)
    {
        //获取管理员信息
        $admin = AuthService::getAuthModel();
        $role = AuthService::getModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();

        if (AuthService::ROLE_FACTORY == $role) {
            //厂家
            $factory_id = $admin_id;
            $condition['factory_id'] = $factory_id;

        } elseif (AuthService::ROLE_FACTORY_ADMIN == $role) {
            //子账号
            $user_id = $admin_id;
            $factory_id = $admin['factory_id'];
            $condition['factory_id'] = $factory_id;
            $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where add_id ={$user_id} and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY_ADMIN . ')'];

        } elseif (AuthService::ROLE_ADMIN == $role) {
            //客服
            $user_id = $admin_id;
            $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where distributor_id={$user_id})"];
        }
        $condition['cancel_status'] = AccessoryService::CANCEL_STATUS_NORMAL;

        $accessory_db = BaseModel::getInstance($this->tableName);
        $opts = [
            'field' => 'accessory_status, count(*) as cnt',
            'where' => $condition,
            'group' => 'accessory_status',
        ];
        $stats_list = $accessory_db->getList($opts);

        $cnt_list = [];
        foreach ($stats_list as $stats) {
            $cnt_list[$stats['accessory_status']] = $stats['cnt'];
        }

        $apply_accessory = $cnt_list[AccessoryService::STATUS_WORKER_APPLY_ACCESSORY] ?? '0';
        $cs_checked = $cnt_list[AccessoryService::STATUS_ADMIN_CHECKED] ?? '0';
        $factory_checked = $cnt_list[AccessoryService::STATUS_FACTORY_CHECKED] ?? '0';
        $factory_sent = $cnt_list[AccessoryService::STATUS_FACTORY_SENT] ?? '0';
        $worker_take = $cnt_list[AccessoryService::STATUS_WORKER_TAKE] ?? '0';
        $send_back = $cnt_list[AccessoryService::STATUS_WORKER_SEND_BACK] ?? '0';

        return [
            'apply_accessory'  => $apply_accessory,
            'cs_checked'       => $cs_checked,
            'factory_checked'  => $factory_checked,
            'factory_sent'     => $factory_sent,
            'worker_take'      => $worker_take,
            'worker_send_back' => $send_back,
        ];
    }

    public function factoryCheck($param)
    {
        //获取参数
        $accessory_id = $param['accessory_id'];
        $is_agree = $param['is_agree'];
        $estimate_time = $param['estimate_time'];
        $remark = $param['remark'];

        //检查参数
        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (1 != $is_agree && 2 != $is_agree) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '审核结果错误');
        }
        if (1 == $is_agree && $estimate_time < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预估配件发出时间错误');
        }

        //获取配件单
        $field = 'accessory_status,cancel_status,worker_order_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];
        $worker_order_id = $info['worker_order_id'];

        //获取工单
        $field = 'orno,distributor_id,worker_order_type';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $orno = $order_info['orno'];
        $distributor_id = $order_info['distributor_id'];
        $worker_order_type = $order_info['worker_order_type'];

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        if (AccessoryService::STATUS_FACTORY_FORBIDDEN == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_FACTORY_FORBIDDEN);
        }
        if (AccessoryService::STATUS_FACTORY_CHECKED == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_FACTORY_CHECKED);
        }
        if (AccessoryService::STATUS_ADMIN_CHECKED != $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_STATUS_ERROR);
        }

        if (
            in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST) &&
            !in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_ACCESSORY_LIST)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '异常保外单不能申请配件单');
        }

        //更新配件单
        $update_data = [
            'factory_check_remark' => $remark,
            'last_update_time'     => NOW_TIME,
            'factory_check_time'   => NOW_TIME,
        ];
        if (1 == $is_agree) {
            $update_data['accessory_status'] = AccessoryService::STATUS_FACTORY_CHECKED;
            $update_data['factory_estimate_time'] = $estimate_time;
        } else {
            $update_data['accessory_status'] = AccessoryService::STATUS_FACTORY_FORBIDDEN;
        }
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => AccessoryService::STATUS_ADMIN_CHECKED,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //配件单日志
        $content = '审核配件单';
        $type = 0;
        $message_type = 0;
        $sys_msg = '';
        $sys_type = 0;
        if (1 == $is_agree) {
            $content .= '(审核通过)';
            $type = AccessoryRecordService::OPERATE_TYPE_FACTORY_CHECKED;
            $message_type = AppMessageService::TYPE_WAIT_ACCESSORY_MASSAGE;

            $remark = strlen($remark) > 0 ? $remark . '，' : '';
            $remark .= '预估配件发出时间为：' . date('Y.m.d H:i', $estimate_time);
            $sys_msg = "工单号{$orno}的配件申请厂家审核通过";
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_PASS;
        } else {
            $content .= '(审核不通过)';
            $type = AccessoryRecordService::OPERATE_TYPE_FACTORY_FORBIDDEN;
            $message_type = AppMessageService::TYPE_ACCESSORY_CHECK_NOT_PASS;

            $sys_msg = "工单号{$orno}的配件申请厂家审核不通过";
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_APPLY_FORBIDDEN;
        }
        //配件单日志
        AccessoryRecordService::create($accessory_id, $type, $content, $remark);

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => $message_type, 'data_id' => $accessory_id]));

        //系统消息
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, $sys_msg, $accessory_id, $sys_type);

        //统计
        BaseModel::getInstance('worker_order_statistics')
            ->update($worker_order_id, ['accessory_unsent_num' => ['exp', 'accessory_unsent_num+1']]);
    }

    public function factoryDelaySend($param)
    {
        //获取参数
        $accessory_id = $param['accessory_id'];
        $estimate_time = $param['estimate_time'];
        $remark = $param['remark'];

        //检查参数
        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if ($estimate_time < NOW_TIME) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '预估配件发出时间错误');
        }

        //获取配件单
        $field = 'accessory_status,cancel_status';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        if (AccessoryService::STATUS_FACTORY_FORBIDDEN == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_FACTORY_FORBIDDEN);
        }
        if (AccessoryService::STATUS_FACTORY_CHECKED != $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_STATUS_ERROR);
        }

        //更新配件单
        $update_data = [
            'factory_estimate_time' => $estimate_time,
            'last_update_time'      => NOW_TIME,
        ];
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => AccessoryService::STATUS_FACTORY_CHECKED,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //配件单日志
        $content = '延迟发货时间至：' . date('Y.m.d H:i', $estimate_time);
        $type = AccessoryRecordService::OPERATE_TYPE_FACTORY_EDIT_PLAN;
        AccessoryRecordService::create($accessory_id, $type, $content, $remark);

        event(new AccessoryCheckEvent(['type' => AppMessageService::TYPE_FACTORY_DELAY_SEND, 'data_id' => $accessory_id]));
    }

    public function factoryConfirmSend($param)
    {
        //获取参数
        $accessory_id = $param['accessory_id'];
        $express_number = $param['express_number'];
        $express_code = $param['express_code'];
        $remark = $param['remark'];
        $is_giveup_return = $param['is_giveup_return'];

        //检查参数
        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (empty($express_number)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '物流单号为空');
        }
        if (empty($express_code)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '物流公司为空');
        }
        if (1 != $is_giveup_return && 2 != $is_giveup_return) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '是否需要返还错误');
        }

        //获取配件单
        $field = 'accessory_status,cancel_status,worker_order_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $worker_order_id = $info['worker_order_id'];
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];

        $field = 'distributor_id,orno,worker_order_type';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $distributor_id = $order_info['distributor_id'];
        $orno = $order_info['orno'];
        $worker_order_type = $order_info['worker_order_type'];

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        if (AccessoryService::STATUS_FACTORY_FORBIDDEN == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_FACTORY_FORBIDDEN);
        }
        if (AccessoryService::STATUS_FACTORY_CHECKED != $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_FACTORY_CHECKED, '配件单非待发件状态');
        }

        if (
            in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST) &&
            !in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_ACCESSORY_LIST)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '异常保外单不能申请配件单');
        }

        if (in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_ACCESSORY_LIST)) {
            if (2 != $is_giveup_return) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保外单配件单不能返还配件');
            }
        }

        //更新配件单
        $update_data = [
            'factory_send_time' => NOW_TIME,
            'last_update_time'  => NOW_TIME,
            'accessory_status'  => AccessoryService::STATUS_FACTORY_SENT,
        ];
        //是否放弃配件返还
        if (1 == $is_giveup_return) {
            $update_data['is_giveup_return'] = AccessoryService::RETURN_ACCESSORY_PASS;
        } else {
            $update_data['is_giveup_return'] = AccessoryService::RETURN_ACCESSORY_FORBIDDEN;
        }
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => AccessoryService::STATUS_FACTORY_CHECKED,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //物流跟踪
        expressTrack($express_code, $express_number, $accessory_id, ExpressTrackingLogic::TYPE_ACCESSORY_SEND);

        //配件单日志
        $is_giveup_return_str = 1 == $is_giveup_return ? '旧件需返还' : '旧件不需返还';
        $company = BaseModel::getInstance('express_com')
            ->getFieldVal(['comcode' => $express_code], 'name');
        $content = "确认发件, {$company}：{$express_number} ($is_giveup_return_str)";
        $type = AccessoryRecordService::OPERATE_TYPE_FACTORY_CONFIRM_SEND;
        AccessoryRecordService::create($accessory_id, $type, $content, $remark);

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => AppMessageService::TYPE_SEND_ACCESSORY_MASSAGE, 'data_id' => $accessory_id]));

        //系统消息
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, "工单号{$orno}的配件已发送", $accessory_id, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_CONFIRM_SEND);

        //统计
        $where = [
            'worker_order_id'      => $worker_order_id,
            'accessory_unsent_num' => ['egt', 1],
        ];
        $update_data = [];
        $update_data['accessory_unsent_num'] = ['exp', 'accessory_unsent_num-1'];
        1 == $is_giveup_return && $update_data['accessory_worker_unreceive_num'] =  ['exp', 'accessory_worker_unreceive_num+1'];
        BaseModel::getInstance('worker_order_statistics')
            ->update($where, $update_data);
    }

    public function giveUpReturn($param)
    {
        //获取参数
        $accessory_id = $param['accessory_id'];
        $reason = $param['reason'];
        $remark = $param['remark'];

        //检查参数
        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        $valid_reason = [1, 2, 3, 4, 5];
        if (!in_array($reason, $valid_reason)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '放弃配件理由错误');
        }

        //获取配件单
        $field = 'accessory_status,cancel_status,is_giveup_return,worker_order_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];
        $is_giveup_return = $info['is_giveup_return'];
        $worker_order_id = $info['worker_order_id'];

        //获取工单
        $field = 'distributor_id,orno,worker_order_type';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $distributor_id = $order_info['distributor_id'];
        $orno = $order_info['orno'];
        $worker_order_type = $order_info['worker_order_type'];

        if (in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保外单配件单不能放弃返件');
        }

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        //放弃返件不能设置放弃返件
        if (
            AccessoryService::RETURN_ACCESSORY_FORBIDDEN == $is_giveup_return ||
            AccessoryService::RETURN_ACCESSORY_GIVE_UP == $is_giveup_return
        ) {
            $this->throwException(ErrorCode::ACCESSORY_NOT_SEND_BACK);
        }
        //厂家已收件
        if (AccessoryService::STATUS_COMPLETE == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_COMPLETED);
        }

        //更新配件单
        $reason_str = '';
        switch ($reason) {
            case '1':
                $reason_str = '取消返还';
                break;
            case '2':
                $reason_str = '维修工丢失配件';
                break;
            case '3':
                $reason_str = '维修工损坏配件';
                break;
            case '4':
                $reason_str = '运输过程丢失';
                break;
            case '5':
                $reason_str = '其他';
                break;
        }
        $factory_giveup_return = $reason_str . PHP_EOL . ($remark ? '备注:' . $remark : '');
        $update_data = [
            'factory_giveup_return' => $factory_giveup_return,
            'last_update_time'      => NOW_TIME,
            'is_giveup_return'      => AccessoryService::RETURN_ACCESSORY_GIVE_UP,
        ];
        if (
            AccessoryService::STATUS_WORKER_TAKE == $accessory_status ||
            AccessoryService::STATUS_WORKER_SEND_BACK == $accessory_status
        ) {
            //技工已签收 或 技工已返还配件 直接把配件状态改为已完成
            $update_data['accessory_status'] = AccessoryService::STATUS_COMPLETE;
        }

        $valid_status = [AccessoryService::STATUS_FACTORY_SENT, AccessoryService::STATUS_WORKER_TAKE, AccessoryService::STATUS_WORKER_SEND_BACK];
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => ['in', $valid_status],
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //配件单日志
        $content = "放弃配件返还({$reason_str})";
        $type = AccessoryRecordService::OPERATE_TYPE_FACTORY_GIVE_UP_SEND_BACK;
        AccessoryRecordService::create($accessory_id, $type, $content, $remark);

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => AppMessageService::TYPE_FACTORY_ABANDON_RETURN, 'data_id' => $accessory_id]));

        //系统消息
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, "工单号{$orno}的配件，厂家已放弃配件返回", $accessory_id, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_GIVE_UP_RETURN);

        //统计
        if (
            AccessoryService::STATUS_FACTORY_SENT == $accessory_status ||
            AccessoryService::STATUS_WORKER_TAKE == $accessory_status ||
            AccessoryService::STATUS_WORKER_SEND_BACK == $accessory_status
        ) {
            $update_data['accessory_unreturn_num'] = ['exp', 'accessory_unreturn_num-1'];
            $where = [
                'worker_order_id'        => $worker_order_id,
                'accessory_unreturn_num' => ['egt', 1],
            ];
            BaseModel::getInstance('worker_order_statistics')
                ->update($where, $update_data);
        }
    }

    public function factoryConfirmSendBack($param)
    {
        //获取参数
        $accessory_id = $param['accessory_id'];

        //检查参数
        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取配件单
        $field = 'accessory_status,cancel_status,is_giveup_return,worker_order_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];
        $is_giveup_return = $info['is_giveup_return'];
        $worker_order_id = $info['worker_order_id'];

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        //厂家已收件
        if (AccessoryService::STATUS_COMPLETE == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_COMPLETED);
        }
        if (
            AccessoryService::RETURN_ACCESSORY_FORBIDDEN == $is_giveup_return ||
            AccessoryService::RETURN_ACCESSORY_GIVE_UP == $is_giveup_return
        ) {
            $this->throwException(ErrorCode::ACCESSORY_GIVE_UP_SEND_BACK);
        }

        //获取工单
        $field = 'worker_order_type';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $worker_order_type = $order_info['worker_order_type'];
        if (in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保外单配件单不能确认返件');
        }

        //更新配件单
        $update_data = [
            'last_update_time'             => NOW_TIME,
            'accessory_status'             => AccessoryService::STATUS_COMPLETE,
            'factory_confirm_receive_time' => NOW_TIME,
            'complete_time'                => NOW_TIME,
        ];
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => AccessoryService::STATUS_WORKER_SEND_BACK,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //配件单日志
        $content = '厂家确认配件返还';
        AccessoryRecordService::create($accessory_id, AccessoryRecordService::OPERATE_TYPE_FACTORY_CONFIRM_SEND_BACK, $content, '');

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => AppMessageService::TYPE_SEND_ACCESSORY_MASSAGE, 'data_id' => $accessory_id]));

        //统计
        $stats_model = BaseModel::getInstance('worker_order_statistics');
        $stats_model->update([
            'worker_order_id'        => $worker_order_id,
            'accessory_unreturn_num' => ['egt', 1],
        ], [
            'accessory_unreturn_num' => ['exp', 'accessory_unreturn_num-1'],
        ]);

    }

    public function factoryStop($param)
    {
        $accessory_id = $param['accessory_id'];
        $reason = $param['reason'];

        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取配件单
        $field = 'accessory_status,cancel_status,worker_order_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];
        $worker_order_id = $info['worker_order_id'];

        //获取工单
        $field = 'distributor_id,orno';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $distributor_id = $order_info['distributor_id'];
        $orno = $order_info['orno'];

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        //已完成
        if (AccessoryService::STATUS_COMPLETE == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_COMPLETED);
        }

        $valid_status = [AccessoryService::STATUS_FACTORY_CHECKED, AccessoryService::STATUS_FACTORY_SENT, AccessoryService::STATUS_WORKER_TAKE, AccessoryService::STATUS_WORKER_SEND_BACK];
        if (!in_array($accessory_status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '配件单当前状态不能终止');
        }

        //更新配件单
        $update_data = [
            'last_update_time' => NOW_TIME,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_FACTORY_STOP,
            'stop_time'        => NOW_TIME,
        ];
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => ['in', $valid_status],
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //配件单日志
        $content = '终止配件单';
        $type = AccessoryRecordService::OPERATE_TYPE_FACTORY_STOP_APPLY;
        AccessoryRecordService::create($accessory_id, $type, $content, $reason);

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => AppMessageService::TYPE_ACCESSORY_END, 'data_id' => $accessory_id]));

        //系统消息
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, "工单号{$orno}的配件，厂家已终止", $accessory_id, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_FACTORY_STOP);

        //统计
        $stats_model = BaseModel::getInstance('worker_order_statistics');
        if (AccessoryService::STATUS_FACTORY_CHECKED == $accessory_status) {
            $where = ['worker_order_id' => $worker_order_id, 'accessory_unsent_num' => ['egt', 1]];
            $update_data = ['accessory_unsent_num' => ['exp', 'accessory_unsent_num-1']];
            $stats_model->update($where, $update_data);
        } elseif (AccessoryService::STATUS_FACTORY_SENT == $accessory_status) {
            $where = ['worker_order_id' => $worker_order_id, 'accessory_worker_unreceive_num' => ['egt', 1]];
            $update_data = ['accessory_worker_unreceive_num' => ['exp', 'accessory_worker_unreceive_num-1']];
            $stats_model->update($where, $update_data);
        } elseif (AccessoryService::STATUS_WORKER_SEND_BACK == $accessory_status) {
            $where = ['worker_order_id' => $worker_order_id, 'accessory_unreturn_num' => ['egt', 1]];
            $update_data = ['accessory_unreturn_num' => ['exp', 'accessory_unreturn_num-1']];
            $stats_model->update($where, $update_data);
        }
    }

    public function checkAllCompleted($worker_order_id)
    {
        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $ignore_status = [AccessoryService::STATUS_ADMIN_FORBIDDEN, AccessoryService::STATUS_FACTORY_FORBIDDEN];
        $field = 'accessory_status';
        $model = BaseModel::getInstance($this->tableName);
        $where = ['worker_order_id' => $worker_order_id, 'cancel_status' => AccessoryService::CANCEL_STATUS_NORMAL, 'accessory_status' => ['not in', $ignore_status]];
        $accessories = $model->getList($where, $field);

        foreach ($accessories as $accessory) {
            $accessory_status = $accessory['accessory_status'];
            if (AccessoryService::STATUS_COMPLETE != $accessory_status) {
                $this->throwException(ErrorCode::ACCESSORY_NOT_UNCOMPLETED);
            }
        }


    }

    public function adminCheck($param)
    {
        //获取参数
        $accessory_id = $param['accessory_id'];
        $is_check = $param['is_check'];
        $remark = $param['remark'];

        //检查参数
        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (1 != $is_check && 2 != $is_check) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '审核结果错误');
        }

        //获取管理员信息
        $admin = AuthService::getAuthModel();

        //获取配件单
        $field = 'accessory_status,cancel_status,worker_order_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];
        $worker_order_id = $info['worker_order_id'];

        //获取工单
        $field = 'add_id,orno,origin_type,worker_order_type';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $origin_type = $order_info['origin_type'];
        $orno = $order_info['orno'];
        $add_id = $order_info['add_id'];
        $worker_order_type = $order_info['worker_order_type'];

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        if (AccessoryService::STATUS_ADMIN_FORBIDDEN == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_CS_FORBIDDEN);
        }
        if (AccessoryService::STATUS_ADMIN_CHECKED == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_CS_CHECKED);
        }
        if (AccessoryService::STATUS_WORKER_APPLY_ACCESSORY != $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_STATUS_ERROR);
        }

        if (
            in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST) &&
            !in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_ACCESSORY_LIST)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '异常保外单不能申请配件单');
        }

        //更新配件单
        $update_data = [
            'admin_check_remark' => $remark,
            'last_update_time'   => NOW_TIME,
            'admin_check_time'   => NOW_TIME,
        ];
        if (1 == $is_check) {
            $update_data['accessory_status'] = AccessoryService::STATUS_ADMIN_CHECKED;
        } else {
            $update_data['accessory_status'] = AccessoryService::STATUS_ADMIN_FORBIDDEN;
        }
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => AccessoryService::STATUS_WORKER_APPLY_ACCESSORY,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //配件单日志
        $content = '审核配件单';
        $type = 0;
        $message_type = 0;
        if (1 == $is_check) {
            $content .= '(审核通过)';
            $type = AccessoryRecordService::OPERATE_TYPE_CS_CHECKED;
            $message_type = AppMessageService::TYPE_WAIT_FACTORY_CHECK;

            //系统消息
            $receiver_type = OrderService::ORIGIN_TYPE_FACTORY == $origin_type ? SystemMessageService::USER_TYPE_FACTORY : SystemMessageService::USER_TYPE_FACTORY_ADMIN;
            $sys_content = "工单号{$orno}申请了配件";
            SystemMessageService::create($receiver_type, $add_id, $sys_content, $accessory_id, SystemMessageService::MSG_TYPE_FACTORY_ACCESSORY_ADMIN_APPLY_PASS);
        } else {
            $content .= '(审核不通过)';
            $type = AccessoryRecordService::OPERATE_TYPE_CS_FORBIDDEN;
            $message_type = AppMessageService::TYPE_ACCESSORY_CHECK_NOT_PASS;
        }
        AccessoryRecordService::create($accessory_id, $type, $content, $remark);

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => $message_type, 'data_id' => $accessory_id]));
    }

    public function adminStop($param)
    {
        $accessory_id = $param['accessory_id'];
        $reason = $param['reason'];

        if (empty($accessory_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取管理员信息
        $admin = AuthService::getAuthModel();

        //获取配件单
        $field = 'accessory_status,cancel_status,worker_order_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($accessory_id, $field);
        $accessory_status = $info['accessory_status'];
        $cancel_status = $info['cancel_status'];
        $worker_order_id = $info['worker_order_id'];

        $field = 'add_id,origin_type,orno';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $origin_type = $order_info['origin_type'];
        $add_id = $order_info['add_id'];
        $orno = $order_info['orno'];

        //检查配件单
        if (AccessoryService::CANCEL_STATUS_NORMAL != $cancel_status) {
            $this->throwException(ErrorCode::ACCESSORY_CANCELED);
        }
        //厂家已收件
        if (AccessoryService::STATUS_COMPLETE == $accessory_status) {
            $this->throwException(ErrorCode::ACCESSORY_COMPLETED);
        }

        $valid_status = [AccessoryService::STATUS_ADMIN_CHECKED, AccessoryService::STATUS_FACTORY_CHECKED, AccessoryService::STATUS_FACTORY_SENT, AccessoryService::STATUS_WORKER_TAKE, AccessoryService::STATUS_WORKER_SEND_BACK];

        if (!in_array($accessory_status, $valid_status)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '配件单当前状态不能终止');
        }

        //更新配件单
        $update_data = [
            'last_update_time' => NOW_TIME,
            'cancel_status'    => AccessoryService::CANCEL_STATUS_ADMIN_STOP,
            'stop_time'        => NOW_TIME,
        ];
        $where = [
            'id'               => $accessory_id,
            'accessory_status' => ['in', $valid_status],
            'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
        ];
        $model->update($where, $update_data);

        //配件单日志
        $content = '终止配件单';
        $type = AccessoryRecordService::OPERATE_TYPE_CS_STOP_APPLY;
        AccessoryRecordService::create($accessory_id, $type, $content, $reason);

        //APP 企业号推送
        event(new AccessoryCheckEvent(['type' => AppMessageService::TYPE_ACCESSORY_END, 'data_id' => $accessory_id]));

        //系统消息
        $receiver_type = OrderService::ORIGIN_TYPE_FACTORY == $origin_type ? SystemMessageService::USER_TYPE_FACTORY : SystemMessageService::USER_TYPE_FACTORY_ADMIN;
        $content = "工单号{$orno}的配件，客服已终止";
        SystemMessageService::create($receiver_type, $add_id, $content, $accessory_id, SystemMessageService::MSG_TYPE_FACTORY_ACCESSORY_ADMIN_STOP);

        //统计
        $stats_model = BaseModel::getInstance('worker_order_statistics');
        if (AccessoryService::STATUS_FACTORY_CHECKED == $accessory_status) {
            $where = ['worker_order_id' => $worker_order_id, 'accessory_unsent_num' => ['egt', 1]];
            $update_data = ['accessory_unsent_num' => ['exp', 'accessory_unsent_num-1']];
            $stats_model->update($where, $update_data);
        } elseif (AccessoryService::STATUS_FACTORY_SENT == $accessory_status) {
            $where = ['worker_order_id' => $worker_order_id, 'accessory_worker_unreceive_num' => ['egt', 1]];
            $update_data = ['accessory_worker_unreceive_num' => ['exp', 'accessory_worker_unreceive_num-1']];
            $stats_model->update($where, $update_data);
        } elseif (AccessoryService::STATUS_WORKER_SEND_BACK == $accessory_status) {
            $where = ['worker_order_id' => $worker_order_id, 'accessory_unreturn_num' => ['egt', 1]];
            $update_data = ['accessory_unreturn_num' => ['exp', 'accessory_unreturn_num-1']];
            $stats_model->update($where, $update_data);
        }

    }
}