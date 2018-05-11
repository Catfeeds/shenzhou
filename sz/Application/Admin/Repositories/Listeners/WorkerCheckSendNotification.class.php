<?php
/**
 * File: WorkerFinishedOrderSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 9:35
 */

namespace Admin\Repositories\Listeners;

use Admin\Logic\WeChatNewsEventLogic;
use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\AccountCheckEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Service\SMSService;

class WorkerCheckSendNotification implements ListenerInterface
{
    /**
     * @param $event
     */
    public function handle(EventAbstract $event)
    {
        try {
            $worker_id = $event->data['worker_id'];
            $type = $event->data['type'];

            $worker_info = BaseModel::getInstance('worker')
                ->getOneOrFail($worker_id);
            $worker_telephone = $worker_info['worker_telephone'];
            $nickname = $worker_info['nickname'];

            $template_sn = '';
            $param = [];
            $is_complete_info = 0;
            if (1 == $type) {
                $template_sn = SMSService::TMP_WORKER_CHECK_PASS;
                $param = [
                    'worker_name' => $nickname,
                ];
                $is_complete_info = 1;
            } elseif (2 == $type) {
                $template_sn = SMSService::TMP_WORKER_CHECK_FORBIDDEN;
                $is_complete_info = 0;
            }
            sendSms($worker_telephone, $template_sn, $param);

            event(new AccountCheckEvent([
                'worker_id'        => $worker_id,
                'is_complete_info' => $is_complete_info //是否完善资料 0不通过 1通过 2待审核
            ]));

        } catch (\Exception $e) {

        }
    }
}
