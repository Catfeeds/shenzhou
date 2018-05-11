<?php
/**
* @User 嘉诚
* @Date 2017/11/13
* @mess 订单
*/
namespace Qiye\Logic;

use Common\Common\Service\WorkerAnnouncementService;
use EasyWeChat\Comment\Comment;
use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Common\Common\Service\AppMessageService;
use Library\Common\Util;

class MessageLogic extends BaseLogic
{
    /*
     * 消息列表
     */
    public function getList($request, $user_id)
    {
        $model = BaseModel::getInstance('worker_notification');
        $where['worker_id'] = $user_id;
        $where['create_time'] = ['gt', strtotime(C('VERSION_3_0_ONLINE_DATE'))];
        if ($request['type'] == AppMessageService::TYPE_ALL_MASSAGE) {
            $where['type'] = ['in', AppMessageService::getStr($request['message_type'])];
        } elseif ($request['type'] == AppMessageService::TYPE_CASH_MASSAGE) {
            $where['type'] = ['in', AppMessageService::getCashMessageStr()];
        } elseif ($request['type'] == AppMessageService::TYPE_BACKSTAGE_OTHER_MASSAGE) {
            $where['type'] = ['in', AppMessageService::getTransactionOtherStr()];
        } elseif ($request['type'] == AppMessageService::TYPE_WORKER_ORDER_OTHER_MASSAGE) {
            $where['type'] = ['in', AppMessageService::getWorkerOrderOtherStr()];
        } elseif ($request['type'] == AppMessageService::TYPE_OTHER_ACCESSORY_MESSAGE) {
            $where['type'] = ['in', AppMessageService::getAccessoryOtherStr()];
        } else {
            $where['type'] = $request['type'];
        }
        $count = $model->getNum($where);
        $list = $model->getList([
            'where' => $where,
            'field' => 'id, data_id, title, content, create_time, type, worker_id, describe, is_read',
            'order' => 'create_time desc',
            'limit' => $this->page()
        ]);
        return $this->paginate($list, $count);
    }

    /*
     * 系统消息列表
     */
    public function getAnnouncementList($request, $user_id)
    {
        $model = BaseModel::getInstance('worker_announcement');
        $where['a.type'] = ['in', AppMessageService::systemAnnouncementStr($request['message_type'])];
        $where['a.is_show'] = 1;
        $count = $model->getNum([
            'alias' => 'a',
            'where' => $where
        ]);
        $list = $model->getList([
            'alias' => 'a',
            'where' => $where,
            'join'  => 'left join worker_announcement_read as ar on ar.worker_id='.$user_id.' and ar.announcement_id=a.id',
            'field' => 'a.id, a.title, a.summary as content, a.summary, a.addtime as create_time, case a.type when 1 then 101 when 2 then 601 when 3 then 102 end as type, case when ar.announcement_id > 0 then 1 else 0 end as is_read',
            'order' => 'addtime desc',
            'limit' => $this->page()
        ]);
        foreach ($list as $k => $v) {
            $list[$k]['url'] = '#/app/manual-content/'.$v['type'].'/'.$v['id'];
        }
        return $this->paginate($list, $count);
    }

    /*
     * 消息详情
     */
    public function detail($id, $user_id)
    {
        $model = BaseModel::getInstance('worker_notification');
        $detail = $model->getOne([
            'where' => [
                'id' => $id,
                'worker_id' => $user_id
            ],
            'field' => 'id, data_id, title, content, create_time, is_read'
        ]);
        if ($detail['is_read'] == '0') {
            $model->update([
                'id' => $id
            ], [
                'is_read' => 1
            ]);
        }
        //return $detail;
    }

    /*
     * 系统消息详情
     */
    public function systemAnnouncementDetail($id, $user_id)
    {
        $data = BaseModel::getInstance('worker_announcement_read')->getOne([
            'where' => [
                'announcement_id' => $id,
                'worker_id' => $user_id
            ]
        ]);
        if (!$data) {
            BaseModel::getInstance('worker_announcement_read')->insert([
                'announcement_id' => $id,
                'worker_id' => $user_id
            ]);
        }
        $detail = BaseModel::getInstance('worker_announcement')->getOne([
            'where' => [
                'id' => $id
            ],
            'field' => 'id, title, content, summary, addtime as create_time'
        ]);
        $detail['content'] = Util::buildImgTagSource($detail['content']);
        return $detail;
    }

    /*
     * 获取未读数
     */
    //public function unreadCount($user_id)
    //{
    //    $worker_notification_model = BaseModel::getInstance('worker_notification');
    //    $system_message_model = BaseModel::getInstance('worker_announcement');
    //    $worker_read_model = BaseModel::getInstance('worker_announcement_read');
    //    $type = 1;
    //    while ($type < 7) {
    //        if ($type == 1 || $type == 6) {
    //            $announcement_ids = $system_message_model->getFieldVal([
    //                'type' => ['in', AppMessageService::systemAnnouncementStr($type)],
    //                'is_show' => 1
    //            ], 'id', true);
    //            $announcement_count = count($announcement_ids);
    //            $announcement_ids = !empty($announcement_ids) ? implode(',', $announcement_ids) : '0';
    //            $read_count = $worker_read_model->getNum([
    //                'worker_id' => $user_id,
    //                'announcement_id' => ['in', $announcement_ids]
    //            ]);
    //            $data['unread_count_type'.$type] = (string)($announcement_count - $read_count);
    //        } else {
    //            $data['unread_count_type'.$type] = $worker_notification_model->getNum([
    //                'worker_id' => $user_id,
    //                'type' => ['in', AppMessageService::getStr($type)],
    //                'is_read' => 0,
    //                'create_time' => ['gt', strtotime(C('VERSION_3_0_ONLINE_DATE'))]
    //            ]);
    //        }
    //        $type++;
    //    }
    //    return $data;
    //}

    public function unreadCount($user_id)
    {
        $worker_notification_model = BaseModel::getInstance('worker_notification');
        $system_message_model = BaseModel::getInstance('worker_announcement');
        $worker_read_model = BaseModel::getInstance('worker_announcement_read');

        //业务通告未读数
        $type = AppMessageService::MESSAGE_TYPE_BACKSTAGE_MESSAGE;
        $announcement_ids = $system_message_model->getFieldVal([
            'type'    => ['in', AppMessageService::systemAnnouncementStr($type)],
            'is_show' => WorkerAnnouncementService::IS_SHOW_YES,
        ], 'id', true);
        $announcement_count = count($announcement_ids);
        $announcement_ids = empty($announcement_ids) ? '-1' : $announcement_ids;
        $read_count = $worker_read_model->getNum([
            'worker_id'       => $user_id,
            'announcement_id' => ['in', $announcement_ids],
        ]);
        $data['unread_count_type'.$type] = (string)($announcement_count - $read_count);

        //交易通知未读数
        $type = AppMessageService::MESSAGE_TYPE_TRANSACTION_MESSAGE;
        $data['unread_count_type' . $type] = $worker_notification_model->getNum([
            'worker_id'   => $user_id,
            'type'        => ['in', AppMessageService::getStr($type)],
            'is_read'     => 0,
            'create_time' => ['gt', strtotime(C('VERSION_3_0_ONLINE_DATE'))],
        ]);

        //工单消息未读数
        $type = AppMessageService::MESSAGE_TYPE_WORKER_ORDER_MESSAGE;
        $data['unread_count_type' . $type] = $worker_notification_model->getNum([
            'worker_id'   => $user_id,
            'type'        => ['in', AppMessageService::getStr($type)],
            'is_read'     => 0,
            'create_time' => ['gt', strtotime(C('VERSION_3_0_ONLINE_DATE'))],
        ]);

        //配件单消息未读数
        $type = AppMessageService::MESSAGE_TYPE_ACCESSORY_MESSAGE;
        $data['unread_count_type' . $type] = $worker_notification_model->getNum([
            'worker_id'   => $user_id,
            'type'        => ['in', AppMessageService::getStr($type)],
            'is_read'     => 0,
            'create_time' => ['gt', strtotime(C('VERSION_3_0_ONLINE_DATE'))],
        ]);

        //费用单消息未读数
        $type = AppMessageService::MESSAGE_TYPE_COST_MESSAGE;
        $data['unread_count_type' . $type] = $worker_notification_model->getNum([
            'worker_id'   => $user_id,
            'type'        => ['in', AppMessageService::getStr($type)],
            'is_read'     => 0,
            'create_time' => ['gt', strtotime(C('VERSION_3_0_ONLINE_DATE'))],
        ]);

        //接单必读未读数
        $type = AppMessageService::MESSAGE_TYPE_ORDER_MESSAGE;
        $announcement_ids = $system_message_model->getFieldVal([
            'type'    => ['in', AppMessageService::systemAnnouncementStr($type)],
            'is_show' => WorkerAnnouncementService::IS_SHOW_YES,
        ], 'id', true);
        $announcement_count = count($announcement_ids);
        $announcement_ids = empty($announcement_ids) ? '-1' : $announcement_ids;
        $read_count = $worker_read_model->getNum([
            'worker_id'       => $user_id,
            'announcement_id' => ['in', $announcement_ids],
        ]);
        $data['unread_count_type'.$type] = (string)($announcement_count - $read_count);

        //投诉消息未读数
        $type = AppMessageService::MESSAGE_TYPE_COMPLAINT;
        $data['unread_count_type' . $type] = $worker_notification_model->getNum([
            'worker_id'   => $user_id,
            'type'        => ['in', AppMessageService::getStr($type)],
            'is_read'     => 0,
            'create_time' => ['gt', strtotime(C('VERSION_3_0_ONLINE_DATE'))],
        ]);

        return $data;
    }

    /*
     * 设为已读
     */
    public function setRead($request, $user_id)
    {
        if (!isset($request['type']) || !isset($request['message_type'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        if (in_array($request['message_type'], [AppMessageService::MESSAGE_TYPE_BACKSTAGE_MESSAGE, AppMessageService::MESSAGE_TYPE_ORDER_MESSAGE])) {
            //系统消息
            $model = BaseModel::getInstance('worker_announcement');
            $read_model = BaseModel::getInstance('worker_announcement_read');
            $announcement_ids = $model->getFieldVal([
                'type' => ['in', AppMessageService::systemAnnouncementStr($request['message_type'])],
                'is_show' => 1
            ], 'id', true);
            if (!empty($announcement_ids)) {
                foreach ($announcement_ids as $v) {
                    if (!$read_model->dataExist([
                        'announcement_id' => $v,
                        'worker_id' => $user_id
                    ]))
                    $data[] = [
                        'announcement_id' => $v,
                        'worker_id' => $user_id
                    ];
                }
                if (!empty($data)) {
                    $read_model->insertAll($data);
                }
            }
        } else {
            $model = BaseModel::getInstance('worker_notification');
            $where['worker_id'] = $user_id;
            if ($request['type'] == AppMessageService::TYPE_ALL_MASSAGE) {
                $where['type'] = ['in', AppMessageService::getStr($request['message_type'])];
            } elseif ($request['type'] == AppMessageService::TYPE_CASH_MASSAGE) {
                $where['type'] = ['in', AppMessageService::getCashMessageStr()];
            } elseif ($request['type'] == AppMessageService::TYPE_BACKSTAGE_OTHER_MASSAGE) {
                $where['type'] = ['in', AppMessageService::getTransactionOtherStr()];
            } elseif ($request['type'] == AppMessageService::TYPE_WORKER_ORDER_OTHER_MASSAGE) {
                $where['type'] = ['in', AppMessageService::getWorkerOrderOtherStr()];
            } elseif ($request['type'] == AppMessageService::TYPE_OTHER_ACCESSORY_MESSAGE) {
                $where['type'] = ['in', AppMessageService::getAccessoryOtherStr()];
            } else {
                $where['type'] = $request['type'];
            }
            $model->update($where, [
                'is_read' => 1
            ]);
        }
    }


}
