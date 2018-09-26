<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 23/09/18
 * Time: 08:15
 */

namespace App\domaine\model;


use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{

    protected $table = 'currencies';
    protected $fillable = ['b_id', 'code', 'name', 'description'];

    public function __construct($b_id = null, $code = null, $name = null, $description = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->b_id = $b_id;
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
    }

}
