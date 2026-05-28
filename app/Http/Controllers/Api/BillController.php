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

            'amount' => $request->required_amount, // Set amount to required_amount for backward compatibility

            'target_amount' => $request->target_amount,

            'required_amount' => $request->required_amount,

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

    /*
    |--------------------------------------------------------------------------
    | COMPLETE — SELESAIKAN TAGIHAN SECARA MANUAL OLEH ADMIN
    |--------------------------------------------------------------------------
    */

    public function complete(Request $request, $id)
    {
        $admin = $request->user();

        if ($admin->role !== User::ROLE_ADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat menutup iuran ini'
            ], 403);
        }

        $room = $admin->room;
        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        $bill = Bill::where('id', $id)
            ->where('room_id', $room->id)
            ->first();

        if (!$bill) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan tidak ditemukan'
            ], 404);
        }

        if ($bill->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Tagihan sudah diselesaikan/ditutup'
            ], 400);
        }

        $bill->update([
            'is_completed' => true,
            'completed_at' => now(),
            'status' => Bill::STATUS_CLOSED
        ]);

        // Batalkan seluruh transaksi/pembayaran pending warga untuk iuran ini
        \App\Models\Payment::where('bill_id', $bill->id)
            ->where('status', \App\Models\Payment::STATUS_PENDING)
            ->update([
                'status' => \App\Models\Payment::STATUS_CANCEL
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Tagihan berhasil diselesaikan dan dinonaktifkan',
            'data' => $bill
        ]);
    }
}
