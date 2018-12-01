<?php
/**
 * Created by PhpStorm.
 * Holder: nkalla
 * Date: 20/09/18
 * Time: 12:57
 */

namespace App\domaine\model;


use Illuminate\Database\Eloquent\Model;

class Holder extends Model
{
    protected $table = 'holders';
    protected $fillable = ['b_id', 'firstname', 'lastname', 'enablement', 'username', 'email', 'phone', 'ewallet', 'mobilebillercreditaccount'];

    public function __construct($b_id = null, $firstname = null, $lastname = null, $enablement = null, $username = null,
                                $email = null, $phone = null, $ewallet = null,  $mobilebillercreditaccount = null, $attributes = array())
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->enablement = $enablement;
        $this->username = $username;
        $this->email = $email;
        $this->phone = $phone;
        $this->ewallet = $ewallet;
        $this->mobilebillercreditaccount = $mobilebillercreditaccount;
    }

    public  function isEnable(){
        return $this->enablement == 1;
    }


}
