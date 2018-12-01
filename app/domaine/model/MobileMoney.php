<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 17/09/18
 * Time: 10:19
 */

namespace App\domain\model;


use Illuminate\Database\Eloquent\Model;

class MobileMoney extends Model
{
    protected $table = 'mobilemoneys';
    protected $fillable = ['b_id', 'phonenumber', 'country_code', 'country_dialing_code','holder', 'issuer', 'active', 'created_at', 'updated_at'];

    public function __construct($b_id = null, $phonenumber = null, $country_code = null, $country_dialing_code = null,
                                $holder = null, $issuer = null, $active = null,  array $attributes = [])
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->phonenumber = $phonenumber;
        $this->country_code = $country_code;
        $this->country_dialing_code = $country_dialing_code;
        $this->holder = $holder;
        $this->issuer = $issuer;
        $this->active = $active;
    }
}
