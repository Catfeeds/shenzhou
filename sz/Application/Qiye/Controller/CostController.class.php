<?php
/**
* 
*/
namespace Qiye\Controller;

use Common\Common\ErrorCode;
use Illuminate\Support\Arr;

class CostController extends BaseController
{
	/*
	 * 费用单列表
	 */
	public function getList()
    {
        try {
            $data = D('Cost', 'Logic')->getList(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 费用单详情
     */
    public function detail()
    {
        try {
            $data = D('Cost', 'Logic')->detail(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 费用单申请
     */
    public function add()
    {
        try {
            $param = ['product_id', 'type', 'fee'];
            $key_arr = array_keys(I('post.'));
            foreach ($param as $v) {
                if (!in_array($v, $key_arr)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少请求参数'.$v);
                }
            }
            $this->checkEmpty(Arr::only(I('post.'), $param));
            $data = D('Cost', 'Logic')->add(I('get.id'), I('post.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
