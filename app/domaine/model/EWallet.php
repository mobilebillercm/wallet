<?php
/**
 * Created by PhpStorm.
 * Holder: nkalla
 * Date: 20/09/18
 * Time: 12:48
 */

namespace App\domaine\model;


use Illuminate\Database\Eloquent\Model;

class EWallet extends Model
{
    protected $table = 'ewallets';
    protected $fillable = ['b_id', 'holder', 'accounts'];

    public function __construct($b_id = null, $holder = null, $accounts = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->holder = $holder;
        $this->accounts = $accounts;
    }

    public function addAccounts(array $accounts){
        $array = json_decode($this->accounts);
        for ($i = 0; $i<count($accounts); $i++){
            array_push($array, $accounts[$i]);
        }

        $this->accounts = json_encode($array, JSON_UNESCAPED_SLASHES);
    }

}
