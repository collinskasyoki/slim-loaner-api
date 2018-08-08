<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
	
    protected $dates = ['deleted_at'];
	
    protected $fillable = [
    	'member_id', 'amount', 'date_due', 'approved', 'date_given', 'paid_full', 'guarantors_csv', 'amount_payable', 'owner', 'defaulted', 'flag', 'warned', 'retention_fee', 'extreme_due', 'installment',
    ];

    protected $casts = [
    	'paid_full'=>'boolean', 'defaulted'=>'boolean', 'warned'=>'boolean',
    ];

    public function member(){
    	return $this->belongsTo('Member');
    }

    public function guarants(){
    	return $this->hasMany('Guarant');
    }

    public function payments(){
    	return $this->hasMany('Payment');
    }
}
