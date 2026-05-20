<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | CONSTANT STATUS
    |--------------------------------------------------------------------------
    */

    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'room_id',
        'title',
        'description',
        'amount',
        'due_date',
        'status',
        'created_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    // Tagihan milik room
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    // Pembuat tagihan
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Tagihan memiliki banyak pembayaran
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
