<?php

// use Illuminate\Notifications\Notifiable;
// use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use Notifiable;
    // use SearchableTrait;


    //protected $table = '';
    protected $dates = ['deleted_at'];

    /**
    protected $searchable = [
        'columns' => [
            'members.name'=>2,
            'members.id_no'=>3,
            'shares.amount'=>4,
            'shares.date_received'=>5,
            'loans.amount' => 4,
            'loans.date_due' => 5,
            'loans.amount_payable' =>6,
            'loans.date_given' => 8,
            'guarants.amount' => 5,
        ],
        'joins' => [
            'shares' => ['members.id', 'shares.member_id'],
            'loans'  => ['members.id', 'loans.member_id'],
            'guarants' => ['members.id', 'guarants.member_id'],
        ],
    ];
    **/

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'id_no', 'email', 'gender', 'phone', 'next_kin_name', 'next_kin_phone', 'next_kin_id', 'pic', 'speciality', 'registered_date', 'is_active', 'is_member', 'is_defector', 'member_level', 'registration_fee', 'shares', 'shares_held',
    ];

    protected $casts = [
        'is_active'=>'boolean', 'is_defector'=>'boolean', 'is_member'=>'boolean', 'shares'=>'float',
    ];


    public function shares(){
        return $this->hasMany('Share');
    }

    public function contributions(){
        return $this->hasMany('Contribution');
    }

    public function loans(){
        return $this->hasMany('Loan');
    }

    public function payments(){
        return $this->hasMany('Payment');
    }

    public function guarants(){
        return $this->hasMany('Guarant');
    }
}
