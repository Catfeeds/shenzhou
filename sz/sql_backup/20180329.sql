ALTER TABLE `pay_platform_record` add `platform_order_no` VARCHAR(125)  CHARACTER SET utf8  COLLATE utf8_general_ci  NULL  DEFAULT ''  COMMENT '第三方平台单号' AFTER `out_order_no`;
