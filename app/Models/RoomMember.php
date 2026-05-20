<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomMember extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | CONSTANT STATUS
    |--------------------------------------------------------------------------
    */

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_LEFT = 'left';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'room_id',
        'user_id',
        'status',
        'joined_at'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    // Membership milik user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Membership milik room
    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}