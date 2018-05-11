/*
Navicat MySQL Data Transfer

Source Server         : 神州测试
Source Server Version : 100210
Source Host           : 120.79.84.241:37813
Source Database       : shenzhou

Target Server Type    : MYSQL
Target Server Version : 100210
File Encoding         : 65001

Date: 2017-12-27 11:06:00
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for ad_position
-- ----------------------------
DROP TABLE IF EXISTS `ad_position`;
CREATE TABLE `ad_position` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(50) NOT NULL DEFAULT '' COMMENT '展示位置名',
  `name` varchar(60) NOT NULL DEFAULT '' COMMENT '展示位置中文名',
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of ad_position
-- ----------------------------
INSERT INTO `ad_position` VALUES ('1', 'index_top', '用户微信端首页顶部', '0');

-- ----------------------------
-- Table structure for ad_position_photo
-- ----------------------------
DROP TABLE IF EXISTS `ad_position_photo`;
CREATE TABLE `ad_position_photo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position_id` int(11) NOT NULL DEFAULT 0,
  `pic_url` varchar(255) NOT NULL DEFAULT '' COMMENT '图片url',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '图片链接',
  `create_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for coupon_operation_record
-- ----------------------------
DROP TABLE IF EXISTS `coupon_operation_record`;
CREATE TABLE `coupon_operation_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_rule_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL COMMENT '管理员id（平台客服id）',
  `origin_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8 COMMENT='优惠劵操作记录';

-- ----------------------------
-- Table structure for coupon_receive_record
-- ----------------------------
DROP TABLE IF EXISTS `coupon_receive_record`;
CREATE TABLE `coupon_receive_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) NOT NULL,
  `draw_record_id` int(11) DEFAULT NULL COMMENT '抽奖记录id',
  `prize_item_id` int(11) DEFAULT NULL COMMENT '奖项记录id',
  `worker_order_id` int(11) DEFAULT NULL COMMENT 'order_id维修工单id',
  `wx_user_id` int(11) NOT NULL,
  `coupon_code` varchar(32) DEFAULT NULL COMMENT '备用字段',
  `receive_mode` tinyint(4) DEFAULT NULL COMMENT '领奖方式：1-抽奖领取，2-定向发送，3-公众号赠送',
  `use_time` int(11) DEFAULT NULL,
  `start_time` int(11) DEFAULT NULL COMMENT '就是优惠劵创建时间',
  `end_time` int(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1 COMMENT '券状态：1-未使用，2-已使用，3-已作废，4-已过期（暂无用）',
  `cp_user_phone` char(11) DEFAULT NULL,
  `cp_full_money` int(11) DEFAULT NULL,
  `cp_reduce_money` int(11) DEFAULT NULL,
  `cp_orno` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2710 DEFAULT CHARSET=utf8 COMMENT='优惠劵领取记录';

-- ----------------------------
-- Table structure for coupon_rule
-- ----------------------------
DROP TABLE IF EXISTS `coupon_rule`;
CREATE TABLE `coupon_rule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL COMMENT '管理员id（平台客服id）',
  `title` varchar(60) DEFAULT NULL,
  `coupon_type` tinyint(4) DEFAULT 1 COMMENT '1-通用优惠劵',
  `form` tinyint(4) DEFAULT 1 COMMENT '1-满减',
  `full_money` int(11) DEFAULT NULL,
  `reduce_money` int(11) DEFAULT NULL,
  `number` int(11) DEFAULT 0,
  `frozen_number` int(11) DEFAULT 0,
  `start_time` int(11) DEFAULT NULL COMMENT '备用字段',
  `end_time` int(11) DEFAULT NULL COMMENT '备用字段',
  `effective_type` int(11) DEFAULT 1 COMMENT '使用有效期类型：1-固定天数，2-固定时间段',
  `effective_time_rule` int(11) DEFAULT NULL,
  `invalid_time_rule` int(11) DEFAULT NULL COMMENT 'effective_type为1存放天数、为2存放过期时间戳',
  `limit_number` int(11) DEFAULT NULL COMMENT '备用字段',
  `status` tinyint(4) DEFAULT NULL COMMENT '状态：1-进行中，2-已暂停，3-已结束',
  `total_receive_user` int(11) DEFAULT 0 COMMENT '总领取人数',
  `total_receive_times` int(11) DEFAULT 0 COMMENT '总领取次数',
  `total_use` int(11) DEFAULT 0 COMMENT '优惠劵已使用数',
  `is_delete` tinyint(4) DEFAULT 0 COMMENT '是否删除：0-否 >0-删除时间戳',
  `is_disable` char(10) DEFAULT '0' COMMENT '是否禁用：0-正常 1-禁用',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8 COMMENT='优惠劵规则';

-- ----------------------------
-- Table structure for draw_record
-- ----------------------------
DROP TABLE IF EXISTS `draw_record`;
CREATE TABLE `draw_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `draw_rule_id` int(11) DEFAULT NULL COMMENT '抽奖活动id',
  `prize_item_id` int(11) NOT NULL,
  `wx_user_id` int(11) DEFAULT NULL,
  `channel` tinyint(4) DEFAULT NULL COMMENT '获奖途径：1-厂家易码，2-师傅码，3-浏览器，4-其他',
  `status` tinyint(4) DEFAULT NULL COMMENT '状态：0-未中奖，1-已中奖（未兑奖），2-已中奖（已兑现），3-已中奖（已过期）',
  `address_json` varchar(255) DEFAULT NULL,
  `express_compay` varchar(32) DEFAULT NULL COMMENT '快递公司',
  `express_code` varchar(16) DEFAULT NULL COMMENT '快递公司代码',
  `express_sn` varchar(16) DEFAULT NULL COMMENT '快递单号',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL COMMENT '更新时间',
  `expire_time` int(11) DEFAULT NULL,
  `ip` varchar(16) DEFAULT NULL COMMENT 'ip地址',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1340 DEFAULT CHARSET=utf8 COMMENT='抽奖记录';

-- ----------------------------
-- Table structure for draw_rule
-- ----------------------------
DROP TABLE IF EXISTS `draw_rule`;
CREATE TABLE `draw_rule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL COMMENT '管理员id（平台客服id）',
  `title` varchar(60) DEFAULT NULL,
  `start_time` int(11) DEFAULT NULL,
  `end_time` int(11) DEFAULT NULL,
  `people_draw_number` int(11) DEFAULT NULL,
  `max_win_draw_number` int(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 0 COMMENT '状态：0-未发布，1-已发布，2-已结束',
  `total_draw_user` int(11) DEFAULT 0 COMMENT '总抽奖人数',
  `total_draw_times` int(11) DEFAULT 0 COMMENT '总抽奖次数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8 COMMENT='抽奖规则';

-- ----------------------------
-- Table structure for draw_statistics
-- ----------------------------
DROP TABLE IF EXISTS `draw_statistics`;
CREATE TABLE `draw_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `draw_rule_id` int(11) NOT NULL,
  `channel` tinyint(4) DEFAULT NULL COMMENT '获奖途径：1-厂家易码，2-师傅码，3-浏览器，4-其他',
  `draw_number` int(11) DEFAULT NULL,
  `draw_people_number` int(11) DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8 COMMENT='抽奖每日统计';

-- ----------------------------
-- Table structure for prize_item
-- ----------------------------
DROP TABLE IF EXISTS `prize_item`;
CREATE TABLE `prize_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `draw_rule_id` int(11) NOT NULL,
  `name` varchar(32) DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL COMMENT '奖品类型：1-单张维修券，2-维修优惠劵包，3-商品',
  `winning_rate` int(11) DEFAULT NULL,
  `prize_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '奖品结果：单张或多张优惠劵存放{"id":"x", "number":"x"}，商品存放直接用外面的prize_name',
  `prize_name` varchar(32) DEFAULT NULL COMMENT '奖项奖品名称',
  `prize_number` int(11) DEFAULT NULL,
  `prize_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=192 DEFAULT CHARSET=utf8 COMMENT='短链接表';
