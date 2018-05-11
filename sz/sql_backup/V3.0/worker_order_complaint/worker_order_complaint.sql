CREATE TABLE worker_order_complaint_backup SELECT * FROM worker_order_complaint;
drop table if exists worker_order_complaint;

/*==============================================================*/
/* Table: worker_order_complaint                                */
/*==============================================================*/
create table worker_order_complaint
(
   id                   int(11) not null auto_increment,
   complaint_modify_type_id int not null,
   complaint_type_id    int not null,
   worker_order_id      int(11) not null comment 'order_id ά�޹���id',
   complaint_number     varchar(30) not null comment 'copltno  Ͷ�ߵ���',
   cp_complaint_type_name varchar(16) not null comment 'Ͷ������',
   complaint_from_id    int not null comment 'complaint_from  Ͷ����ID',
   complaint_from_type  tinyint not null comment 'Ͷ�������# F�����ң�W������S���ͷ� 1�����ң�2������3���ͷ�',
   complaint_to_id      int not null comment 'complaint_to ��Ͷ���˵�ID',
   complaint_to_type    tinyint not null comment 'Ͷ�߶������� #��S���ͷ�����W��������F�����ң� 1�����ң�2������3���ͷ�',
   content              text not null comment 'Ͷ�߾�������',
   create_time          int not null comment 'Ͷ���ύʱ��',
   contact_name         varchar(50) not null comment 'link_name ��ϵ������ (������,֪ͨ���˵���ϵ��Ϣ)',
   contact_tell         varchar(50) not null comment 'link_tell ��ϵ�˵绰',
   reply_result         text not null comment '����(�ظ�)',
   reply_time           int not null comment '�����ظ���ʱ��',
   is_true              tinyint not null default 0 comment 'is_check Ͷ���Ƿ���ʵ��0��1�� (��ʵ������,,����רԱ)',
   cp_complaint_type_name_modify varchar(20) not null comment 'comfirm_type ������,��ʵ���Ͷ������ ( complaint_to_type )',
   response_type        varchar(20) not null comment '���η���1ά���̣�2�ͷ���3�µ��ˣ�4�û�',
   is_saticsfy          tinyint not null comment 'Ͷ�����Ƿ����⣬1�ǣ�2��',
   comfirm_remark       text not null comment 'remark  �������ܱ�ע',
   worker_deductions    tinyint not null default 0 comment 'deduction �����۷�',
   primary key (id),
	 key `worker_order_id` (`worker_order_id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='����Ͷ��';

INSERT into worker_order_complaint(id, worker_order_id, complaint_number, `cp_complaint_type_name`, complaint_from_id, complaint_from_type,
complaint_to_id, complaint_to_type, content, create_time, contact_name, contact_tell, reply_result, reply_time, is_true,
cp_complaint_type_name_modify, response_type, is_saticsfy, comfirm_remark, worker_deductions) SELECT
id, worker_order_id, copltno, comfirm_type, complaint_from, complaint_from_type, complaint_to, complaint_to_type, content, addtime, link_name,
link_tell, reply_result, reply_time, is_check, comfirm_type, response_type, is_saticsfy, remark, deduction FROM worker_order_complaint_backup;