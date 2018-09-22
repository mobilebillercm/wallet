<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 20/09/18
 * Time: 14:10
 */

namespace App\domaine\model;


interface ITransactionOperator
{
    public function canBeDone(MobileBillerCreditAccountTransaction $transaction);
}
