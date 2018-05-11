<?php
/**
 * File: OrderContactController.class.php
 * User: sakura
 * Date: 2017/11/15
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderContactService;
use Common\Common\Service\WorkerService;
use Library\Common\Util;


class OrderContactLogic extends BaseLogic
{

    protected $tableName = 'worker_contact_record';

    public function getList($param)
    {
        $admin_name = $param['admin_name'];
        $worker_name = $param['worker_name'];
        $worker_phone = $param['worker_phone'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $limit = $param['limit'];
        $is_export = $param['is_export'];

        $model = BaseModel::getInstance($this->tableName);

        $where = [];
        if (!empty($admin_name)) {
            $where['admin_id'] = ['exp', "in (select id from admin where nickout like '%{$admin_name}%')"];
        }
        if (!empty($worker_name)) {
            $where['worker_id'][] = ['exp', "in (select worker_id from worker where nickname like '%{$worker_name}%')"];
        }
        if (!empty($worker_phone)) {
            $where['worker_id'][] = ['exp', "in (select worker_id from worker where worker_telephone like '%{$worker_phone}%')"];
        }
        if ($date_from > 0) {
            $where['create_time'][] = ['egt', $date_from];
        }
        if ($date_to > 0) {
            $where['create_time'][] = ['lt', $date_to];
        }

        if (1 == $is_export) {
            $export_opts = ['where' => $where];
            (new ExportLogic())->adminWorkerContact($export_opts);
        } else {
            $cnt = $model->getNum($where);

            $field = 'id,admin_id,worker_id,contact_method,contact_type,contact_result, contact_report,contact_remark,create_time';
            $opts = [
                'field' => $field,
                'where' => $where,
                'order' => 'id desc',
                'limit' => $limit,
            ];
            $list = $model->getList($opts);

            $admin_ids = [];
            $worker_ids = [];
            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];

                $admin_ids[] = $admin_id;
                $worker_ids[] = $worker_id;
            }

            $workers = $this->getWorkers($worker_ids);
            $admins = $this->getAdmins($admin_ids);

            foreach ($list as $key => $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];

                $val['admin'] = $admins[$admin_id]?? null;
                $val['worker'] = $workers[$worker_id]?? null;

                $list[$key] = $val;
            }

            return [
                'data' => $list,
                'cnt'  => $cnt,
            ];
        }
    }

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,nickout,role_id';
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

        $filed = 'worker_id,nickname';
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

    public function add($param)
    {
        //获取参数
        $worker_id = $param['worker_id'];
        $contact_method = $param['contact_method'];
        $contact_type = $param['contact_type'];
        $contact_result = $param['contact_result'];
        $contact_report = $param['contact_report'];
        $contact_remark = $param['contact_remark'];
        $contact_object = $param['contact_object'];
        $contact_object_other = $param['contact_object_other'];
        $worker_order_id = $param['worker_order_id'];

        //检查参数
        if (empty($worker_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        $valid_method = [OrderContactService::METHOD_PHONE, OrderContactService::METHOD_QQ, OrderContactService::METHOD_SMS, OrderContactService::METHOD_WEIXIN];
        if (!in_array($contact_method, $valid_method)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '联系方式异常');
        }
        $valid_type = [OrderContactService::TYPE_DISTRIBUTE_CONSULT, OrderContactService::TYPE_OFFER, OrderContactService::TYPE_OTHER, OrderContactService::TYPE_ROUTINE, OrderContactService::TYPE_SEARCH_WEBSITE, OrderContactService::TYPE_TECHNOLOGY_CONSULT];
        if (!in_array($contact_type, $valid_type)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '联系类型异常');
        }
        $valid_result = [OrderContactService::RESULT_NOT_PASS, OrderContactService::RESULT_OTHER, OrderContactService::RESULT_PASS];
        if (!in_array($contact_result, $valid_result)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '联系结果异常');
        }
        $valid_report = [OrderContactService::REPORT_GIVE_UP, OrderContactService::REPORT_OTHER, OrderContactService::REPORT_PASS, OrderContactService::REPORT_THINK];
        if (!in_array($contact_report, $valid_report)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服评估异常');
        }

        if (strlen($contact_object) > 0) {
            $valid_object = [OrderContactService::OBJECT_TYPE_OTHER,OrderContactService::OBJECT_TYPE_WORKER,OrderContactService::OBJECT_TYPE_SHOPKEEPER,OrderContactService::OBJECT_TYPE_SHOPKEEPER_AND_WORKER,OrderContactService::OBJECT_TYPE_BUSINESS,OrderContactService::OBJECT_TYPE_VENDOR,OrderContactService::OBJECT_TYPE_VENDOR_AND_WORKER,];
            if (!in_array($contact_object, $valid_object)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '账户类型异常');
            }
            if (
                OrderContactService::OBJECT_TYPE_OTHER == $contact_object &&
                strlen($contact_object_other) <= 0
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '账户属于其他需要补充信息');
            }
        }

        //获取客服
        $admin = AuthService::getAuthModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();
        $role_id = $admin['role_id'];

//        $root_role = AdminRoleService::getRoleRoot();
//        $admin_role = AdminRoleService::getRoleAdminRoot();
//        $distributor = AdminRoleService::getRoleDistributor();
//        $returnee = AdminRoleService::getRoleReturnee();
//        $channel = AdminRoleService::getRoleChannel();
//
//        //权限
//        $valid_role = array_merge($returnee, $distributor, $admin_role, $root_role, $channel);
//        if (!in_array($role_id, $valid_role)) {
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户没有权限');
//        }

        $model = BaseModel::getInstance($this->tableName);

        $insert_data = [
            'admin_id'             => $admin_id,
            'worker_id'            => $worker_id,
            'contact_method'       => $contact_method,
            'contact_type'         => $contact_type,
            'contact_result'       => $contact_result,
            'contact_report'       => $contact_report,
            'contact_remark'       => $contact_remark,
            'create_time'          => NOW_TIME,
            'contact_object'       => $contact_object,
            'contact_object_other' => $contact_object_other,
            'worker_order_id'      => $worker_order_id,
        ];
        $model->insert($insert_data);
    }

    /**
     * 添加记录兼注册技工
     *
     * @param $param
     */
    public function addAndRegister($param)
    {
        //获取参数
        $phone = $param['phone'];
        $user_name = $param['user_name'];
        $province_id = $param['province_id'];
        $city_id = $param['city_id'];
        $district_id = $param['district_id'];
        $address = $param['address'];

        //检查参数
        if (empty($phone) || empty($user_name)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号格式错误');
        }
        if (
            empty($province_id) ||
            empty($city_id) ||
            empty($address)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '地址信息为空');
        }

        //查找技工
        $model = BaseModel::getInstance('worker');
        $field = 'worker_id';
        $where = ['worker_telephone' => $phone];
        $worker_info = $model->getOne($where, $field);
        $worker_id = 0;

        if (empty($worker_info)) {
            //不存在就注册
            $area_ids = [$province_id, $city_id, $district_id];
            $area_list = AreaService::getAreaNameMapByIds($area_ids);
            $worker_address = '';
            foreach ($area_list as $val) {
                $worker_address .= $val['name'] . '-';
            }
            $worker_address = rtrim($worker_address, '-');

            $insert_data = [
                'worker_telephone'      => $phone,
                'add_time'              => NOW_TIME,
                'is_check'              => WorkerService::CHECK_PASS,
                'is_qianzai'            => WorkerService::IDENTIFY_POTENTIAL,
                'is_complete_info'      => WorkerService::DATA_NOT_PASS,
                'nickname'              => $user_name,
                'worker_area_ids'       => implode(',', $area_ids),
                'worker_area_id'        => $district_id,
                'worker_address'        => $worker_address,
                'worker_detail_address' => $address,
            ];
            $worker_id = $model->insert($insert_data);
        } else {
            $worker_id = $worker_info['worker_id'];
        }

        $param['worker_id'] = $worker_id;
        $this->add($param);
    }

    public function history($param)
    {
        $worker_id = $param['worker_id'];
        $limit = $param['limit'];

        if (empty($worker_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $where = ['worker_id' => $worker_id];

        $filed = 'id,create_time,admin_id,contact_method,contact_type,contact_result,contact_report,contact_remark';
        $opts = [
            'field' => $filed,
            'where' => $where,
            'order' => 'id desc',
            'limit' => $limit,
        ];
        $model = BaseModel::getInstance($this->tableName);

        $list = $model->getList($opts);
        $cnt = $model->getNum($opts);

        $admin_ids = [];
        foreach ($list as $val) {
            $admin_id = $val['admin_id'];

            $admin_ids[] = $admin_id;
        }

        $admins = $this->getAdmins($admin_ids);

        foreach ($list as $key => $val) {
            $admin_id = $val['admin_id'];

            $val['admin'] = $admins[$admin_id]?? null;

            $list[$key] = $val;
        }

        return [
            'data' => $list,
            'cnt'  => $cnt,
        ];
    }
}