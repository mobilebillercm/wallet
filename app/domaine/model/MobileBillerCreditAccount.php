<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 21/09/18
 * Time: 09:08
 */

namespace App\domaine\model;


use Illuminate\Database\Eloquent\Model;

class MobileBillerCreditAccount extends Model
{
    protected $table = 'mobilebillercreditaccounts';
    protected $fillable = ['b_id', 'accountnumber', 'holder', 'balance', 'photo', 'issuer', 'active', 'currency'];

    public function __construct($b_id = null, $accountnumber = null, $holder = null, $balance = null,
                                $photo = null, $issuer = null, $active = null, $currency = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->accountnumber = $accountnumber;
        $this->holder = $holder;
        $this->balance = $balance;
        $this->photo = $photo;
        $this->issuer = $issuer;
        $this->active = $active;
        $this->currency = $currency;
    }

    public function makeOperation(TransactionType $transactionType, float $amount){
        if ($transactionType->signe === '-'){
            $this->balance -= $amount;
        }else{
            $this->balance += $amount;
        }

    }

    public function isPossible(TransactionType $transactionType, float $amount){
        if ($transactionType->signe === '-'){
            if ($this->balance < $amount){
                return false;
            }
        }

        return true;
    }
}
