<?php
/**
 * File: ComplaintController.class.php
 * Function:
 * User: sakura
 * Date: 2018/4/9
 */

namespace Qiye\Controller;


use Qiye\Model\BaseModel;

class ComplaintController extends BaseController
{

    public function info()
    {
        try {
            $id = I('id', 0, 'intval');

            $model = BaseModel::getInstance('worker_order_complaint');

            $field = 'id,content,worker_order_id,create_time';
            $info = $model->getOneOrFail($id, $field);
            $worker_order_id = $info['worker_order_id'];

            $field = 'orno';
            $order = BaseModel::getInstance('worker_order')->getOneOrFail($worker_order_id, $field);

            $info['order'] = $order;

            $this->response($info);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}