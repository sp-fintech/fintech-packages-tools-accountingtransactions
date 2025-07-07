<?php

namespace Apps\Fintech\Packages\Accounting\Transactions;

use Apps\Fintech\Packages\Accounting\Accounts\Model\AppsFintechAccountingAccounts;
use Apps\Fintech\Packages\Accounting\Books\AccountingBooks;
use Apps\Fintech\Packages\Accounting\Transactions\Model\AppsFintechAccountingTransactions;
use System\Base\BasePackage;

class AccountingTransactions extends BasePackage
{
    protected $modelToUse = AppsFintechAccountingTransactions::class;

    protected $packageName = 'accountingtransactions';

    public $accountingtransactions;

    public function getAccountingTransactionById($id)
    {
        $accountingtransactions = $this->getById($id);

        if ($accountingtransactions) {
            //
            $this->addResponse('Success');

            return;
        }

        $this->addResponse('Error', 1);
    }

    public function addAccountingTransaction($data)
    {
        if (isset($data['clone']) && $data['clone'] == 'true') {
            $accountingtransaction = $this->getById($data['id']);

            $accountingtransaction['clone'] = false;
            $accountingtransaction['sequence'] = 99;
            $accountingtransaction['attachments'] = [];
            unset($accountingtransaction['id']);

            if (!$this->addAccountingTransaction($accountingtransaction)) {
                $this->addResponse('Error cloning transaction', 1);

                return false;
            }

            $clonedAccountTransaction = $this->packagesData->last;

            if ($accountingtransaction['has_splits']) {
                $cloneAccountingSplitTransactions = $this->getAccountTransactions([
                    'book_id'           => $data['book_id'],
                    'accounts_uuid'     => $data['accounts_uuid'],
                    'transaction_uuid'  => $accountingtransaction['uuid'],
                    'is_split'          => 'true',
                ]);

                if (isset($cloneAccountingSplitTransactions['transactions']) &&
                    count($cloneAccountingSplitTransactions['transactions']) > 0
                ) {
                    foreach ($cloneAccountingSplitTransactions['transactions'] as $cloneTransactionId => $cloneTransaction) {
                        unset($cloneTransaction['id']);
                        $cloneTransaction['uuid'] = $clonedAccountTransaction['uuid'];

                        if (!$this->add($cloneTransaction)) {
                            $this->addResponse('Error cloning split transaction', 1);

                            return false;
                        }
                    }
                }
            }

            $this->getAccountTransactions($data);

            return true;
        } else {
            if (!isset($data['description']) ||
                (isset($data['description']) && $data['description'] === '')
            ) {
                $this->addResponse('Please provide transaction description', 1);

                return false;
            }

            if (isset($data['sequence'])) {
                if ((int) $data['sequence'] <= 0) {
                    $data['sequence'] = 0;
                }

                if ((int) $data['sequence'] > 99) {
                    $data['sequence'] = 99;
                }
            } else {
                $data['sequence'] = 0;
            }

            if (isset($data['amount'])) {
                if ((int) $data['amount'] <= 0) {
                    $this->addResponse('Please provide transaction amount', 1);

                    return false;
                }
            } else {
                $this->addResponse('Please provide transaction amount', 1);

                return false;
            }

            if ($data['cr_accounts_uuid'] === '' || $data['dr_accounts_uuid'] === '') {
                $this->addResponse('Please provide credit/debit accounts', 1);

                return false;
            }

            if (isset($data['id']) && isset($data['is_split'])) {
                $accountingtransaction = $this->getById($data['id']);

                if (!$accountingtransaction) {
                    $this->addResponse('Transaction with ID not found', 1);

                    return false;
                }

                $accountingtransactions = $this->getAccountTransactions([
                    'book_id'           => $data['book_id'],
                    'accounts_uuid'     => $data['accounts_uuid'],
                    'transaction_uuid'  => $accountingtransaction['uuid'],
                    'is_split'          => 'true',
                ]);

                if ($accountingtransactions['balance'] + (float) $data['amount'] > $accountingtransaction['amount']) {
                    $this->addResponse('Split transactions amount exceeds main transaction amount', 1);

                    return false;
                }

                $newSplitAccountingTransaction = $accountingtransaction;
                $newSplitAccountingTransaction['dr_accounts_uuid'] = $data['dr_accounts_uuid'];
                $newSplitAccountingTransaction['sequence'] = $data['sequence'];
                $newSplitAccountingTransaction['description'] = $data['description'];
                $newSplitAccountingTransaction['amount'] = $data['amount'];
                $newSplitAccountingTransaction['has_splits'] = false;
                $newSplitAccountingTransaction['is_split'] = true;
                unset($newSplitAccountingTransaction['id']);
                // trace([$data]);
                if ($this->add($newSplitAccountingTransaction)) {
                    $accountingtransaction['has_splits'] = true;

                    $this->update($accountingtransaction);

                    $data['transaction_uuid'] = $accountingtransaction['uuid'];

                    $this->getAccountTransactions($data);

                    return true;
                }

                $this->addResponse('Error adding split transaction', 1);

                return false;
            } else {
                $data['uuid'] = $this->getNewTransactionUUID();
                $data['status'] = 'n';
            }

            if (!isset($data['date'])) {
                $data['date'] = (\Carbon\Carbon::now())->toDateString();
            }

            $data['has_splits'] = false;
            if ($data['cr_accounts_uuid'] === 'split' || $data['dr_accounts_uuid'] === 'split') {
                $data['has_splits'] = true;
            }

            if (!isset($data['is_split'])) {
                $data['is_split'] = false;
            }

            if ($this->add($data)) {
                if (!isset($data['clone'])) {
                    $this->getAccountTransactions($data);
                }

                return true;
            }
        }

        $this->addResponse('Error adding transaction', 1);

        return false;
    }

    public function updateAccountingTransaction($data)
    {
        $accountingtransaction = $this->getById($data['id']);

        if (isset($data['is_split']) && isset($data['split_id'])) {
            $accountingSplitTransactions = $this->getAccountTransactions([
                'book_id'           => $accountingtransaction['book_id'],
                'accounts_uuid'     => $data['accounts_uuid'],
                'transaction_uuid'  => $accountingtransaction['uuid'],
                'is_split'          => 'true',
            ]);

            if (count($accountingSplitTransactions['transactions']) > 0) {
                $accountingSplitTransactions['balance'] = $accountingSplitTransactions['balance'] - $accountingSplitTransactions['transactions'][$data['split_id']]['amount'];
            }

            if ($accountingSplitTransactions['balance'] + (float) $data['amount'] > (float) $accountingtransaction['amount']) {
                $this->addResponse('Split transactions amount exceeds main transaction amount', 1);

                return false;
            }

            $data['id'] = $data['split_id'];

            $accountingtransaction = $this->getById($data['id']);//Get Split Transaction
        }

        if (!$accountingtransaction) {
            $this->addResponse('Transaction with ID not found', 1);

            return false;
        }

        if (isset($data['removeAttachment']) && isset($data['attachmentUUID'])) {
            $key = array_search($data['attachmentUUID'], $accountingtransaction['attachments']);

            if ($key !== false) {
                $this->basepackages->storages->changeOrphanStatus(newUUID : $data['attachmentUUID'], status: 1);

                unset($accountingtransaction['attachments'][$key]);
            }
        }

        $accountingtransaction = array_merge($accountingtransaction, $data);

        if ($this->update($accountingtransaction)) {
            if (isset($data['status'])) {
                $this->getAccountTransactions($data);

                return true;
            } else {
                if ($accountingtransaction['has_splits']) {
                    $accountingSplitTransactions = $this->getAccountTransactions([
                        'book_id'           => $data['book_id'],
                        'accounts_uuid'     => $data['accounts_uuid'],
                        'transaction_uuid'  => $accountingtransaction['uuid'],
                        'is_split'          => 'true',
                    ]);

                    if (isset($accountingSplitTransactions['transactions']) &&
                        count($accountingSplitTransactions['transactions']) > 0
                    ) {
                        foreach ($accountingSplitTransactions['transactions'] as $splitTransactionId => $splitTransaction) {
                            $splitTransaction['date'] = $data['date'];

                            if (!$this->update($splitTransaction)) {
                                $this->addResponse('Error updating split transaction', 1);

                                return false;
                            }
                        }
                    }

                    $this->getAccountTransactions($data);

                    return true;
                }

                if (isset($data['is_split']) && isset($data['split_id'])) {
                    $data['transaction_uuid'] = $accountingtransaction['uuid'];
                }

                $this->getAccountTransactions($data);

                return true;
            }

            $this->addResponse('Updated Transaction');

            return true;
        }

        $this->addResponse('Error updating transaction', 1);

        return false;
    }

    public function removeAccountingTransaction($data)
    {
        $accountingtransaction = $this->getById($data['id']);

        if (!$accountingtransaction) {
            $this->addResponse('Transaction with ID not found', 1);

            return false;
        }

        if ($accountingtransaction['attachments'] && count($accountingtransaction['attachments']) > 0) {
            foreach ($accountingtransaction['attachments'] as $key => $attachment) {
                $this->basepackages->storages->changeOrphanStatus(newUUID : $attachment, status: 1);//mark it as orphan

                unset($accountingtransaction['attachments'][$key]);
            }
        }

        if ($accountingtransaction['has_splits']) {
            if (!$this->remove($accountingtransaction['id'])) {
                $this->addResponse('Error removing transaction', 1);

                return false;
            }

            $splitTransactions = $this->getAccountTransactions([
                'book_id'           => $accountingtransaction['book_id'],
                'transaction_uuid'  => $accountingtransaction['uuid'],
                'is_split'          => 'true',
            ]);

            if (isset($splitTransactions['transactions']) &&
                count($splitTransactions['transactions']) > 0
            ) {
                foreach ($splitTransactions['transactions'] as $splitTransactionId => $splitTransaction) {
                    if (!$this->remove($splitTransaction['id'])) {
                        $this->addResponse('Error removing split transaction', 1);

                        return false;
                    }
                }
            }

            $this->getAccountTransactions($data);

            return true;
        } else {
            if ($this->remove($accountingtransaction['id'])) {
                if ($accountingtransaction['is_split']) {
                    $data['transaction_uuid'] = $accountingtransaction['uuid'];
                }

                $this->getAccountTransactions($data);

                return true;
            }
        }

        $this->addResponse('Error updating transaction', 1);

        return false;
    }

    public function reconcileAccountingTransactions($data)
    {
        $data['is_split'] = 'false';
        $data['not_reconciled'] = 'true';
        $bookPackage = $this->usePackage(AccountingBooks::class);

        $book = $bookPackage->getAccountingBookById((int) $data['book_id']);

        if (!$book) {
            $this->addResponse('Book with ID not found', 1);

            return false;
        }

        $reconciledBalance = 0;
        if (isset($book['accounts'][$data['accounts_uuid']]['last_reconciled_balance'])) {
            $reconciledBalance = $book['accounts'][$data['accounts_uuid']]['last_reconciled_balance'];
        }

        $transactionsArr = $this->getAccountTransactions($data);

        if ($transactionsArr['transactions'] &&
            count($transactionsArr['transactions']) > 0
        ) {
            $transactions = [];

            $statementDate = \Carbon\Carbon::parse($data['reconcile_date']);

            foreach ($transactionsArr['transactions'] as $transactionArr) {
                if (in_array($transactionArr['id'], $data['transaction_ids'])) {
                    $transactionDate = \Carbon\Carbon::parse($transactionArr['date']);

                    if ($transactionDate->lte($statementDate)) {
                        $transactions[$transactionArr['id']] = $transactionArr;
                    }
                }
            }

            if (count($transactions) > 0) {
                $endingBalance = (float) $data['reconcile_ending_balance'];
                $difference = (float) $endingBalance - $reconciledBalance;

                foreach ($transactions as $transaction) {
                    if ($transaction['cr_accounts_uuid'] === $data['accounts_uuid']) {
                        $reconciledBalance = numberFormatPrecision($reconciledBalance - $transaction['amount']);
                        $difference = numberFormatPrecision($difference + $transaction['amount']);
                    } else {
                        $reconciledBalance = numberFormatPrecision($reconciledBalance + $transaction['amount']);
                        $difference = numberFormatPrecision($difference - $transaction['amount']);
                    }
                }

                if ($reconciledBalance !== $endingBalance) {
                    $this->addResponse('Ending Balance mismatch. Provided ending balanace : ' . $endingBalance . '. Calculated Balance : ' . $reconciledBalance, 1);

                    return false;
                }

                if ($difference != 0) {
                    $this->addResponse('Difference mismatch. Difference should come to 0 but instead we get : ' . $difference, 1);

                    return false;
                }

                foreach ($transactions as $transaction) {
                    $transaction['status'] = 'r';

                    $this->update($transaction);
                }

                unset($data['not_reconciled']);
                unset($data['reconcile_date']);
                unset($data['reconcile_ending_balance']);
                unset($data['transaction_ids']);
                $data['is_split'] = 'false';
                // trace([$data]);
                $this->getAccountTransactions($data);

                return true;
            }
        }

        $this->addResponse('No transactions to reconcile');
    }

    public function getNewTransactionUUID()
    {
        $uuid = str_replace('-', '', $this->random->uuid());

        $this->addResponse('UUID Generated', 0, ['uuid' => $uuid]);

        return $uuid;
    }

    public function getAccountTransactions($data)
    {
        if ($this->config->databasetype === 'db') {
            if (isset($data['transaction_uuid'])) {
                $conditions =
                    [
                        'conditions'    => 'book_id = :bookId: AND is_split = :isSplit: AND uuid = :UUID:',
                        'bind'          =>
                            [
                                'bookId'            => $data['book_id'],
                                'isSplit'           => $data['is_split'] === 'true' ? 1 : 0,
                                'uuid'              => $data['transaction_uuid']
                            ]
                    ];
            } else {
                if (isset($data['not_reconciled']) && $data['not_reconciled'] == 'true') {
                    $conditions =
                        [
                            'conditions'    => 'book_id = :bookId: AND is_split = :isSplit: AND status = :statusN: OR status = :statusC: AND cr_accounts_uuid = :crAccountsUUID: OR dr_accounts_uuid = :drAccountsUUID:',
                            'bind'          =>
                                [
                                    'bookId'            => $data['book_id'],
                                    'isSplit'           => 0,
                                    'statusN'           => 'n',
                                    'statusC'           => 'c',
                                    'drCccountsUUID'    => $data['accounts_uuid'],
                                    'crCccountsUUID'    => $data['accounts_uuid'],
                                    'drCccountsUUID'    => $data['accounts_uuid']
                                ]
                        ];
                } else {
                    $conditions =
                        [
                            'conditions'    => 'book_id = :bookId: AND cr_accounts_uuid = :crAccountsUUID: OR dr_accounts_uuid = :drAccountsUUID:',
                            'bind'          =>
                                [
                                    'bookId'            => $data['book_id'],
                                    'crCccountsUUID'    => $data['accounts_uuid'],
                                    'drCccountsUUID'    => $data['accounts_uuid']
                                ]
                        ];
                    }
                }

            $transactionsArr = $this->getByParams($conditions);
        } else {
            $this->ffStore = $this->ff->store($this->ffStoreToUse);

            if (isset($data['transaction_uuid'])) {
                $transactionsArr = $this->ffStore->findBy(
                    [
                        ['book_id', '=', (int) $data['book_id']],
                        ['uuid', '=', $data['transaction_uuid']],
                        ['is_split', '=', $data['is_split'] === 'true' ? true : false]
                    ],
                    ['date' => 'asc', 'sequence' => 'asc']
                );
            } else {
                if (isset($data['not_reconciled']) && $data['not_reconciled'] == 'true') {
                    $transactionsArr = $this->ffStore->findBy(
                        [
                            ['book_id', '=', (int) $data['book_id']],
                            ['is_split', '=', false],
                            [
                                ['status', '=', 'n'],
                                'OR',
                                ['status', '=', 'c']
                            ],
                            [
                                ['cr_accounts_uuid', '=', $data['accounts_uuid']],
                                'OR',
                                ['dr_accounts_uuid', '=', $data['accounts_uuid']]
                            ]
                        ],
                        ['date' => 'asc', 'sequence' => 'asc']
                    );
                } else {
                    $transactionsArr = $this->ffStore->findBy(
                        [
                            ['book_id', '=', (int) $data['book_id']],
                            [
                                ['cr_accounts_uuid', '=', $data['accounts_uuid']],
                                'OR',
                                ['dr_accounts_uuid', '=', $data['accounts_uuid']]
                            ]
                        ],
                        ['date' => 'asc', 'sequence' => 'asc']
                    );
                }
            }
        }

        $responseData =
            [
                'transactions' => [],
                'count' => 0,
                'balance' => 0,
                'unreconciled_balance' => 0,
                'last_reconciled_date' => null,
                'last_reconciled_balance' => 0,
                'balances' => [],
            ];

        // trace([$data, $transactionsArr]);

        if ($transactionsArr && count($transactionsArr) > 0) {
            $balance = 0;
            $unreconciledBalance = 0;

            $transactions = [];
            // trace([$data]);
            if (!isset($data['transaction_uuid']) &&
                // !isset($data['not_reconciled']) &&
                (isset($data['is_split']) && $data['is_split'] == 'false')
            ) {
                // trace([$data, $transactionsArr]);
                $balances = [];
                $lastReconciledDate = null;
                $lastReconciledBalance = 0;
                $transactionType = null;

                // trace([$transactionsArr]);
                foreach ($transactionsArr as $transactionKey => $transaction) {
                    if (!isset($data['not_reconciled'])) {
                        if ($transaction['attachments'] && count($transaction['attachments']) > 0) {
                            $attachments = [];

                            foreach ($transaction['attachments'] as $attachmentUUID) {
                                $attachments[$attachmentUUID] = $this->basepackages->storages->getFileInfo($attachmentUUID);
                            }

                            if (count($attachments) > 0) {
                                $transaction['attachments'] = $attachments;
                            } else {
                                $transaction['attachments'] = null;
                            }
                        }
                        if ($transaction['is_split']) {
                            if ($transaction['dr_accounts_uuid'] !== $data['accounts_uuid']) {
                                continue;
                            }
                        }
                    }

                    if ($data['accounts_uuid'] === $transaction['dr_accounts_uuid'] ||
                        $transaction['cr_accounts_uuid'] === 'split'
                    ) {
                        $transaction['balance'] = $balance = numberFormatPrecision($balance + $transaction['amount']);
                        $transactionType = 'dr';

                        if (!isset($data['not_reconciled']) && $transaction['cr_accounts_uuid'] === 'split') {
                            $data['transaction_uuid'] = $transaction['uuid'];
                            $data['is_split'] = 'true';
                            $transaction['splits'] = $this->getAccountTransactions($data);
                            $splitsTotal = 0;

                            if (isset($transaction['splits']['transactions']) &&
                                count($transaction['splits']['transactions']) > 0
                            ) {
                                foreach ($transaction['splits']['transactions'] as $splitTransaction) {
                                    $splitsTotal += (float) $splitTransaction['amount'];
                                }
                            }

                            if ($transaction['amount'] != $splitsTotal) {
                                $transaction['error'] = 'Split transactions total does not match transaction total.';
                            }
                        }
                    } else if ($data['accounts_uuid'] === $transaction['cr_accounts_uuid'] ||
                               $transaction['dr_accounts_uuid'] === 'split'
                    ) {
                        $transaction['balance'] = $balance = numberFormatPrecision($balance - $transaction['amount']);
                        $transactionType = 'cr';

                        if (!isset($data['not_reconciled']) && $transaction['dr_accounts_uuid'] === 'split') {
                            $data['transaction_uuid'] = $transaction['uuid'];
                            $data['is_split'] = 'true';
                            $transaction['splits'] = $this->getAccountTransactions($data);
                            $splitsTotal = 0;

                            if (isset($transaction['splits']['transactions']) &&
                                count($transaction['splits']['transactions']) > 0
                            ) {
                                foreach ($transaction['splits']['transactions'] as $splitTransaction) {
                                    $splitsTotal += (float) $splitTransaction['amount'];
                                }
                            }

                            if ($transaction['amount'] != $splitsTotal) {
                                $transaction['error'] = 'Split transactions total does not match transaction total.';
                            }
                        }
                    }

                    //Fill the rest of dates with its previous balance.
                    if ($transactionKey !== count($transactionsArr) - 1) {
                        if ($transaction['date'] !== $transactionsArr[$transactionKey + 1]['date']) {
                            $balances[$transaction['date']] = $transaction['balance'];

                            $this->fillBalanceDays($balances, $transaction, $transactionsArr[$transactionKey + 1]);
                        } else {
                            $balances[$transaction['date']] = $transaction['balance'];
                        }
                    } else {
                        $balances[$transaction['date']] = $transaction['balance'];
                    }

                    $transactions['"' . $transaction['id'] . '"'] = $transaction;//Quotes will maintain the sequence, else json will sort it again in JS

                    if ($transaction['status'] !== 'r') {
                        $unreconciledBalance += $transaction['amount'];
                    } else {
                        $lastReconciledDate = $transaction['date'];
                        if ($transactionType === 'dr') {
                            $lastReconciledBalance += $transaction['amount'];
                        } else if ($transactionType === 'cr') {
                            $lastReconciledBalance -= $transaction['amount'];
                        }
                    }
                }

                if (!isset($data['not_reconciled']) && count($balances) > 0) {
                    $lastDateOfBalance = \Carbon\Carbon::parse($this->helper->lastKey($balances));
                    $today = \Carbon\Carbon::now();
                    if ($today->gt($lastDateOfBalance)) {
                        $startEndDates = (\Carbon\CarbonPeriod::between($this->helper->lastKey($balances), $today->toDateString()))->toArray();

                        if (count($startEndDates) >= 2) {
                            foreach ($startEndDates as $startEndDateKey => $startEndDate) {
                                if ($startEndDateKey === 0) {
                                    continue;
                                }
                                $balances[$startEndDate->toDateString()] = $this->helper->last($balances);
                            }
                        }
                    }
                }

                // if (!isset($data['not_reconciled'])) {
                    // trace([$transactions]);
                // }
                $responseData['balance'] = $balance;
                $responseData['unreconciled_balance'] = $unreconciledBalance;
                $responseData['last_reconciled_date'] = $lastReconciledDate;
                $responseData['last_reconciled_balance'] = $lastReconciledBalance;
                $responseData['balances'] = $balances;
                $responseData['transactions'] = $transactions;

                $accountsModel = new AppsFintechAccountingAccounts;

                if ($this->config->databasetype === 'db') {
                    $accounts = $accountsModel::findFirst(['uuid' => $data['accounts_uuid']]);
                } else {
                    $accountsStore = $this->ff->store($accountsModel->getSource());

                    $accounts = $accountsStore->findOneBy(['uuid', '=', $data['accounts_uuid']]);
                }

                if ($accounts) {
                    $accounts['balance'] = $responseData['balance'];
                    $accounts['last_reconciled_date'] = $lastReconciledDate;
                    $accounts['last_reconciled_balance'] = $lastReconciledBalance;

                    if ($this->config->databasetype === 'db') {
                        $accountsModel->assign($accounts);

                        $accountsModel->update();
                    } else {
                        $accountsStore->update($accounts);
                    }
                }

                $this->addResponse('Retrieved transactions successfully', 0, $responseData);

                return $responseData;
            } else {//Get Split Transactions Balance
                foreach ($transactionsArr as $transactionKey => $transaction) {
                    $balance = numberFormatPrecision($balance + $transaction['amount']);

                    $transactions['"' . $transaction['id'] . '"'] = $transaction;//Quotes will maintain the sequence, else json will sort it again in JS
                }

                $responseData['balance'] = $balance;
                $responseData['transactions'] = $transactions;

                unset($responseData['balances']);
            }

            $responseData['count'] = count($transactions);

            $this->addResponse('Retrieved transactions successfully', 0, $responseData);

            return $responseData;
        }

        $this->addResponse('No transactions found', 0, $responseData);

        return $responseData;
    }

    public function getAccountTransactionViaUUID($uuid)
    {
        //
    }

    protected function fillBalanceDays(&$balances, $transaction, $nextTransaction)
    {
        $startEndDates = (\Carbon\CarbonPeriod::between($transaction['date'], $nextTransaction['date']))->toArray();

        if (count($startEndDates) > 2) {
            foreach ($startEndDates as $dateKey => $date) {
                if ($dateKey === 0 ||
                    $dateKey === count($startEndDates) - 1
                ) {
                    continue;
                }

                $balances[$date->toDateString()] = $transaction['balance'];
            }
        }
    }

    public function searchTransactionDescriptionsAction(string $descriptionQueryString)
    {
        if ($this->config->databasetype === 'db') {
            $searchDescriptions = $this->getByParams(
                [
                    'conditions'    => 'description LIKE :description:',
                    'bind'          => [
                        'description'     => '%' . $descriptionQueryString . '%'
                    ]
                ]
            );
        } else {
            $searchDescriptions = $this->getByParams(['conditions' => ['description', 'LIKE', '%' . $descriptionQueryString . '%']]);
        }

        $descriptions = [];

        if ($searchDescriptions) {
            foreach ($searchDescriptions as $descriptionKey => $descriptionValue) {
                $descriptions[$descriptionKey]['description'] = $descriptionValue['description'];
            }
        }

        $this->addResponse('Ok', 0, ['descriptions' => $descriptions]);

        return $descriptions;
    }
}