<?php
/**
 * File: OrderMessageLogic.class.php
 * User: sakura
 * Date: 2017/11/14
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderMessageService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SystemMessageService;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Common\Common\Service\UserTypeService;

class OrderMessageLogic extends BaseLogic
{

    const ROLE_CHECKER     = 1;
    const ROLE_DISTRIBUTOR = 2;
    const ROLE_RETURNEE    = 3;

    protected $tableName = 'worker_order_message';

    public function getList($param)
    {
        $last_query_id = $param['last_query_id'];
        $worker_order_id = $param['worker_order_id'];
        $limit = $param['limit'];

        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '工单ID为空');
        }

        //获取聊天记录
        //获取条件[发送和接收必须是客服和厂家,不能用in,会把与其他角色聊天的内容带进来]
        $model = BaseModel::getInstance($this->tableName);

        $where = [
            'worker_order_id' => $worker_order_id,
            '_string'         => '((add_type=' . OrderMessageService::ADD_TYPE_CS . ' and receive_type in (' . OrderMessageService::RECEIVE_TYPE_FACTORY . ',' . OrderMessageService::RECEIVE_TYPE_FACTORY_ADMIN . ')) or (add_type in (' . OrderMessageService::ADD_TYPE_FACTORY . ',' . OrderMessageService::ADD_TYPE_FACTORY_ADMIN . ') and receive_type=' . OrderMessageService::RECEIVE_TYPE_CS . '))',
        ];
        $cnt = $model->getNum($where);

        $filed = 'id as msg_id,add_type,add_id,content,create_time,receive_type,is_read';
        $where['id'] = ['gt', $last_query_id];
        $opts = [
            'field' => $filed,
            'where' => $where,
            'order' => 'id',
            'limit' => $limit,
        ];
        $list = $model->getList($opts);

        foreach ($list as $key => $val) {
            $add_type = $val['add_type'];
            $add_id = $val['add_id'];
            $content = $val['content'];

            $content = htmlspecialchars_decode($content);
            preg_replace("#</?script[^>]*>#isg", '', $content);

            $user_obj = UserTypeService::getTypeData($add_type, $add_id, UserInfoType::USER_ORDER_MESSAGE_TYPE);

            $name = $user_obj->getName();
            $val['user_name'] = $name['name'];
            $val['thumb'] = $user_obj->getThumb();
            $val['content'] = $content;
            $list[$key] = $val;

        }

        //获取客服
        $role = AuthService::getModel();
        $stats = [];
        $where = ['worker_order_id' => $worker_order_id];

        //统计
        if (AuthService::ROLE_ADMIN == $role) {
            //客服
            $stats['unread_message_num'] = ['exp', 'unread_message_num-unread_message_admin'];
            $stats['unread_message_admin'] = 0;
            $where['unread_message_num'] = ['exp', '>=unread_message_admin'];
        } else {
            //厂家 或 子账号
            $stats['unread_message_num'] = ['exp', 'unread_message_num-unread_message_factory'];
            $stats['unread_message_factory'] = 0;
            $where['unread_message_num'] = ['exp', '>=unread_message_factory'];
        }
        $model = BaseModel::getInstance('worker_order_statistics');
        $model->update($where, $stats);

        //设为已读
        $message_where = ['worker_order_id' => $worker_order_id, 'is_read' => 0];
        if (AuthService::ROLE_ADMIN == $role) {
            //客服
            $message_where['add_type'] = ['in', [OrderMessageService::RECEIVE_TYPE_FACTORY, OrderMessageService::RECEIVE_TYPE_FACTORY_ADMIN]];
        } else {
            //厂家 或 子账号
            $message_where['add_type'] = ['in', [OrderMessageService::RECEIVE_TYPE_CS,]];
        }
        BaseModel::getInstance($this->tableName)
            ->update($message_where, ['is_read' => 1, 'read_time' => NOW_TIME]);

        return [
            'data' => $list,
            'cnt'  => $cnt,
        ];
    }

    public function add($param)
    {
        $worker_order_id = $param['worker_order_id'];
        $content = $param['content'];

        if (empty($worker_order_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '工单ID为空');
        }
        if (empty($content)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '内容为空');
        }

        $field = 'origin_type,factory_check_order_type,factory_check_order_id,worker_order_status,orno,auditor_id,distributor_id,returnee_id,checker_id,add_id';
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id, $field);
        $origin_type = $order['origin_type'];
        $worker_order_status = $order['worker_order_status'];
        $orno = $order['orno'];
        $checker_id = $order['checker_id'];
        $distributor_id = $order['distributor_id'];
        $returnee_id = $order['returnee_id'];
        $order_add_id = $order['add_id'];

        $add_type = 0;
        $receive_type = 0;

        //获取客服
        $role = AuthService::getModel();
        $user_id = AuthService::getAuthModel()->getPrimaryValue();

        if (AuthService::ROLE_ADMIN == $role) {
            //客服
            $add_type = OrderMessageService::ADD_TYPE_CS;
            //哪个账号创建工单,客服则把留言给谁
            if (OrderService::FACTORY_CHECK_ORDER_TYPE_FACTORY == $order['factory_check_order_type']) {
                //厂家
                $receive_type = OrderMessageService::RECEIVE_TYPE_FACTORY;
            } else {
                //子账号
                $receive_type = OrderMessageService::RECEIVE_TYPE_FACTORY_ADMIN;
            }

        } elseif (
            AuthService::ROLE_FACTORY == $role ||
            AuthService::ROLE_FACTORY_ADMIN == $role
        ) {
            //厂家 厂家子账号
            $receive_type = OrderMessageService::RECEIVE_TYPE_CS;

            if (AuthService::ROLE_FACTORY == $role) {
                //厂家
                $add_type = OrderMessageService::ADD_TYPE_FACTORY;
            } else {
                //子账号
                $add_type = OrderMessageService::ADD_TYPE_FACTORY_ADMIN;
            }
        }

        $model = BaseModel::getInstance($this->tableName);

        $insert_data = [
            'worker_order_id' => $worker_order_id,
            'add_type'        => $add_type,
            'add_id'          => $user_id,
            'content_type'    => 1,
            'content'         => $content,
            'create_time'     => NOW_TIME,
            'receive_type'    => $receive_type,
        ];
        $insert_id = $model->insert($insert_data);

        $sys_receiver_type = 0;
        $sys_type = 0;
        $sys_receiver_id = 0;

        if (AuthService::ROLE_ADMIN == $role) {
            if (OrderService::FACTORY_CHECK_ORDER_TYPE_FACTORY == $order['factory_check_order_type']) {
                //厂家
                $sys_receiver_type = SystemMessageService::USER_TYPE_FACTORY;
            } else {
                //子账号
                $sys_receiver_type = SystemMessageService::USER_TYPE_FACTORY_ADMIN;
            }
            $sys_type = SystemMessageService::MSG_TYPE_ADMIN_LEAVE_MESSAGE_NEW_MESSAGE;
            $sys_receiver_id = $order['factory_check_order_id'];
        } elseif (AuthService::ROLE_FACTORY == $role || AuthService::ROLE_FACTORY_ADMIN == $role) {
            $role_info = $this->getCurrentAdmin($worker_order_status);

            $role = $role_info['role'];
            $sys_receiver_id = 0;
            if (self::ROLE_CHECKER == $role) {
                $sys_receiver_id = $checker_id;
            } elseif (self::ROLE_DISTRIBUTOR == $role) {
                $sys_receiver_id = $distributor_id;
            } elseif (self::ROLE_RETURNEE == $role) {
                $sys_receiver_id = $returnee_id;
            }

            $sys_receiver_type = SystemMessageService::USER_TYPE_ADMIN;
            $sys_type = SystemMessageService::MSG_TYPE_FACTORY_LEAVE_MESSAGE_NEW_MESSAGE;
        }

        $sys_msg = "工单号{$orno}，有留言";
        SystemMessageService::create($sys_receiver_type, $sys_receiver_id, $sys_msg, $worker_order_id, $sys_type);

        $stats = [
            'total_message_num'  => ['exp', 'total_message_num+1'],
            'unread_message_num' => ['exp', 'unread_message_num+1'],
        ];
        if (AuthService::ROLE_ADMIN == $role) {
            //客服 发给厂家
            $stats['unread_message_factory'] = ['exp', 'unread_message_factory+1'];
        } else {
            //厂家 或 子账号 发给客服
            $stats['unread_message_admin'] = ['exp', 'unread_message_admin+1'];
        }
        BaseModel::getInstance('worker_order_statistics')
            ->update($worker_order_id, $stats);

        return [
            'msg_id' => $insert_id,
        ];
    }

    protected function getCurrentAdmin($worker_order_status)
    {
        //可接单状态: 1.工单待财务审核之后 2.待派发之后 工单待财务审核之前 3.工单待核实
        //核实合法状态
        $checked_valid_status = [
            OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
            OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
        ];

        //派单合法状态
        $distributor_valid_status = [
            OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
            OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL,
            OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
            OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
            OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
            OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
            OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
            OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
            OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
            OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
        ];

        //回访合法状态
        $returnee_valid_status = [
            OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
            OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
            OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
            OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
        ];

        $valid_status = array_merge($checked_valid_status, $distributor_valid_status, $returnee_valid_status);

        if (!in_array($worker_order_status, $valid_status)) {
            return false;
        }

        if (in_array($worker_order_status, $checked_valid_status)) {
            return [
                'role' => self::ROLE_CHECKER,
            ];
        } elseif (in_array($worker_order_status, $distributor_valid_status)) {
            return [
                'role' => self::ROLE_DISTRIBUTOR,
            ];
        } elseif (in_array($worker_order_status, $returnee_valid_status)) {
            return [
                'role' => self::ROLE_RETURNEE,
            ];
        }

        return false;

    }

}