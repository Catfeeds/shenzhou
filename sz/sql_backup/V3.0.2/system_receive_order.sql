create table admin_config_receive (
	admin_id int unsigned not null comment '客服id,取自admin',
	type tinyint unsigned not null default 0 comment '接单类型 1-按厂家 2-按品类 3-按地区 4-轮流 5-按厂家组别',
	is_auto_receive tinyint unsigned not null default 0 comment '是否接单 0-否 1-是',
	max_receive_times smallint unsigned not null default 0 comment '每日最大接单量',
	primary key(admin_id)
)default charset=utf8 engine=innodb comment '自动接单-客服配置表';

create table admin_config_receive_factory(
	id int unsigned not null auto_increment,
	admin_id int unsigned not null default 0 comment '客服id 取自admin',
	factory_id int unsigned not null default 0 comment '厂家id 取自factory',
	primary key(id),
	KEY `factory_id` (`factory_id`,`admin_id`)
)engine=innodb default charset=utf8 comment '自动接单-厂家客服关系表';

create table admin_config_receive_category(
	id int unsigned not null auto_increment,
	admin_id int unsigned not null default 0 comment '客服id 取自admin',
	category_id int unsigned not null default 0 comment '品类id',
	parent_id int unsigned not null default 0 comment '父级地区id,顶级为0 ',
	primary key(id),
	KEY `category_id` (`category_id`,`admin_id`)
)engine=innodb default charset=utf8 comment '自动接单-品类客服关系表';

create table admin_config_receive_area(
	id int unsigned not null auto_increment,
	area_id int unsigned not null default 0 comment '地区id 取自area',
	admin_id int unsigned not null default 0 comment '客服id 取自admin',
	parent_id int unsigned not null default 0 comment '父级地区id,顶级为0 ',
	primary key(id),
	KEY `area_id` (`area_id`,`admin_id`)
)engine=innodb default charset=utf8 comment '自动接单-地区客服关系表';

create table admin_config_receive_workday(
	id int unsigned not null auto_increment,
	workday tinyint unsigned not null default 0 comment '星期几',
	admin_id int unsigned not null default 0 comment '客服id 取自admin',
	is_on_duty tinyint unsigned not null default 0 comment '是否值日 0-否 1-是',
	primary key(id)
)engine=innodb default charset=utf8 comment '自动接单-工作日客服关系表';

create table admin_config_receive_factory_group(
  id int unsigned not null auto_increment,
  group_id SMALLINT unsigned not null default 0 comment '组id',
  admin_id int unsigned not null default 0 comment '客服id 取自admin',
  primary key(id),
  key group_id (group_id,admin_id)
)engine=innodb default charset=utf8 comment '自动接单-厂家组别关系表';

create table admin_config_receive_partner(
  id int unsigned not null auto_increment,
  partner_admin_id int unsigned not null default 0 comment '合作客服id 取自admin',
  admin_id int unsigned not null default 0 comment '客服id 取自admin',
  primary key(id),
  key group_id (partner_admin_id,admin_id)
)engine=innodb default charset=utf8 comment '自动接单-对接人关系表';