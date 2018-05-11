ALTER TABLE `factory` ADD COLUMN `is_show_yima_ad` tinyint NOT NULL DEFAULT 1 COMMENT '是否显示易码激活后广告：1是，0否';

ALTER TABLE `factory` ADD `qrcode_person` varchar(255) DEFAULT NULL COMMENT '厂家易码联系人姓名' AFTER `technology_tel`;
ALTER TABLE `factory` ADD `qrcode_tell` varchar(100) DEFAULT NULL COMMENT '厂家易码联系人电话' AFTER `qrcode_person`;