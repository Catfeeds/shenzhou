<?php
/**
 * File: SystemMessageLogic.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/29
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\SystemMessageService;

class SystemMessageLogic extends BaseLogic
{

    protected $tableName = 'worker_order_system_message';

    // 将未读的通知信息全部以列表形式展示
    public function getList($param)
    {
        $limit = $param['limit'];
        $user_id = AuthService::getAuthModel()->getPrimaryValue();
        $user_type = SystemMessageService::getUserType();

        $where = [
            'user_id' => $user_id,
            'user_type' => $user_type,
            'is_read' => SystemMessageService::UNREAD,
        ];

        $model = BaseModel::getInstance($this->tableName);

        $cnt = $model->getNum($where);

        $filed = 'id,msg_content,msg_type,data_id,create_time';
        $opts = [
            'field' => $filed,
            'where' => $where,
            'order' => 'id desc',
            'limit' => $limit,
        ];
        $data = $model->getList($opts);

        return [
            'data'       => $data,
            'cnt'        => $cnt,
            'unread_num' => $cnt,
        ];

    }

    public function read($param)
    {
        $msg_id = $param['msg_id'];

        if ($msg_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $user_id = AuthService::getAuthModel()->getPrimaryValue();
        $user_type = SystemMessageService::getUserType();

        $model = BaseModel::getInstance($this->tableName);

        $where = [
            'user_id'   => $user_id,
            'user_type' => $user_type,
            'id'        => $msg_id,
        ];
        $model->getOneOrFail($where, 'id');

        $update_data = [
            'is_read'   => SystemMessageService::IS_READ,
            'read_time' => NOW_TIME,
        ];

        $model->update($msg_id, $update_data);
    }

    public function readAll()
    {
        $user_id = AuthService::getAuthModel()->getPrimaryValue();
        $user_type = SystemMessageService::getUserType();

        $model = BaseModel::getInstance($this->tableName);

        $where = [
            'user_id'   => $user_id,
            'user_type' => $user_type,
        ];
        $model->getOneOrFail($where, 'id');

        $update_data = [
            'is_read'   => SystemMessageService::IS_READ,
            'read_time' => NOW_TIME,
        ];

        $model->update($where, $update_data);
    }

}