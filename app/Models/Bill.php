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
        'target_amount',
        'required_amount',
        'collected_amount',
        'is_completed',
        'completed_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $appends = ['user_payment_status'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'required_amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
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

    // Mengambil status pembayaran personal warga yang sedang login
    public function getUserPaymentStatusAttribute()
    {
        $userId = auth()->id();
        if (!$userId) {
            return 'unpaid';
        }

        $hasPaid = $this->payments()
            ->where('user_id', $userId)
            ->where('status', Payment::STATUS_SETTLEMENT)
            ->exists();

        if ($hasPaid) {
            return 'paid';
        }

        $hasPending = $this->payments()
            ->where('user_id', $userId)
            ->where('status', Payment::STATUS_PENDING)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if ($hasPending) {
            return 'pending';
        }

        return 'unpaid';
    }
}
