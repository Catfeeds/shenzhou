<?php
/**
* @User 嘉诚
* @Date 2017/11/13
* @mess 订单
*/
namespace Qiye\Logic;

use Common\Common\Logic\ExpressTrackingLogic;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\OrderSettlementService;
use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Library\Common\Util;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\AccessoryRecordService;
use Common\Common\Service\SystemMessageService;
use Common\Common\Service\OrderService;

class AccessoryLogic extends BaseLogic
{
    /*
     * 配件单列表
     */
    public function getList($order_id, $request, $user_id)
    {
        $accessory_model = BaseModel::getInstance('worker_order_apply_accessory');
        $accessory_item_model = BaseModel::getInstance('worker_order_apply_accessory_item');
        if ($request['is_send_back'] == '1') {
            $where['is_giveup_return'] = '0';
            $where['accessory_status'] = AccessoryService::STATUS_WORKER_TAKE;
            $where['cancel_status']    = 0;
        } elseif ($request['is_send_back'] == '2') {
            $where['is_giveup_return'] = ['in', '1, 2'];
        }
        $where['worker_order_id'] = $order_id;
        $list = $accessory_model->getList([
            'where' => $where,
            'field' => 'id, is_giveup_return, accessory_number, accessory_status, cancel_status',
            'order' => 'create_time desc'
        ]);
        foreach ($list as $k => $v) {
            if ($v['cancel_status'] > 0) {
                $list[$k]['accessory_status'] = '10';
            }
            $list[$k]['accessory_item'] = $accessory_item_model->getList([
                'where' => [
                    'accessory_order_id' => $v['id']
                ],
                'field' => 'id, name'
            ]);
        }
        return $list;
    }

    /*
     * 获取配件单信息
     */
    public function detail($id, $user_id)
    {
        $apply_accessory = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
            'where' => [
                'id' => $id
            ],
            'field' => '*',
            'order' => 'create_time desc'
        ]);
        if (!empty($apply_accessory['accessory_imgs'])) {
            if (strpos($apply_accessory['accessory_imgs'], 'quot;')) {
                $apply_accessory['accessory_imgs'] = html_entity_decode($apply_accessory['accessory_imgs']);
            }
            $accessory_imgs = json_decode($apply_accessory['accessory_imgs'], true);
            unset($apply_accessory['accessory_imgs']);
            foreach ($accessory_imgs as $v) {
                $apply_accessory['accessory_imgs'][] = Util::getServerFileUrl($v['url']);
            }
        } else {
            $apply_accessory['accessory_imgs'] = null;
        }
        $apply_accessory['accessory_item'] = BaseModel::getInstance('worker_order_apply_accessory_item')->getList([
            'where' => [
                'accessory_order_id' => $apply_accessory['id']
            ],
            'field' => 'id, name, remark, code, nums'
        ]);
        if ($apply_accessory['accessory_status'] >= 8) {
            $type = 2;
        } else {
            $type = 1;
        }
        $apply_accessory['express_number'] = BaseModel::getInstance('express_tracking')->getFieldVal([
            'type' => $type,
            'data_id' => $id
        ], 'express_number');
        if ($apply_accessory['cancel_status'] > 0) {
            $apply_accessory['accessory_status'] = '10';
        }
        return $apply_accessory;
    }

    /*
     * 厂家信息
     */
    public function factoryDetail($id, $user_id)
    {
        $accessory_info = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
            'alias' => 'aa',
            'where' => [
                'aa.id' => $id
            ],
            'join'  => 'left join worker_order as wo on wo.id=aa.worker_order_id',
            'field' => 'aa.factory_id, wo.distributor_id'
        ]);
        $detail = BaseModel::getInstance('factory')->getOne([
            'where' => [
                'factory_id' => $accessory_info['factory_id']
            ],
            'field' => 'factory_id, receive_address, receive_tell, receive_person'
        ]);
        $detail['custom_service'] = D('Order', 'Logic')->getCustom($accessory_info['distributor_id'], 'user_name');
        return $detail;
    }

    /*
     * 配件回寄
     */
    public function accessoryReturn($id, $request, $user_id)
    {
        if (empty($request['express_num'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '物流单号不能为空');
        }
        if (empty($request['express_code'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '物流公司代号不能为空');
        }
        if (empty($request['worker_return_pay_method']) || !in_array($request['worker_return_pay_method'], AccessoryService::PAY_METHOD_ARR)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '技工返件付款方式不能为空');
        }

        $info = BaseModel::getInstance('worker_order_apply_accessory')->getOne([
            'id' => $id
        ], 'worker_id, worker_order_id, cancel_status, is_giveup_return, accessory_status');
        if ($info['is_giveup_return'] != '0' || $info['cancel_status'] != '0' || $info['accessory_status'] != AccessoryService::STATUS_WORKER_TAKE) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '配件单不需要返件');
        }
        $order_info = $this->checkWorkerOrder($info['worker_order_id'], $user_id);

        $worker_order_type = $order_info['worker_order_type'];
        if (in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保外单配件单不能返件');
        }


        M()->startTrans();
        //物流订阅
        expressTrack($request['express_code'], $request['express_num'], $id, ExpressTrackingLogic::TYPE_ACCESSORY_SEND_BACK);

        $express_name = BaseModel::getInstance('express_com')->getFieldVal([
            'comcode' => $request['express_code']
        ], 'name');

        //更新返件信息
        BaseModel::getInstance('worker_order_apply_accessory')->update([
            'id' => $id
        ], [
            'worker_return_pay_method'      => $request['worker_return_pay_method'],
//            'worker_return_time'          => $request['worker_return_time'],
            'worker_return_time'            => NOW_TIME,
            'accessory_status'              => AccessoryService::STATUS_WORKER_SEND_BACK,
            'worker_transport_fee'          => $request['worker_transport_fee'],
            'worker_transport_fee_modify'   => $request['worker_transport_fee'],
            'factory_transport_fee'         => $request['worker_transport_fee'],
            'factory_transport_fee_modify'  => $request['worker_transport_fee'],
        ]);
        if ($info['cancel_status'] == '0') {
            BaseModel::getInstance('worker_order_statistics')->setNumDec(['worker_order_id' => $info['worker_order_id']], 'accessory_unreturn_num');
        }

        //更新返件费用
        if ($request['worker_return_pay_method'] == '1') {
            $worker_return_pay_method = '现付';
            $fee_model = BaseModel::getInstance('worker_order_fee');
            $accessory_return_fee = $fee_model->getOne([
                'worker_order_id' => $info['worker_order_id']
            ], 'worker_accessory_return_fee,worker_accessory_return_fee_modify,factory_accessory_return_fee,factory_accessory_return_fee_modify');
            AccessoryService::checkWorkerOrderWhenUpdateAccessoryStatus($info['worker_order_id'], $info['accessory_status']);
            //结算
            OrderSettlementService::orderFeeStatisticsUpdateFee($info['worker_order_id'], [
                'worker_accessory_return_fee'        => $accessory_return_fee['worker_accessory_return_fee']        + $request['worker_transport_fee'],
                'worker_accessory_return_fee_modify' => $accessory_return_fee['worker_accessory_return_fee_modify'] + $request['worker_transport_fee'],
                'factory_accessory_return_fee'       => $accessory_return_fee['factory_accessory_return_fee']       + $request['worker_transport_fee'],
                'factory_accessory_return_fee_modify'=> $accessory_return_fee['factory_accessory_return_fee_modify']+ $request['worker_transport_fee'],
            ]);
        } else {
            $worker_return_pay_method = '到付';
        }

        $content = '回寄配件,'.$express_name.':'.$request['express_num'].'('.$worker_return_pay_method.$request['worker_transport_fee'].'元)';

//        $record_service = new AccessoryRecordService;
//        $record_service->addRecord($id, AccessoryRecordService::WORKER_SEND_BACK, $content, '');
        AccessoryRecordService::create($id, AccessoryRecordService::OPERATE_TYPE_WORKER_SEND_BACK, $content, '');

        //后台推送
        $content = '工单号'.$order_info['orno'].'的配件,技工已返件';
        $content2 = '工单号'.$order_info['orno'].'的配件,已返件';
        $this->sendAdminMessage($order_info, $id, $content, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_WORKER_SENT, $content2);
        M()->commit();
    }

    /*
     * 配件申请
     */
    public function add($order_id, $request, $user_id)
    {
        if (!trim($request['address'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写详细收件地址');
        }
        $order_info = $this->checkWorkerOrder($order_id, $user_id, '*', [
            'worker_order_status' => ['egt', OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]
        ]);

        $worker_order_type = $order_info['worker_order_type'];
        if (
            in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST) &&
            !in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_ACCESSORY_LIST)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '异常保外单不能申请配件单');
        }

        //  开启事务
        M()->startTrans();

        //检查最后一次预约是否已经签到
        $this->checkLastAppoint($order_id, $order_info['worker_id']);

        $data = [
            'worker_order_product_id'=> $request['product_id'],
            'accessory_number'       => $this->genArNo(),
            'factory_id'             => $order_info['factory_id'],
            'worker_order_id'        => $order_id,
            'worker_id'              => $order_info['worker_id'],
            'apply_reason'           => !empty($request['apply_reason']) ? $request['apply_reason'] : '',
            'addressee_name'         => $request['user_name'],
            'addressee_phone'        => $request['phone'],
            'addressee_address'      => $request['address'],
            'accessory_imgs'         => !empty($request['accessory_imgs']) ? html_entity_decode(html_entity_decode($request['accessory_imgs'])) : '',
            'addressee_area_ids'     => $request['province_id'].','.$request['city_id'].','.$request['area_id'],
            'cp_addressee_area_desc' => $request['province_name'].','.$request['city_name'].','.$request['area_name'],
            'receive_address_type'   => $request['receive_address_type'] ?? '0',
            'accessory_status'       => 1,
            'cancel_status'          => 0,
            'create_time'            => NOW_TIME,
            'last_update_time'       => NOW_TIME
        ];
        $accessory_id = BaseModel::getInstance('worker_order_apply_accessory')->insert($data);
        $item_data = [
            'accessory_order_id' => $accessory_id,
            'worker_id'          => $order_info['worker_id'],
            'name'               => $request['name'],
            'nums'               => $request['num'],
            'code'               => !empty($request['code']) ? $request['code'] : '',
            'remark'             => $request['remark']
        ];
        BaseModel::getInstance('worker_order_apply_accessory_item')->insert($item_data);

        $statistics_model = BaseModel::getInstance('worker_order_statistics');
        $statistics_model->setNumInc(['worker_order_id' => $order_id], 'total_accessory_num');
        $statistics_model->setNumInc(['worker_order_id' => $order_id], 'accessory_order_num', $request['num']);
        //$statistics_model->setNumInc(['worker_order_id' => $order_id], 'accessory_unsent_num');

        //添加操作记录
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_APPLY_ACCESSORY, [
            'operator_id' => $user_id,
            'worker_order_product_id' => $request['product_id'],
            'content_replace' => [
                'accessory_content' => $request['name'].',配件单号为:'.$data['accessory_number']
            ],
            'remark' => $data['apply_reason'].$this->handleImage($data['accessory_imgs']),
            'see_auth' => OrderOperationRecordService::PERMISSION_CS | OrderOperationRecordService::PERMISSION_WORKER
        ]);
        $content = '工单号'.$order_info['orno'].'申请了配件';
        AccessoryRecordService::create($accessory_id, AccessoryRecordService::OPERATE_TYPE_WORKER_APPLY, $content, $data['apply_reason']);

        //后台推送
        $this->sendAdminMessage($order_info, $accessory_id, $content, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_WORKER_APPLY);
        //  结束事务
        M()->commit();

    }

    /*
     * 配件签收
     */
    public function accessorySignIn($id, $user_id)
    {
        $model = BaseModel::getInstance('worker_order_apply_accessory');
        $info = $model->getOne([
            'id' => $id
        ], 'worker_id, worker_order_id, is_giveup_return, cancel_status, accessory_status');
        $order_info = $this->checkWorkerOrder($info['worker_order_id'], $user_id);

        $worker_order_type = $order_info['worker_order_type'];
        if (
            in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST) &&
            !in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_ACCESSORY_LIST)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '异常保外单配件单不能签收');
        }

        if ($info['accessory_status'] != AccessoryService::STATUS_FACTORY_SENT) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '配件单不需要签收');
        }

        M()->startTrans();
        $update_data = [
            'accessory_status'    => AccessoryService::STATUS_WORKER_TAKE,
            'last_update_time'    => NOW_TIME,
            'worker_receive_time' => NOW_TIME,
        ];
        if (in_array($info['is_giveup_return'], [
            AccessoryService::RETURN_ACCESSORY_FORBIDDEN,
            AccessoryService::RETURN_ACCESSORY_GIVE_UP,
        ])) {
            //放弃返件,配件单流程直接结束
            $update_data['accessory_status'] = AccessoryService::STATUS_COMPLETE;
            $update_data['complete_time'] = NOW_TIME;
            if (AccessoryService::CANCEL_STATUS_NORMAL == $info['cancel_status']) {
                BaseModel::getInstance('worker_order_statistics')->setNumDec(['worker_order_id' => $info['worker_order_id']], 'accessory_worker_unreceive_num');
            }
        }
        $model->update([
            'id' => $id
        ], $update_data);
        BaseModel::getInstance('express_tracking')->update([
            'data_id' => $id,
            'type'    => 1
        ], [
            'state' => 3
        ]);

        $content = '工单号'.$order_info['orno'].'的配件,技工已签收';
        AccessoryRecordService::create($id, AccessoryRecordService::OPERATE_TYPE_WORKER_TAKE, '确认配件签收', '');

        //后台推送
        //$this->sendAdminMessage($order_info, $id, $content, SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_WORKER_TAKE);
        M()->commit();
    }

    /*
     * 生成配件单号
     */
    public function genArNo(){

        //获取毫秒数（时间戳）
        list($t1, $t2) = explode(' ', microtime());

        $microtime =  (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);

        $microStr   = substr($microtime,7,6);

        $timeStr = date('ymd',time());

        $arno = $timeStr.$microStr;

        $id = BaseModel::getInstance('worker_order_apply_accessory')->getFieldVal([
            'accessory_number' => $arno
        ], 'id');

        if(!empty($id)){
            return $this->genArNo();
        } else {
            return $arno;
        }
    }

    /*
     * 后台消息推送
     */
    public function sendAdminMessage($order_info, $data_id, $content, $msg_type, $send_factory_content = false)
    {
        //后台推送
        //客服
        SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $order_info['distributor_id'], $content, $data_id, $msg_type);
        //下单的工单账号
        if ($send_factory_content) {
            if ($order_info['origin_type'] == '1') {
                //厂家
                $receiver_type = SystemMessageService::USER_TYPE_FACTORY;
            } elseif ($order_info['origin_type'] == '2') {
                //厂家子账号
                $receiver_type = SystemMessageService::USER_TYPE_FACTORY_ADMIN;
            } else {
                $receiver_type = SystemMessageService::USER_TYPE_WX_USER;
            }
            SystemMessageService::create($receiver_type, $order_info['add_id'], $send_factory_content, $data_id, $msg_type);
        }
    }

}
