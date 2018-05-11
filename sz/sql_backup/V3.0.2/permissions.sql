/*==============================================================*/
/* Table: admin_roles                                           */
/*==============================================================*/
DROP TABLE if exists `admin_roles`;
create table admin_roles
(
   id                   int not null auto_increment,
   name                 varchar(64) not null,
   is_disable           tinyint not null default 0 comment '状态：0启用,1禁用',
   level                tinyint not null default 1 comment '角色级别：1普通客服，2主管',
   create_time          int not null default 0 comment 'add_time,数据添加时间（操作时间）',
   update_time          int not null default 0 comment '',
   is_delete            Integer(11) not null default 0,
   `type`               tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '自动接单可接工单类型 1-核实 2-派单 4-回访 8-财务 ',
   primary key (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '管理员角色';
INSERT INTO `admin_roles` (`id`, `name`, `is_disable`, `level`, `create_time`, `update_time`, `is_delete`, `type`) VALUES (4, '超级管理员', '0', '1', '0', '0', '0', '0');

/*==============================================================*/
/* Table: admin_group                                           */
/*==============================================================*/
drop table if exists admin_group;
create table admin_group
(
   id                   int(11) not null auto_increment,
   name                 varchar(64) not null,
   is_disable           tinyint not null default 0 comment '状态：0启用,1禁用',
   create_time          int not null default 0 comment 'add_time，添加时间',
   update_time          int not null default 0,
   is_delete            Integer(11) not null default 0,
   primary key (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '客服组别';

/*==============================================================*/
/* Table: rel_admin_group                                       */
/*==============================================================*/
drop table if exists rel_admin_group;
create table rel_admin_group
(
   admin_id             int(11) not null comment '管理员id（平台客服id）',
   admin_group_id       int(11) not null,
   primary key (admin_id, admin_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '客服与客服组别';

INSERT INTO `admin_group` (`id`, `name`, `is_disable`, `create_time`, `update_time`, `is_delete`)
VALUES
	(1,'A组',0,1520770782,1520770782,0),
	(2,'B组',0,1520770782,1520770782,0),
	(3,'C组',0,1520770782,1520770782,0),
	(4,'D组',0,1520770782,1520770782,0),
	(5,'E组',0,1520770782,1520770782,0),
	(6,'F组',0,1520770782,1520770782,0),
	(7,'G组',0,1520770782,1520770782,0);

/*==============================================================*/
/* Table: rel_admin_roles                                        */
/*==============================================================*/
drop table if exists rel_admin_roles;
create table rel_admin_roles
(
   admin_id             int(11) not null comment '管理员id（平台客服id）',
   admin_roles_id        int not null,
   primary key (admin_id, admin_roles_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '管理员角色关系';

/*==============================================================*/
/* Table: rel_frontend_routing_admin_roles                          */
/*==============================================================*/
drop table if exists rel_frontend_routing_admin_roles;
create table rel_frontend_routing_admin_roles
(
   admin_roles_id        int not null,
   frontend_routing_id  int not null,
   primary key (admin_roles_id, frontend_routing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '前端路由与管理员角色关系';

/*==============================================================*/
/* Table: frontend_routing                                      */
/*==============================================================*/
drop table if exists frontend_routing;
create table frontend_routing
(
   id                   int not null auto_increment,
   routing              varchar(128) not null,
   name                 varchar(64) not null,
   is_show              tinyint not null default 1 comment '是否显示：1显示，0不显示',
   is_menu              tinyint not null default 1 comment '是否是菜单：1是，0否',
   parent_id            int(11) NOT NULL DEFAULT 0 COMMENT '父级id',
   serial               varchar(128) not null comment '编号，用于前端作对应特殊处理',
   sort                 int not null default 0 comment '排序',
   create_time          int not null default 0 comment 'add_time
            添加时间',
   is_delete            Integer(11) not null default 0,
   primary key (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '权限(前端路由)';

/*==============================================================*/
/* Table: rel_backend_frontend_routing                          */
/*==============================================================*/
drop table if exists rel_backend_frontend_routing;
create table rel_backend_frontend_routing
(
   frontend_routing_id  int not null,
   backend_routing_id   int(11) not null,
   primary key (frontend_routing_id, backend_routing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '前端路由与后端路由关系';


/*==============================================================*/
/* Table: backend_routing                                       */
/*==============================================================*/
drop table if exists backend_routing;
create table backend_routing
(
   id                   int(11) not null auto_increment,
   routing              varchar(128) not null,
   name                 varchar(64) not null,
   description          varchar(255) not null,
   create_time          Integer(11) not null default 0 comment 'datetime，添加时间',
   is_delete            Integer(11) not null default 0,
   primary key (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 comment '后端路由';

alter table `admin` modify `last_login_time` int unsigned not null default 0 comment '最后登录时间,时间戳,单位:秒';

ALTER TABLE `wx_user` DROP INDEX  if exists `openid`;
alter table wx_user add key (openid(10));

ALTER TABLE `worker_order` DROP INDEX  if exists `children_worker_id`;
ALTER TABLE `worker_order` ADD INDEX (`children_worker_id`);


alter table admin modify agent varchar(16) NOT NULL DEFAULT '' COMMENT '电信云坐席号';