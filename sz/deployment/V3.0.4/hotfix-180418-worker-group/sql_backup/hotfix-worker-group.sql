alter table worker_group modify cp_owner_nickname varchar(256) not null DEFAULT '' COMMENT '群主昵称';

update worker_group set cp_owner_nickname=(select nickname from worker where worker_id=worker_group.owner_worker_id) where id>0;