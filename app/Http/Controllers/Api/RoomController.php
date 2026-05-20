<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ROOM
    |--------------------------------------------------------------------------
    */

    public function show(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | ADMIN ROOM
        |--------------------------------------------------------------------------
        */

        if ($user->role === User::ROLE_ADMIN) {

            $room = $user->room;

            if (!$room) {

                return response()->json([
                    'success' => false,
                    'message' => 'Room tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data room admin',
                'data' => [
                    'id' => $room->id,
                    'room_name' => $room->room_name,
                    'room_code' => $room->room_code,
                    'description' => $room->description,
                    'status' => $room->status,
                    'total_members' => $room->members()
                        ->where('status', RoomMember::STATUS_APPROVED)
                        ->count()
                ]
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | USER MEMBERSHIP
        |--------------------------------------------------------------------------
        */

        $membership = RoomMember::with('room')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$membership) {

            return response()->json([
                'success' => false,
                'message' => 'Anda belum memiliki room'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | PENDING
        |--------------------------------------------------------------------------
        */

        if ($membership->status === RoomMember::STATUS_PENDING) {

            return response()->json([
                'success' => false,
                'message' => 'Menunggu persetujuan admin'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | REJECTED
        |--------------------------------------------------------------------------
        */

        if ($membership->status === RoomMember::STATUS_REJECTED) {

            return response()->json([
                'success' => false,
                'message' => 'Permintaan masuk ditolak admin'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | APPROVED
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Data room user',
            'data' => [
                'membership_status' => $membership->status,

                'room' => [
                    'id' => $membership->room->id,
                    'room_name' => $membership->room->room_name,
                    'room_code' => $membership->room->room_code,
                    'description' => $membership->room->description,
                    'status' => $membership->room->status
                ]
            ]
        ]);
    }
}