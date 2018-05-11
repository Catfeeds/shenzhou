<?php
/**
 * File: FactoryAccessories.class.php
 * User: sakura
 * Date: 2017/11/7
 */

namespace Admin\Controller;

use Admin\Logic\AccessoryLogic;
use Common\Common\Service\AccessoryRecordService;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AuthService;
use Admin\Model\BaseModel;
use Admin\Common\ErrorCode;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;

class AccessoryController extends BaseController
{

    public function add()
    {
        $id = I('get.id', 0);
        $product_id = I('get.product_id', 0);
        $post = I('post.', []);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $logic = new AccessoryLogic();
            M()->startTrans();
            $logic->add($id, $product_id, $post);
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function index()
    {
        try {
            //客服,厂家共用
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
                'status'               => I('status', 0, 'intval'),
                'accessory_number'     => I('accessory_number'),
                'is_giveup_return'     => I('is_giveup_return', -1, 'intval'),
                'orno'                 => I('orno', ''),
                'date_from'            => I('date_from', 0, 'intval'),
                'date_to'              => I('date_to', 0, 'intval'),
                'is_confirm_send_back' => I('is_confirm_send_back', 0, 'intval'),
                'admin_group_id'       => I('admin_group_id', 0, 'intval'),
                'admin_ids'            => I('admin_ids'),
                'factory_ids'          => I('factory_ids'),
                'factory_group_ids'    => I('factory_group_id_list'),
                'tag_id'               => I('tag_id'),
                'send_back_express_no' => I('send_back_express_no'),

                'limit' => $this->page(),
            ];

            $result = D('Accessory', 'Logic')->getList($param);

            $this->paginate($result['list'], $result['cnt']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getStatusCnt()
    {
        try {
            //客服 厂家后台共用
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
            ];

            $stats = D('Accessory', 'Logic')->getStatusCnt($param);

            $this->response($stats);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //修改配件单收件人信息
    public function addressEdit()
    {
        try {
            // 获取客服角色
            $this->requireAuth(AuthService::ROLE_ADMIN);
            // 接收参数
            $param = [
                'accessory_id'           => I('get.accessory_id', 0 , 'intval'),
                'addressee_name'         => I('put.addressee_name'),
                'addressee_phone'        => I('put.addressee_phone'),
                'cp_addressee_area_desc' => I('put.cp_addressee_area_desc'),
                'addressee_address'      => I('put.addressee_address'),
            ];
            // 获取地址栏上的配件单，查询其操作状态
            $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');
            $where = ['id' => $param['accessory_id']];
            $accessory_info = $accessory_model->getOne([
                'field' => 'id,accessory_status',
                'where' => $where
            ]);
            $id = $accessory_info['id'];
            //可修改的区间里
            if ($accessory_info['accessory_status'] == AccessoryService::STATUS_WORKER_APPLY_ACCESSORY || $accessory_info['accessory_status'] == AccessoryService::STATUS_ADMIN_CHECKED) {
                // 接收地址信息(可修改)
                $this->checkEmpty($param);
                $update_data = [
                    'addressee_name'         =>  $param['addressee_name'],
                    'addressee_phone'        =>  $param['addressee_phone'],
                    'cp_addressee_area_desc' =>  $param['cp_addressee_area_desc'],
                    'addressee_address'      =>  $param['addressee_address'],
                ];

                BaseModel::getInstance('worker_order_apply_accessory')->update($id, $update_data);
            } else {
                $this->throwException(ErrorCode::SYS_REQUEST_METHOD_ERROR, '配件单当前状态不在可修改状态范围内');
            }

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function info()
    {
        try {
            //客服 厂家后台共用
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN]);

            $param = [
                'accessory_id' => I('accessory_id', 0, 'intval'),
            ];

            $data = D('Accessory', 'Logic')->getInfo($param);

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryCheck()
    {
        try {
            //客服 厂家后台共用
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'accessory_id'  => I('get.accessory_id', 0, 'intval'),
                'is_agree'      => I('is_agree', 0, 'intval'),
                'estimate_time' => I('estimate_time', 0, 'intval'),
                'remark'        => I('remark', ''),
            ];

            M()->startTrans();

            D('Accessory', 'Logic')->factoryCheck($param);

            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryDelaySend()
    {
        try {

            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'accessory_id'  => I('get.accessory_id', 0, 'intval'),
                'estimate_time' => I('estimate_time', 0, 'intval'),
                'remark'        => I('remark', ''),
            ];

            M()->startTrans();

            D('Accessory', 'Logic')->factoryDelaySend($param);

            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryConfirmSend()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'accessory_id'     => I('get.accessory_id', 0, 'intval'),
                'express_number'   => I('express_number'),
                'express_code'     => I('express_code'),
                'remark'           => I('remark', ''),
                'is_giveup_return' => I('is_giveup_return', 0, 'intval'),
            ];

            M()->startTrans();
            D('Accessory', 'Logic')->factoryConfirmSend($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function giveUpReturn()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'accessory_id' => I('get.accessory_id', 0, 'intval'),
                'reason'       => I('reason', 0, 'intval'),
                'remark'       => I('remark', ''),
            ];

            M()->startTrans();
            D('Accessory', 'Logic')->giveUpReturn($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryConfirmSendBack()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'accessory_id' => I('get.accessory_id', 0, 'intval'),
            ];

            M()->startTrans();
            D('Accessory', 'Logic')->factoryConfirmSendBack($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryStop()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            $param = [
                'accessory_id' => I('get.accessory_id', 0, 'intval'),
                'reason'       => I('reason'),
            ];

            M()->startTrans();
            D('Accessory', 'Logic')->factoryStop($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function adminCheck()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'accessory_id' => I('get.accessory_id', 0, 'intval'),
                'remark'       => I('remark'),
                'is_check'     => I('is_check', 0, 'intval'),
            ];

            M()->startTrans();
            D('Accessory', 'Logic')->adminCheck($param);
            M()->commit();

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function adminStop()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $param = [
                'accessory_id' => I('get.accessory_id', 0, 'intval'),
                'reason'       => I('reason'),
            ];

            M()->startTrans();
            D('Accessory', 'Logic')->adminStop($param);
            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }


}