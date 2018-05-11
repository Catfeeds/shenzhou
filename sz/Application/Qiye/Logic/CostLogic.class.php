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
use Common\Common\Service\CostRecordService;
use Common\Common\Service\SystemMessageService;
use Common\Common\Service\OrderService;

class CostLogic extends BaseLogic
{
    /*
     * 费用单列表
     */
    public function getList($order_id, $user_id)
    {
        $model = BaseModel::getInstance('worker_order_apply_cost');
        $list = $model->getList([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => 'id, apply_cost_number, type, fee, status',
            'order' => 'create_time desc'
        ]);
        return $list;
    }

    /*
     * 费用单详情
     */
    public function detail($id, $user_id)
    {
        $model = BaseModel::getInstance('worker_order_apply_cost');
        $detail = $model->getOne([
            'where' => [
                'id' => $id
            ],
            'field' => 'id, apply_cost_number, type, fee, status, create_time, imgs, factory_check_remark, admin_check_remark, reason'
        ]);
        if (!empty($detail['imgs'])) {
            if (strpos($detail['imgs'], 'quot;')) {
                $detail['imgs'] = html_entity_decode($detail['imgs']);
            }
            $imgs = json_decode($detail['imgs'], true);
            unset($detail['imgs']);
            foreach ($imgs as $v) {
                $detail['imgs'][] = Util::getServerFileUrl($v['url']);
            }
        } else {
            $detail['imgs'] = null;
        }
        $strpos = strpos($detail['factory_check_remark'], '<img');
        $detail['factory_check_remark'] = substr($detail['factory_check_remark'], 0, $strpos);
        return $detail;
    }

    /*
     * 费用单申请
     */
    public function add($order_id, $request, $user_id)
    {
        if ($request['type'] == '5' && empty($request['remark'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '备注不能为空');
        }
        $order_info = $this->checkWorkerOrder($order_id, $user_id, '*', [
            'worker_order_status' => ['egt', OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT]
        ]);
        //开启事务
        M()->startTrans();

        //检查最后一次预约是否已经签到
        $this->checkLastAppoint($order_id, $order_info['worker_id']);

        $data = [
            'factory_id'        => $order_info['factory_id'],
            'worker_order_product_id' => $request['product_id'],
            'worker_id'         => $order_info['worker_id'],
            'worker_order_id'   => $order_id,
            'apply_cost_number' => $this->genCrNo(),
            'type'              => $request['type'],
            'reason'            => !empty($request['remark']) ? $request['remark'] : '',
            'fee'               => $request['fee'],
            'imgs'              => !empty($request['imgs']) ? html_entity_decode(html_entity_decode($request['imgs'])) : '',
            'create_time'       => NOW_TIME,
            'last_update_time'  => NOW_TIME,
            'status'            => 0
        ];
        $cost_id = BaseModel::getInstance('worker_order_apply_cost')->insert($data);

        BaseModel::getInstance('worker_order_statistics')->setNumInc(['worker_order_id' => $order_id], 'cost_order_num');

        //添加操作记录
        OrderOperationRecordService::create($order_id, OrderOperationRecordService::WORKER_APPLY_COST, [
            'operator_id' => $user_id,
            'content_replace' => [
                'cost_number' => $data['apply_cost_number'],
            ],
            'remark' => $data['reason'].$this->handleImage($data['imgs']),
            'see_auth' => OrderOperationRecordService::PERMISSION_CS | OrderOperationRecordService::PERMISSION_WORKER
        ]);
        $content = '工单号'.$order_info['orno'].'申请费用'.$data['fee'].'元';
        CostRecordService::create($cost_id, CostRecordService::TYPE_WORKER_APPLY, $content, $data['reason']);

        //后台推送
        D('Accessory', 'Logic')->sendAdminMessage($order_info, $cost_id, $content, SystemMessageService::MSG_TYPE_ADMIN_COST_WORKER_APPLY);
        //  结束事务
        M()->commit();
    }

    /*
     * 生成费用单号
     */
    public function genCrNo(){
        //获取毫秒数（时间戳）
        list($t1, $t2) = explode(' ', microtime());

        $microtime =  (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);

        $microStr   = substr($microtime,7,6);

        $timeStr = date('ymd',time());

        $crno = $timeStr.$microStr;

        $id = BaseModel::getInstance('worker_order_apply_cost')->getFieldVal([
            'apply_cost_number' => $crno
        ], 'id');

        if(!empty($id)){
            return $this->genCrNo();
        } else {
            return $crno;
        }
    }

}
