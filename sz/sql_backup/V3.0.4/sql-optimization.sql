ALTER TABLE `worker_order` DROP INDEX  if exists `worker_group_id`;
ALTER TABLE `worker_order` ADD INDEX (`worker_group_id`);


ALTER TABLE `consumer` DROP INDEX  if exists `tell`;
ALTER TABLE `consumer` ADD INDEX (`tell`);

