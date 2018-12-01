<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 20/09/18
 * Time: 13:16
 */

namespace App\domaine\model;


use Illuminate\Database\Eloquent\Model;

class MobileBillerCreditAccountTransaction extends Model
{
    const PENDING = "PENDING";
    const FAILED = "FAILED";
    const SUCCESS = "SUCCESS";
    protected $table = 'mobilebillercreditaccounttransactions';
    protected $fillable = ['b_id', 'date', 'mobilebillercreditaccount', 'made_by', 'amount', 'transaction_type',
        'transaction_details', 'user_transaction_number', 'state', 'returned_result', 'accountstate'];

    public function __construct($b_id = null, $date = null, $mobilebillercreditaccount = null, $made_by = null, $amount = null,
                                $transaction_type = null, TransactionDetail $transaction_details = null, $user_transaction_number = null,
                                $state = null, $returned_result = null, $accountstate = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->date = $date;
        $this->mobilebillercreditaccount = $mobilebillercreditaccount;
        $this->made_by = $made_by;
        $this->amount = $amount;
        $this->transaction_type = $transaction_type;
        $this->transaction_details = json_encode($transaction_details, JSON_UNESCAPED_SLASHES);
        $this->user_transaction_number = $user_transaction_number;
        $this->state = $state;
        $this->returned_result = $returned_result;
        $this->accountstate = $accountstate;
    }


}
