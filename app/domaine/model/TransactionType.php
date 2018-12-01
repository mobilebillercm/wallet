<?php
/**
 * Created by PhpStorm.
 * Holder: nkalla
 * Date: 20/09/18
 * Time: 12:35
 */

namespace App\domaine\model;


use Illuminate\Database\Eloquent\Model;

/*
 * topup
 * payment
 * deposit
 * withdraw
 */

class TransactionType extends Model
{
    protected $table = 'transactiontypes';
    protected $fillable = ['b_id', 'code', 'name', 'description', 'signe'];

    public function __construct($b_id = null, $code = null, $name = null, $description = null, $signe = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
        $this->signe = $signe;
    }

}
