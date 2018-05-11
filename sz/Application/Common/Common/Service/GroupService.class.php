<?php
/**
 * File: GroupService.class.php
 * User: 嘉诚
 * Date: 2018/1/24
 */

namespace Common\Common\Service;


use Common\Common\Model\BaseModel;
use Common\Common\ErrorCode;

class GroupService
{

    // 群状态
    const GROUP_STATUS_AUDITING                = 1; // 审核中
    const GROUP_STATUS_AUDIT_PASS              = 2; // 审核通过
    const GROUP_STATUS_AUDIT_NOT_PASS          = 3; // 审核不通过

    // 技工审核状态
    const WORKER_STATUS_AUDITING               = 1; // 审核中
    const WORKER_STATUS_AUDIT_PASS             = 2; // 审核通过
    const WORKER_STATUS_AUDIT_NOT_PASS         = 3; // 审核不通过
    const WORKER_STATUS_AUTO_AUDIT_NOT_PASS    = 4; // 7天未审核退回
    const WORKER_STATUS_GROUP_CULL             = 5; // 被剔出群
    const WORKER_STATUS_IN_GROUP = [
        self::WORKER_STATUS_AUDITING,
        self::WORKER_STATUS_AUDIT_PASS
    ];

    // 技工与群的关联
    const WORKER_RELATION_GROUP_OWNER          = 1; // 群主
    const WORKER_RELATION_GROUP_MEMBER         = 2; // 群成员
    const WORKER_RELATION_GROUP_NOT_IN_GROUP   = 3; // 非群内成员

    // 技工身份
    const WORKER_TYPE_ORDINARY_WORKER          = 1; // 普通技工
    const WORKER_TYPE_GROUP_OWNER              = 2; // 群主
    const WORKER_TYPE_GROUP_MEMBER             = 3; // 群成员
    const WORKER_TYPE_JOINING_GROUP            = 4; // 入群审核中
    const WORKER_TYPE_AUDITING_CREATE_GROUP    = 5; // 建群审核中
    const WORKER_TYPE_CREATE_GROUP_FAIL        = 6; // 建群失败
    const WORKER_TYPE_JOIN_GROUP_FAIL          = 7; // 入群失败
    const WORKER_TYPE_REMOVE_TO_GROUP          = 8; // 被剔出群

    // 群关联操作类型
    const GROUP_APPLY_STATUS_NULL              = 0; // 无操作
    const GROUP_APPLY_STATUS_JOIN_AUDITING     = 1; // 入群审核中
    const GROUP_APPLY_STATUS_CREATE_AUDITING   = 2; // 建群审核中

    // 群记录操作人员类型
    const GROUP_RECORD_OPERATOR_TYPE_BY_ADMIN  = 1; // 平台管理员
    const GROUP_RECORD_OPERATOR_TYPE_BY_OWNER  = 2; // 群主
    const GROUP_RECORD_OPERATOR_TYPE_BY_WORKER = 3; // 普通技工
    const GROUP_RECORD_OPERATOR_TYPE_BY_SYSTEM = 4; // 系统

    // 群记录类型
    const GROUP_RECORD_TYPE_CREATE_GROUP         = 1; // 创建群
    const GROUP_RECORD_TYPE_CREATE_GROUP_PASS    = 2; // 审核通过
    const GROUP_RECORD_TYPE_CREATE_GROUP_NO_PASS = 3; // 审核不通过
    const GROUP_RECORD_TYPE_APPLY_JOIN_GROUP     = 4; // 申请加入群
    const GROUP_RECORD_TYPE_ALLOW_JOIN_GROUP     = 5; // 允许加入群
    const GROUP_RECORD_TYPE_NOT_ALLOW_JOIN_GROUP = 6; // 不允许加入群
    const GROUP_RECORD_TYPE_REMOVE_FROM_GROUP    = 7; // 剔出群
    const GROUP_RECORD_TYPE_SYSTEM_AUTO_AUDIT    = 8; // 7天系统自动审核不通过
    const GROUP_RECORD_TYPE_UPDATE_GROUP_NAME    = 9; // 修改群名称

    // 群关联检索返回类型
    const CHECK_TYPE_BY_USER_IS_NORMAL         = '0'; // 用户正常
    const CHECK_TYPE_BY_USER_CREATE_GROUP      = '1'; // 用户能否创建群
    const CHECK_TYPE_BY_USER_JOIN_GROUP        = '2'; // 用户能否加入群
    const CHECK_TYPE_BY_USER_IN_GROUP          = '3'; // 用户已创建/加入群
    const CHECK_TYPE_MSG = [
        '正常',
        '用户未缴齐质保金，无法创建群',
        '用户还有未结算的工单，不能加入网点群',
        '用户已关联某个群，无法创建群或加入群'
    ];

    // 群错误码类型
    const GROUP_ERROR_CODE = [
        ErrorCode::GROUP_IDENTITY_ERROR_WORKER_NOT_IN_GROUP,
        ErrorCode::GROUP_IDENTITY_ERROR_BY_ORDINARY_WORKER,
        ErrorCode::GROUP_IDENTITY_ERROR_BY_GROUP_OWNER,
        ErrorCode::GROUP_IDENTITY_ERROR_BY_GROUP_WORKER,
        ErrorCode::GROUP_IDENTITY_ERROR_BY_AUDITING_JOIN_GROUP,
        ErrorCode::GROUP_IDENTITY_ERROR_BY_AUDITING_CREATE_GROUP,
        ErrorCode::GROUP_IDENTITY_ERROR_BY_CREATE_GROUP_FAIL,
        ErrorCode::GROUP_IDENTITY_ERROR_BY_JOIN_GROUP_FAIL
    ];

    // 工单数量类型
    const ORDER_WORKER_ORDER_NUMBER            = 1; // 群工单总数
    const ORDER_WAIT_APPOINT_ORDER_NUMBER      = 2; // 待预约工单数
    const ORDER_WAIT_SERVICE_ORDER_NUMBER      = 3; // 待服务工单数
    const ORDER_SERVICING_ORDER_NUMBER         = 4; // 服务中工单数
    const ORDER_FINISH_ORDER_NUMBER            = 5; // 已完结工单数

    const ORDER_NUMBER_TYPE = [
        self::ORDER_WORKER_ORDER_NUMBER        => 'worker_order_number',
        self::ORDER_WAIT_APPOINT_ORDER_NUMBER  => 'wait_appoint_order_number',
        self::ORDER_WAIT_SERVICE_ORDER_NUMBER  => 'wait_service_order_number',
        self::ORDER_SERVICING_ORDER_NUMBER     => 'servicing_order_number',
        self::ORDER_FINISH_ORDER_NUMBER        => 'finish_order_number',
    ];
    const WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE = [
        OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT => 'wait_appoint_order_number',
        OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE => 'wait_service_order_number',
        OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE => 'servicing_order_number',
        OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE => 'finish_order_number',
        OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE => 'servicing_order_number'
    ];

    const NUMBER_ADD_WORKER_ORDER_NUMBER       = 1; // 增加技工工单数量
    const NUMBER_REDUCE_WORKER_ORDER_NUMBER    = 2; // 减少技工工单数量

    /*
     * 获取技工当前状态和群id
     */
    public static function getWorkerStatus($worker_id, $type, $group_apply_status)
    {
        if ($type == self::WORKER_TYPE_ORDINARY_WORKER) {
            switch ($group_apply_status) {
                case 0 :
                    $record_info = BaseModel::getInstance('worker_group_record')->getOne([
                        'where' => [
                            'operated_worker_id' => $worker_id,
                            'type'               => ['in', [self::GROUP_RECORD_TYPE_CREATE_GROUP_NO_PASS, self::GROUP_RECORD_TYPE_NOT_ALLOW_JOIN_GROUP, self::GROUP_RECORD_TYPE_SYSTEM_AUTO_AUDIT, self::GROUP_RECORD_TYPE_REMOVE_FROM_GROUP]]
                        ],
                        'order' => 'create_time desc',
                        'field' => 'type, worker_group_id'
                    ]);
                    if (!empty($record_info)) {
                        if ($record_info['type'] == self::GROUP_RECORD_TYPE_CREATE_GROUP_NO_PASS) {
                            $type = self::WORKER_TYPE_CREATE_GROUP_FAIL;
                        } elseif ($record_info['type'] == self::GROUP_RECORD_TYPE_NOT_ALLOW_JOIN_GROUP || $record_info['type'] == self::GROUP_RECORD_TYPE_SYSTEM_AUTO_AUDIT) {
                            $type = self::WORKER_TYPE_JOIN_GROUP_FAIL;
                        } else {
                            $type = self::WORKER_TYPE_REMOVE_TO_GROUP;
                        }
                        $worker_group_id = $record_info['worker_group_id'];
                    } else {
                        $type = self::WORKER_TYPE_ORDINARY_WORKER;
                    }
                    break;
                case 1 :
                    $type = self::WORKER_TYPE_JOINING_GROUP;
                    $worker_group_id = BaseModel::getInstance('worker_group_relation')->getFieldVal([
                        'worker_id' => $worker_id,
                        'status'    => self::WORKER_STATUS_AUDITING
                    ], 'worker_group_id');
                    break;
                case 2 :
                    $worker_group_id = BaseModel::getInstance('worker_group')->getFieldVal([
                        'owner_worker_id' => $worker_id,
                        'status'          => self::GROUP_STATUS_AUDITING
                    ], 'id');
                    $type = self::WORKER_TYPE_AUDITING_CREATE_GROUP;
                    break;
            }
        } else {
            $worker_group_id = BaseModel::getInstance('worker_group_relation')->getFieldVal([
                'worker_id' => $worker_id,
                'status'    => self::WORKER_STATUS_AUDIT_PASS
            ], 'worker_group_id');
        }
        return [
            'type' => (string)$type,
            'worker_group_id' => $worker_group_id ?? null
        ];
    }

    /*
     * 添加群操作记录
     * @param int $worker_group_id 技工群id
     * @param int $type            操作记录类型
     * @param array $extra         额外信息
     *                                |-record_operator_id    操作者用户id
     *                                |-record_operator_type  操作人类型
     *                                |-operated_worker_id    被操作的技工id
     *                                |-content               操作内容
     *                                |-remark                备注
     */
    public static function create($worker_group_id, $type, $extra = [])
    {
        $record_id = BaseModel::getInstance('worker_group_record')->insert([
            'worker_group_id'      => $worker_group_id,
            'record_operator_id'   => $extra['record_operator_id'],
            'record_operator_type' => $extra['record_operator_type'],
            'operated_worker_id'   => $extra['operated_worker_id'],
            'type'                 => $type,
            'create_time'          => NOW_TIME,
            'content'              => $extra['content'],
            'remark'               => $extra['remark'],
        ]);
        return $record_id;
    }

    /*
     * 获取指定时间内技工完成工单数，无时间段默认为当月
     */
    public static function getWorkerMonthFinishOrderNumber($worker_group_id, $worker_id, $user_type = self::WORKER_RELATION_GROUP_OWNER, $time = [])
    {
        if (!empty($time['start_time'])) {
            $where['worker_repair_time'] = ['egt', $time['start_time']];
        }
        if (!empty($time['end_time'])) {
            $where['worker_repair_time'] = ['elt', $time['end_time']];
        }
        if (!empty($time['start_time']) && !empty($time['end_time'])) {
            $where['worker_repair_time'] = $where['worker_repair_time'] = ['between', [$time['start_time'], $time['end_time']]];
        }
        if (empty($time['start_time']) && empty($time['end_time'])) {
            $time['start_time'] = strtotime(date('Y-m-1 00:00:00', time()));
            $time['end_time'] = strtotime(date('Y-m-'.date('t').'23:59:59',time()));
            $where['worker_repair_time'] = ['between', [$time['start_time'], $time['end_time']]];
        }
        if ($user_type == self::WORKER_RELATION_GROUP_OWNER) {
            $where['worker_id'] = $worker_id;
            $where['children_worker_id'] = ['exp', 'is null'];
        } elseif ($user_type == self::WORKER_RELATION_GROUP_MEMBER) {
            $where['children_worker_id'] = $worker_id;
        } else {

        }
        $where['worker_group_id'] = $worker_group_id;
        $where['worker_order_status'] = ['in', OrderService::getOrderCompleteInGroup()];
        $num = BaseModel::getInstance('worker_order')->getNum($where);
        return $num;
    }

    /*
     * 生成群号
     */
    public static function getGroupNo()
    {
        //生成群号，从122331开始，随机+1—99，递增
        if (empty(S('group_no')) || S('group_no') < C('GROUP_NO_START')) {
            S('group_no', C('GROUP_NO_START'));
        }
        $group_no = S('group_no') + rand(1, 99);
        S('group_no', $group_no);
        //检查群号是否重复
        $group_id = BaseModel::getInstance('worker_group')->getFieldVal([
            'group_no' => $group_no
        ], 'id');
        if ($group_id) {
            return self::getGroupNo();
        } else {
            return $group_no;
        }
    }

    /*
     * 获取技工所在的群id
     */
    public static function getGroupId($worker_id)
    {
        return BaseModel::getInstance('worker_group_relation')->getFieldVal([
            'worker_id' => $worker_id,
            'status'    => GroupService::WORKER_STATUS_AUDIT_PASS
        ], 'worker_group_id');
    }

    /*
     * 获取某个群的群主id
     */
    public static function getOwnerId($worker_group_id)
    {
        return BaseModel::getInstance('worker_group')->getFieldVal([
            'id'     => $worker_group_id,
            'status' => self::GROUP_STATUS_AUDIT_PASS
        ], 'owner_worker_id');
    }

    /*
     * 检查技工是否在群内
     */
    public static function checkWorkerInGroup($worker_group_id, $user_id)
    {
        if (!BaseModel::getInstance('worker_group_relation')->dataExist([
            'where' => [
                'worker_id'       => $user_id,
                'worker_group_id' => $worker_group_id,
                'status'          => self::WORKER_STATUS_AUDIT_PASS,
                'user_type'       => ['in', [self::WORKER_RELATION_GROUP_OWNER, self::WORKER_RELATION_GROUP_MEMBER]]
            ]
        ])) {
            return false;
        }
        return true;
    }

    /*
     * 根据操作记录自动调整工单数量
     */
    public static function autoUpdateOrderNumber($worker_order_id, $operation_type, $original_handle_worker_id = null, $original_handle_children_worker_id = null, $original_worker_order_status = null, $original_handle_group_id = null)
    {
        if (!in_array($operation_type, [
            OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE,
            OrderOperationRecordService::WORKER_APPOINT_SUCCESS,
            OrderOperationRecordService::WORKER_SIGN_SUCCESS,
//            OrderOperationRecordService::WORKER_APPLY_ACCESSORY,
//            OrderOperationRecordService::WORKER_APPLY_COST,
            OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT,
            OrderOperationRecordService::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED,
            OrderOperationRecordService::WORKER_OWNER_DISTRIBUTE_ORDER,
            OrderOperationRecordService::WORKER_RETURN_ORDER_TO_OWNER,
            OrderOperationRecordService::WORKER_RETURN_ORDER,
            OrderOperationRecordService::CS_CANCEL_ORDER,
            OrderOperationRecordService::FACTORY_CANCEL_ORDER,
            OrderOperationRecordService::CS_ORDER_STOP,
        ])) {
            return;
        }
        $order_info = BaseModel::getInstance('worker_order')->getOne([
            'where' => [
                'id' => $worker_order_id
            ],
            'field' => 'worker_id, worker_group_id, worker_order_status, children_worker_id'
        ]);
        if (!empty($order_info['worker_group_id']) || !empty($original_handle_group_id)) {
            self::updateOrderNumber($operation_type, $order_info, $original_handle_worker_id, $original_handle_children_worker_id, $original_worker_order_status, $original_handle_group_id);
        }
    }

    /*
     * 更新工单数量
     * 客服派单，技工待预约工单数+1；群接单总数+1，群待预约工单数+1
     * 技工预约，技工待预约工单数-1，待服务工单数+1；群待预约工单数-1，群待服务工单数+1
     * 技工首次签到，技工待服务工单数-1，服务中工单数+1；群待服务工单数-1，群服务中工单数+1
     * 技工上传完成服务报告，技工服务中工单数-1，已完成工单数+1；群服务中工单数-1，已完成工单数+1
     * 客服回访不通过，技工已完成工单数-1，服务中工单数+1；群已完成工单数-1，群服务中工单数+1
     * 群主派发/改派工单给群成员，群成员对应状态的工单数+1；原群成员对应状态的工单数-1
     * 群成员退回工单，对应状态的工单数-1
     * 群主退回工单，群主(和对应技工)对应状态的工单数-1；群对应状态工单数-1
     * 客服改派工单，群主(和对应技工)对应状态的工单数-1；群对应状态工单数-1
     * 客服/厂家取消工单，群主(和对应技工)对应状态的工单数-1；群对应状态工单数-1
     */
    public static function updateOrderNumber($operation_type, $order_info, $original_handle_worker_id = null, $original_handle_children_worker_id = null, $original_worker_order_status = null, $original_handle_group_id = null)
    {
        $group_model    = BaseModel::getInstance('worker_group');
        $relation_model = BaseModel::getInstance('worker_group_relation');
        $group_where = [
            'id' => $order_info['worker_group_id']
        ];
        $relation_where = [
            'worker_id'       => $order_info['worker_id'],
            'worker_group_id' => $order_info['worker_group_id']
        ];
        $children_relation_where = [
            'worker_id'       => $order_info['children_worker_id'],
            'worker_group_id' => $order_info['worker_group_id']
        ];
        switch ($operation_type) {
            case OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE :
                //客服派单 群接单总数+1;群待预约工单数+1;群主待预约工单数+1;
                $group_model->setNumInc($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_WORKER_ORDER_NUMBER]);
                $group_model->setNumInc($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_APPOINT_ORDER_NUMBER]);
                $relation_model->setNumInc($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_APPOINT_ORDER_NUMBER]);
                if (!empty($original_handle_group_id)) {
                    //客服重新派单 群对应状态工单数-1；原技工对应状态工单数-1/群主对应状态工单数-1；
                    $group_id = $original_handle_group_id;
                    $group_model->setNumDec(['id' => $group_id], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                    if (!empty($original_handle_children_worker_id)) {
                        $relation_model->setNumDec([
                            'worker_id'       => $original_handle_children_worker_id,
                            'worker_group_id' => $group_id
                        ], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                    } else {
                        $relation_model->setNumDec([
                            'worker_id'       => $original_handle_worker_id,
                            'worker_group_id' => $group_id
                        ], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                    }
                }
                break;
            case OrderOperationRecordService::WORKER_APPOINT_SUCCESS :
                //技工待预约工单数-1，待服务工单数+1/群主待预约工单数-1，群主待服务工单数+1;群待预约工单数-1，群待服务工单数+1
                $group_model->setNumInc($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_SERVICE_ORDER_NUMBER]);
                $group_model->setNumDec($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_APPOINT_ORDER_NUMBER]);
                if (!empty($order_info['children_worker_id'])) {
                    $relation_model->setNumInc($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_SERVICE_ORDER_NUMBER]);
                    $relation_model->setNumDec($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_APPOINT_ORDER_NUMBER]);
                } else {
                    $relation_model->setNumInc($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_SERVICE_ORDER_NUMBER]);
                    $relation_model->setNumDec($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_APPOINT_ORDER_NUMBER]);
                }
                break;
            case OrderOperationRecordService::WORKER_SIGN_SUCCESS :
            //case OrderOperationRecordService::WORKER_APPLY_ACCESSORY :
            //case OrderOperationRecordService::WORKER_APPLY_COST :
                //技工首次签到 技工/群主待服务工单数-1，服务中工单数+1；群待服务工单数-1，群服务中工单数+1
                $group_model->setNumInc($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                $group_model->setNumDec($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_SERVICE_ORDER_NUMBER]);
                if (!empty($order_info['children_worker_id'])) {
                    $relation_model->setNumInc($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                    $relation_model->setNumDec($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_SERVICE_ORDER_NUMBER]);
                } else {
                    $relation_model->setNumInc($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                    $relation_model->setNumDec($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_WAIT_SERVICE_ORDER_NUMBER]);
                }
                break;
            case OrderOperationRecordService::WORKER_SUBMIT_PRODUCT_REPORT :
                //技工上传完成服务报告 技工/群主服务中工单数-1，已完成工单数+1；群服务中工单数-1，已完成工单数+1;
                $group_model->setNumInc($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                $group_model->setNumDec($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                if (!empty($order_info['children_worker_id'])) {
                    $relation_model->setNumInc($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                    $relation_model->setNumDec($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                } else {
                    $relation_model->setNumInc($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                    $relation_model->setNumDec($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                }
                break;
            case OrderOperationRecordService::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED :
                //客服回访不通过 技工/群主已完成工单数-1，服务中工单数+1；群已完成工单数-1，群服务中工单数+1;
                $group_model->setNumInc($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                $group_model->setNumDec($group_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                if (!empty($order_info['children_worker_id'])) {
                    $relation_model->setNumInc($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                    $relation_model->setNumDec($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                } else {
                    $relation_model->setNumInc($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_SERVICING_ORDER_NUMBER]);
                    $relation_model->setNumDec($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                }
                break;
            case OrderOperationRecordService::WORKER_OWNER_DISTRIBUTE_ORDER :
                //群主派发工单给群成员 群成员对应状态的工单数+1；
                if (empty($order_info['children_worker_id'])) {
                    //$order_info['children_worker_id']为空，工单派回给群主
                    $relation_model->setNumInc($relation_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$order_info['worker_order_status']]);
                } else {
                    $relation_model->setNumInc($children_relation_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$order_info['worker_order_status']]);
                }
                if (!empty($original_handle_children_worker_id)) {
                    //群主改派 原群成员对应状态的工单数-1
                    $relation_model->setNumDec([
                        'worker_id'       => $original_handle_children_worker_id,
                        'worker_group_id' => $order_info['worker_group_id']
                    ], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$order_info['worker_order_status']]);
                } else {
                    //群主直接派发 群主对应状态的工单数-1
                    $relation_model->setNumDec($relation_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$order_info['worker_order_status']]);
                }
                break;
            case OrderOperationRecordService::WORKER_RETURN_ORDER_TO_OWNER :
                //群成员退回工单 对应状态的工单数-1,群主对应状态工单数+1
                $relation_model->setNumDec([
                    'worker_id'       => $original_handle_children_worker_id,
                    'worker_group_id' => $order_info['worker_group_id']
                ], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$order_info['worker_order_status']]);
                $relation_model->setNumInc($relation_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$order_info['worker_order_status']]);
                break;
            case OrderOperationRecordService::WORKER_RETURN_ORDER :
            case OrderOperationRecordService::CS_CANCEL_ORDER :
            case OrderOperationRecordService::FACTORY_CANCEL_ORDER :
                //群主退回工单 客服/厂家取消工单 群主对应状态的工单数-1；群对应状态工单数-1
                $group_id = self::getGroupId($original_handle_worker_id);
                $group_model->setNumDec(['id' => $group_id], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                if (!empty($original_handle_children_worker_id)) {
                    //负责技工对应状态的工单数-1
                    $relation_model->setNumDec([
                        'worker_id'       => $original_handle_children_worker_id,
                        'worker_group_id' => $group_id
                    ], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                } else {
                    $relation_model->setNumDec([
                        'worker_id'       => $original_handle_worker_id,
                        'worker_group_id' => $group_id
                    ], self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                }
                break;
            case OrderOperationRecordService::CS_ORDER_STOP :
                //客服终止工单 群对应状态工单数-1 群已完成工单数+1;技工对应状态的工单数-1 已完成工单数+1
                $group_model->setNumDec($group_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                $group_model->setNumInc($group_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE]);
                if (!empty($original_handle_children_worker_id)) {
                    $relation_model->setNumDec($children_relation_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                    $relation_model->setNumInc($children_relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                } else {
                    $relation_model->setNumDec($relation_where, self::WORKER_ORDER_STATUS_CORRESPONDING_ORDER_NUMBER_TYPE[$original_worker_order_status]);
                    $relation_model->setNumInc($relation_where, self::ORDER_NUMBER_TYPE[self::ORDER_FINISH_ORDER_NUMBER]);
                }
                break;
            default :
                break;
        }
    }

}