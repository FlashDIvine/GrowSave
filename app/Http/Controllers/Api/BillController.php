<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Bill;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Http\Request;

class BillController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ROOM ID HELPER
    |--------------------------------------------------------------------------
    */

    /**
     * Ambil room_id berdasarkan role user.
     * Admin: dari relasi room.
     * User: dari membership yang approved.
     *
     * @return int|null
     */
    private function getRoomId($user)
    {
        if ($user->role === User::ROLE_ADMIN) {
            return $user->room?->id;
        }

        $membership = RoomMember::where('user_id', $user->id)
            ->where('status', RoomMember::STATUS_APPROVED)
            ->latest()
            ->first();

        return $membership?->room_id;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX — DAFTAR TAGIHAN
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ID
        |--------------------------------------------------------------------------
        */

        $roomId = $this->getRoomId($user);

        if (!$roomId) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | AMBIL DATA TAGIHAN
        |--------------------------------------------------------------------------
        */

        $bills = Bill::with('creator:id,name')
            ->where('room_id', $roomId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan',
            'data' => $bills
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW — DETAIL TAGIHAN
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, $id)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ID
        |--------------------------------------------------------------------------
        */

        $roomId = $this->getRoomId($user);

        if (!$roomId) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI TAGIHAN
        |--------------------------------------------------------------------------
        */

        $bill = Bill::with('creator:id,name')
            ->where('id', $id)
            ->where('room_id', $roomId)
            ->first();

        if (!$bill) {

            return response()->json([
                'success' => false,
                'message' => 'Tagihan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail tagihan',
            'data' => $bill
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — BUAT TAGIHAN BARU
    |--------------------------------------------------------------------------
    */

    public function store(StoreBillRequest $request)
    {
        $admin = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ADMIN
        |--------------------------------------------------------------------------
        */

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | BUAT TAGIHAN
        |--------------------------------------------------------------------------
        */

        $bill = Bill::create([
            'room_id' => $room->id,

            'title' => $request->title,

            'description' => $request->description,

            'amount' => $request->amount,

            'due_date' => $request->due_date,

            'status' => $request->status ?? Bill::STATUS_ACTIVE,

            'created_by' => $admin->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tagihan berhasil dibuat',
            'data' => $bill
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — UBAH TAGIHAN
    |--------------------------------------------------------------------------
    */

    public function update(UpdateBillRequest $request, $id)
    {
        $admin = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ADMIN
        |--------------------------------------------------------------------------
        */

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI TAGIHAN MILIK ROOM
        |--------------------------------------------------------------------------
        */

        $bill = Bill::where('id', $id)
            ->where('room_id', $room->id)
            ->first();

        if (!$bill) {

            return response()->json([
                'success' => false,
                'message' => 'Tagihan tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE DATA
        |--------------------------------------------------------------------------
        */

        $bill->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tagihan berhasil diperbarui',
            'data' => $bill
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — HAPUS TAGIHAN (SOFT DELETE)
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ADMIN
        |--------------------------------------------------------------------------
        */

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI TAGIHAN MILIK ROOM
        |--------------------------------------------------------------------------
        */

        $bill = Bill::where('id', $id)
            ->where('room_id', $room->id)
            ->first();

        if (!$bill) {

            return response()->json([
                'success' => false,
                'message' => 'Tagihan tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | SOFT DELETE
        |--------------------------------------------------------------------------
        */

        $bill->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tagihan berhasil dihapus'
        ]);
    }
}
