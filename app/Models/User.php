<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | CONSTANT ROLE
    |--------------------------------------------------------------------------
    */

    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'email',
        'whatsapp_number',
        'house_block',
        'house_number',
        'password',
        'role',
        'profile_photo'
    ];

    /*
    |--------------------------------------------------------------------------
    | HIDDEN
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    // Admin memiliki 1 room
    public function room()
    {
        return $this->hasOne(Room::class, 'admin_id');
    }

    // User memiliki banyak membership
    public function memberships()
    {
        return $this->hasMany(RoomMember::class);
    }

    // User memiliki banyak pembayaran
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Accessor untuk mendapatkan room_id secara dinamis.
     * Dapat diakses via $user->room_id.
     *
     * @return int|null
     */
    public function getRoomIdAttribute()
    {
        if ($this->isAdmin()) {
            return $this->room?->id;
        }

        $membership = RoomMember::where('user_id', $this->id)
            ->where('status', RoomMember::STATUS_APPROVED)
            ->latest()
            ->first();

        return $membership?->room_id;
    }
}