<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomMember;
use Illuminate\Http\Request;

class RoomRequestController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST REQUEST JOIN ROOM
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $admin = $request->user();

        // Ambil room milik admin
        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        // Ambil semua request pending
        $requests = RoomMember::with('user')
            ->where('room_id', $room->id)
            ->where('status', RoomMember::STATUS_PENDING)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar request room',
            'data' => $requests
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE USER
    |--------------------------------------------------------------------------
    */

    public function approve(Request $request, $id)
    {
        $admin = $request->user();

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        // Cari membership milik room admin
        $member = RoomMember::where('id', $id)
            ->where('room_id', $room->id)
            ->first();

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Request member tidak ditemukan'
            ], 404);
        }

        // Update status
        $member->update([
            'status' => RoomMember::STATUS_APPROVED,
            'joined_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diapprove',
            'data' => $member
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT USER
    |--------------------------------------------------------------------------
    */

    public function reject(Request $request, $id)
    {
        $admin = $request->user();

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        // Cari membership milik room admin
        $member = RoomMember::where('id', $id)
            ->where('room_id', $room->id)
            ->first();

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Request member tidak ditemukan'
            ], 404);
        }

        // Update status
        $member->update([
            'status' => RoomMember::STATUS_REJECTED
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditolak',
            'data' => $member
        ]);
    }
}