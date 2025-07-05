<?php

namespace Apps\Fintech\Packages\Accounting\Transactions\Install\Schema;

use Phalcon\Db\Column;
use Phalcon\Db\Index;

class AccountingTransactions
{
    public function columns()
    {
        return
        [
           'columns' => [
                new Column(
                    'id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                        'autoIncrement' => true,
                        'primary'       => true,
                    ]
                ),
                new Column(
                    'book_id',
                    [
                        'type'          => Column::TYPE_SMALLINTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'cr_accounts_uuid',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 50,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'dr_accounts_uuid',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 50,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'uuid',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 50,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'date',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 15,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'sequence',
                    [
                        'type'          => Column::TYPE_TINYINTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'description',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 100,
                        'notNull'       => true,
                    ]
                ),
                // new Column(
                //     'type',
                //     [
                //         'type'          => Column::TYPE_VARCHAR,
                //         'size'          => 2,
                //         'notNull'       => true,
                //     ]
                // ),
                new Column(
                    'status',//n-not cleared, c-cleared, r-reconciled
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 1,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'amount',
                    [
                        'type'          => Column::TYPE_FLOAT,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'has_splits',
                    [
                        'type'          => Column::TYPE_BOOLEAN,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'is_split',
                    [
                        'type'          => Column::TYPE_BOOLEAN,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'attachments',
                    [
                        'type'          => Column::TYPE_JSON,
                        'notNull'       => false,
                    ]
                ),
            ],
            // 'indexes' => [
            //     new Index(
            //         'column_UNIQUE',
            //         [
            //             'last_name'
            //         ],
            //         'UNIQUE'
            //     )
            // ],
            'options' => [
                'TABLE_COLLATION' => 'utf8mb4_general_ci'
            ]
        ];
    }

    public function indexes()
    {
        return
        [
            new Index(
                'column_INDEX',
                [
                    'book_id',
                    'description',
                    'cr_accounts_uuid',
                    'dr_accounts_uuid',
                    'uuid'
                ],
                'INDEX'
            )
        ];
    }
}
