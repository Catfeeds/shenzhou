<?php
/**
* 
*/
namespace Qiye\Controller;

use Common\Common\ErrorCode;
use Common\Common\Repositories\Events\FeedbackMessageEvent;

class FeedBackController extends BaseController
{
	/*
	 * 意见反馈列表
	 */
	public function getList()
    {
        try {
            $data = D('FeedBack', 'Logic')->getList($this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 意见反馈详情
     */
    public function detail()
    {
        try {
            $data = D('FeedBack', 'Logic')->detail(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 意见反馈
     */
    public function add()
    {
        try {
            $param = ['type', 'content'];
            $key_arr = array_keys(I('post.'));
            foreach ($param as $v) {
                if (!in_array($v, $key_arr)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少请求参数'.$v);
                }
            }
            $this->checkEmpty(I('post.'));
            $data = D('FeedBack', 'Logic')->add(I('post.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
