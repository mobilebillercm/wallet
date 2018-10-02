<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 20/09/18
 * Time: 13:57
 */

namespace App\domaine\model;


class TransactionDetail
{
    public $made_by, $account_number, $account_holder, $account_security_code, $account_type, $beneficiary, $transactionType;

    /**
     * TransactionDetail constructor.
     * @param $made_by
     * @param $account_number
     * @param $account_holder
     * @param $account_security_code
     * @param $account_type
     */
    public function __construct($made_by, $account_number, $account_holder, $account_security_code, $account_type, $beneficiary, $transactionType)
    {
        $this->made_by = $made_by;
        $this->account_number = $account_number;
        $this->account_holder = $account_holder;
        $this->account_security_code = $account_security_code;
        $this->account_type = $account_type;  // VISA ou MOBILE MONEY ou MOBILE BILLER ACCOUNT  (se referera paymentMethodType)
        $this->beneficiary = $beneficiary;
        $this->transactionType = $transactionType;
    }


}
