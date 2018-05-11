<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/8
 * Time: 17:03
 */

namespace Common\Common\Service\OrderService;

use Admin\Logic\FactoryLogic;
use Admin\Logic\ProductLogic;
use Admin\Model\WxUserModel;
use Common\Common\CacheModel\WorkerOrderProductCacheModel;
use Common\Common\CacheModel\WorkerOrderUserInfoCacheModel;
use Common\Common\ErrorCode;
use Common\Common\Logic\ExpressTrackingLogic;
use Common\Common\Model\BaseModel;
use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class CreateOrderService
{
    protected $factory_id;
    protected $full_product_info;
    protected $orno;

    protected $order;
    protected $order_products;
    protected $order_user;
    protected $order_ext_info;

    protected static $factory;
    protected static $factory_helper_map;

    protected static $user_model;
    protected static $express_tracking_logic;
    protected static $factory_logic;
    protected static $product_logic;

    protected static $pdo;
    protected static $stmts = [];

    /**
     * CreateOrderService constructor.
     * @param $factory_id
     * @param $full_product_info
     */
    public function __construct($factory_id, $full_product_info = false)
    {
        $this->factory_id = $factory_id;
        $this->full_product_info = $full_product_info;
    }


    public function getCreateOrno()
    {
        return $this->orno;
    }

    public function create()
    {
        $factory = self::getFactory($this->factory_id);

        $worker_order_id = 0;

        // 工单用户信息完善
        $order_user_info = $this->order_user;
        $area_id_name_map = AreaService::getAreaNameMapByIds([$order_user_info['province_id'], $order_user_info['city_id'], $order_user_info['area_id']]);
        $order_user_info['cp_area_names'] = implode('-', Arr::pluck($area_id_name_map, 'name'));
        $order_user_info['worker_order_id'] = &$worker_order_id;

        // 工单额外信息完善
        $order_ext_info = $this->order_ext_info;
        $helper_info = self::getFactoryHelper($order_ext_info['factory_helper_id']);
        $order_ext_info['cp_factory_helper_name'] = $helper_info['name'];
        $order_ext_info['cp_factory_helper_phone'] = $helper_info['telephone'];
        $order_ext_info['factory_base_distance'] = $factory['base_distance'];
        $order_ext_info['factory_base_distance_fee'] = $factory['base_distance_cost'];
        $order_ext_info['factory_exceed_distance_fee'] = $factory['exceed_cost'];

        // 工单产品信息
        $product_info = $this->order_products;

        $product_logic = self::getProductLogic();
        if (!$this->full_product_info) {
            $product_logic->loadProductCpDetailInfo($product_info);
        }

        $order_products_info = [];
        $product_count = 0;
        $frozen = 0;

        $operation_recode_products = [];
        foreach ($product_info as $key => $product) {
            $product_frozen_money = 0;
            if ($this->order['is_insured']) {      // 保内
                $product_frozen_money = FactoryMoneyFrozenRecordService::getInsuredOrderProductFrozenPrice($this->order['service_type'], $this->order['factory_id'], $product['product_category_id'], $product['product_standard_id'], $factory['default_frozen']);
            }
            $product['product_id'] = $product['product_id'] ?? $product_logic->getFactoryProductIdByInfo($factory['factory_id'], $product['product_category_id'], $product['product_standard_id'], $product['product_brand_id'], $product['cp_product_mode']);

            for ($i = 0; $i < $product['product_nums']; ++$i) {
                $frozen += $product_frozen_money;

                $order_products_info[$product_count] = $product;
                $order_products_info[$product_count]['product_nums'] = 1;
                $order_products_info[$product_count]['worker_order_id'] = &$worker_order_id;
                $order_products_info[$product_count]['frozen_money'] = $product_frozen_money;
                $order_products_info[$product_count]['factory_repair_fee'] = $product_frozen_money;
                $order_products_info[$product_count]['factory_repair_fee_modify'] = $product_frozen_money;
                $order_products_info[$product_count]['user_service_request'] = $product['user_service_request'] ?? '';
                $order_products_info[$product_count]['fault_label_ids'] = $product['fault_label_ids'] ?? '';
                $order_products_info[$product_count]['product_id'] = $product['product_id'] ?? 0;
                $order_products_info[$product_count]['cp_product_mode'] = $product['cp_product_mode'] ?? '';
                $order_products_info[$product_count]['product_brand_id'] = $product['product_brand_id'] ?? 0;

                $operation_recode_products[] = "{$order_products_info[$product_count]['cp_product_brand_name']}-{$order_products_info[$product_count]['cp_product_standard_name']}-{$order_products_info[$product_count]['cp_product_mode']}-{$order_products_info[$product_count]['cp_category_name']} X{$order_products_info[$product_count]['product_nums']}";
                ++$product_count;
            }
        }
        $service_fee = 0;
        $insurance_fee = 1;
        if ($this->order['is_insured'] && in_array($this->order['service_type'], [OrderService::TYPE_WORKER_INSTALLATION, OrderService::TYPE_PRE_RELEASE_INSTALLATION])) {
            $service_fee = C('ORDER_INSURED_SERVICE_FEE');
        }

        $total_frozen = round($frozen + $service_fee, 2);

        // 工单费用完善
        $worker_order_fee = [
            'worker_order_id'           => $worker_order_id,
            'factory_repair_fee'        => 0,
            'factory_repair_fee_modify' => 0,
            'service_fee'               => $service_fee,
            'service_fee_modify'        => $service_fee,
            'factory_total_fee'         => $service_fee,
            'factory_total_fee_modify'  => $service_fee,
            'insurance_fee'             => $insurance_fee,
        ];

        $data = $this->order;
        $data['factory_id'] = $factory['factory_id'];
        if (!isset($data['worker_order_type'])) {
            if ($data['is_insured'] == 1) {
                $data['worker_order_type'] = OrderService::ORDER_TYPE_FACTORY_IN_INSURANCE;
            } else {
                $data['worker_order_type'] = OrderService::ORDER_TYPE_FACTORY_OUT_INSURANCE;
            }
        }

        if (in_array($data['worker_order_type'], [OrderService::ORDER_TYPE_FACTORY_EXPORT_IN_INSURANCE, OrderService::ORDER_TYPE_FACTORY_IN_INSURANCE])) {
            $factory_frozen_money = self::getFactoryLogic()->getFrozenMoney($factory['factory_id']);
            if ($factory['money'] - $factory_frozen_money - $total_frozen < 0) {
                throw new \Exception('资金不足，下单失败');
            }
        }

        if (!isset($data['origin_type'])) {
            $data['origin_type'] = AuthService::getModel() == AuthService::ROLE_FACTORY ? OrderService::ORIGIN_TYPE_FACTORY : OrderService::ORIGIN_TYPE_FACTORY_ADMIN;
        }
        $data['add_id'] = AuthService::getAuthModel()->getPrimaryValue();
        $data['create_time'] = NOW_TIME;
        $data['last_update_time'] = NOW_TIME;
        $data['orno'] = isset($data['order_classification']) ? OrderService::generateOrno($data['order_classification']) : OrderService::generateOrno();

        $order_model = BaseModel::getInstance('worker_order');
        $worker_order_id = $order_model->insert($data);

        $order_user_info['wx_user_id'] = Util::isPhone($order_user_info['phone']) && !$order_user_info['wx_user_id'] ? self::getUserModel()->getUserByPhoneOrCreate($order_user_info['phone'], $order_user_info) : (int)$order_user_info['wx_user_id'];

        // TODO 批量添加insertAll會插入不全？
        foreach ($order_products_info as $item) {
            BaseModel::getInstance('worker_order_product')->insert($item);
        }

        $this->createOrderExtData($worker_order_id, $order_user_info, $order_ext_info, $worker_order_fee);
        if ($data['service_type'] == OrderService::TYPE_PRE_RELEASE_INSTALLATION) {
            $express_info = $data['express'];
            $express_code = $express_info['express_code'];
            $express_number = $express_info['express_number'];
            $data_id = $worker_order_id;
            $type = ExpressTrackingLogic::TYPE_ORDER_PRE_INSTALL_SEND;
            expressTrack($express_code, $express_number, $data_id, $type);
        }
        if ($data['is_insured'] && $data['worker_order_status'] != OrderService::STATUS_CREATED) {
            // 方法里面更新厂家总冻结金
            FactoryMoneyFrozenRecordService::process($worker_order_id, FactoryMoneyFrozenRecordService::TYPE_FACTORY_ADD_ORDER, $total_frozen);
//            BaseModel::getInstance('factory')->setNumInc(['factory_id' => $factory['factory_id']], 'frozen_money', $total_frozen);
        }
        // 添加操作记录
        $operation_type = 0;
        $insurance_type = '';
        if ($data['worker_order_type'] == OrderService::ORDER_TYPE_FACTORY_IN_INSURANCE || $data['worker_order_type'] == OrderService::ORDER_TYPE_FACTORY_OUT_INSURANCE) {
            $operation_type = OrderOperationRecordService::FACTORY_ORDER_CREATE;
        } elseif ($data['worker_order_type'] == OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE || $data['worker_order_type'] == OrderService::ORDER_TYPE_WX_USER_OUT_INSURANCE || $data['worker_order_type'] == 8) { // TODO 微信保外类型
            $operation_type = OrderOperationRecordService::WX_USER_CREATE_ORDER;
            $data['worker_order_type'] == OrderService::ORDER_TYPE_WX_USER_IN_INSURANCE && $insurance_type = '易码保内';
            $data['worker_order_type'] == OrderService::ORDER_TYPE_WX_USER_OUT_INSURANCE && $insurance_type = '易码保外';
            $data['worker_order_type'] == 8 && $insurance_type = '微信保外';
        }

        $operation_type && OrderOperationRecordService::create($worker_order_id, $operation_type, [
            'content_replace' => [
                'order_products' => '<br/>' . implode('<br/>', $operation_recode_products) . '<br/>',
                'service_type'   => OrderService::SERVICE_TYPE[$data['service_type']],
                'insurance_type' => $insurance_type,
                'pre_text'       => '',
            ],
        ]);

        return $worker_order_id;
    }

    public static function getFactory($factory_id)
    {
        if (!self::$factory) {
            self::$factory = BaseModel::getInstance('factory')->getOneOrFail([
                'where' => ['factory_id' => $factory_id],
                'field' => 'factory_id,dateto,money,default_frozen,base_distance,base_distance_cost,exceed_cost',
            ]);
            (new FactoryLogic())->checkFactoryExpireByTime(self::$factory['dateto']);
        }

        return self::$factory;
    }

    public static function getFactoryHelper($factory_helper_id)
    {
        if (!isset(self::$factory_helper_map[$factory_helper_id])) {
            self::$factory_helper_map[$factory_helper_id] = BaseModel::getInstance('factory_helper')
                ->getOne($factory_helper_id, 'name,telephone');
        }
        return self::$factory_helper_map[$factory_helper_id];
    }

    public static function getUserModel()
    {
        if (!isset(self::$user_model)) {
            self::$user_model = new WxUserModel();
        }
        return self::$user_model;
    }

    public static function getExpressTrackingLogic()
    {
        if (!isset(self::$express_tracking_logic)) {
            self::$express_tracking_logic = new ExpressTrackingLogic();
        }
        return self::$express_tracking_logic;
    }

    public static function getFactoryLogic()
    {
        if (!isset(self::$factory_logic)) {
            self::$factory_logic = new FactoryLogic();
        }
        return self::$factory_logic;
    }

    public static function getProductLogic()
    {
        if (!isset(self::$product_logic)) {
            self::$product_logic = new ProductLogic();
        }

        return self::$product_logic = new ProductLogic();
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param mixed $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @return mixed
     */
    public function getOrderProducts()
    {
        return $this->order_products;
    }

    /**
     * @param mixed $order_products
     */
    public function setOrderProducts($order_products)
    {
        $this->order_products = $order_products;
    }

    /**
     * @return mixed
     */
    public function getOrderUser()
    {
        return $this->order_user;
    }

    /**
     * @param mixed $order_user
     */
    public function setOrderUser($order_user)
    {
        $this->order_user = $order_user;
    }

    /**
     * @return mixed
     */
    public function getOrderExtInfo()
    {
        return $this->order_ext_info;
    }

    /**
     * @param mixed $order_ext_info
     */
    public function setOrderExtInfo($order_ext_info)
    {
        $this->order_ext_info = $order_ext_info;
    }

    /**
     * @param       $worker_order_id
     * @param array $worker_order_user_info
     * @param array $worker_order_ext_info
     * @param array $worker_order_fee
     * @param array $worker_order_statistics
     */
    public function createOrderExtData(
        $worker_order_id,
        $worker_order_user_info = [],
        $worker_order_ext_info = [],
        $worker_order_fee = [],
        $worker_order_statistics = [])
    {
        $worker_order_user_info['worker_order_id'] = $worker_order_id;
        $worker_order_ext_info['worker_order_id'] = $worker_order_id;
        $worker_order_fee['worker_order_id'] = $worker_order_id;
        $worker_order_statistics['worker_order_id'] = $worker_order_id;

//        self::addTableData('worker_order_user_info', $worker_order_user_info);
//        self::addTableData('worker_order_ext_info', $worker_order_ext_info);
//        self::addTableData('worker_order_fee', $worker_order_fee);
//        self::addTableData('worker_order_statistics', $worker_order_statistics);
        BaseModel::getInstance('worker_order_user_info')
            ->insert($worker_order_user_info);
        BaseModel::getInstance('worker_order_ext_info')
            ->insert($worker_order_ext_info);
        BaseModel::getInstance('worker_order_fee')->insert($worker_order_fee);
        BaseModel::getInstance('worker_order_statistics')
            ->insert($worker_order_statistics);
    }

    public static function addTableData($table, $data)
    {
        if (!isset(self::$stmts[$table])) {
            $keys = [];
            $s = [];
            foreach ($data as $key => $item) {
                $keys[] = $key;
                $s[] = ':' . $key;
            }
            $keys = implode(',', $keys);
            $s = implode(',', $s);
            $sql = "INSERT INTO `{$table}`({$keys}) VALUES ({$s})";
            self::$stmts[$table] = self::getPDO()->prepare($sql);
        }
        foreach ($data as $key => $item) {
            self::$stmts[$table]->bindValue(':' . $key, $item);
        }
        $res = self::$stmts[$table]->execute();
        if (!$res) {
            throw new \Exception('系统繁忙,请稍后再试');
        }
    }

    protected static function getPDO()
    {
        if (!isset(self::$pdo)) {
            self::$pdo = new \PDO('mysql:dbname=shenzhou;host=120.79.84.241;port=37813', 'shenzhou', '3N@shenzhou#2017!aa', [\PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8"]);
        }

        return self::$pdo;
    }
}