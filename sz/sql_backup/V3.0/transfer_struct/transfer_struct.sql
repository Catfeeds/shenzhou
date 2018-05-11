/*==============================================================*/
/* 配件单                                */
/*==============================================================*/
ALTER TABLE `factory_acce_order` ADD COLUMN `db_transfer_change_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '增量迁移使用';
ALTER TABLE `factory_acce_order_detail` ADD COLUMN `db_transfer_change_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '增量迁移使用';
ALTER TABLE `factory_acce_order_record` ADD COLUMN `db_transfer_change_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '增量迁移使用';
ALTER TABLE `express_track` ADD COLUMN `db_transfer_change_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '增量迁移使用';