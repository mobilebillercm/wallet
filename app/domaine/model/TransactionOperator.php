<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 20/09/18
 * Time: 14:09
 */

namespace App\domaine\model;


class TransactionOperator implements ITransactionOperator
{


    public function canBeDone(MobileBillerCreditAccountTransaction $transaction)
    {

        $mobilebillercreditaccounts = MobileBillerCreditAccount::where('b_id', '=', $transaction->mobilebillercreditaccount)->get();
        if (!(count($mobilebillercreditaccounts) === 1)){
            return false;
        }
        $mobilebillercreditaccount = $mobilebillercreditaccounts[0];
        if ($transaction->amount > $mobilebillercreditaccount->balance){
            return false;
        }
        $transactionTypes = TransactionType::where('b_id', '=', $transaction->transaction_type)->get();
        if (!(count($transactionTypes) === 1)){
            return false;
        }

        $holders = Holder::where('b_id', '=', $mobilebillercreditaccount->holder)->get();

        if(!(count($holders) === 1)){
            return false;
        }

        if ($holders[0]->enablement == 0){
            $transactionType = $transactionTypes[0];
            if (strpos(strtolower($transactionType->name), strtolower('withdraw')) === false or
                strpos(strtolower($transactionType->name), strtolower('payment')) === false) {
                return false;
            }
        }

        return true;
    }
}
