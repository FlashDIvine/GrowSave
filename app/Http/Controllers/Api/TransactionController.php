<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\RoomMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ROOM ID HELPER
    |--------------------------------------------------------------------------
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
    | INDEX — RINGKASAN & RIWAYAT (BISA DIAKSES ADMIN & WARGA)
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
        | HITUNG SALDO KESELURUHAN (TOTAL MASUK - TOTAL KELUAR)
        |--------------------------------------------------------------------------
        */

        $totalIn = Transaction::where('room_id', $roomId)
            ->where('type', Transaction::TYPE_IN)
            ->sum('amount');

        $totalOut = Transaction::where('room_id', $roomId)
            ->where('type', Transaction::TYPE_OUT)
            ->sum('amount');

        $totalSaldo = $totalIn - $totalOut;

        /*
        |--------------------------------------------------------------------------
        | HITUNG METRIK BULAN INI
        |--------------------------------------------------------------------------
        */

        $monthIn = Transaction::where('room_id', $roomId)
            ->where('type', Transaction::TYPE_IN)
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $monthOut = Transaction::where('room_id', $roomId)
            ->where('type', Transaction::TYPE_OUT)
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | AMBIL 10 TRANSAKSI TERAKHIR
        |--------------------------------------------------------------------------
        */

        $history = Transaction::with('user:id,name')
            ->where('room_id', $roomId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Data transparansi kas',
            'data' => [
                'total_saldo' => (float) $totalSaldo,
                'pemasukan_bulan_ini' => (float) $monthIn,
                'pengeluaran_bulan_ini' => (float) $monthOut,
                'riwayat_transaksi' => $history
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — CATAT TRANSAKSI BARU (HANYA ADMIN)
    |--------------------------------------------------------------------------
    */

    public function store(StoreTransactionRequest $request)
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
        | BUAT TRANSAKSI
        |--------------------------------------------------------------------------
        */

        $transaction = Transaction::create([
            'room_id' => $room->id,
            'user_id' => $admin->id,
            'type' => $request->type,
            'category' => $request->category,
            'amount' => $request->amount,
            'transaction_date' => $request->transaction_date,
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dicatat',
            'data' => $transaction
        ], 201);
    }
}
