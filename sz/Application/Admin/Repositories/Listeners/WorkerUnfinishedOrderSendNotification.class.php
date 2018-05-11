<?php
/**
 * File: WorkerUnfinishedOrderSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 10:14
 */

namespace Admin\Repositories\Listeners;

use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkerUnfinishedOrderEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class WorkerUnfinishedOrderSendNotification implements ListenerInterface
{

    /**
     * @param WorkerUnfinishedOrderEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $info = BaseModel::getInstance('worker_order_detail')
            ->getOneOrFail(['order_detail_id' => $event->data['order_detail_id']], 'worker_order_id,servicepro_desc,servicebrand_desc,model');

        $order = BaseModel::getInstance('worker_order')->getOneOrFail($info['worker_order_id'], 'servicetype,add_member_id,worker_id,datetime,order_type');
        $user_info = BaseModel::getInstance('worker_order_user_info')
            ->getOneOrFail(
                [
                    'where' => ['order_id' => $info['worker_order_id']],
                    'join' => 'LEFT JOIN wx_user ON wx_user.id=worker_order_user_info.wx_user_id',
                ],
                'openid,wx_user_id'
            );
        $open_id = $user_info['openid'];
        if (!$open_id) {
            return ;
        }

        $admin_info = BaseModel::getInstance('worker_order_operation_record')
            ->getOneOrFail([
                'field' => 'user_name,tell_out',
                'where' => ['order_id' => $info['worker_order_id'], 'ope_type' => 'SH'],   // SH为客服接单
                'join' => [
                    'LEFT JOIN admin ON admin.id=worker_order_operation_record.ope_user_id'
                ],
                'order' => 'worker_order_access.id DESC',
                'limit' => 1
            ]);

        $servicetype = '';
        if ($order['servicetype'] == 106) {
            $servicetype = '上门安装';
        } elseif ($order['servicetype'] == 107) {
            $servicetype = '上门维修';
        } elseif ($order['servicetype'] == 108) {
            $servicetype = '上门维护';
        } elseif ($order['servicetype'] == 109) {
            $servicetype = '用户送修';
        } elseif ($order['servicetype'] == 110) {
            $servicetype = '预发件安装';
        }

        // 检查产品是保内还是保外
        $order_detail_info = BaseModel::getInstance('worker_order_detail')
            ->getOneOrFail(['worker_order_id' => $info['worker_order_id']], 'product_id');
        $user_product_info = BaseModel::getInstance('wx_user_product')
            ->getOneOrFail(
                [
                    'where' => [
                        'wx_user_id' => $user_info['wx_user_id'],
                        'wx_product_id' => $order_detail_info['product_id']
                    ],
                    'order' => 'id DESC',
                ]
            );
        $product_excel_info = D('FactoryExcel')->getExcelDataByMyPidOrFail($user_product_info['md5code']);
        // 计算质保时间
        // $time = get_limit_date($product_excel_info['active_time'], $product_excel_info['zhibao_time']);
        // if ($time > $order['datetime']) {
        if (isInWarrantPeriod($order['order_type'])) {
            $notification = "【产品返厂】尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单无法完成维修，产品需要返厂。维修费请与师傅当面结算。如果对我们的服务有任何意见和建议，请与您的客服联系:{$admin_info['user_name']}-{$admin_info['tell_out']}。神州联保祝您生活愉快！";
        } else {
            $notification = "【产品返厂】尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单无法完成维修，产品需要返厂。如果对我们的服务有任何意见和建议，请与您的客服联系:{$admin_info['user_name']}-{$admin_info['tell_out']}。神州联保祝您生活愉快！";
        }

        D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($open_id, $notification, 'text');
    }

}
