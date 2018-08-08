<?php

use Illuminate\Database\Eloquent\Model;

class NotifySend extends Model
{
  protected $table = 'notify_sends';
  protected $fillable = ['messageto', 'messagefrom', 'message', 'member_id'];
}
