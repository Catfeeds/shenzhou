<?php
/**
 * Function: 费用单
 * File: CostLogic.class.php
 * User: sakura
 * Date: 2017/11/9
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\CostOrderCheckEvent;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\AuthService;
use Common\Common\Service\CostRecordService;
use Common\Common\Service\CostService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\SystemMessageService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class CostLogic extends BaseLogic
{

    protected $tableName = 'worker_order_apply_cost';

    public function getList($param)
    {
        //获取参数
        $status = $param['status'];
        $apply_cost_number = $param['apply_cost_number'];
        $type = $param['type'];
        $orno = $param['orno'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $limit = $param['limit'];
        $worker_name = $param['worker_name'];
        $worker_tel = $param['worker_tel'];
        $fee_from = $param['fee_from'];
        $fee_to = $param['fee_to'];

        $tag_id = $param['tag_id']; //子账号组
        $admin_ids = $param['admin_ids']; // 工单客服
        $admin_ids = Util::filterIdList($admin_ids);

        $factory_ids = $param['factory_ids']; //所属厂家id
        $factory_ids = Util::filterIdList($factory_ids);

        $factory_group_ids = $param['factory_group_id_list']; // 厂家组别
        $factory_group_ids = Util::filterIdList($factory_group_ids);

        //获取管理员信息
        $admin = AuthService::getAuthModel();
        $role = AuthService::getModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();

        //组织查询
        $condition = [];
        if (AuthService::ROLE_FACTORY == $role) {
            //厂家
            $factory_id = $admin_id;
            $condition['c.factory_id'] = $factory_id;
            $condition['c.status'][] = ['not in', [CostService::STATUS_ADMIN_FORBIDDEN, CostService::STATUS_APPLY]];
            if (strlen($tag_id) > 0) {
                if ('0' != $tag_id) {
                    $where = [
                        'factory_id' => $factory_id,
                        'tags_id'    => $tag_id,
                    ];
                    $factory_admin = BaseModel::getInstance('factory_admin')
                        ->getFieldVal($where, 'id', true);
                    $in = empty($factory_admin) ? '-1' : implode(',', array_unique($factory_admin));
                    $condition['c.worker_order_id'][] = ['exp', "IN (select id from worker_order where add_id in ({$in}) and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY_ADMIN . ')'];
                }
            }
        } elseif (AuthService::ROLE_FACTORY_ADMIN == $role) {
            //子账号
            $factory_id = $admin['factory_id'];
            $condition['c.status'][] = ['not in', [CostService::STATUS_ADMIN_FORBIDDEN, CostService::STATUS_APPLY]];
            $condition['c.factory_id'] = $factory_id;
            if (strlen($tag_id) > 0) {
                if ('0' != $tag_id) {
                    $where = [
                        'factory_id' => $factory_id,
                        'tags_id'    => $tag_id,
                    ];
                    $factory_admin = BaseModel::getInstance('factory_admin')
                        ->getFieldVal($where, 'id', true);
                    $in = empty($factory_admin) ? '-1' : implode(',', array_unique($factory_admin));
                    $condition['c.worker_order_id'][] = ['exp', "IN (select id from worker_order where add_id in ({$in}) and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY_ADMIN . ')'];
                } else {
                    $condition['c.worker_order_id'][] = ['exp', "IN (select id from worker_order where (add_id={$factory_id} and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY . ") or (add_id={$admin_id} and origin_type=" . OrderService::ORIGIN_TYPE_FACTORY_ADMIN . "))"];
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
            $admin_in && $condition['c.worker_order_id'][] = ['exp', "IN (select id from worker_order where distributor_id in ({$admin_in}))"];

            //厂家列表
            if (!empty($factory_ids)) {
                $condition['c.factory_id'][] = ['in', $factory_ids];
            }

            if (!empty($factory_group_ids)) {
                $where = ['group_id' => ['in', $factory_group_ids]];
                $factory_ids = BaseModel::getInstance('factory')
                    ->getFieldVal($where, 'factory_id', true);
                $in = empty($factory_ids) ? '-1' : implode(',', $factory_ids);
                $condition['factory_id'][] = ['in', $in];
            }
        }

        //状态
        if (strlen($status) > 0) {
            $condition['c.status'] = $status;
        }
        //配件单号
        if (!empty($apply_cost_number)) {
            $condition['apply_cost_number'] = ['like', '%' . $apply_cost_number . '%'];
        }
        //是否需要返件
        if (!empty($type)) {
            $condition['c.type'] = $type;
        }
        //工单号
        if (!empty($orno)) {
            $condition['c.worker_order_id'] = ['exp', "IN (select id from worker_order where orno like '%{$orno}%')"];
        }
        //申请时间
        if ($date_from > 0) {
            $condition['c.create_time'][] = ['gt', $date_from];
        }
        if ($date_to > 0) {
            $condition['c.create_time'][] = ['lt', $date_to];
        }

        if (!empty($worker_name)) {
            $condition['c.worker_id'][] = ['exp', "IN (select worker_id from worker where nickname like '%{$worker_name}%')"];
        }
        if (!empty($worker_tel)) {
            $condition['c.worker_id'][] = ['exp', "IN (select worker_id from worker where worker_telephone like '%{$worker_tel}%' or worker_tell like '%{$worker_tel}%')"];
        }

        //申请额度
        if ($fee_from > 0) {
            $condition['c.fee'][] = ['egt', $fee_from];
        }
        if ($fee_to > 0) {
            $condition['c.fee'][] = ['elt', $fee_to];
        }

        $model = BaseModel::getInstance($this->tableName);

        //获取数据总量,分页使用
        $cnt = $model->getNum(['where' => $condition, 'alias' => 'c']);

        //获取列表数据
        $field = 'c.id as cost_id,apply_cost_number,c.status,type,fee,reason,c.create_time,c.worker_order_id,c.worker_order_product_id,c.worker_id,wo.origin_type,c.factory_id,wo.add_id,wo.distributor_id';
        $opts = [
            'alias' => 'c',
            'field' => $field,
            'where' => $condition,
            'join'  => ['left join worker_order as wo on wo.id=c.worker_order_id'],
            'order' => 'c.id desc',
            'limit' => $limit,
        ];
        $list = $model->getList($opts);

        $worker_order_ids = [];
        $worker_order_product_ids = [];
        $factory_ids = [];
        $factory_admin_ids = [];
        $wx_user_ids = [];
        $admin_ids = [];
        $worker_ids = [];

        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];
            $worker_order_product_id = $val['worker_order_product_id'];
            $worker_id = $val['worker_id'];
            $distributor_id = $val['distributor_id'];
            $factory_id = $val['factory_id'];
            $origin_type = $val['origin_type'];
            $add_id = $val['add_id'];

            $worker_order_ids[] = $worker_order_id;
            $worker_order_product_ids[] = $worker_order_product_id;
            $factory_ids[] = $factory_id;
            $admin_ids[] = $distributor_id;
            $worker_ids[] = $worker_id;

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
        $admins = $this->getAdmins($admin_ids);
        $products = $this->getWorkerOrderProducts($worker_order_product_ids);
        $workers = $this->getWorkers($worker_ids);

        //整理数据
        foreach ($list as $key => $val) {
            $worker_order_id = $val['worker_order_id'];
            $worker_order_product_id = $val['worker_order_product_id'];
            $worker_id = $val['worker_id'];
            $origin_type = $val['origin_type'];
            $add_id = $val['add_id'];
            $factory_id = $val['factory_id'];
            $distributor_id = $val['distributor_id'];

            $factory = $factories[$factory_id] ?? null;

            //获取工单
            $val['order'] = $worker_orders[$worker_order_id] ?? null;

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

            //获取维修产品
            $val['product'] = $products[$worker_order_product_id] ?? null;
            $val['worker'] = $workers[$worker_id] ?? null;

            $val['cs'] = $admins[$distributor_id] ?? null;

            $list[$key] = $val;
        }

        return [
            'list' => $list,
            'cnt'  => $cnt,
        ];
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

        $filed = 'id,orno,origin_type,add_id,factory_id,distributor_id';
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

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,nickout as user_name';
        $where = ['id' => ['in', $admin_ids]];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $admin_id = $val['id'];

            $data[$admin_id] = $val;
        }

        return $data;
    }

    protected function getWorkers($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $filed = 'worker_id,nickname as user_name,worker_telephone as user_tel';
        $where = ['worker_id' => ['in', $worker_ids]];
        $model = BaseModel::getInstance('worker');
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $worker_id = $val['worker_id'];

            $data[$worker_id] = $val;
        }

        return $data;
    }

    public function getInfo($param)
    {
        //获取参数
        $cost_id = $param['cost_id'];

        //检查参数
        if (empty($cost_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取配件单
        $field = 'id as cost_id,apply_cost_number,status,type,fee,reason,create_time,imgs,worker_order_id,worker_order_product_id';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($cost_id, $field);
        $worker_order_id = $info['worker_order_id'];
        $worker_order_product_id = $info['worker_order_product_id'];

        $img = $info['imgs'];
        $info['imgs'] = null;
        if (!empty($img)) {
            $img = json_decode($img, true);
            $img = array_map(function ($image) {
                return Util::getServerFileUrl($image['url']);
            }, $img);
            $info['imgs'] = $img ?? null;
        }

        $orders = $this->getWorkerOrders([$worker_order_id]);

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

        $accessory_service = new CostRecordService();
        $accessory_service->searchRecord($cost_id);

        $info['order'] = $orders[$worker_order_id] ?? null;
        $info['product'] = $product;
        $info['record'] = $accessory_service->getRecord();

        return $info;
    }

    public function getStatusCnt($param)
    {
        //获取管理员信息
        $admin = AuthService::getAuthModel();
        $role = AuthService::getModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();

        $condition = [];
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
            $admin_list = [$user_id];
            $admin_in = implode(',', array_unique($admin_list));
            $condition['worker_order_id'][] = ['exp', "IN (select id from worker_order where distributor_id in ({$admin_in}))"];
        }

        $accessory_db = BaseModel::getInstance($this->tableName);
        $opts = [
            'field' => 'status, count(*) as cnt',
            'group' => 'status',
            'where' => $condition,
        ];
        $stats_list = $accessory_db->getList($opts);

        $cnt_list = [];
        foreach ($stats_list as $stats) {
            $status = $stats['status'];
            $cnt = $stats['cnt'];

            $cnt_list[$status] = $cnt;
        }

        $apply_cost = $cnt_list[CostService::STATUS_APPLY] ?? '0';
        $cs_checked = $cnt_list[CostService::STATUS_ADMIN_PASS] ?? '0';

        return [
            'apply_cost' => $apply_cost,
            'cs_checked' => $cs_checked,
        ];
    }

    public function factoryCheck($param)
    {
        //获取参数
        $cost_id = $param['cost_id'];
        $is_check = $param['is_check'];
        $remark = $param['remark'];

        //获取管理员信息
        $role = AuthService::getModel();

        //检查参数
        if (empty($cost_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (1 != $is_check && 2 != $is_check) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '审核结果异常');
        }

        //获取费用单
        $field = 'status,worker_order_id,fee';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($cost_id, $field);
        $status = $info['status'];
        $worker_order_id = $info['worker_order_id'];
        $cost_fee = $info['fee'];

        $field = 'distributor_id,orno';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $distributor_id = $order_info['distributor_id'];
        $orno = $order_info['orno'];

        //检查费用单
        if (CostService::STATUS_FACTORY_PASS == $status) {
            $this->throwException(ErrorCode::COST_FACTORY_CHECKED);
        }
        if (CostService::STATUS_FACTORY_FORBIDDEN == $status) {
            $this->throwException(ErrorCode::COST_FACTORY_FORBIDDEN);
        }
        if (CostService::STATUS_ADMIN_PASS != $status) {
            $this->throwException(ErrorCode::COST_STATUS_ERROR);
        }

        //更新费用单
        $update_data = [
            'last_update_time'     => NOW_TIME,
            'factory_check_remark' => $remark,
            'factory_check_time'   => NOW_TIME,
        ];
        if (1 == $is_check) {
            $update_data['status'] = CostService::STATUS_FACTORY_PASS;
        } else {
            $update_data['status'] = CostService::STATUS_FACTORY_FORBIDDEN;
        }
        $where = ['status' => CostService::STATUS_ADMIN_PASS, 'id' => $cost_id];
        $model->update($where, $update_data);

        //费用单日志
        $content = '审核费用单';
        $type = 0;
        $message_type = 0;
        $sys_msg = '';
        $sys_type = 0;
        if (1 == $is_check) {
            $content .= '(审核通过)';
            if (AuthService::ROLE_FACTORY_ADMIN == $role) {
                $type = CostRecordService::TYPE_FACTORY_ADMIN_CHECKED;
            } else {
                $type = CostRecordService::TYPE_FACTORY_CHECKED;
            }
            $message_type = AppMessageService::TYPE_CHECK_PASS_MASSAGE;
            $sys_msg = "工单号{$orno}的费用申请厂家审核通过";
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_PASS;
        } else {
            $content .= '(审核不通过)';
            if (AuthService::ROLE_FACTORY_ADMIN == $role) {
                $type = CostRecordService::TYPE_FACTORY_ADMIN_FORBIDDEN;
            } else {
                $type = CostRecordService::TYPE_FACTORY_FORBIDDEN;
            }
            $message_type = AppMessageService::TYPE_CHECK_NOT_PASS_MASSAGE;
            $sys_msg = "工单号{$orno}的费用申请厂家审核不通过";
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_FORBIDDEN;
        }
        CostRecordService::create($cost_id, $type, $content, $remark);

        event(new CostOrderCheckEvent(['type' => $message_type, 'data_id' => $cost_id]));

        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, $sys_msg, $cost_id, $sys_type);

        if (1 == $is_check) {
            $order_fees = $this->getWorkerOrderFees([$worker_order_id]);
            $worker_cost_fee = $order_fees[$worker_order_id]['worker_cost_fee'] + $cost_fee;
            $factory_cost_fee = $order_fees[$worker_order_id]['factory_cost_fee'] + $cost_fee;
            $update_data = [
                'worker_cost_fee'  => $worker_cost_fee,
                'factory_cost_fee' => $factory_cost_fee,
            ];
            OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, $update_data);
        }
    }

    protected function getWorkerOrderFees($worker_order_ids)
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('worker_order_fee');

        $where = ['worker_order_id' => 'in', $worker_order_ids];
        $field = 'worker_order_id,worker_cost_fee,factory_cost_fee';
        $list = $model->getList($where, $field);

        $data = [];

        foreach ($list as $val) {
            $worker_order_id = $val['worker_order_id'];

            $data[$worker_order_id] = $val;
        }

        return $data;

    }

    public function adminCheck($param)
    {
        //获取参数
        $cost_id = $param['cost_id'];
        $is_check = $param['is_check'];
        $remark = $param['remark'];

        //获取管理员信息
        $admin = AuthService::getAuthModel();

        $admin_id = AuthService::getAuthModel()->getPrimaryValue();

        //检查参数
        if (empty($cost_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (1 != $is_check && 2 != $is_check) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '审核结果异常');
        }

        //获取费用单
        $field = 'status,worker_order_id,fee';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($cost_id, $field);
        $status = $info['status'];
        $worker_order_id = $info['worker_order_id'];
        $fee = $info['fee'];

        $field = 'origin_type,add_id,orno';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $origin_type = $order_info['origin_type'];
        $orno = $order_info['orno'];
        $add_id = $order_info['add_id'];

        //检查费用单
        if (CostService::STATUS_ADMIN_PASS == $status) {
            $this->throwException(ErrorCode::COST_CS_CHECKED);
        }
        if (CostService::STATUS_ADMIN_FORBIDDEN == $status) {
            $this->throwException(ErrorCode::COST_CS_FORBIDDEN);
        }
        if (CostService::STATUS_APPLY != $status) {
            $this->throwException(ErrorCode::COST_STATUS_ERROR);
        }

        //更新费用单
        $update_data = [
            'admin_id'           => $admin_id,
            'last_update_time'   => NOW_TIME,
            'admin_check_remark' => $remark,
            'admin_check_time'   => NOW_TIME,
        ];
        if (1 == $is_check) {
            $update_data['status'] = CostService::STATUS_ADMIN_PASS;
        } else {
            $update_data['status'] = CostService::STATUS_ADMIN_FORBIDDEN;
        }
        $where = ['status' => CostService::STATUS_APPLY, 'id' => $cost_id];
        $model->update($where, $update_data);

        //费用单日志
        $content = '审核费用单';
        $type = 0;
        $message_type = 0;
        if (1 == $is_check) {
            $content .= '(审核通过)';
            $type = CostRecordService::TYPE_CS_CHECKED;
            $message_type = AppMessageService::TYPE_WAIT_CHECK_MASSAGE;

            $sys_msg = "工单号{$orno}申请费用" . sprintf('%.2f', $fee) . "元";
            $sys_type = SystemMessageService::MSG_TYPE_FACTORY_COST_ADMIN_APPLY_PASS;

            $receiver_type = OrderService::ORIGIN_TYPE_FACTORY == $origin_type ? SystemMessageService::USER_TYPE_FACTORY : SystemMessageService::USER_TYPE_FACTORY_ADMIN;
            SystemMessageService::create($receiver_type, $add_id, $sys_msg, $cost_id, $sys_type);
        } else {
            $content .= '(审核不通过)';
            $type = CostRecordService::TYPE_CS_FORBIDDEN;
            $message_type = AppMessageService::TYPE_CHECK_NOT_PASS_MASSAGE;
        }
        CostRecordService::create($cost_id, $type, $content, $remark);

        event(new CostOrderCheckEvent(['type' => $message_type, 'data_id' => $cost_id]));
    }

    /**
     * 代厂家审核
     *
     * @param $param
     */
    public function pendingTrial($param)
    {
        $cost_id = $param['cost_id'];
        $is_check = $param['is_check'];
        $images = $param['img'];
        $remark = $param['remark'];

        if (empty($cost_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (1 != $is_check && 2 != $is_check) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '审核结果异常');
        }
        if (empty($images)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请上传厂家授权凭证');
        }

        if (count($images) > 3) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '图片不能超过3张');
        }

        //获取管理员信息
        $admin = AuthService::getAuthModel();

        //获取费用单
        $field = 'status,worker_order_id,fee';
        $model = BaseModel::getInstance($this->tableName);
        $info = $model->getOneOrFail($cost_id, $field);
        $worker_order_id = $info['$worker_order_id'];
        $status = $info['status'];
        $fee = $info['fee'];

        $field = 'distributor_id,orno';
        $order_model = BaseModel::getInstance('worker_order');
        $order_info = $order_model->getOneOrFail($worker_order_id, $field);
        $distributor_id = $order_info['distributor_id'];
        $orno = $order_info['orno'];

        //检查费用单
        if (CostService::STATUS_FACTORY_PASS == $status) {
            $this->throwException(ErrorCode::COST_FACTORY_CHECKED);
        }
        if (CostService::STATUS_FACTORY_FORBIDDEN == $status) {
            $this->throwException(ErrorCode::COST_FACTORY_FORBIDDEN);
        }
        if (CostService::STATUS_ADMIN_PASS != $status) {
            $this->throwException(ErrorCode::COST_STATUS_ERROR);
        }

        foreach ($images as $image) {
            $url = $image['url'];
            $remark .= "<img src='{$url}'>";
        }
        $update_data = [
            'factory_check_remark' => $remark,
            'last_update_time'     => NOW_TIME,
            'factory_check_time'   => NOW_TIME,
        ];

        if (1 == $is_check) {
            $update_data['status'] = CostService::STATUS_FACTORY_PASS;
        } else {
            $update_data['status'] = CostService::STATUS_FACTORY_FORBIDDEN;
        }
        $model->update($cost_id, $update_data);

        //费用单日志
        $content = '客服代厂家审核费用单';
        $type = 0;
        $message_type = 0;
        $sys_msg = '';
        $sys_type = 0;
        if (1 == $is_check) {
            $content .= '(审核通过)';
            $type = CostRecordService::TYPE_CS_ACT_FACTORY_CHECKED;
            $message_type = AppMessageService::TYPE_CHECK_PASS_MASSAGE;

            $sys_msg = "工单号{$orno}的费用申请厂家审核通过";
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_PASS;
        } else {
            $content .= '(审核不通过)';
            $type = CostRecordService::TYPE_CS_ACT_FACTORY_FORBIDDEN;
            $message_type = AppMessageService::TYPE_CHECK_NOT_PASS_MASSAGE;

            $sys_msg = "工单号{$orno}的配件申请厂家审核不通过";
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_COST_ADMIN_APPLY_FORBIDDEN;
        }
        CostRecordService::create($cost_id, $type, $content, $remark);

        event(new CostOrderCheckEvent(['type' => $message_type, 'data_id' => $cost_id]));

        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, $sys_msg, $cost_id, $sys_type);

        if (1 == $is_check) {
            //结算
            $fee_model = BaseModel::getInstance('worker_order_fee');
            $fee_info = $fee_model->getOneOrFail($worker_order_id, 'worker_cost_fee,factory_cost_fee');
            $worker_cost_fee = $fee_info['worker_cost_fee'] + $fee;
            $factory_cost_fee = $fee_info['factory_cost_fee'] + $fee;
            $update_data = [
                'worker_cost_fee'  => $worker_cost_fee,
                'factory_cost_fee' => $factory_cost_fee,
            ];
            OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id, $update_data);
        }
    }

    public function checkAllCompleted($worker_order_id)
    {
        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        $field = 'status';
        $model = BaseModel::getInstance($this->tableName);
        $where = ['worker_order_id' => $worker_order_id];
        $cost = $model->getList($where, $field);

        $ignore_status = [CostService::STATUS_ADMIN_FORBIDDEN, CostService::STATUS_FACTORY_FORBIDDEN];

        foreach ($cost as $val) {
            $status = $val['status'];
            if (in_array($status, $ignore_status)) {
                //审核不通过 忽略
                continue;
            }

            if (CostService::STATUS_FACTORY_PASS != $status) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分费用单尚未审核');
            }
        }

    }
}