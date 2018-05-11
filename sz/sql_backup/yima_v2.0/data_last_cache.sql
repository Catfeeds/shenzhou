CREATE TABLE `data_last_cache` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(250) NOT NULL DEFAULT '' COMMENT '用户类型：admin平台客服，factory 厂家客服，factory_admin厂家子账号客服，worker 技工，wxuser 微信普通用户，wxdealer 微信用户经销商',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `content` JSON NOT NULL COMMENT '缓存内容 json',
  `content_type` tinyint(2) NOT NULL COMMENT '缓存数据的类型 1，申请记录绑定的质保策略',
  `update_time` int(10) NOT NULL COMMENT '最后的时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;