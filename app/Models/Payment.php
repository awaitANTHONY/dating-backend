<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'user_id', 
        'title',
        'date', 
        'amount', 
        'platform',
        'transaction_id',
        'original_transaction_id',
        'payment_type', // verification, subscription, boost
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id')->withDefault(['name' => '']);
    }

}
