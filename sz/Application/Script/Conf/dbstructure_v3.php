<?php
return [
    'TRANSFER_EXTEND_ID' => 'transfer_extend_id', // 部分迁移数据时，删除数据使用

    'SYNC_TIME' => 'sync_time', // F方法存储上一次同步时间键名
    'SYNC_FIELD_NAME' => 'db_transfer_change_time', // 数据库记录数据变动列名
    'SYNC_LOG_PATH' => './sql_backup/V3.0/sync_time/', // F方法存储值位置


    'STRUCTURES_F_KEY'  => 'db/structures',
    'STRUCTURES_F_PATH' => './sql_backup/V3.0/structures_cache',

    'DATA_REANSFER_F_KEY'  => 'data/transfer_',
    'DATA_REANSFER_F_PATH' => './sql_backup/V3.0/data_transfer_cache',

	'OLD_AND_NEW_BACKUP' => '_backup',
	'STRUCTURES_CONFIG_V3' => [
		'worker_order' => [
			'name'  => 'worker_order',
			'logic' => 'WorkerOrder',
			'model' => 'WorkerOrder',
			'other' => [
				'worker_order_detail' => 'worker_order_product',
				'worker_order_user_info' => 'worker_order_user_info',
				'worker_order_ext_info' => 'worker_order_ext_info',
				'worker_order_total' => 'worke_order_statistics',
				'worker_order_fee' => 'worker_order_fee',
			],
		],
        'worker_order_fee'  => [
            'name'  => 'worker_order_fee',
            'logic' => 'workerOrderFee',
        ],
		'worker_order_operation_record' => [
			'name' 	=> 'worker_order_operation_record',
			'logic' => 'workerOrderOperationRecord',
		],
		'worker_order_revisit' => [
			'name'  => 'worker_order_revisit_record',
			'logic' => 'WorkerOrderRevisit',
			// 'model' => 'WorkerOrder',
		],
		//  厂家冻结金模块
		'factory_money_frozen' => [
			'name'  => 'factory_money_frozen',
			'logic' => 'FactoryMoneyFrozen',
			'other' => [
				'factory_money_frozen_record' => 'factory_money_frozen_record',
			],
		],
		'worker_order_appoint' => [
			'name'  => 'worker_order_appoint_record',
			'logic' => 'WorkerOrderAppoint',
			// 'model' => 'WorkerOrder',
		],
		// 'product_category' => [
		// 	'name'  => 'product_category',
		// 	'logic' => 'ProductCategory',
		// 	'comment' => 'cm_list_item => add',
		// ],
		// 'area' => [
		// 	'name'  => 'area',
		// 	'logic' => 'Area',
		// 	'comment' => 'cm_list_item => add',
		// ],

        // ================================================================================================================================================
        'worker_money_adjust_record' => [
            'name' => 'worker_money_adjust_record',
            'logic' => 'WorkerMoneyAdjustRecord',
        ],
        'worker_contact_record' => [
            'name'  => 'worker_contact_record',
            'logic' => 'WorkerContactRecord',
        ],
        'worker_money_out_record' => [
            'name'  => 'worker_withdrawcash_record',
            'logic' => 'WorkerMoneyOutRecord',
        ],
        'worker_money_excel_out' => [
            'name'  => 'worker_withdrawcash_excel',
            'logic' => 'WorkerMoneyExcelOut',
        ],
        'worker_order_mess' => [
            'name'  => 'worker_order_message',
            'logic' => 'WorkerOrderMess',
        ],
        'worker_add_apply' => [
            'name'  => 'worker_add_apply',
            'logic' => 'WorkerAddApply',
        ],
        'worker_order_complaint' => [
            'name'  => 'worker_order_complaint',
            'logic' => 'WorkerOrderComplaint',
            'other' => [
                'complaint_type' => 'complaint_type',
            ],
        ],
        'worker_order_incost' => [
            'name'  => 'worker_order_apply_allowance',
            'logic' => 'WorkerOrderIncost',
        ],
        'factory_cost_order' => [
            'name'  => 'worker_order_apply_cost',
            'logic' => 'FactoryCostOrder',
        ],
        'factory_cost_order_record' => [
            'name'  => 'worker_order_apply_cost_record',
            'logic' => 'FactoryCostOrderRecord',
        ],
        'worker_money_record'   => [
            'name'  => 'worker_money_record',
            'logic' => 'WorkerMoneyRecord',
        ],

        'factory_money_set_record'  => [
            'name'  => 'factory_fee_config_record',
            'logic' => 'FactoryMoneySetRecord',
            'other' => [
                'factory_money_change_record' => 'factory_money_change_record',
            ],
        ],

        'worker_money_in_record' => [
            'name'  => 'worker_repair_money_record',
            'logic' => 'WorkerMoneyInRecord',
        ],

        'factory_money_pay_record' => [
        	'name'  => 'factory_repair_pay_record',
        	'logic' => 'FactoryMoneyPayRecord',
        ],
        'worker_order_msg' => [
            'name'  => 'worker_order_system_message',
            'logic' => 'WorkerOrderMsg',
            'other' => [
                'factory_order_msg' => 'worker_order_system_message',
            ],
        ],
        'worker_order_settle_note'      => [
            'name'  => 'worker_order_audit_remark',
            'logic' => 'WorkerOrderSettleNote',
            'model' => 'WorkerOrderSettleNote',
        ],

        'factory_acce_order'        => [
            'name'  => 'worker_order_apply_accessory',
            'logic' => 'Accessories',
        ],
        'factory_acce_order_record' => [
            'name'  => 'worker_order_apply_accessory_record',
            'logic' => 'AccessoriesRecord',
        ],
        'factory_acce_order_detail' => [
            'name'  => 'worker_order_apply_accessory_item',
            'logic' => 'AccessoriesDetail',
        ],
        'express_track'             => [
            'name'  => 'express_tracking',
            'logic' => 'ExpressTracking',
        ],
        'worker_order_hint'             => [
            'name'  => 'worker_notification',
            'logic' => 'WorkerOrderHint',
        ],

    ],
    'DATA_TRANSFER_CONFIG' => [
        // [
        //     'worker_order',
        // ],
        // [
        //      'factory_acce_order',
        // ],
        // [
        //      'factory_acce_order_detail',
        // ],
        // [
        //      'factory_acce_order_record',
        // ],
        // [
        //      'express_track',
        // ],
        // [
        //     'worker_order_incost',
        // ],
        // [
        //     'factory_cost_order',
        // ],
        // [
        //     'factory_cost_order_record',
        // ],
        // [
        //     'worker_order_mess',
        // ],
        // [
        //     'factory_money_frozen',
        // ],
        // [
        //     'factory_money_pay_record',
        // ],
        // [
        //     'factory_money_set_record',
        // ],
        // [
        //     'worker_money_adjust_record',
        // ],
        // [
        //    'worker_money_in_record',
        // ],
        // [
        //     'worker_money_out_record',
        // ],
        // [
        //     'worker_money_record',
        // ],
        // [
        //     'worker_money_excel_out',
        // ],
        // [
        //     'worker_order_appoint',
        // ],
        // [
        //     'worker_order_revisit',
        // ],
        // [
        //     'worker_order_operation_record',
        // ],
        // [
        //     'worker_contact_record',
        // ],
        // [
        //     'worker_order_settle_note',
        // ],
        // [
        //     'worker_order_msg',
        // ],
        // [
        //     'worker_order_complaint',
        // ],
        // [
        //     'worker_add_apply',
        // ],
        // [    
        //    'worker_order_fee', // 必须放在模块最后
        // ]
    ],
    'FUNCTION_CONFIG_MODEL' => [
        'worker_order'                  => [
            'worker_order',
            'worker_order_detail',
            // 'worker_order_user_info',
            // 'worker_order_ext_info',
            // 'worker_order_statistics',
        ],
        'worker_order_fee'              => [
            // 'worker_order_fee'
        ],
        'worker_order_operation_record' => [
            'worker_order_operation_record',
        ],
        'worker_order_revisit'          => [
            'worker_order_revisit',
        ],
        'factory_money_frozen'          => [
            'factory_money_frozen',
        ],
        'worker_order_appoint'          => [
            'worker_order_appoint',
        ],
        'worker_contact_record'         => [
            'worker_contact_record',
        ],
        'worker_money_out_record'       => [
            'worker_money_out_record',
        ],
        'worker_money_excel_out'        => [
            'worker_money_excel_out',
        ],
        'worker_order_mess'             => [
            'worker_order_mess',
        ],
        'worker_add_apply'              => [
            'worker_add_apply',
        ],
        'worker_order_complaint'        => [
            'worker_order_complaint',
        ],
        'worker_order_incost'           => [
            'worker_order_incost',
        ],
        'factory_cost_order'            => [
            'factory_cost_order',
        ],
        'factory_cost_order_record' => [
            'factory_cost_order_record',
        ],
        'worker_money_record'           => [
            // 'worker_money_record'
            // 'worker_money_in_record',
            'worker_money_adjust_record',
            // 'worker_money_out_record',
        ],
        'factory_money_set_record'      => [
            'factory_money_set_record',
            // 'factory_money_change_record',
        ],
        'worker_money_in_record'        => [
            'worker_money_in_record',
            // 'worker_quality_money_record',
        ],
        'factory_money_pay_record'      => [
            'factory_money_pay_record',
        ],
        'worker_order_msg'              => [
            'worker_order_msg',
            'factory_order_msg',
        ],
        'worker_order_settle_note'      => [
            'worker_order_settle_note',
        ],
        'factory_acce_order'            => [
            'factory_acce_order',
        ],
        'factory_acce_order_record'     => [
            'factory_acce_order_record',
        ],
        'factory_acce_order_detail'     => [
            'factory_acce_order_detail',
        ],
        'express_track'                 => [
            'express_track',
        ],
        'worker_order_hint'             => [
            'worker_order_hint',
        ],
    ],
];