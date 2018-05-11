create table `worker_order_workbench_config` (

  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL DEFAULT 0 COMMENT '配置名称',
  `val` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '配置值',
  `remark` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (id)

) default charset=utf8 engine=innodb ;