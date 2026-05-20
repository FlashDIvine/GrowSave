<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Http\Request;

class CashBalanceController extends Controller
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
    | INDEX — TRANSPARANSI KAS RT
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
        | TOTAL SALDO KAS
        |--------------------------------------------------------------------------
        */

        $totalSaldoKas = Payment::where('status', Payment::STATUS_SETTLEMENT)
            ->whereHas('bill', function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })
            ->join('bills', 'payments.bill_id', '=', 'bills.id')
            ->sum('bills.amount');

        /*
        |--------------------------------------------------------------------------
        | PEMASUKAN BULAN INI
        |--------------------------------------------------------------------------
        */

        $pemasukanBulanIni = Payment::where('status', Payment::STATUS_SETTLEMENT)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->whereHas('bill', function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })
            ->join('bills', 'payments.bill_id', '=', 'bills.id')
            ->sum('bills.amount');

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Data transparansi kas',
            'data' => [
                'total_saldo_kas' => (float) $totalSaldoKas,
                'pemasukan_bulan_ini' => (float) $pemasukanBulanIni
            ]
        ]);
    }
}
