<?php
/**
* 
*/
namespace Qiye\Controller;

use Common\Common\ErrorCode;
use Illuminate\Support\Arr;

class AccessoryController extends BaseController
{
	/*
	 * 配件单列表
	 */
	public function getList()
    {
        try {
            $data = D('Accessory', 'Logic')->getList(I('get.id'), I('get.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 配件单详情
     */
    public function detail()
    {
        try {
            $data = D('Accessory', 'Logic')->detail(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 配件单厂家信息
     */
    public function factoryDetail()
    {
        try {
            $data = D('Accessory', 'Logic')->factoryDetail(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 配件申请
     */
    public function add()
    {
        try {
            $param = ['product_id', 'user_name', 'phone', 'province_id', 'city_id', 'area_id', 'province_name', 'city_name', 'area_name', 'address', 'name', 'num', 'remark'];
            $key_arr = array_keys(I('post.'));
            foreach ($param as $v) {
                if (!in_array($v, $key_arr)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少请求参数');
                }
            }
            $this->checkEmpty(I('post.'));
            $data = D('Accessory', 'Logic')->add(I('get.id'), I('post.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 配件回寄
     */
    public function accessoryReturn()
    {
        try {
            $data = D('Accessory', 'Logic')->accessoryReturn(I('get.id'), I('post.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 配件签收
     */
    public function accessorySignIn()
    {
        try {
            $data = D('Accessory', 'Logic')->accessorySignIn(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
