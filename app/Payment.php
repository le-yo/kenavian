<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'client_name',
        'phone',
        'amount',
        'status',
        'transaction_id',
        'account_no',
        'transaction_time',
        'paybill',
        'comments'
    ];
}
