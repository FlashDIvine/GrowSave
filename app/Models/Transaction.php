<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | CONSTANT TYPE
    |--------------------------------------------------------------------------
    */

    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'room_id',
        'user_id',
        'type',
        'category',
        'amount',
        'transaction_date',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Accessor untuk type.
     * Mengubah format database 'in' -> 'income', 'out' -> 'expense'
     * agar sesuai dengan kebutuhan serialisasi data aplikasi Android.
     */
    public function getTypeAttribute($value)
    {
        return $value === 'in' ? 'income' : 'expense';
    }

    /**
     * Mutator untuk type.
     * Mengubah format input dari aplikasi 'income'/'in' -> 'in', 'expense'/'out' -> 'out'
     * sebelum disimpan ke database.
     */
    public function setTypeAttribute($value)
    {
        $this->attributes['type'] = ($value === 'income' || $value === 'in') ? 'in' : 'out';
    }
}
