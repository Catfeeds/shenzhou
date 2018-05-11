<?php
/**
 * File: WorkerFinishedOrderSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 9:35
 */

namespace Admin\Repositories\Listeners;

use Admin\Logic\WeChatNewsEventLogic;
use Admin\Model\BaseModel;
use Admin\Model\FactoryExcelModel;
use Admin\Repositories\Events\WorkerFinishedOrderEvent;
use Common\Common\Logic\Sms\SmsServerLogic;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Library\Common\Util;

class WorkerFinishedOrderSendNotification implements ListenerInterface
{
    /**
     * @param $event
     */
    public function handle(EventAbstract $event)
    {
        $order = BaseModel::getInstance('worker_order')->getOneOrFail($event->data['order_id'], 'servicetype,add_member_id,worker_id,datetime,tell,order_origin');

        $info = BaseModel::getInstance('worker_order_detail')
            ->getOneOrFail(['worker_order_id' => $event->data['order_id']], 'worker_order_id,servicepro_desc,servicebrand_desc,model,code');
        $user_info = BaseModel::getInstance('wx_user')
            ->getOneOrFail(
               ['telephone' => $order['tell']]
            );
        $open_id = $user_info['openid'];

        $admin_info = BaseModel::getInstance('worker_order_operation_record')
            ->getOneOrFail([
                'field' => 'user_name,tell_out',
                'where' => ['order_id' => $info['worker_order_id'], 'ope_type' => 'SH'], // SH为客服接单
                'join' => [
                    'LEFT JOIN admin ON admin.id=worker_order_operation_record.ope_user_id'
                ],
                'order' => 'worker_order_operation_record.id DESC',
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

        if ($order['order_origin'] == 'F') {
            $message = "您的{$info['servicebrand_desc']}{$info['servicepro_desc']}{$servicetype}已完成，对我们的服务有任何意见或建议，欢迎致电4008309995。感谢使用我们的服务，送您30元代金券，豪华电陶炉用券后只需99元，关注“神州聚惠”微信验证您的手机号即可领取，数量有限先到先得！";
            $add_data = [
                'table_id' => 0,
                'phone'    => $order['tell'],
                'content'  => $message,
                'type'     => 98,
            ];
            $add_datas[] = $add_data;
            (new SmsServerLogic('queue_message', true))->addTemporary($add_datas);
        } else {
            // 不是厂家后台下单才有code
            $md5_code = D('WorkerOrderDetail')->codeToMd5Code($info['code']);
            $product_excel_info = (new FactoryExcelModel())->getExcelDataByMyPidOrFail($md5_code);

            // 计算质保时间
            // $time = get_limit_date($product_excel_info['active_time'], $product_excel_info['zhibao_time']);
            // if ($time > $order['datetime'] || !$product_excel_info['zhibao_time'] || !$product_excel_info) {
            if (isInWarrantPeriod($order['order_type'])) {
                // 保内
                $notification = "【维修完成】尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单已完成维修，如果对我们的服务有任何意见和建议，请与您的客服联系:{$admin_info['user_name']}-{$admin_info['tell_out']}。神州联保祝您生活愉快！";
            } else {
                // 保外
                $notification = "【维修完成】尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单已完成维修，维修费请与师傅当面结算。如果对我们的服务有任何意见和建议，请与您的客服联系:{$admin_info['user_name']}-{$admin_info['tell_out']}。神州联保祝您生活愉快！";
            }
            $logic = new WeChatNewsEventLogic();
            $logic->wxSendNewsByOpenId($open_id, $notification, 'text');

            $news_list[] = [
                'title' => '送您30元代金劵，无需注册，一键到账',
                'description' => '恭喜您收到一个红包！！！',
                'image' => Util::getServerFileUrl('/Public/shop_news_image.jpg'),
                'url' => 'http://mp.weixin.qq.com/s?__biz=MzA3OTYwMTYwMA==&mid=2648558398&idx=1&sn=add3082865bfbed4caaac189df9c2daf&chksm=87986191b0efe887c6092fdb72bec128b6746d1350095a467e064f4c37aa4f9bcf827c167fed&mpshare=1&scene=1&srcid=0315ClvR55R516S4eppvHECy#rd'
            ];

            $logic->wxSendNewsByOpenId($open_id, $news_list, 'news');
        }
    }
}
