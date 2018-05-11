<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/10/11
 * PM 16:44
 */
namespace Script\Logic;

use Script\Logic\DbLogic;
use Script\Model\BaseModel;
use QiuQiuX\IndexedArray\IndexedArray;

class WorkerOrderLogic extends DbLogic
{
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order';
	const RULE_NUMS = 1000;

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => 'order_id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(0, $this->structureKey)],
    			],
    		]);
    	
    	foreach ($data as $key => $value) {
    		$merge[parent::WORKER_ORDER_P_STRING][] = $value['order_id'];
    	}
    }

    public function sqlDataTransferWhere(&$where, $arr = [])
    {
    	$return = [];
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case parent::WORKER_ORDER_P_STRING:
    				$ids = implode(',', array_unique(array_filter($value)));
    				if ($ids) {
    				 	$return['id'] = ['in', $ids];
						if ($where['order_id']) {
							$where['_complex'] = [
								'_logic'   => 'and',
								'order_id' => $return['id'],
							];
						} else {
							$where['order_id'] = $return['id'];
						}
    				} 
    				break;
    		}
    	}
    	return $return;
    }

	// 数据开始迁移
	public function sqlDataTransfer($arr = [])
	{
		set_time_limit(0);
		$db_model = $this->setVarThisDbModel();  // 新数据库的model
		$ch_model = $this->setVarCheckModel();  // 旧数据库的model

		// 测试的厂家账号
		$test_factory_ids_sql = 'SELECT factory_id FROM `factory` WHERE `linkphone` IN (18818461566,18922363292,13602418838,18888888881,13450788338,13926112540)';
		// 工单来源为微信端，却找不到微信用户
		$not_in_orders = 'select a.*,count(a.id) as nums,group_concat(a.order_id) as order_ids from wx_user_order a left join wx_user b on a.wx_user_id = b.id left join worker_order c on a.order_id = c.order_id where c.factory_id in ('.$test_factory_ids_sql.') and b.id is null group by a.wx_user_id order by a.id desc';
		$datas = $ch_model->query($not_in_orders);
		$not_in_oids = arrFieldForStr($datas, 'order_ids');

		// 没有操作记录的工单
		$not_record_ids_sql = 'select a.order_id from worker_order a left join worker_order_operation_record b on a.order_id = b.order_id where b.order_id is null group by a.order_id order by a.order_id desc';
		$datas2 = $ch_model->query($not_record_ids_sql);
		$not_in_oids2 = arrFieldForStr($datas2, 'order_id');

		// 合并不迁移数据的的order_ids
		$not_in_oids = implode(',', array_filter([$not_in_oids, $not_in_oids2]));

		// $db_last_data = $db_model->getOne(['order' => 'id DESC']);
		// $where = $db_last_data['id'] ? ['gt', $db_last_data['id']] : [];
		$where = [];
		$not_in_oids && $where['order_id'] = ['not in', $not_in_oids];
		// $where['order_id'] = 148155;
		// $arr[parent::WORKER_ORDER_P_STRING] = [148155];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$db_model->deleteNewWorkerOrderByIds($delete['id']);
		}

		$num = $ch_model->getNum($where);
		$rule_nums = self::RULE_NUMS;
		$foreach_num = ceil($num/$rule_nums);
		// var_dump($foreach_num);
		// $foreach_num = 2;

		// 删除工单产品中找不到主表数据的数据
		// $ch_model->deleteOrderIdOfWorkerOrderDetail();
		
		$return = [];
		for ($i=1; $i <= $foreach_num; $i++) {

			$data = $ch_model->getListSetOrderStatus($where, $rule_nums);
			foreach ($data as $k => $v) {
				$v = array_filter($v);
				if (!$v) {
					continue;
				}
				$model = $this->setOrderAtModel($k, false);
				$v && $model->insertAll($v);
			}

			$end = end($data['worker_order']);
			$end['id'] && $where['_string'] = ' order_id > '.$end['id'].' ';
			unset($data);
		}
		
		return $return;
	}	

	/*
	 * 根据新表，旧表，备份表存在情况处理
	 * @param $rule  根据新表，旧表，备份表存在情况
	 */
	public function structureResult()
	{
		// 删除
		$this->createNewStructures(true);
		$this->workerOrderProduct(true);
		$this->workerOrderUserInfo(true);
		$this->workerOrderExtInfo(true);
		$this->workeOrderStatistics(true);
		// 新增
		$this->createNewStructures();
		$this->workerOrderProduct();
		$this->workerOrderUserInfo();
		$this->workerOrderExtInfo();
		$this->workeOrderStatistics();
		return $this->resultRule;
	}
	
	protected function createNewStructures($is_delete = false)
	{
		$result = $this->deleteTable($this->rule['db_name']);
		$this->resultRule[] = $result['sql'];
		if ($is_delete) {
			$this->rule['is_db_name'] = $this->tableShowStatus($this->rule['db_name']);
			return $result;
		}
		$b_model = $this->setVarCheckModel();
		$data = $b_model->getOne(['order' => 'order_id DESC', 'field' => 'order_id']);
		$nums = $data['order_id'] ? $data['order_id']+1 : 0;

		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment comment 'order_id，维修工单id',
		   worker_id            int(11) comment 'worker_id，技工id',
		   auditor_id           int(11) not null comment '管理员id（平台客服id）',
		   distributor_id       int(11) not null comment '管理员id（平台客服id）',
		   returnee_id          int(11) not null comment '管理员id（平台客服id）',
		   factory_id           int(11) not null,
		   checker_id           int(11) not null comment '管理员id（平台客服id）',
		   orno                 char(16) not null comment '工单号',
		   worker_order_type    smallint not null default 0 comment 'order_type，v3.0类型：1 厂家保内；2 厂家保外；3 厂家导单保内；4 厂家导单保外；5 易码保内；6易码保外；7 平台保外； 8微信保外；',
		   worker_order_status  tinyint not null default 0 comment 'order_status，工单状态：0创建工单,1自行处理,2外部工单经过厂家审核（待平台核实客服接单）,3平台核实客服接单（待平台核实客服核实信息），4平台核实客服核实用户信息 （待平台派单客服接单）,5平台派单客服接单 （待派发）,6已派发 （抢单池）,7技工接单成功 （待技工预约上门）,8预约成功 （待上门服务）,9已上门（待全部维修项完成维修）,10完成维修 （待平台回访客服接单）,11平台回访客服接单 （待回访）,12平台回访客服回访不通过 （已上门）,13平台回访客服已回访 （待平台财务客服接单）,14平台财务客服接单 （待平台财务客服审核）,15平台财务客服审核不通过 （重新回访客服回访）,16平台财务客服审核 （待厂家财务审核）,17厂家财务审核不通过 （平台财务重新审核）,18厂家财务审核 （已完成工单）',
		   factory_check_order_time int not null default 0 comment 'order_status 为（1，2），厂家确认将工单交给平台处理或者自行处理时间',
		   factory_check_order_type tinyint not null default 0 comment '厂家下单帐号类型，1厂家账号 2厂家子账号',
		   factory_check_order_id int not null default 0 comment '厂家下单人id，自行处理或下单至平台的（factory_check_order_type）操作人id ',
		   cancel_status        tinyint not null default 0 comment '取消状态：0正常，1C端用户取消，2C端经销商取消，3厂家取消，4客服取消，5 客服终止工单（可结算）',
		   canceler_id          int not null default 0 comment '取消人id',
		   cancel_time          int not null default 0 comment '取消时间',
		   cancel_type          smallint not null default 0 comment 'giveup_reason，客服取消原因(1：没网点，2：用户原因，3：厂家原因，4：其他)',
		   cancel_remark        varchar(255) comment 'cancel_reason，取消备注',
		   origin_type          smallint not null default 1 comment 'order_origin，下单来源：1厂家，2厂家子账号，3厂家外部客户，4普通用户，5经销商',
		   add_id               int not null default 0 comment 'add_member_id，下单人ID（结合来源：origin_type使用）',
		   service_type         int not null comment 'V1.0  servicetype，服务类型 : (cm_list_item.list_id = 41)',
		   extend_appoint_time  int not null default 0 comment '延长预约的小时数,默认为0，每申请延长一次则加3',
		   create_time          int not null comment 'datetime，添加时间',
		   checker_receive_time int not null default 0 comment '核实客服接单时间',
		   check_time           int not null default 0 comment '核实客服核实时间',
		   distributor_receive_time int not null default 0 comment '派单客服接单时间',
		   distribute_time      int not null default 0 comment '派单客服派发时间',
		   worker_receive_time  int not null default 0 comment '技工接单时间',
		   worker_first_appoint_time int not null comment 'appoint_time，首次预约时间',
		   worker_first_sign_time int not null default 0 comment '首次上传维修报告时间(签到)',
		   worker_repair_time   int not null default 0 comment 'repair_time，技工维修完成时间',
		   returnee_receive_time int not null default 0 comment '回访客服接单时间',
		   return_time          int not null comment '回访时间',
		   auditor_receive_time int not null default 0 comment '平台财务接单时间',
		   audit_time           int not null default 0 comment '平台财务审核时间，platform_check_time',
		   factory_audit_time   int not null default 0 comment '厂家财务审核时间，factory_check_time',
		   factory_audit_remark text comment '厂家审核工单时备注，fact_check_notes',
		   is_worker_pay        tinyint not null default 0 comment '技工是否已付费，0否，1是',
		   distribute_mode      tinyint not null default 2 comment 'send_model，派发模式 0-首选 1-放入抢单池 2-指定维修商',
		   cp_worker_phone      char(11) comment '技工电话，worker_phone',
		   create_remark        varchar(255) comment '外部下单备注，add_remark',
		   last_update_time     int not null default 0 comment 'last_uptime，最后更新时间',
		   important_level      tinyint not null default 0 comment 'import_sign，工单重要程度，1正常，1一般，2非常',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);

	}

	protected function workerOrderProduct($is_delete = false)
	{
		$table = 'worker_order_product';
		if ($is_delete) {
			$result = $this->deleteTable($table);
			$this->resultRule[] = $result['sql'];
			return $result;
		} elseif ($this->tableShowStatus($table)) {
			return false;
		}
		$sql = <<<MYSQL
		create table {$table}
		(
			id                   int(11) not null auto_increment comment 'order_detail_id，工单详情id',
			product_brand_id     int(11) not null,
			product_category_id  int(11) not null comment '选项id',
			product_standard_id  int(11) not null,
			worker_order_id      int(11) not null comment 'order_id，维修工单id',
			fault_id             int(11),
			product_id           int(11) not null,
			cp_fault_name        varchar(255) not null comment 'fault_desc，产品维修项名称',
			admin_edit_fault_times int not null default 0 comment 'cs_change_fault_num，客服修改维修项次数',
			frozen_money         decimal(10,2) not null default 0.00 comment '冻结金额',
			factory_repair_fee   decimal(10,2) not null default 0.00 comment 'fact_cost，厂家产品维修金原价',
			factory_repair_fee_modify decimal(10,2) not null default 0.00 comment 'fact_cost_modify，厂家产品维修金实价（修改后）',
			factory_repair_reason text not null comment 'fact_modify_remark，产品维修金修改原因',
			worker_repair_fee    decimal(10,2) not null default 0 comment 'work_cost，技工维修金原价',
			worker_repair_fee_modify decimal(10,2) not null default 0.00 comment 'work_cost_modify，技工维修金（修改后）',
			worker_repair_reason text not null comment 'work_modify_remark，技工维修金修改原因',
			service_fee          decimal(10,2) not null default 0.00 comment '平台服务费',
			service_fee_modify   decimal(10,2) not null default 0.00 comment '平台服务费（修改后）',
			service_reason       varchar(255) not null comment '平台服务费修改原因',
			cp_category_name     varchar(255) not null comment 'servicepro_desc，产品类别中文描述',
			cp_product_brand_name varchar(255) not null comment 'servicebrand_desc，产品品牌中文描述',
			cp_product_standard_name text not null comment 'stantard_desc，产品规格中文描述',
			fault_label_ids      text not null comment 'servicefault，关联故障标签ID,多个逗号分隔'',''',
			product_nums         int not null default 0 comment 'nums，产品数量',
			cp_product_mode      text not null comment 'model，产品型号',
			yima_code            varchar(15) not null comment 'code，产品二维码',
			user_service_request text not null comment 'description，用户服务要求，故障描述',
			is_complete          tinyint not null comment '是否完成.0 未处理；1 完成维修；2  不能完成维修',
			worker_report_imgs   text not null comment 'report_imgs，报告图片 json化数组 记录',
			service_request_imgs text not null comment '微信用户申请维修的服务请求图片',
			worker_report_remark varchar(255) not null comment 'report_desc，报告备注',
			is_reset             tinyint not null default 0 comment 'is_return，是否重置产品，0否，1是 （App/技工  可提交维修报告）',
			wrong_lock_status    int not null comment 'wrong_lock_status，错误锁定状态 0未处理 1锁定 2已进入正常流程 3 通过错误报告 4 错误报告不通过',
		   primary key (id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='工单产品表';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
	}

	protected function workerOrderUserInfo($is_delete = false)
	{
		$table = 'worker_order_user_info';
		if ($is_delete) {
			$result = $this->deleteTable($table);
			$this->resultRule[] = $result['sql'];
			return $result;
		} elseif ($this->tableShowStatus($table)) {
			return false;
		}
		$sql = <<<MYSQL
		create table {$table}
		(
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   area_id              int(11) not null comment '选项id',
		   province_id          int(11) not null comment '选项id',
		   wx_user_id           int(11),
		   city_id              int(11) not null comment '选项id',
		   real_name            varchar(48) not null comment '用户名称',
		   phone                char(16) not null comment '用户电话',
		   cp_area_names        varchar(128) comment '用户所在区域,''-''',
		   address              varchar(255) comment '用户具体地址',
		   lat                  float comment '纬度',
		   lon                  float comment '经度',
		   pay_type             tinyint not null default 0 comment '保外单支付类型，0=暂未支付 ，1=微信支付，2=现金支付',
		   is_user_pay          tinyint not null default 0 comment '保外单支付状态，0=未支付，1=已支付',
		   pay_time             int not null default 0 comment '保外单支付时间',
		   primary key (worker_order_id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='工单用户信息表 (四级地区)';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
	}

	protected function workerOrderExtInfo($is_delete = false)
	{
		$table = 'worker_order_ext_info';
		if ($is_delete) {
			$result = $this->deleteTable($table);
			$this->resultRule[] = $result['sql'];
			return $result;
		} elseif ($this->tableShowStatus($table)) {
			return false;
		}
		$sql = <<<MYSQL
		create table {$table}
		(
			worker_order_id      int(11) not null comment 'order_id,维修工单id',
			factory_helper_id    int(11) not null,
			cp_factory_helper_name char(32) comment '厂家技术支持人姓名',
			cp_factory_helper_phone char(16) comment '厂家技术支持人手机',
			appoint_start_time   int not null default 0 comment '创建人希望技工上门时间的开始时间',
			appoint_end_time     int not null default 0 comment '创建人希望技工上门时间的结束时间',
			is_send_user_message tinyint not null default 0 comment 'is_send_user_mess,对用户发短信,0为不需，1为需要',
			user_message         text comment '对用户发送信息的补充',
			is_send_worker_message tinyint not null default 0 comment 'is_send_worker_mess,要对技工发送信息，0为不需，1为需要',
			worker_message       text comment '对技工发送信息的补充',
			est_miles            numeric(10,2) not null default 0.00 comment '上门里程数',
			straight_miles       int not null comment '维修点到用户直线距离',
			worker_base_distance decimal(10,2) not null default 0.00 comment '技工基本里程',
			worker_base_distance_fee decimal(10,2) not null default 0.00 comment '技工基本里程费',
			worker_exceed_distance_fee decimal(10,2) not null default 0.00 comment '技工超程单价：元/里',
			factory_base_distance decimal(10,2) not null default 0.00 comment '厂家基本里程',
			factory_base_distance_fee decimal(10,2) not null default 0.00 comment '厂家基本里程费',
			factory_exceed_distance_fee decimal(10,2) not null default 0.00 comment '厂家超程单价：元/里',
			service_evaluate     char(1) not null comment '用户服务评价，A满意，B一般，C不满意',
			reset_nums           tinyint not null default 0 comment '(return)',
			is_worker_show_factory tinyint not null default 0 comment '''是否给厂家显示技工联系信息'' is_show_tell ',
		   primary key (worker_order_id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='工单额外信息表';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
	}

	protected function workeOrderStatistics($is_delete = false)
	{
		$table = 'worker_order_statistics';
		if ($is_delete) {
			$result = $this->deleteTable($table);
			$this->resultRule[] = $result['sql'];
			return $result;
		} elseif ($this->tableShowStatus($table)) {
			return false;
		}
		$sql = <<<MYSQL
		create table {$table}
		(
		   worker_order_id      int(11) not null comment 'order_id,维修工单id',
		   accessory_order_num  smallint not null default 0 comment '配件数量',
		   cost_order_num       smallint not null default 0 comment '费用单数量',
		   allowance_order_num  smallint not null default 0 comment '补贴单数量',
		   complaint_order_num  smallint not null default 0 comment '投诉单数量',
		   total_accessory_num  smallint not null default 0 comment '配件单总数',
		   accessory_unsent_num smallint not null default 0 comment '未发件数量',
		   accessory_worker_unreceive_num smallint not null default 0 comment '配件待技工签收数(配件单技工未签收数量)',
		   accessory_unreturn_num smallint not null default 0 comment '配件单未返件数',
		   total_message_num    int not null default 0,
		   unread_message_num   smallint not null default 0 comment '留言数量',
		   unread_message_worker smallint not null default 0 comment '最近留言数（用于计算是否有留言跟新）',
		   unread_message_factory smallint not null default 0 comment '最近厂家留言数',
		   unread_message_admin smallint not null default 0 comment '未读客服最近留言数',
		   worker_add_apply_num smallint not null default 0,
		   primary key (worker_order_id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='工单附加信息统计（配件、费用等）表';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
	}

}
