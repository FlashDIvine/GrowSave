<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | CONSTANT STATUS
    |--------------------------------------------------------------------------
    */

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'admin_id',
        'room_name',
        'room_code',
        'description',
        'status'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    // Room dimiliki admin
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // Room memiliki banyak member
    public function members()
    {
        return $this->hasMany(RoomMember::class);
    }

    // Room memiliki banyak member yang sudah approved
    public function approvedMembers()
    {
        return $this->hasMany(RoomMember::class)->where('status', 'approved');
    }

    // Room memiliki banyak pengumuman
    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    // Room memiliki banyak tagihan
    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}