<?php
/**
 * File: AdminController.class.php
 * User: xieguoqiu
 * Date: 2017/4/7 9:51
 */

namespace Admin\Controller;

use Admin\Logic\AdminLogic;
use Admin\Logic\SystemReceiveOrderCacheLogic;
use Admin\Repositories\Events\SystemReceiveOrderEvent;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminGroupCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\Service\AdminRoleService;
use Common\Common\CacheModel\CacheModel;
use Common\Common\Service\AdminService;
use Common\Common\Service\AuthService;
use Admin\Common\ErrorCode;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\ComplaintService;
use EasyWeChat\Payment\Order;
use Illuminate\Support\Arr;
use Library\Common\Util;
use Admin\Model\BaseModel;
use Admin\Logic\FactoryLogic;
use Common\Common\Service\FactoryMoneyRecordService;
use Common\Common\Service\AccessoryService;
use Think\Auth;
use Carbon\Carbon;

class AdminController extends BaseController
{

    const FACTORY_FEE_CONFIG_TABLE_NAME    = 'factory_fee_config_record';
    const ADMIN_TABLE_NAME                 = 'admin';
    const ORDER_TABLE_NAME                 = 'worker_order';
    const ORDER_RECORD_TABLE_NAME          = 'worker_order_operation_record';
    const ORDER_APPLY_ACCESSORY_TABLE_NAME = 'worker_order_apply_accessory';
    const ORDER_COMPLAINT_TABLE_NAME       = 'worker_order_complaint';

    public function getFactoryFeeConfig()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $fid = I('get.id', 0, 'intval');
            $field = '*,"" as admin_name';
            $where = [
                'factory_id' => $fid,
            ];
            $model = BaseModel::getInstance(self::FACTORY_FEE_CONFIG_TABLE_NAME);
            $count = $model->getNum($where);
            $list = $count ? $model->getList([
                'field' => $field,
                'where' => $where,
                'limit' => getPage(),
                'order' => 'create_time desc',
            ]) : [];

            $admin_ids = arrFieldForStr($list, 'admin_id');
            $admins = $admin_ids ? BaseModel::getInstance(self::ADMIN_TABLE_NAME)
                ->getList([
                    'field' => 'id,user_name',
                    'where' => [
                        'id' => ['in', $admin_ids],
                    ],
                    'index' => 'id',
                ]) : [];

            foreach ($list as $k => &$v) {
                $v['admin_name'] = $admins[$v['admin_id']]['user_name'] ?? '';
            }

            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryFeeConfig()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $fid = I('get.id', 0, 'intval');
            $update = I('post.');

            M()->startTrans();
            (new FactoryLogic())->updateFactoryFeeConfigByFid($fid, $update);
            M()->commit();

            $this->response(null);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryRechargeMoney()
    {
        $fid = I('get.id', 0, 'intval');
        $type = I('post.type', 0, 'intval');
        $amount = number_format(I('post.amount', '0.00'), 2, '.', '');
        $orno = htmlEntityDecode(I('post.orno', ''));
        $remark = htmlEntityDecode(I('post.remark', ''));
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            if (!$type || ($type == FactoryMoneyRecordService::CHANGE_TYPE_WORKER_ORDER_ADJUST && !$orno)) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }

            $amount == 0 && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写金额');
            $extends = [
                'remark' => $remark,
            ];

            M()->startTrans();
            FactoryMoneyRecordService::platformCreate($fid, $amount, $type, $orno, $extends);
            M()->commit();

            $this->response(null);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryRecharges()
    {
        $fid = I('get.factory_id', 0, 'intval');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $is_export = I('is_export', 0, 'intval');

            $list = null;
            $count = 0;
            $logic = new FactoryLogic();
            $logic->getRechargesPaginate($fid, $list, $count, $total_money);

            if (1 != $is_export) {
                $total_money = number_format($total_money, 2, '.', '');
                $this->paginate($list, $count, ['total_money' => $total_money]);
            }

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function all()
    {
        try {
            $this->requireAuth('admin');

            $where['state'] = 0;
            if ($name = I('name')) {
                $where['nickout'] = ['LIKE', "%{$name}%"];
            }
            $factories = BaseModel::getInstance('admin')->getList([
                'field' => 'id,nickout nickname',
                'where' => $where,
                'order' => 'id ASC',
            ]);

            return $this->responseList($factories);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getList()
    {
        $get = I('get.');
        $role_ids = I('get.role_ids', '');
        $group_ids = I('get.group_ids', '');
        $state = I('get.state', '');
        $nickout = I('get.real_name', '');
        $user_name = I('get.nickname', '');
        $tell = I('get.phone', '');
        $is_limit_ip = I('get.is_limit_ip', '');
        $exclude_admin_ids = I('get.exclude_admin_ids');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $where = [];

            $intersect = [];
            if (!empty($get['role_ids'])) {
                $arr_ids = BaseModel::getInstance('rel_admin_roles')->getList([
                    'field' => 'admin_id',
                    'where' => [
                        'admin_roles_id' => ['in', $role_ids],
                    ],
                    'index' => 'admin_id',
                ]);
                !$arr_ids && $this->paginate();
                $intersect[] = array_keys($arr_ids);
            }
            if (!empty($get['group_ids'])) {
                $group_ids = implode(',', array_unique(array_filter(explode(',', $group_ids))));
                $arr_ids = $group_ids ? BaseModel::getInstance('rel_admin_group')
                    ->getList([
                        'field' => 'admin_id',
                        'where' => [
                            'admin_group_id' => ['in', $group_ids],
                        ],
                        'index' => 'admin_id',
                    ]) : [];
                !$arr_ids && $this->paginate();
                $intersect[] = array_keys($arr_ids);
            }

            if ($intersect) {
                $admin_ids = reset($intersect);
                for ($i = 1; $i < count($intersect); $i++) {
                    $admin_ids = array_intersect($admin_ids, $intersect[$i]);
                }
                !$admin_ids && $this->paginate();
                $where['id'][] = ['in', implode(',', $admin_ids)];
            }

            //            isset($get['state']) && $where['state'] = $state;
            //            isset($get['real_name']) && $where['nickout'] = ['like', "%{$nickout}%"];
            //            isset($get['nickname']) && $where['user_name'] = ['like', "%{$user_name}%"];
            //            isset($get['phone']) && $where['tell'] = ['like', "%{$tell}%"];

            is_numeric($state) && $where['state'] = $state;
            is_numeric($is_limit_ip) && $where['is_limit_ip'] = $is_limit_ip;
            !empty($get['real_name']) && $where['nickout'] = ['like', "%{$nickout}%"];
            !empty($get['nickname']) && $where['user_name'] = ['like', "%{$user_name}%"];
            !empty($get['phone']) && $where['tell'] = ['like', "%{$tell}%"];
            !empty($get['exclude_admin_ids']) && $where['id'][] = ['not in', $exclude_admin_ids];

            $model = BaseModel::getInstance(AdminCacheModel::getTableName());
            $cnt = $model->getNum($where);
            !$cnt && $this->paginate();
            $admins = $model->getList([
                'field' => 'id,nickout nickname,user_name,tell,state,add_time,last_login_time,is_limit_ip',
                'where' => $where,
                'order' => 'add_time desc,id desc',
                'limit' => getPage(),
            ]);
            //            AdminCacheModel::insertAllAdminRoleRelation(193, []);
            if ($admins) {
                foreach ($admins as $k => $v) {
                    $admins[$k]['roles'] = null;
                    $admins[$k]['groups'] = null;
                    foreach (AdminCacheModel::getRelation($v['id'], 'rel_admin_roles', 'admin_id', 'admin_roles_id') as $role_v) {
                        $role = AdminRoleCacheModel::getOne($role_v, 'name,is_disable,is_delete');
                        if (!$role || $role['is_disable'] || $role['is_delete']) {
                            continue;
                        }
                        $admins[$k]['roles'][] = $role['name'];
                    }

                    foreach (AdminCacheModel::getRelation($v['id'], 'rel_admin_group', 'admin_id', 'admin_group_id') as $group_v) {
                        $group = AdminGroupCacheModel::getOne($group_v, 'name,is_disable,is_delete');
                        if (!$group || $group['is_disable'] || $group['is_delete']) {
                            continue;
                        }
                        $admins[$k]['groups'][] = $group['name'];
                    }
                }
            }

            $this->paginate($admins, $cnt);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getAvailableList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'name' => I('name', ''),
            ];

            $result = (new AdminLogic())->getAvailableList($param);

            $this->paginate($result['data'], $result['cnt']);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaAppliesExcelYima()
    {
        try {
            $token_id = $this->requireAuth(['admin', 'factory']);
            $data = BaseModel::getInstance('factory_excel')->getOneOrFail([
                'excel_id' => I('get.id', 0),
                'is_check' => 1,
            ]);
            $f_data = BaseModel::getInstance('factory')
                ->getOneOrFail($data['factory_id']);
            // $file_name = $f_data['factory_full_name'].'-易码导出'.$data['first_code'].'至'.$data['last_code'].'（'.date('Y-m-d H:i:s').'）';
            $file_name = $f_data['factory_full_name'] . '-易码导出' . $data['first_code'] . '至' . $data['last_code'] . '（' . date('Y.m.d-H：i') . '）';
            (new \Admin\Logic\YimaLogic())->excelYimaApplyYimasBetween($f_data, $file_name, $data['first_code'], $data['last_code']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function checkYimaApplies()
    {
        $id = I('get.id', 0);
        $check = I('put.check', 0);
        try {
            $this->requireAuth('admin');

            if (!in_array($check, [1, 2])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }
            $model = BaseModel::getInstance('factory_excel');
            $data = $model->getOneOrFail($id);
            if ($data['is_check'] != 0) {
                $this->throwException(ErrorCode::NOT_AGEN_CHECK);
            }

            $update = [
                'is_check'   => $check,
                'check_time' => NOW_TIME,
            ];
            if ($check == 1) {
                $check_data = $model->getOne([
                    'where' => [
                        'factory_id' => $data['factory_id'],
                        'is_check'   => 1,
                    ],
                    'order' => 'last_code DESC',
                ]);

                $qr_data = BaseModel::getInstance('factory_product_qrcode')
                    ->getOne([
                        'where' => [
                            'factory_id' => $data['factory_id'],
                        ],
                        'order' => 'qr_last_int DESC',
                    ]);

                if ($qr_data['qr_last_int'] > $check_data['last_code']) {
                    $last_code = $qr_data['qr_last_int'] ? $qr_data['last_code'] + 1 : C('YIMA_MIN_CODE');
                } else {
                    $last_code = $check_data['last_code'] ? $check_data['last_code'] + 1 : C('YIMA_MIN_CODE');
                }


                $update['first_code'] = $last_code;
                $update['last_code'] = $last_code + $data['nums'] - 1;
            }

            $model->update($id, $update);

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaAppliesCount()
    {
        $name = I('get.name', '', '');
        $phone = I('get.phone', '');
        $need_check = I('get.need_check', 0);
        $has_apply = I('get.has_apply', 0);
        try {
            $where = [];

            if (!empty($name)) {
                // $where['F.factory_short_name'] = ['like', "%{$name}%"];
                $where['F.factory_full_name'] = ['like', "%{$name}%"];
            }

            if (!empty($phone)) {
                $where['F.linkphone'] = ['like', "%{$phone}%"];
            }

            if ($need_check) {
                $where['FE.is_check'] = 0;
            }

            if (in_array($has_apply, [1, 2])) {
                switch ($has_apply) {
                    case 1:
                        $where['_string'] = 'FE.excel_id IS NOT NULL';
                        break;

                    case 2:
                        $where['_string'] = 'FE.excel_id IS NULL';
                        break;
                }
            }

            $model = BaseModel::getInstance('factory');
            $all = $model->getList([
                'alias' => 'F',
                'join'  => 'LEFT JOIN factory_excel FE ON FE.factory_id = F.factory_id',
                'field' => 'F.factory_id,COUNT(FE.excel_id) as has_apply',
                'where' => $where,
                'group' => 'F.factory_id',
            ]);

            $count = count($all);
            if (!$count) {
                $this->paginate();
            }

            // $field = 'F.factory_id,F.linkphone,F.factory_full_name,F.factory_short_name,SUM(IFNULL(FE.nums,0)) as all_apply_nums,IFNULL(MAX(FE.add_time),0) as last_add_time';
            // $list = $model->getList([
            //            'alias' => 'F',
            //            'join'  => 'LEFT JOIN factory_excel FE ON FE.factory_id = F.factory_id',
            //            'field' => $field,
            //            'where' => $where,
            //            'limit' => getPage(),
            //            'order' => 'factory_id DESC',
            //            'group' => 'factory_id',
            // 	]);
            $field = 'F.factory_id,F.linkphone,F.factory_full_name,F.factory_short_name,concat(F.factory_type,F.code) as yima_pre_code,SUM(IFNULL(FE.nums,0)) as all_apply_nums,IFNULL(MAX(FE.add_time),0) as last_add_time';
            $list = $model->getList([
                'alias' => 'F',
                'join'  => 'LEFT JOIN factory_excel FE ON FE.factory_id = F.factory_id',
                'field' => $field,
                'where' => $where,
                'limit' => getPage(),
                'order' => 'factory_id DESC',
                'group' => 'factory_id',
            ]);

            $yima_arr = $factory_bind_ids = $factory_ids = [];

            foreach ($list as $k => $v) {
                if ($v['all_apply_nums'] > 0) {
                    $yima_arr[factoryIdToModelName($v['factory_id'])][] = $v['factory_id'];
                    $factory_bind_ids[$v['factory_id']] = $v['factory_id'];
                }
                $factory_ids[$v['factory_id']] = $v['factory_id'];
            }
            $factory_ids = implode(',', array_filter($factory_ids));
            $factory_bind_ids = implode(',', array_filter($factory_bind_ids));

            $dealer = $factory_ids ? BaseModel::getInstance('factory_product_white_list')
                ->getList([
                    'field' => 'factory_id,COUNT(*) as dealer_all_nums,SUM(IF(status = 1,1,0)) as dealer_check_nums',
                    'where' => [
                        'factory_id' => ['in', $factory_ids],
                    ],
                    'group' => 'factory_id',
                    'index' => 'factory_id',
                ]) : [];

            $bind = $factory_bind_ids ? BaseModel::getInstance('factory_product_qrcode')
                ->getList([
                    'field' => 'factory_id,SUM(IF(product_id > 0,nums,0)) as bind_nums',
                    'where' => [
                        'factory_id' => ['in', $factory_bind_ids],
                    ],
                    'group' => 'factory_id',
                    'index' => 'factory_id',
                ]) : [];

            $register = [];
            foreach ($yima_arr as $k => $v) {
                $f_model = BaseModel::getInstance($k);
                $register += (array)$f_model->getList([
                    'field' => 'factory_id,SUM(IF(register_time > 0,1,0)) as register_nums',
                    'where' => [
                        'factory_id' => ['in', implode(',', $v)],
                    ],
                    'group' => 'factory_id',
                    'index' => 'factory_id',
                ]);
            }

            foreach ($list as $k => $v) {
                $v['bind_nums'] = number_format($bind[$v['factory_id']]['bind_nums'], 0, '.', '');
                $v['register_nums'] = number_format($register[$v['factory_id']]['register_nums'], 0, '.', '');
                $v['dealer_all_nums'] = number_format($dealer[$v['factory_id']]['dealer_all_nums'], 0, '.', '');
                $v['dealer_check_nums'] = number_format($dealer[$v['factory_id']]['dealer_check_nums'], 0, '.', '');
                $list[$k] = $v;
            }

            $this->paginate($list, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaAppliesDelete()
    {
        try {
            $token_id = $this->requireAuth(['admin']);
            $fid = I('get.id', 0, 'intval');
            $qids = I('get.qrcode_ids', '');

            (new \Admin\Logic\YimaLogic())->adminYimaAppliesDeleteByFidAndQrId($fid, $qids);
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function info()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $admin = AuthService::getAuthModel();
            $data = [
                'create_time'     => $admin['add_time'],
                'last_login_time' => $admin['last_login_time'],
                'nickout'         => $admin['nickout'],
                'user_name'       => $admin['user_name'],
                'tell'            => $admin['tell'],
                'tell_out'        => $admin['tell_out'],
            ];

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function infoById()
    {
        try {
            $id = I('get.id', 0);
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $admin = AdminCacheModel::getOneOrFail($id, 'id,user_name,nickout,tell,tell_out,thumb,state,add_time,last_login_time');
            $admin['thumb_url'] = $admin['thumb'] ? Util::getServerFileUrl($admin['thumb']) : '';
            $admin['role_ids'] = null;
            $admin['group_ids'] = null;

            $role_id_arr = AdminCacheModel::getRelation($id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
            foreach ($role_id_arr as $v) {
                $role = AdminRoleCacheModel::getOne($v, 'id,name,is_disable');
                if ($role['is_disable'] == 1) {
                    continue;
                }
                $admin['role_ids'][] = $role['id'];
            }

            $group_id_arr = AdminCacheModel::getRelation($id, 'rel_admin_group', 'admin_id', 'admin_group_id', 1);
            foreach ($group_id_arr as $v) {
                $role = AdminGroupCacheModel::getOne($v, 'id,name,is_disable,is_delete');
                if ($role['is_disable'] || $role['is_delete']) {
                    continue;
                }
                $admin['role_ids'][] = $role['id'];
            }

            $this->response($admin);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function editPassword()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $old_password = I('old');
            $new_password = I('new');

            if (strlen($old_password) <= 0 || strlen($new_password) <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $admin = AuthService::getAuthModel();
            $admin_id = $admin['id'];
            $admin_password = $admin['password'];

            if ($admin_password != $old_password) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '密码错误');
            }
            if ($old_password == $new_password) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '新旧密码相同');
            }

            $update_data = [
                'password' => $new_password,
            ];

            AdminCacheModel::update($admin_id, $update_data);
            //            BaseModel::getInstance('admin')->update($admin_id, $update_data);

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function resetOtherPassword()
    {
        try {
            $id = I('get.id', 0);
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $admin = AdminCacheModel::getOneOrFail($id, 'tell');
            $pwd = substr($admin['tell'], -6);
            AdminCacheModel::update($id, ['password' => md5($pwd)]);
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function adminKpiExport()
    {
        set_time_limit(0);
        $start_day = I('get.start_time', '');
        $end_day = I('get.end_time', '');
        try {
            $start = new Carbon($start_day);
            $end = new Carbon($end_day);
            $end->addDay();
            $between = ['between', ($start->timestamp) . ',' . ($end->timestamp - 1)];
            $where = [
                'worker_order_status' => OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
                'orno' => ['LIKE', 'A%'],
                'factory_audit_time'  => $between,
            ];
            $model = BaseModel::getInstance(self::ORDER_TABLE_NAME);

            $finished_list = $model->getList([
                'field' => 'id,service_type,checker_id,factory_check_order_time,check_time,distributor_id,distributor_receive_time,distribute_time,worker_repair_time,returnee_id,return_time,auditor_id,auditor_receive_time,audit_time,factory_audit_time',
                'where' => $where,
                'index' => 'id',
            ]);

            $order_ids = $repair_ids = [];
            foreach ($finished_list as $k => $v) {
                $order_ids[$v['id']] = $v['id'];
                if ($v['service_type'] == OrderService::TYPE_WORKER_REPAIR) {
                    $repair_ids[$v['id']] = $v['id'];
                }
            }
            $order_ids = implode(',', $order_ids);
            $repair_ids = implode(',', $repair_ids);
            //  有配件单的维修工单 (完单时效使用)  不确定 worker_order_statistcs 是否包括取消的配件 直接查明细就算了
            $has_acces = $repair_ids ? BaseModel::getInstance(self::ORDER_APPLY_ACCESSORY_TABLE_NAME)
                ->getList([
                    'field' => 'worker_order_id',
                    'where' => [
                        'worker_order_id' => ['in', $repair_ids],
                        'cancel_status'   => AccessoryService::CANCEL_STATUS_NORMAL,
                    ],
                    'group' => 'worker_order_id',
                    'index' => 'worker_order_id',
                ]) : [];

            $record_type_arr = [
                OrderOperationRecordService::FACTORY_ORDER_READD,
                OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE,
            ];
            $distribute_record = $order_ids ? BaseModel::getInstance(self::ORDER_RECORD_TABLE_NAME)
                ->getList([
                    'field' => 'worker_order_id,group_concat(concat(\'|\',operation_type,\'.\',create_time) order by create_time asc separator \'\') as records',
                    'where' => [
                        'worker_order_id' => ['in', $order_ids],
                        'operation_type'  => ['in', implode(',', $record_type_arr)],
                    ],
                    'group' => 'worker_order_id',
                    'index' => 'worker_order_id',
                ]) : [];

            if (I('get.is_debug', 0, 'intval') == 1) {
                $this->responseList(array_diff_key($finished_list, $distribute_record));
                die;
            }

            $cut = '|' . (OrderOperationRecordService::FACTORY_ORDER_READD) . '.';
            $distribute_diff_min_arr = $service_diff_hour_arr = [];
            foreach ($distribute_record as $k => $v) {
                // 派发数据 (第一次派单)
                $check_time = $finished_list[$k]['check_time'];
                $distribute_time = explode('.', explode('|', end(explode($cut, $v['records'])))[1])[1];
                $distribute_diff_min_arr[$k] = kpiTimeDiff($check_time, $distribute_time, 'min');
                // 完单数据
                $worker_repair_time = $finished_list[$k]['worker_repair_time'];
                $service_diff_hour_arr[$k] = kpiTimeDiff($distribute_time, $worker_repair_time, 'hour', false);
            }

            // 投诉单量
            // @PS 有重新下单的前后的时间问题：重新下单之前的客服id没办法回查。所以判断使用下单后的客服id（所以不需要使用操作记录来回查）
            $complaints = BaseModel::getInstance(self::ORDER_COMPLAINT_TABLE_NAME)
                ->getList([
                    'field' => 'worker_order_id,response_type_id,verify_time',
                    'where' => [
                        'response_type' => ComplaintService::TO_TYPE_CS,
                        'verify_time'   => $between,
                    ],
                    'group' => 'worker_order_id',
                ]);

            $complaint_order_ids = [];
            foreach ($complaints as $k => $v) {
                !$v['response_type_id'] && $complaint_order_ids[$v['worker_order_id']] = $v['worker_order_id'];
            }
            $complaint_order_ids = implode(',', $complaint_order_ids);
            $complaint_order = $complaint_order_ids ? BaseModel::getInstance(self::ORDER_TABLE_NAME)
                ->getList([
                    'field' => 'id,checker_id,distributor_id,returnee_id,distributor_receive_time,distribute_time,auditor_receive_time,audit_time',
                    'where' => [
                        'id' => ['in', $complaint_order_ids],
                    ],
                    'index' => 'id',
                ]) : [];
            $complaint_admins = [];
            foreach ($complaints as $v) {
                if ($v['response_type_id']) {
                    $complaint_admins[$v['response_type_id']][$v['worker_order_id']] = $v['worker_order_id'];
                    continue;
                }
                $times = $complaint_order[$v['worker_order_id']];
                $distributor_receive_time = $times['distributor_receive_time'] ?? $times['distribute_time'];
                $auditor_receive_time = $times['auditor_receive_time'] ?? $times['audit_time'];
                $response_type_id = 0;
                if ($v['verify_time'] < $distributor_receive_time) {
                    $response_type_id = $times['checker_id'];
                } elseif ($v['verify_time'] < $auditor_receive_time) {
                    $response_type_id = $times['distributor_id'];
                } else {
                    $response_type_id = $times['returnee_id'];
                }

                $complaint_admins[$response_type_id][$v['worker_order_id']] = $v['worker_order_id'];
            }

            // 取消单量
            $cancels = BaseModel::getInstance(self::ORDER_TABLE_NAME)->getList([
                'field' => 'id,checker_id,distributor_id,distributor_receive_time,distribute_time,returnee_receive_time,return_time,cancel_time,cancel_type,cancel_remark',
                'where' => [
                    'cancel_status' => OrderService::CANCEL_TYPE_CS,
                    'cancel_time'   => $between,
                ],
                'group' => 'id',
            ]);
            $cancel_admins = [];
            foreach ($cancels as $v) {
                $distributor_receive_time = $v['distributor_receive_time'] ?? $v['distribute_time'];
                $returnee_receive_time = $v['returnee_receive_time'] ?? $v['return_time'];
                $response_type_id = 0;
                if ($v['cancel_time'] < $distributor_receive_time || !$v['distributor_id']) {
                    $response_type_id = $v['checker_id'];
                    $cancel_admins[$response_type_id]['check'][] = $v['id'];
                    //                } elseif ($v['cancel_time'] < $returnee_receive_time) {
                    //                    $response_type_id = $v['distributor_id'];
                } else {
                    $response_type_id = $v['distributor_id'];
                    $cancel_admins[$response_type_id]['distribute'][$v['id']] = $v['id'];
                }
                $cancel_admins[$response_type_id]['worker_order_id'][$v['id']] = $v['id'];
                $cancel_admins[$response_type_id]['cancel_type'][$v['cancel_type']][$v['id']] = $v['id'];
            }

            $totals = [];
            // 总核实
            $check_totals = BaseModel::getInstance(self::ORDER_TABLE_NAME)
                ->getList([
                    'field' => 'checker_id,count(*) as nums',
                    'where' => [
                        'check_time' => $between,
                    ],
                    'group' => 'checker_id',
                    'index' => 'checker_id',
                ]);
            $distribute_totals = BaseModel::getInstance(self::ORDER_TABLE_NAME)
                ->getList([
                    'field' => 'distributor_id,count(*) as nums',
                    'where' => [
                        'distribute_time' => $between,
                    ],
                    'group' => 'distributor_id',
                    'index' => 'distributor_id',
                ]);
            // 回访
            $return_totals = BaseModel::getInstance(self::ORDER_TABLE_NAME)
                ->getList([
                    'field' => 'returnee_id,count(*) as nums',
                    'where' => [
                        'return_time' => $between,
                    ],
                    'group' => 'returnee_id',
                    'index' => 'returnee_id',
                ]);
            // 财务审核
            $audit_totals = BaseModel::getInstance(self::ORDER_TABLE_NAME)
                ->getList([
                    'field' => 'auditor_id,count(*) as nums',
                    'where' => [
                        'audit_time' => $between,
                    ],
                    'group' => 'auditor_id',
                    'index' => 'auditor_id',
                ]);

            $table_one = [];
            $demo_one = ['A' => ''];
            $i = 2;
            $nums = 75;
            $endnums = $i + $nums;
            for ($s = $i; $s < $endnums; $s++) {
                $demo_one[getExcelKeyStr($s)] = 0;
            }
            // 已完结单量必然有核实、派发、完单、回访
            foreach ($finished_list as $k => $v) {
                // 核实数据
                $set_data = $table_one[$v['checker_id']] ?? $demo_one;
                $check_diff_min = kpiTimeDiff($v['factory_check_order_time'], $v['check_time'], 'min');
                if ($check_diff_min > 90) {
                    $set_data['I'] += 1;
                } elseif ($check_diff_min > 30) {
                    $set_data['H'] += 1;
                } elseif ($check_diff_min > 20) {
                    $set_data['G'] += 1;
                } elseif ($check_diff_min > 15) {
                    $set_data['F'] += 1;
                } elseif ($check_diff_min > 10) {
                    $set_data['E'] += 1;
                } elseif ($check_diff_min > 5) {
                    $set_data['D'] += 1;
                } else {
                    $set_data['C'] += 1;
                }
                $set_data['B'] += 1;
                $table_one[$v['checker_id']] = $set_data;
                // ====================================================================================================================================
                // 派发数据
                $set_data = $table_one[$v['distributor_id']] ?? $demo_one;
                $distribute_diff_min = isset($distribute_diff_min_arr[$k]) ? $distribute_diff_min_arr[$k] : kpiTimeDiff($v['check_time'], $v['distribute_time'], 'min');
                if ($distribute_diff_min > 180) {
                    $set_data['Q'] += 1;
                } elseif ($distribute_diff_min > 60) {
                    $set_data['P'] += 1;
                } elseif ($distribute_diff_min > 45) {
                    $set_data['O'] += 1;
                } elseif ($distribute_diff_min > 35) {
                    $set_data['N'] += 1;
                } elseif ($distribute_diff_min > 25) {
                    $set_data['M'] += 1;
                } elseif ($distribute_diff_min > 15) {
                    $set_data['L'] += 1;
                } else {
                    $set_data['K'] += 1;
                }
                $set_data['J'] += 1;
                $table_one[$v['distributor_id']] = $set_data;
                // ====================================================================================================================================
                // 回访数据
                $returnee_diff_hour = kpiTimeDiff($v['worker_repair_time'], $v['return_time'], 'hour', false);
                // 完单数据
                $service_diff_hour = isset($service_diff_hour_arr[$k]) ? $service_diff_hour_arr[$k] : kpiTimeDiff($v['distribute_time'], $v['worker_repair_time'], 'hour', false);
                switch ($v['service_type']) {
                    case OrderService::TYPE_WORKER_REPAIR: // 上门维修
                        $set_data = $table_one[$v['returnee_id']] ?? $demo_one;
                        if ($returnee_diff_hour > 120) {
                            $set_data['BM'] += 1;
                        } elseif ($returnee_diff_hour > 96) {
                            $set_data['BL'] += 1;
                        } elseif ($returnee_diff_hour > 72) {
                            $set_data['BK'] += 1;
                        } elseif ($returnee_diff_hour > 48) {
                            $set_data['BJ'] += 1;
                        } else {
                            $set_data['BI'] += 1;
                        }
                        $set_data['BC'] += 1;
                        $table_one[$v['returnee_id']] = $set_data;
                        // ====================================================================================================================================
                        if (isset($has_acces[$v['id']])) { // 有配件维修单
                            $set_data = $table_one[$v['distributor_id']] ?? $demo_one;
                            if ($service_diff_hour > 216) {
                                $set_data['AD'] += 1;
                            } elseif ($service_diff_hour > 192) {
                                $set_data['AC'] += 1;
                            } elseif ($service_diff_hour > 168) {
                                $set_data['AB'] += 1;
                            } elseif ($service_diff_hour > 144) {
                                $set_data['AA'] += 1;
                            } elseif ($service_diff_hour > 120) {
                                $set_data['Z'] += 1;
                            } else {
                                $set_data['Y'] += 1;
                            }
                            $set_data['R'] += 1;
                            $table_one[$v['distributor_id']] = $set_data;
                        } else { // 无配件维修单
                            $set_data = $table_one[$v['distributor_id']] ?? $demo_one;
                            if ($service_diff_hour > 120) {
                                $set_data['X'] += 1;
                            } elseif ($service_diff_hour > 96) {
                                $set_data['W'] += 1;
                            } elseif ($service_diff_hour > 72) {
                                $set_data['V'] += 1;
                            } elseif ($service_diff_hour > 48) {
                                $set_data['U'] += 1;
                            } elseif ($service_diff_hour > 24) {
                                $set_data['T'] += 1;
                            } else {
                                $set_data['S'] += 1;
                            }
                            $set_data['R'] += 1;
                            $table_one[$v['distributor_id']] = $set_data;
                        }
                        break;
                    case OrderService::TYPE_WORKER_INSTALLATION: // 上门安装
                        $set_data = $table_one[$v['returnee_id']] ?? $demo_one;
                        if ($returnee_diff_hour > 96) {
                            $set_data['BH'] += 1;
                        } elseif ($returnee_diff_hour > 72) {
                            $set_data['BG'] += 1;
                        } elseif ($returnee_diff_hour > 48) {
                            $set_data['BF'] += 1;
                        } elseif ($returnee_diff_hour > 24) {
                            $set_data['BE'] += 1;
                        } else {
                            $set_data['BD'] += 1;
                        }
                        $set_data['BC'] += 1;
                        $table_one[$v['returnee_id']] = $set_data;
                        // ====================================================================================================================================
                        $set_data = $table_one[$v['distributor_id']] ?? $demo_one;
                        if ($service_diff_hour > 120) {
                            $set_data['AJ'] += 1;
                        } elseif ($service_diff_hour > 96) {
                            $set_data['AI'] += 1;
                        } elseif ($service_diff_hour > 72) {
                            $set_data['AH'] += 1;
                        } elseif ($service_diff_hour > 48) {
                            $set_data['AG'] += 1;
                        } elseif ($service_diff_hour > 24) {
                            $set_data['AF'] += 1;
                        } else {
                            $set_data['AE'] += 1;
                        }
                        $set_data['R'] += 1;
                        $table_one[$v['distributor_id']] = $set_data;
                        break;
                    case OrderService::TYPE_PRE_RELEASE_INSTALLATION: // 预发件安装
                        $set_data = $table_one[$v['returnee_id']] ?? $demo_one;
                        if ($returnee_diff_hour > 96) {
                            $set_data['BH'] += 1;
                        } elseif ($returnee_diff_hour > 72) {
                            $set_data['BG'] += 1;
                        } elseif ($returnee_diff_hour > 48) {
                            $set_data['BF'] += 1;
                        } elseif ($returnee_diff_hour > 24) {
                            $set_data['BE'] += 1;
                        } else {
                            $set_data['BD'] += 1;
                        }
                        $set_data['BC'] += 1;
                        $table_one[$v['returnee_id']] = $set_data;
                        // ====================================================================================================================================
                        $set_data = $table_one[$v['distributor_id']] ?? $demo_one;
                        if ($service_diff_hour > 144) {
                            $set_data['AP'] += 1;
                        } elseif ($service_diff_hour > 120) {
                            $set_data['AO'] += 1;
                        } elseif ($service_diff_hour > 96) {
                            $set_data['AN'] += 1;
                        } elseif ($service_diff_hour > 72) {
                            $set_data['AM'] += 1;
                        } elseif ($service_diff_hour > 48) {
                            $set_data['AL'] += 1;
                        } else {
                            $set_data['AK'] += 1;
                        }
                        $set_data['R'] += 1;
                        $table_one[$v['distributor_id']] = $set_data;
                        break;
                    case OrderService::TYPE_USER_SEND_FACTORY_REPAIR: // 用户送修
                        $set_data = $table_one[$v['returnee_id']] ?? $demo_one;
                        if ($returnee_diff_hour > 120) {
                            $set_data['BR'] += 1;
                        } elseif ($returnee_diff_hour > 96) {
                            $set_data['BQ'] += 1;
                        } elseif ($returnee_diff_hour > 72) {
                            $set_data['BP'] += 1;
                        } elseif ($returnee_diff_hour > 48) {
                            $set_data['BO'] += 1;
                        } else {
                            $set_data['BN'] += 1;
                        }
                        $set_data['BC'] += 1;
                        $table_one[$v['returnee_id']] = $set_data;
                        // ====================================================================================================================================
                        $set_data = $table_one[$v['distributor_id']] ?? $demo_one;
                        if ($service_diff_hour > 216) {
                            $set_data['AV'] += 1;
                        } elseif ($service_diff_hour > 192) {
                            $set_data['AU'] += 1;
                        } elseif ($service_diff_hour > 168) {
                            $set_data['AT'] += 1;
                        } elseif ($service_diff_hour > 144) {
                            $set_data['AS'] += 1;
                        } elseif ($service_diff_hour > 120) {
                            $set_data['AR'] += 1;
                        } else {
                            $set_data['AQ'] += 1;
                        }
                        $set_data['R'] += 1;
                        $table_one[$v['distributor_id']] = $set_data;
                        break;
                    case OrderService::TYPE_WORKER_MAINTENANCE: // 上门维护
                        $set_data = $table_one[$v['returnee_id']] ?? $demo_one;
                        if ($returnee_diff_hour > 120) {
                            $set_data['BR'] += 1;
                        } elseif ($returnee_diff_hour > 96) {
                            $set_data['BQ'] += 1;
                        } elseif ($returnee_diff_hour > 72) {
                            $set_data['BP'] += 1;
                        } elseif ($returnee_diff_hour > 48) {
                            $set_data['BO'] += 1;
                        } else {
                            $set_data['BN'] += 1;
                        }
                        $set_data['BC'] += 1;
                        $table_one[$v['returnee_id']] = $set_data;
                        // ====================================================================================================================================
                        $set_data = $table_one[$v['distributor_id']] ?? $demo_one;
                        if ($service_diff_hour > 120) {
                            $set_data['BB'] += 1;
                        } elseif ($service_diff_hour > 96) {
                            $set_data['BA'] += 1;
                        } elseif ($service_diff_hour > 72) {
                            $set_data['AZ'] += 1;
                        } elseif ($service_diff_hour > 48) {
                            $set_data['AY'] += 1;
                        } elseif ($service_diff_hour > 24) {
                            $set_data['AX'] += 1;
                        } else {
                            $set_data['AW'] += 1;
                        }
                        $set_data['R'] += 1;
                        $table_one[$v['distributor_id']] = $set_data;
                        break;
                }
            }

            foreach ($complaint_admins as $k => $v) {
                $set_data = $table_one[$k] ?? $demo_one;
                $set_data['BX'] = count($v);
                $table_one[$k] = $set_data;
            }


            $table_two = [];
            $demo_two = ['A' => ''];
            $i = 2;
            $nums = 11;
            $endnums = $i + $nums;
            for ($s = $i; $s < $endnums; $s++) {
                $demo_two[getExcelKeyStr($s)] = 0;
            }
            foreach ($check_totals as $k => $v) {
                $set_data = $table_two[$k] ?? $demo_two;
                $set_data['B'] = $v['nums'];
                $table_two[$k] = $set_data;
            }
            foreach ($distribute_totals as $k => $v) {
                $set_data = $table_two[$k] ?? $demo_two;
                $set_data['C'] = $v['nums'];
                $table_two[$k] = $set_data;
            }
            foreach ($return_totals as $k => $v) {
                $set_data = $table_two[$k] ?? $demo_two;
                $set_data['D'] = $v['nums'];
                $table_two[$k] = $set_data;
            }
            foreach ($audit_totals as $k => $v) {
                $set_data = $table_two[$k] ?? $demo_two;
                $set_data['E'] = $v['nums'];
                $table_two[$k] = $set_data;
            }
            foreach ($cancel_admins as $k => $v) {
                $set_data = $table_two[$k] ?? $demo_two;
                $set_data['F'] = count($v['worker_order_id']);
                $set_data['G'] = count($v['check']);
                $set_data['H'] = count($v['distribute']);
                $set_data['I'] = count($v['cancel_type'][1]);
                $set_data['J'] = count($v['cancel_type'][2]);
                $set_data['K'] = count($v['cancel_type'][3]);
                $set_data['L'] = count($v['cancel_type'][4]);
                $table_two[$k] = $set_data;
            }

            $admins = BaseModel::getInstance(self::ADMIN_TABLE_NAME)->getList([
                'field' => 'id,nickout',
                'where' => [],
                'index' => 'id',
            ]);
            $filePath = './Public/admin_KPI_export_demo.xls';
            $logic = new \Common\Common\Logic\ExportDataLogic($filePath);

            $index = 0;
            $row = 5;
            foreach ($table_one as $k => $v) {
                foreach ($v as $sk => $sv) {
                    $logic->objPHPExcel->setActiveSheetIndex($index)
                        ->setCellValue($sk . $row, $sv);
                    $logic->objPHPExcel->setActiveSheetIndex($index)
                        ->setCellValue('A' . $row, $admins[$k]['nickout']);
                }
                ++$row;
            }

            ++$index;

            $row = 3;
            foreach ($table_two as $k => $v) {
                foreach ($v as $sk => $sv) {
                    $logic->objPHPExcel->setActiveSheetIndex($index)
                        ->setCellValue($sk . $row, $sv);
                    $logic->objPHPExcel->setActiveSheetIndex($index)
                        ->setCellValue('A' . $row, $admins[$k]['nickout']);
                }
                ++$row;
            }

            $logic->putOut("客服KPI表格" . $start->toDateString() . "—" . $end->subDay()
                    ->toDateString());

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addHandle()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'tell'              => I('tell'),
                'tell_out'          => I('tell_out'),
                'nickout'           => I('nickout'),
                'user_name'         => I('user_name'),
                'thumb'             => I('thumb'),
                'state'             => I('state', 0, 'intval'),
                'role_ids'          => I('role_ids'),
                'group_ids'         => I('group_ids'),
                'is_auto_receive'   => I('is_auto_receive', 0, 'intval'),
                'receive_type'      => I('receive_type', 0, 'intval'),
                'max_receive_times' => I('max_receive_times', 0, 'intval'),
                'workdays'          => I('workdays'),
                'factory_ids'       => I('factory_ids'),
                'category_ids'      => I('category_ids'),
                'area_ids'          => I('area_ids'),
                'agent'             => I('agent'),
                'partner_ids'       => I('partner_ids'),
                'factory_group_ids' => I('factory_group_ids'),
                'is_limit_ip'       => I('is_limit_ip'),
            ];

            M()->startTrans();
            (new AdminLogic())->add($param);
            M()->commit();

            event(new SystemReceiveOrderEvent([]));

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function editHandle()
    {
//        $url = urldecode("id=708&user_name=ceshi%E5%AE%A2%E6%9C%8DF&nickout=%E8%B0%A2&tell=17324213109&tell_out=020&thumb=&state=0&last_login_time=1524105676&add_time=1523670473&agent=0114&is_limit_ip=0&group_names=&thumb_url=http%3A%2F%2Fntest.szlb.cc&role_ids%5B%5D=13&role_ids%5B%5D=14&role_ids%5B%5D=15&role_names%5B%5D=%E6%A0%B8%E5%AE%9E%E5%AE%A2%E6%9C%8D&role_names%5B%5D=%E6%B4%BE%E5%8D%95%E5%AE%A2%E6%9C%8D&role_names%5B%5D=%E5%9B%9E%E8%AE%BF%E5%AE%A2%E6%9C%8D&is_auto_receive=1&max_receive_times=100&receive_type=0&factory_ids=&category_ids=&area_ids%5B%5D=110000&area_ids%5B%5D=120000&area_ids%5B%5D=130000&area_ids%5B%5D=140000&area_ids%5B%5D=150000&area_ids%5B%5D=210000&area_ids%5B%5D=220000&area_ids%5B%5D=230000&area_ids%5B%5D=310000&area_ids%5B%5D=320000&area_ids%5B%5D=330000&area_ids%5B%5D=340000&area_ids%5B%5D=350000&area_ids%5B%5D=360000&area_ids%5B%5D=370000&area_ids%5B%5D=410000&area_ids%5B%5D=420000&area_ids%5B%5D=430000&area_ids%5B%5D=440000&area_ids%5B%5D=450000&area_ids%5B%5D=460000&area_ids%5B%5D=500000&area_ids%5B%5D=510000&area_ids%5B%5D=520000&area_ids%5B%5D=530000&area_ids%5B%5D=540000&area_ids%5B%5D=610000&area_ids%5B%5D=620000&area_ids%5B%5D=630000&area_ids%5B%5D=640000&area_ids%5B%5D=650000&area_ids%5B%5D=710000&area_ids%5B%5D=810000&area_ids%5B%5D=820000&workdays%5B%5D=0&workdays%5B%5D=4&workdays%5B%5D=5&workdays%5B%5D=6&factory_names=&category_names=&area_names%5B%5D=%E5%8C%97%E4%BA%AC&area_names%5B%5D=%E5%A4%A9%E6%B4%A5&area_names%5B%5D=%E6%B2%B3%E5%8C%97&area_names%5B%5D=%E5%B1%B1%E8%A5%BF&area_names%5B%5D=%E5%86%85%E8%92%99%E5%8F%A4&area_names%5B%5D=%E8%BE%BD%E5%AE%81&area_names%5B%5D=%E5%90%89%E6%9E%97&area_names%5B%5D=%E9%BB%91%E9%BE%99%E6%B1%9F&area_names%5B%5D=%E4%B8%8A%E6%B5%B7&area_names%5B%5D=%E6%B1%9F%E8%8B%8F&area_names%5B%5D=%E6%B5%99%E6%B1%9F&area_names%5B%5D=%E5%AE%89%E5%BE%BD&area_names%5B%5D=%E7%A6%8F%E5%BB%BA&area_names%5B%5D=%E6%B1%9F%E8%A5%BF&area_names%5B%5D=%E5%B1%B1%E4%B8%9C&area_names%5B%5D=%E6%B2%B3%E5%8D%97&area_names%5B%5D=%E6%B9%96%E5%8C%97&area_names%5B%5D=%E6%B9%96%E5%8D%97&area_names%5B%5D=%E5%B9%BF%E4%B8%9C&area_names%5B%5D=%E5%B9%BF%E8%A5%BF&area_names%5B%5D=%E6%B5%B7%E5%8D%97&area_names%5B%5D=%E9%87%8D%E5%BA%86&area_names%5B%5D=%E5%9B%9B%E5%B7%9D&area_names%5B%5D=%E8%B4%B5%E5%B7%9E&area_names%5B%5D=%E4%BA%91%E5%8D%97&area_names%5B%5D=%E8%A5%BF%E8%97%8F&area_names%5B%5D=%E9%99%95%E8%A5%BF&area_names%5B%5D=%E7%94%98%E8%82%83&area_names%5B%5D=%E9%9D%92%E6%B5%B7&area_names%5B%5D=%E5%AE%81%E5%A4%8F&area_names%5B%5D=%E6%96%B0%E7%96%86&area_names%5B%5D=%E5%8F%B0%E6%B9%BE&area_names%5B%5D=%E9%A6%99%E6%B8%AF&area_names%5B%5D=%E6%BE%B3%E9%97%A8&partner_ids%5B%5D=706&partner_names%5B%5D=%E8%B0%A2&factory_group_ids%5B%5D=1&factory_group_ids%5B%5D=5&factory_group_names%5B%5D=B%E7%BB%84&factory_group_names%5B%5D=F%E7%BB%84");
//        $url = str_replace('&', "\n", $url);
//        $url = str_replace('=', ":", $url);
//        die($url);
        try {
            $admin_id = $this->requireAuth([AuthService::ROLE_ADMIN]);

            $admin_roles_id = BaseModel::getInstance('rel_admin_roles')->getFieldVal(['admin_id' => $admin_id], 'admin_roles_id', true);
            $level = BaseModel::getInstance('admin_roles')->getFieldVal(['id' => ['IN', $admin_roles_id]], 'level', true);  //查出角色级别
            $level_set_info_arr = [
                3 => [
                    'is_auto_receive',
                    'max_receive_times',
                    'workdays',
                    'partner_ids'
                ],
                2 => [
                    'tell',
                    'tell_out',
                    'nickout',
                    'user_name',
                    'thumb',
                    'state',
                    'role_ids',
                    'group_ids',
                    'is_auto_receive',
                    'receive_type',
                    'max_receive_times',
                    'workdays',
                    'factory_ids',
                    'category_ids',
                    'area_ids',
                    'agent',
                    'partner_ids',
                    'factory_group_ids',
                    'is_limit_ip'
                ],
            ];
//            if (!in_array('2', $level) && !in_array('3', $level)) {
//                $this->throwException(ErrorCode::SYS_NOT_POWER, '当前操作客服的角色级别没有此权限');
//            }

            $levels = array_intersect($level, array_keys($level_set_info_arr));

            !$levels && $this->fail(ErrorCode::SYS_NOT_POWER, '当前操作客服的角色级别没有此权限');

            $put = [
                'tell'              => I('tell'),
                'tell_out'          => I('tell_out'),
                'nickout'           => I('nickout'),
                'user_name'         => I('user_name'),
                'thumb'             => I('thumb'),
                'state'             => I('state', 0, 'intval'),
                'role_ids'          => I('role_ids'),
                'group_ids'         => I('group_ids'),
                'is_auto_receive'   => I('is_auto_receive', 0, 'intval'),
                'receive_type'      => I('receive_type', 0, 'intval'),
                'max_receive_times' => I('max_receive_times', 0, 'intval'),
                'workdays'          => I('workdays'),
                'factory_ids'       => I('factory_ids'),
                'category_ids'      => I('category_ids'),
                'area_ids'          => I('area_ids'),
                'agent'             => I('agent'),
                'partner_ids'       => I('partner_ids'),
                'factory_group_ids' => I('factory_group_ids'),
                'is_limit_ip'       => I('is_limit_ip'),
            ];

            $param = [
                'id' => I('get.id', 0),
            ];
            foreach ($levels as $v) {
                $edit_field_key = (array)array_flip($level_set_info_arr[$v]);
                $param += array_intersect_key($put, $edit_field_key);
            }


            $config = [];
//            if (isset($param['is_auto_receive'])) {
//                $config = [];
//            } else {
//                if (isset($param['max_receive_times'])) {
//
//                }
//                if (isset($param['workdays'])) {
//
//                }
//                if (isset($param['partner_ids'])) {
//
//                }
//
//            }

            //当是组长的时候，改变接收的参数
//            if (!in_array('2', $level) && in_array('3', $level)) {
//                $id = I('get.id');
//                $param = $this->roleIsGroup($id);
//            }

            M()->startTrans();
            (new AdminLogic())->edit($param, $config);
            M()->commit();

            event(new SystemReceiveOrderEvent([]));

            $this->okNull();
        } catch (\Exception $e) {
            //echo $e->getTraceAsString();
            $this->getExceptionError($e);
        }
    }

    public function adminInfo()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'id' => I('get.id'),
            ];
            $data = (new AdminLogic())->info($param);

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function importAdminExcelConfig()
    {
        try {
            $file_info = Util::upload();

            $objPHPExcelReader = \PHPExcel_IOFactory::load($file_info['file_path']);

            $sheet = $objPHPExcelReader->getSheet();


            $admin_data = [];
            $highest_row = $sheet->getHighestRow();
            for ($i = 2; $i <= $highest_row; ++$i) {
                $admin_data[] = $sheet->rangeToArray('A' . $i . ':N' . $i, null, true, false)[0];
            }

            $admin_group_name_id_map = BaseModel::getInstance('admin_group')
                ->getFieldVal([], 'name,id', true);
            $admin_role_name_id_map = BaseModel::getInstance('admin_roles')
                ->getFieldVal([], 'name,id', true);
            $admin_phone_id_map = BaseModel::getInstance('admin')
                ->getFieldVal([], 'tell,id', true);

            M()->startTrans();
            foreach ($admin_data as $item) {
                if ($item[5]) {
                    BaseModel::getInstance('rel_admin_group')
                        ->insert(['admin_id' => $admin_phone_id_map[$item[3]], 'admin_group_id' => $admin_group_name_id_map[$item[5]]]);
                }
                if ($item[6]) {
                    $roles = explode('/', $item[6]);
                    foreach ($roles as $role) {
                        BaseModel::getInstance('rel_admin_roles')->insert([
                            'admin_id'       => $admin_phone_id_map[$item[3]],
                            'admin_roles_id' => $admin_role_name_id_map[$role],
                        ]);
                    }
                }
            }
            M()->commit();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function roleIsGroup($id)
    {
        //查出当前修改客服的基本信息
        $admin_info = BaseModel::getInstance('admin')->getOne($id);
        $admin_role_ids = BaseModel::getInstance('rel_admin_roles')->getFieldVal(['admin_id' => $id], 'admin_roles_id', true);
        $admin_group_ids = BaseModel::getInstance('rel_admin_group')->getFieldVal(['admin_id' => $id], 'admin_group_id', true);
        $admin_receive_type = BaseModel::getInstance('admin_config_receive')->getOne($id);
        $admin_factory_ids = BaseModel::getInstance('admin_config_receive_factory')->getFieldVal(['admin_id' => $id], 'factory_id', true);
        $admin_category_ids = BaseModel::getInstance('admin_config_receive_category')->getFieldVal(['admin_id' => $id], 'category_id', true);
        $admin_area_ids = BaseModel::getInstance('admin_config_receive_area')->getFieldVal(['admin_id' => $id], 'area_id', true);
        $admin_factory_group_ids = BaseModel::getInstance('admin_config_receive_factory_group')->getFieldVal(['admin_id' => $id], 'group_id', true);
        $param = [  //角色级别是3组长时候
            'tell'              => $admin_info['tell'],
            'tell_out'          => $admin_info['tell_out'],
            'nickout'           => $admin_info['nickout'],
            'user_name'         => $admin_info['user_name'],
            'thumb'             => $admin_info['thumb'],
            'state'             => $admin_info['state'],
            'role_ids'          => $admin_role_ids,
            'group_ids'         => $admin_group_ids,
            'is_auto_receive'   => I('is_auto_receive', 0, 'intval'), //不改
            'receive_type'      => $admin_receive_type['type'],
            'max_receive_times' => I('max_receive_times', 0, 'intval'),  //不改
            'workdays'          => I('workdays'), //不改
            'factory_ids'       => $admin_factory_ids,
            'category_ids'      => $admin_category_ids,
            'area_ids'          => $admin_area_ids,
            'agent'             => $admin_info['agent'],
            'partner_ids'       => I('partner_ids'),  //不改
            'factory_group_ids' => $admin_factory_group_ids,
            'is_limit_ip'       => $admin_info['is_limit_ip'],
        ];

        return $param;
    }

}
