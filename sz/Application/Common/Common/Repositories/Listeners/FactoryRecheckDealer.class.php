<?php
/**
 * File: FactoryRecheckDealer.class.php
 * User: xieguoqiu
 * Date: 2016/12/25 10:31
 */

namespace Common\Common\Repositories\Listeners;

use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\DealerUpdateDataEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class FactoryRecheckDealer implements ListenerInterface
{

    /**
     * @param DealerUpdateDataEvent $event
     */
    public function handle(EventAbstract $event)
    {
        // 经销商修改营业执照需要厂家重新审批
        if ($event->data && $event->data['license_image']) {
            BaseModel::getInstance('factory_product_white_list')
                ->update(['user_name' => $event->dealer_phone], ['status' => 0]);
        }
    }


}
