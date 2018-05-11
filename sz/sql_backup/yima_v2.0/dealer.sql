ALTER TABLE factory_product_white_list CHANGE is_use `status` tinyint NOT NULL DEFAULT 0 COMMENT '状态：0待授权，1启用，2停用';

UPDATE factory_product_white_list set `status`=if(`status`=1,0,1);

ALTER TABLE factory_product_white_list ADD COLUMN `desc` text COMMENT '备注';

ALTER TABLE wx_user ADD COLUMN `add_time` INT NOT NULL DEFAULT 0 COMMENT '添加时间';

ALTER TABLE dealer_bind_products DROP COLUMN `md5code`;