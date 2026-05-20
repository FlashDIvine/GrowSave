<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | CONSTANT STATUS (MIDTRANS)
    |--------------------------------------------------------------------------
    */

    const STATUS_PENDING = 'pending';
    const STATUS_SETTLEMENT = 'settlement';
    const STATUS_EXPIRE = 'expire';
    const STATUS_CANCEL = 'cancel';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'bill_id',
        'user_id',
        'notes',
        'snap_token',
        'transaction_id',
        'payment_type',
        'status',
        'paid_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    // Pembayaran milik tagihan
    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    // Pembayaran milik user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
