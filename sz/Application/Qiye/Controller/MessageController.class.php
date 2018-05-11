<?php
/**
* 
*/
namespace Qiye\Controller;

use Common\Common\ErrorCode;
use Common\Common\Service\AppMessageService;
use Qiye\Model\BaseModel;

class MessageController extends BaseController
{
	/*
	 * 消息列表
	 */
	public function getList()
    {
        try {
            if (empty(I('get.message_type'))) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少请求参数message_type');
            }
            if (in_array(I('get.message_type'), [AppMessageService::MESSAGE_TYPE_BACKSTAGE_MESSAGE, AppMessageService::MESSAGE_TYPE_ORDER_MESSAGE])) {
                $data = D('Message', 'Logic')->getAnnouncementList(I('get.'), $this->requireAuth());
            } else {
                $data = D('Message', 'Logic')->getList(I('get.'), $this->requireAuth());
            }
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 消息详情
     */
    public function detail()
    {
        try {
            if (empty(I('get.type'))) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少请求参数type');
            }
            if (in_array(I('get.type'), [AppMessageService::TYPE_BUSINESS_MESSAGE, AppMessageService::TYPE_ACTIVITY_MESSAGE, AppMessageService::TYPE_ORDERS_MASSAGE])) {
                $data = D('Message', 'Logic')->systemAnnouncementDetail(I('get.id'), $this->requireAuth());
            } else {
                $data = D('Message', 'Logic')->detail(I('get.id'), $this->requireAuth());
            }
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 系统消息详情
     */
    public function systemAnnouncementDetail()
    {
        try {
            $data = D('Message', 'Logic')->systemAnnouncementDetail(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 获取未读数
     */
    public function unreadCount()
    {
        try {
            $data = D('Message', 'Logic')->unreadCount($this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 标为已读
     */
    public function setRead()
    {
        try {
            if (!empty(I('put.id'))) {
                if (in_array(I('put.type'), [AppMessageService::TYPE_BUSINESS_MESSAGE, AppMessageService::TYPE_ACTIVITY_MESSAGE, AppMessageService::TYPE_ORDERS_MASSAGE])) {
                    $data = '';
                } else {
                    $data = D('Message', 'Logic')->detail(I('put.id'), $this->requireAuth());
                }
            } else {
                $data = D('Message', 'Logic')->setRead(I('put.'), $this->requireAuth());
            }
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
