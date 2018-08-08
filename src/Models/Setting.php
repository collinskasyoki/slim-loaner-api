<?php

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
    	'name', 'share_value', 'loan_duration', 'loan_interest', 'loan_borrowable', 'min_guarantors', 'retention_fee', 'notifications', 'notification_number',
    ];

    protected $casts = [
    	'notifications'=>'boolean',
    ];
}
