<?php
/**
* 
*/
namespace Qiye\Controller;

use Common\Common\Service\AuthService;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use Qiye\Controller\BaseController;
use Qiye\Model\BaseModel;
use Library\Common\Util;

class OrderController extends BaseController
{
	/*
	 * 工单列表
	 */
	public function getList()
    {
        try {
            $data = D('Order', 'Logic')->getList(I('get.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 工单详情
     */
    public function detail()
    {
        try {
            $data = D('Order', 'Logic')->detail(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 提交预约
     */
    public function addAppoint()
    {
        try {
            $data = D('Order', 'Logic')->addAppoint(I('get.id'), I('post.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 修改预约
     */
    public function updateAppoint()
    {
        try {
            $data = D('Order', 'Logic')->updateAppoint(I('get.id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 预约记录
     */
    public function appointmentLog()
    {
        try {
            $data = D('Order', 'Logic')->appointmentLog(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 预约签到
     */
    public function appointmentSign()
    {
        try {
            $data = D('Order', 'Logic')->appointmentSign(I('get.id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 服务项目列表
     */
    public function getServices()
    {
        try {
            $data = D('Order', 'Logic')->getServices(I('get.order_id'), I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 获取工单上传服务报告相关数据
     */
    public function getServiceReport()
    {
        try {
            $id = I('get.id', 0);
            $data = BaseModel::getInstance('worker_order')->getOneOrFail($id, 'worker_order_status,worker_order_type,service_type');
            $data['service_type_full_name'] = OrderService::SERVICE_TYPE[$data['service_type']];
            $data['service_type_short_name']= OrderService::SERVICE_TYPE_SHORT_NAME_FOR_APP[$data['service_type']];
            $fee = BaseModel::getInstance('worker_order_fee')->getOne(['worker_order_id' => $id], 'worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,service_fee_modify,worker_total_fee,worker_total_fee_modify');
            $ext = BaseModel::getInstance('worker_order_ext_info')->getOne(['worker_order_id' => $id], 'worker_repair_out_fee_reason,accessory_out_fee_reason');
            $use = BaseModel::getInstance('worker_order_user_info')->getOne(['worker_order_id' => $id], 'pay_type,is_user_pay');
            $out_fees = BaseModel::getInstance('worker_order_out_worker_add_fee')->getList([
                'field' => 'is_add_fee,pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee_modify,pay_time',
                'where' => [
                    'worker_order_id' => $id
                ],
            ]);
            $data['pay_type'] = $use['pay_type'];
            $data['pay_type_detail'] = WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO;
            $data['is_user_pay'] = $use['is_user_pay'];
            $total = $is_apy_total = 0;
            $not_pay = [];
            foreach ($out_fees as $k => $v) {
                if ($v['pay_type'] != WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO && $v['pay_time']) {
                    $is_apy_total += $v['total_fee_modify'];
                    $data['pay_type_detail'] = $v['pay_type'];
                } else {
                    $not_pay = $v;
                }
                $total += $v['total_fee_modify'];
                unset($out_fees[$k]['total_fee_modify']);
            }
            $data['out_fee_info'] = [
                'total_worker_fee' => $fee['worker_total_fee'],
                'total_worker_fee_modify' => $fee['worker_total_fee_modify'],
                'total_worker_repair_fee' => $fee['worker_repair_fee'],
                'total_worker_repair_fee_modify' => $fee['worker_repair_fee_modify'],
                'total_worker_repair_fee_reason' => $ext['worker_repair_out_fee_reason'],
                'total_accessory_out_fee' => $fee['accessory_out_fee'],
                'total_accessory_out_fee_modify' => $fee['accessory_out_fee_modify'],
                'total_accessory_out_fee_reason' => $ext['accessory_out_fee_reason'],
                'need_pay_total_fee' => number_format($total, 2, '.', ''),
                'pay_total_fee' => number_format($is_apy_total, 2, '.', ''),
                'last_not_pay' => $not_pay ?? null,
                'out_fees' => $out_fees,
            ];

            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 选择服务项
     */
    public function selectService()
    {
        try {
            $data = D('Order', 'Logic')->selectService(I('get.order_id'), I('get.product_id'), I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 上传服务报告
     */
    public function uploadServiceReport()
    {
        try {
            $data = D('Order', 'Logic')->uploadServiceReport(I('get.order_id'), I('get.product_id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 工单退回
     */
    public function orderReturn()
    {
        try {
            $data = D('Order', 'Logic')->orderReturn(I('get.id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 工单延时
     */
    public function orderDelay()
    {
        try {
            $data = D('Order', 'Logic')->orderDelay(I('get.id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 工单跟踪
     */
    public function orderTrack()
    {
        try {
            $data = D('Order', 'Logic')->orderTrack(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 工单费用明细
     */
    public function orderCharge()
    {
        try {
            $data = D('Order', 'Logic')->orderCharge(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 保外单修改费用
     */
    public function updateWarrantyFee()
    {
        try {
            $data = D('Order', 'Logic')->updateWarrantyFee(I('get.id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 保外单现金支付
     */
    public function cashPaySuccess()
    {
        try {
            $data = D('Order', 'Logic')->cashPaySuccess(I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 保外单费用详情
     */
    public function warrantyFeeInfo()
    {
        try {
            $order_id = I('get.id');
            $data = D('Order', 'Logic')->warrantyFeeInfo($order_id, I('get.'), $this->requireAuth());
            $data['out_fee_info'] = null;

            $out_fees = BaseModel::getInstance('worker_order_out_worker_add_fee')->getList([
                'field' => 'is_add_fee,pay_type,worker_repair_fee,worker_repair_fee_modify,accessory_out_fee,accessory_out_fee_modify,total_fee_modify,pay_time',
                'where' => [
                    'worker_order_id' => $order_id
                ],
            ]);
            $total = $is_apy_total = 0;
            foreach ($out_fees as $k => $v) {
                $v['pay_type'] != WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO &&
                $v['pay_time'] &&
                $is_apy_total += $v['total_fee_modify'];
                $total += $v['total_fee_modify'];
                unset($out_fees[$k]['total_fee_modify']);
            }
            $data['out_fee_info'] = [
                'total_fee' => number_format($total, 2, '.', ''),
                'pay_total_fee' => number_format($is_apy_total, 2, '.', ''),
                'out_fees' => $out_fees,
            ];

            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 产品规格列表
     */
    public function productStandards()
    {
        try {
            $data = D('Order', 'Logic')->productStandards(I('get.order_id'), I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 选择产品规格
     */
    public function updateProductStandard()
    {
        try {
            $data = D('Order', 'Logic')->updateProductStandard(I('get.order_id'), I('get.product_id'), I('get.id'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
