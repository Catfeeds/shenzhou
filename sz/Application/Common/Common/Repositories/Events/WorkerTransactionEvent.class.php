<?php
/**
 * File: WorkerExtractedEvent.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:19
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\WorkerTransactionSendNotification;
use Common\Common\Repositories\Listeners\SMS\WorkerTransactionSend;
use Common\Common\Model\BaseModel;
use Common\Common\Service\OrderService;

class WorkerTransactionEvent extends EventAbstract
{
    public $data;
    public $db_worker_order;
    public $db_worker_order_product;
    public $db_worker_info;
    public $db_order_fee;
    public $db_order_user_info;
    public $db_admin_info;
    public $service_type_name;
    public $db_repair_money_record;

    protected $listeners = [
        // 发送企业号与app信息
        WorkerTransactionSendNotification::class,
        // 短信信息
        WorkerTransactionSend::class,
    ];

    /**
     * WorkerExtractedEvent constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->db_worker_order  = BaseModel::getInstance('worker_order')->getOne($data['data_id'], 'id,orno,worker_id,worker_order_type,service_type,distributor_id, worker_group_id, children_worker_id');
        $this->db_worker_order_product = BaseModel::getInstance('worker_order_product')->getOne([
                'field' => 'cp_category_name,cp_product_brand_name,cp_product_standard_name',
                'where' => [
                    'worker_order_id' => $data['data_id'],
                ],
                'order' => 'id asc',
            ]);
        $this->db_worker_info    = BaseModel::getInstance('worker')->getOne($data['worker_id'], 'worker_id, nickname,money,worker_telephone,jpush_alias');
        $this->db_order_fee = BaseModel::getInstance('worker_order_fee')->getOne($data['data_id']);
        $this->db_order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne($data['data_id'], 'real_name,phone');
        $this->db_repair_money_record = BaseModel::getInstance('worker_repair_money_record')->getOne([
            'where' => [
                'worker_order_id' => $data['data_id'],
                'worker_id'       => $data['worker_id']
            ]
        ]);
    }

    public function getServiceTypeName($service_type)
    {
        $this->service_type_name = OrderService::SERVICE_TYPE[$service_type] ? OrderService::SERVICE_TYPE[$service_type] : OrderService::TYPE_USER_SEND_FACTORY_REPAIR;
        return $this->service_type_name;
    }

    public function getDbAdmin()
    {
        $this->db_admin_info = BaseModel::getInstance('admin')->getOne($this->db_worker_order['distributor_id'], 'id,user_name,nickout,tell,tell_out');
        return $this->db_admin_info;
    }

}
