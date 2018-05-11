<?php
/**
 * File: FeedbackController.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/25
 */

namespace Admin\Controller;

use Common\Common\Repositories\Events\FeedbackMessageEvent;

class FeedbackController extends BaseController
{

    /*
     * 意见反馈推送
     */
    public function feedbackEvent()
    {
        try {
            event(new FeedbackMessageEvent([
                'data_id' => I('get.id')
            ]));
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}