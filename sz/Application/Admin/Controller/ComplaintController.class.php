<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/18
 * Time: 20:57
 */

namespace Admin\Controller;

use Admin\Logic\AdminGroupLogic;
use Admin\Common\ErrorCode;
use Admin\Logic\ComplaintLogic;
use Admin\Logic\ExportLogic;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\ComplaintService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SystemMessageService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class ComplaintController extends BaseController
{
    public function getList()
    {
        try {
            $this->requireAuth();
            $admin = AuthService::getAuthModel();

            $where = [];
            if (AuthService::getModel() != AuthService::ROLE_ADMIN) {
                $where['complaint_create_type'] = ['IN', [ComplaintService::CREATE_TYPE_FACTORY, ComplaintService::CREATE_TYPE_FACTORY_ADMIN]];
                //$where['complaint_from_id'] = AuthService::getAuthModel()->getPrimaryValue();
            }

            if ($orno = I('orno')) {
                $worker_order_id = BaseModel::getInstance('worker_order')
                    ->getFieldVal(['orno' => $orno], 'id');
                $where['worker_order_id'] = $worker_order_id;
            }
            if ($contact_name = I('contact_name')) {
                $where['contact_name'] = ['LIKE', "%{$contact_name}%"];
            }
            if ($contact_tell = I('contact_tell')) {
                $where['contact_tell'] = ['LIKE', "%{$contact_tell}%"];
            }

            $where_string = [];
            $factory_group_ids = I('factory_group_ids');
            $factory_group_ids = Util::filterIdList($factory_group_ids);
            if (!empty($factory_group_ids)) {
                // 厂家组别
                $factory_ids = BaseModel::getInstance('factory')
                    ->getFieldVal(['group_id' => ['IN', $factory_group_ids]], 'factory_id', true);
                $factory_ids = empty($factory_ids) ? '-1': implode(',', $factory_ids);

                $factory_admin_ids = BaseModel::getInstance('factory_admin')
                    ->getFieldVal([
                        'factory_id' => ['in', $factory_ids],
                    ], 'id', true);
                $factory_admin_ids = empty($factory_admin_ids) ? '-1': implode(',', $factory_admin_ids);

                $where_string[] = "( (complaint_from_type = ".ComplaintService::FROM_TYPE_FACTORY." AND complaint_from_id IN ({$factory_ids})) or (complaint_from_type = ".ComplaintService::FROM_TYPE_FACTORY_ADMIN." AND complaint_from_id IN ({$factory_admin_ids}))) ";
            }

            $factory_ids = I('factory_ids');
            $factory_ids = Util::filterIdList($factory_ids);
            if (!empty($factory_ids)) {
                $factory_admin_ids = BaseModel::getInstance('factory_admin')
                    ->getFieldVal([
                        'factory_id' => ['in', $factory_ids],
                    ], 'id', true);
                $factory_admin_ids = empty($factory_admin_ids) ? '-1': implode(',', $factory_admin_ids);
                $factory_ids = empty($factory_ids) ? '-1': implode(',', $factory_ids);

                $where_string[] = "( (complaint_from_type = ".ComplaintService::FROM_TYPE_FACTORY." AND complaint_from_id IN ({$factory_ids})) or (complaint_from_type = ".ComplaintService::FROM_TYPE_FACTORY_ADMIN." AND complaint_from_id IN ({$factory_admin_ids}))) ";
            }

            $worker_name = I('worker_name');
            $worker_phone = I('worker_phone');
            if ($worker_name || $worker_phone) {
                $worker_where = [];
                $worker_name && $worker_where['nickname'] = ['LIKE', "%{$worker_name}%"];
                $worker_phone && $worker_where['worker_telephone'] = ['LIKE', "%{$worker_phone}%"];
                $worker_ids = BaseModel::getInstance('worker')
                    ->getFieldVal($worker_where, 'worker_id', true);
                $worker_ids = implode(',', $worker_ids) ?: 0;
                $complaint_type = ComplaintService::FROM_TYPE_WORKER;
                $where_string[] = " (complaint_from_type = {$complaint_type} AND complaint_from_id IN ({$worker_ids})) ";
            }

            if ($complaint_time_from = I('complaint_time_from')) {
                $where['create_time'][] = ['EGT', $complaint_time_from];
            }

            if ($complaint_time_to = I('complaint_time_to')) {
                $where['create_time'][] = ['LT', $complaint_time_to];
            }

            if ($reply_status = I('reply_status')) {
                if ($reply_status == 1) {
                    $where['reply_time'] = 0;
                } elseif ($reply_status == 2) {
                    $where['reply_time'] = ['GT', 0];
                }
            }

            if ($verify_status = I('verify_status')) {
                if ($verify_status == 1) {
                    $where['reply_time'] = ['GT', 0];
                    $where['is_satisfy'] = 0;
                } elseif ($verify_status == 2) {
                    $where['is_satisfy'] = ['GT', 0];
                }
            }

            $admin_ids = I('admin_ids');
            $admin_ids = Util::filterIdList($admin_ids);
            if (!empty($admin_ids)) {
                $where['replier_id'] = ['IN', $admin_ids];
            } else {
                $admin_group_id = I('admin_group_id');
                $group_admin_ids = (new AdminGroupLogic())->getManageGroupAdmins($admin_group_id ? [$admin_group_id] : []);
                $group_admin_ids && $where['replier_id'] = ['in', $group_admin_ids];
            }
            if ($response_type = I('response_type')) {
                if ($response_type == ComplaintService::RESPONSE_TYPE_FACTORY) {
                    $where['response_type'] = ['IN', [ComplaintService::RESPONSE_TYPE_FACTORY, ComplaintService::RESPONSE_TYPE_FACTORY_ADMIN]];
                } else {
                    $where['response_type'] = $response_type;
                }
            }
            $where_string && $where['_string'] = implode(' AND ', $where_string);

            $is_export = I('is_export', 0, 'intval');
            if (1 == $is_export) {
                $export_opts = ['where' => $where];
                (new ExportLogic())->adminComplaint($export_opts);
            } else {
                $complaints = BaseModel::getInstance('worker_order_complaint')
                    ->getList([
                        'where' => $where,
                        'field' => 'id,worker_order_id,replier_id,complaint_type_id,complaint_number,complaint_from_id,complaint_from_type,complaint_to_id,complaint_to_type,response_type,response_type_id,content,reply_result,create_time,reply_time,contact_name,contact_tell,verify_time,is_satisfy,is_prompt_complaint_to',
                        'order' => 'id DESC',
                        'limit' => getPage(),
                    ]);

                $admin_ids = Arr::pluck($complaints, 'replier_id') ?: '0';
                $field = 'id,user_name';
                if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
                    $field = 'id,nickout';
                }
                $admin_id_name_map = BaseModel::getInstance('admin')
                    ->getFieldVal(['id' => ['IN', $admin_ids]], $field, true);
                $worker_order_ids = Arr::pluck($complaints, 'worker_order_id') ?: '-1';
                $worker_order_id_orno_map = BaseModel::getInstance('worker_order')
                    ->getList([
                        'where' => ['id' => ['IN', $worker_order_ids]],
                        'field' => 'id,orno,children_worker_id',
                        'index' => 'id',
                    ]);

                $children_worker_ids = array_column($worker_order_id_orno_map, 'children_worker_id');
                $children_worker_ids = array_unique(array_filter($children_worker_ids));
                $children_worker_ids = empty($children_worker_ids) ? ['-1'] : $children_worker_ids;

                $children_workers = BaseModel::getInstance('worker')->getList([
                    'where' => ['worker_id' => ['in', $children_worker_ids]],
                    'field' => 'worker_id,worker_telephone,nickname',
                    'index' => 'worker_id',
                ]);

                foreach ($complaints as $key => $complaint) {
                    $worker_order_id = $complaint['worker_order_id'];

                    $complaint['replier_name'] = $admin_id_name_map[$complaint['replier_id']] ?? '';
                    $complaint['orno'] = $worker_order_id_orno_map[$worker_order_id]['orno'];
                    $children_worker_id = $worker_order_id_orno_map[$worker_order_id]['children_worker_id'];

                    $complaint['children_worker'] = null;

                    if (!empty($children_workers[$children_worker_id])) {
                        $complaint['children_worker'] = [
                            'name'  => $children_workers[$children_worker_id]['nickname'],
                            'phone' => $children_workers[$children_worker_id]['worker_telephone'],
                        ];
                    }


                    $complaints[$key] = $complaint;
                }
                BaseModel::getInstance('complaint_type')
                    ->attachField2List($complaints, 'id,name', [], 'complaint_type_id');

                //            BaseModel::getInstance('admin')->attachMany2List($complaints, 'id', ['id', 'user_name'], [], 'replier_id', 'admin');

                ComplaintService::loadComplaintFromUser($complaints);
                ComplaintService::loadComplaintToUser($complaints);
                ComplaintService::loadComplaintResponseUser($complaints);

                $num = BaseModel::getInstance('worker_order_complaint')
                    ->getNum($where);

                $this->paginate($complaints, $num);
            }


        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function info()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $admin = AuthService::getAuthModel();
            $id = I('id');
            $complaint = BaseModel::getInstance('worker_order_complaint')
                ->getOneOrFail($id, 'id,worker_order_id,replier_id,complaint_type_id,complaint_number,complaint_to_id,complaint_to_type,response_type,response_type_id,content,reply_result,create_time,reply_time,is_satisfy,verify_remark,contact_name,contact_tell,verify_time,verifier_id,is_satisfy,is_prompt_complaint_to');
            $complaint['complaint_type_name'] = BaseModel::getInstance('complaint_type')
                ->getFieldVal($complaint['complaint_type_id'], 'name');
            $complaint['replier_name'] = $complaint['replier_id'] ? BaseModel::getInstance('admin')
                ->getFieldVal($complaint['replier_id'], 'nickout') : '';
            $complaint['verifier_name'] = $complaint['verifier_id'] ? BaseModel::getInstance('admin')
                ->getFieldVal($complaint['verifier_id'], 'nickout') : '';
            $complaints = [&$complaint];
            //            ComplaintService::loadComplaintFromUser($complaints);
            ComplaintService::loadComplaintToUser($complaints);
            ComplaintService::loadComplaintResponseUser($complaints);
            $order = BaseModel::getInstance('worker_order')
                ->getOneOrFail($complaint['worker_order_id'], 'orno,children_worker_id');
            $complaint['orno'] = $order['orno'];

            $complaint['children_worker'] = null;
            if (!empty($order['children_worker_id'])) {
                $children_worker = BaseModel::getInstance('worker')
                    ->getOneOrFail($order['children_worker_id'], 'worker_telephone,nickname');

                $complaint['children_worker'] = [
                    'name'  => $children_worker['nickname'],
                    'phone' => $children_worker['worker_telephone'],
                ];
            }

            $this->response($complaint);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function create()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $data = Arr::only(I(''), ['contact_name', 'contact_tell', 'complaint_to_type', 'complaint_type_id', 'content', 'orno', 'complaint_from_type']);
            $this->checkEmpty($data);
            $complaint_from_type = $data['complaint_from_type'];
            if (!in_array($complaint_from_type, ComplaintService::FROM_TYPE_VALID_ARRAY)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '投诉类型错误');
            }

            $field = 'id,orno';
            $order = BaseModel::getInstance('worker_order')
                ->getOneOrFail(['orno' => $data['orno']], $field);
            $data['worker_order_id'] = $order['id'];

            $complaint_create_type = $this->getComplaintCreateType();
            $complaint_from = $this->getComplaintFromInfo($data['complaint_from_type'], $order['id']);
            $complaint_from_id = 0;
            if (!empty($complaint_from)) {
                $complaint_from_id = $complaint_from['id'];
            }
            $complaint_create_id = AuthService::getAuthModel()
                ->getPrimaryValue();

            //委派客服
            $admin_id = (new ComplaintLogic())->getMatchAdmin($order['id']);
            $data['replier_id'] = $admin_id;

            $person = ComplaintService::getResponsePerson($data['complaint_to_type'], $data['worker_order_id']);
            $data['complaint_to_id'] = $person['user_id'];
            $data['create_time'] = NOW_TIME;
            $data['complaint_number'] = ComplaintService::generateComplaintNumber();
            $data['complaint_from_type'] = $complaint_from_type;
            $data['complaint_from_id'] = $complaint_from_id;
            $data['complaint_create_type'] = $complaint_create_type;
            $data['complaint_create_id'] = $complaint_create_id;
            $data['cp_complaint_type_name'] = BaseModel::getInstance('complaint_type')
                ->getFieldVal($data['complaint_type_id'], 'name');
            $worker_order_complaint_model = BaseModel::getInstance('worker_order_complaint');

            $worker_order_complaint_model->startTrans();
            $worker_order_complaint_model->insert($data);
            BaseModel::getInstance('worker_order_statistics')
                ->setNumInc(['worker_order_id' => $data['worker_order_id']], 'complaint_order_num');

            SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $admin_id, "工单号{$order['orno']}有投诉", $data['worker_order_id'], SystemMessageService::MSG_TYPE_ADMIN_COMPLAINT_APPLY);
            $worker_order_complaint_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function getComplaintCreateType()
    {
        $user_type = AuthService::getModel();

        if ($user_type == AuthService::ROLE_FACTORY_ADMIN) {
            return ComplaintService::CREATE_TYPE_FACTORY_ADMIN;
        } elseif ($user_type == AuthService::ROLE_FACTORY) {
            return ComplaintService::CREATE_TYPE_FACTORY;
        } else {
            return ComplaintService::CREATE_TYPE_CS;
        }
    }

    protected function getComplaintFromInfo($complaint_from_type, $worker_order_id)
    {
        $data = null;

        $field = 'id,factory_id,worker_id,origin_type,add_id';
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id, $field);
        $worker_order_id = $order['id'];

        if (!in_array($complaint_from_type, ComplaintService::FROM_TYPE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '投诉类型错误');
        }

        if (
            ComplaintService::FROM_TYPE_FACTORY == $complaint_from_type ||
            ComplaintService::FROM_TYPE_FACTORY_ADMIN == $complaint_from_type
        ) {
            //厂家获取下单人信息
            if ($order['origin_type'] == OrderService::ORIGIN_TYPE_FACTORY) {
                $info = BaseModel::getInstance('factory')
                    ->getOneOrFail($order['add_id']);
                $data = [
                    'id'    => $order['add_id'],
                    'name'  => $info['linkman'],
                    'phone' => $info['linkphone'],
                ];
            } elseif ($order['origin_type'] == OrderService::ORIGIN_TYPE_FACTORY_ADMIN) {
                $info = BaseModel::getInstance('factory_admin')
                    ->getOneOrFail($order['add_id']);
                $data = [
                    'id'    => $order['add_id'],
                    'name'  => $info['nickout'],
                    'phone' => $info['tell'],
                ];
            } elseif ($order['origin_type'] == OrderService::ORIGIN_TYPE_WX_USER) {
                $info = BaseModel::getInstance('wx_user')
                    ->getOneOrFail($order['add_id']);
                $data = [
                    'id'    => $order['add_id'],
                    'name'  => $info['real_name'],
                    'phone' => $info['telephone'],
                ];
            } elseif ($order['origin_type'] == OrderService::ORIGIN_TYPE_WX_DEALER) {

            }
        } elseif (ComplaintService::FROM_TYPE_WORKER == $complaint_from_type) {
            //技工
            if (empty($order['worker_id'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '技工未接单');
            }

            $info = BaseModel::getInstance('worker')
                ->getOne($order['worker_id']);
            if (!empty($info)) {
                $data = [
                    'id'    => $order['worker_id'],
                    'name'  => $info['nickname'],
                    'phone' => $info['worker_telephone'],
                ];
            }

        } elseif (ComplaintService::FROM_TYPE_WX_USER == $complaint_from_type) {
            //用户
            $user_info = BaseModel::getInstance('worker_order_user_info')
                ->getOne($worker_order_id, 'wx_user_id,real_name,phone');
            $data = [
                'id'    => $user_info['wx_user_id'],
                'name'  => $user_info['real_name'],
                'phone' => $user_info['phone'],
            ];
        }

        return $data;
    }

    public function getComplainType()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);
            $type = I('type');
            $user_type = I('user_type');
            if (!$user_type) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择用户类型');
            }
            $complaint_type = BaseModel::getInstance('complaint_type')
                ->getList([
                    'where' => [
                        'user_type' => $user_type,
                        'type'      => $type,
                        'is_delete' => ComplaintService::IS_DELETE_NO
                    ],
                    'field' => 'id,name',
                    'order' => 'sort',
                ]);

            return $this->responseList($complaint_type);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function verify()
    {
        try {
            $id = I('get.id');
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $admin = AuthService::getAuthModel();
            //            if (!in_array($admin['role_id'], ComplaintService::getCanVerifyRoles())) {
            //                $this->fail(ErrorCode::WORKER_ORDER_ADMIN_NO_PERMISSION, '您的角色无法进行该操作');
            //            }

            $data = Arr::only(I(''), ['is_satisfy', 'user_type']);
            $this->checkEmpty($data);

            if (!in_array($data['is_satisfy'], ComplaintService::IS_SATISFY_VALID_ARRAY)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '是否满意错误');
            }

            $data['complaint_modify_type_id'] = I('complaint_modify_type_id');
            //            if ($data['user_type'] != 99 && !$data['complaint_modify_type_id']) {
            //                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择投诉类型');
            //            }


            $complaint = BaseModel::getInstance('worker_order_complaint')
                ->getOneOrFail($id, 'id,worker_order_id');
            $data['cp_complaint_type_name_modify'] = BaseModel::getInstance('complaint_type')
                ->getFieldVal($data['complaint_modify_type_id'], 'name');
            $response_person = ComplaintService::getResponsePerson($data['user_type'], $complaint['worker_order_id']);
            $data['response_type'] = $response_person['user_type'];
            $data['response_type_id'] = $response_person['user_id'];
            $data['is_true'] = 1;   // TODO 原型没有是否属实

            $data['verify_remark'] = I('verify_remark', '');
            $data['verifier_id'] = AuthService::getAuthModel()
                ->getPrimaryValue();
            $data['verify_time'] = NOW_TIME;


            $complaint_model = BaseModel::getInstance('worker_order_complaint');
            $complaint = $complaint_model->getOne($id, 'worker_order_id');
            $complaint_model->startTrans();
            if ($data['response_type'] == ComplaintService::RESPONSE_TYPE_WORKER) {
                $score_deductions = BaseModel::getInstance('complaint_type')
                    ->getFieldVal($data['complaint_modify_type_id'], 'score_deductions');
                $worker_order_reputation = BaseModel::getInstance('worker_order_reputation')
                    ->getOne(['worker_order_id' => $complaint['worker_order_id'], 'worker_id' => $data['response_type_id']], 'id,complt_fraction');
                BaseModel::getInstance('worker_order_reputation')
                    ->update($worker_order_reputation['id'], [
                        'complt_fraction' => $worker_order_reputation['complt_fraction'] + $score_deductions,
                    ]);
            }
            $complaint_model->update($id, $data);
            $complaint_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function reply()
    {
        try {
            $id = I('get.id');
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $complaint = BaseModel::getInstance('worker_order_complaint')
                ->getOneOrFail($id, 'id,complaint_from_id,worker_order_id,reply_time,complaint_from_type');
            $order = BaseModel::getInstance('worker_order')
                ->getOne($complaint['worker_order_id'], 'orno');
            if ($complaint['reply_time'] > 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该投诉单已回复~');
            }
            $reply_result = I('reply_result');
            if (!$reply_result) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写投诉回复');
            }
            $worker_order_complaint_model = BaseModel::getInstance('worker_order_complaint');
            $worker_order_complaint_model->startTrans();
            $worker_order_complaint_model->update($id, [
                'reply_result' => $reply_result,
                'reply_time'   => NOW_TIME,
                'replier_id'   => AuthService::getAuthModel()
                    ->getPrimaryValue(),
            ]);

            if (
                ComplaintService::RESPONSE_TYPE_FACTORY == $complaint['complaint_from_type'] ||
                ComplaintService::RESPONSE_TYPE_FACTORY_ADMIN == $complaint['complaint_from_type']
            ) {
                SystemMessageService::create(SystemMessageService::USER_TYPE_FACTORY, $complaint['complaint_from_id'], "工单号{$order['orno']}的投诉有回复,请查看", $complaint['worker_order_id'], SystemMessageService::MSG_TYPE_FACTORY_COMPLAINT_RESPONSE);
            }

            $is_prompt_complaint_to = I('is_prompt_complaint_to', 0, 'intval');
            if (ComplaintService::IS_PROMPT_COMPLAINT_TO_YES == $is_prompt_complaint_to) {
                (new ComplaintLogic())->promptComplaintTo($id);
            }

            $worker_order_complaint_model->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function promptComplaintTo()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $id = I('get.id', 0, 'intval');
            if ($id <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            (new ComplaintLogic())->promptComplaintTo($id);

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getComplaintFrom()
    {
        try {
            $orno = I('orno', '');
            $complaint_from_type = I('complaint_from_type', 0, 'intval');

            if (empty($orno)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            if (!in_array($complaint_from_type, ComplaintService::FROM_TYPE_VALID_ARRAY)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '投诉类型错误');
            }

            $order = BaseModel::getInstance('worker_order')->getOneOrFail([
                'orno' => $orno,
            ], 'id');

            $data = $this->getComplaintFromInfo($complaint_from_type, $order['id']);

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }
}