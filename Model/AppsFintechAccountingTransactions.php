<?php

namespace Apps\Fintech\Packages\Accounting\Transactions\Model;

use System\Base\BaseModel;

class AppsFintechAccountingTransactions extends BaseModel
{
    public $id;

    public $book_id;

    public $cr_accounts_uuid;

    public $dr_accounts_uuid;

    public $uuid;

    public $date;

    public $sequence;

    public $description;

    public $status;

    public $amount;

    public $has_split;

    public $is_split;

    public $attachments;
}