<?php
/**
 * File: DealerActivatedSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 14:46
 */

namespace Admin\Repositories\Listeners;

use Admin\Model\BaseModel;
use Admin\Repositories\Events\DealerActivatedEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class DealerActivatedSendNotification implements ListenerInterface
{

    /**
     * @param DealerActivatedEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $dealer = BaseModel::getInstance('factory_product_white_list')->getOneOrFail($event->data['id']);
        $open_id = D('User')->getFieldVal(['telephone' => $dealer['user_name']], 'openid');
        if (!$open_id) {
            return ;
        }
        $factory_info = BaseModel::getInstance('factory')->getOne($dealer['factory_id'], 'factory_full_name,linkphone');
        if ($event->data['is_use'] == 0) {
            $notification = "您申请的{$factory_info['factory_full_name']}的质保激活授权已通过，现在您可以为{$factory_info['factory_full_name']}的产品登记质保了。";
        } else {
            $notification = "您申请的{$factory_info['factory_full_name']}的质保激活授权未通过，如有紧急需要，请自行联系{$factory_info['factory_full_name']}处理，厂家联系电话：{$factory_info['linkphone']}。";
        }

        D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($open_id, $notification, 'text');
    }
}
