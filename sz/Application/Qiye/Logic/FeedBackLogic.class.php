<?php
/**
* @User 嘉诚
* @Date 2017/11/13
* @mess 订单
*/
namespace Qiye\Logic;

use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Library\Common\Util;
use Common\Common\Service\OrderOperationRecordService;

class FeedBackLogic extends BaseLogic
{
    public function add($request, $user_id)
    {
        if (!in_array($request['type'], [1, 2, 3, 4])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'type类型错误');
        }
        $phone = BaseModel::getInstance('worker')->getFieldVal([
            'worker_id' => $user_id
        ], 'worker_telephone');
        $add = [
            'worker_id' => $user_id,
            'phone'     => !empty($phone) ? $phone : '',
            'type'      => $request['type'],
            'content'   => $request['content'],
            'status'    => 0,
            'addtime'   => NOW_TIME,
        ];
        BaseModel::getInstance('worker_feedback')->insert($add);
    }

    public function getList($user_id)
    {
        $where = [
            'worker_id' => $user_id
        ];
        $opt = [
            'where' => $where,
            'limit' => getPage(),
            'field' => 'id, worker_id, type, status, content, addtime as create_time',
            'order' => 'addtime DESC',
        ];
        $model = BaseModel::getInstance('worker_feedback');
        $count = $model->getNum($where);
        $list  = $count ? $model->getList($opt) : [];

        return $this->paginate((array)$list, $count);
    }

    public function detail()
    {
        $id = I('get.id', 0, 'intval');
        $field = 'id, worker_id, type, status, content, reply, customer_id, addtime as create_time, reply_time';
        $info = BaseModel::getInstance('worker_feedback')->getOneOrFail($id, $field);

        if ($info['customer_id']) {
            $info['customer_name'] = BaseModel::getInstance('admin')->getFieldVal($info['customer_id'], 'user_name');
        }
        return $info;
    }

}
