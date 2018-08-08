<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guarant extends Model
{

    protected $dates = ['deleted_at'];

    protected $fillable = [
    	'member_id', 'loan_id', 'amount', 'received_date', 'loan_owner_id', 'to_release', 'retention_fee',
    ];

    public function loan_owner(){
    	return $this->hasOne('Member', 'id', 'loan_owner_id');
    }

    public function loan(){
    	return $this->hasOne('Loan', 'id', 'loan_id');
    }

    public function member(){
    	return $this->belongsTo('Member');
    }
}
