<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 14/09/18
 * Time: 10:15
 */

namespace App\domain\model;


use Illuminate\Database\Eloquent\Model;

class PaymentMethodType extends Model
{
    const CREDIT_CARD = "0";
    const MOBILE_MONEY = "1";
    const MOBILE_BILLER_ACCOUNT = "2";
    //const
    protected $table = 'paymentmethodtypes';
    protected $fillable = ['b_id', 'name', 'description', 'provider', 'icon', 'active', 'api', 'created_by', 'created_at', 'updated_at'];

    public function __construct($b_id = null, $name = null, $description = null, $provider = null, $icon = null,
                                $active = null, Api $api = null, $created_by = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->name = $name;
        $this->description = $description;
        $this->provider = $provider;
        $this->icon = $icon;
        $this->active = $active;
        $this->api = json_encode($api, JSON_UNESCAPED_SLASHES);
        $this->created_by = $created_by;
    }

}
