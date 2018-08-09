<?php

// use Nicolaslopezj\Searchable\SearchableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Share extends Model
{

    protected $dates = ['deleted_at'];
    protected $casts = ['amount'=>'float'];
    //protected $searchable = [
    //	'columns' => [
    //		'shares.amount'=>3,
    //		'shares.date_received'=>4,
    //	],
   // ];
    
    protected $fillable = [
    	'member_id', 'amount', 'date_received', 'received_by_id', 'received_by', 'paid_by_id', 'paid_by',
    ];

    public function member(){
    	return $this->belongsTo('Member');
    }
}
