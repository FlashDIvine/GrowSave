<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RoomMember;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — STATISTIK DASHBOARD ADMIN
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
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
        | TOTAL SALDO KAS
        |--------------------------------------------------------------------------
        | Sum amount dari bills yang memiliki payment settlement
        | di room ini.
        */

        $totalSaldoKas = Payment::where('status', Payment::STATUS_SETTLEMENT)
            ->whereHas('bill', function ($query) use ($room) {
                $query->where('room_id', $room->id);
            })
            ->join('bills', 'payments.bill_id', '=', 'bills.id')
            ->sum('bills.amount');

        /*
        |--------------------------------------------------------------------------
        | TOTAL WARGA (APPROVED)
        |--------------------------------------------------------------------------
        */

        $totalWarga = RoomMember::where('room_id', $room->id)
            ->where('status', RoomMember::STATUS_APPROVED)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | PEMASUKAN BULAN INI
        |--------------------------------------------------------------------------
        | Sama seperti saldo kas, tapi filter paid_at di bulan ini.
        */

        $pemasukanBulanIni = Payment::where('status', Payment::STATUS_SETTLEMENT)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->whereHas('bill', function ($query) use ($room) {
                $query->where('room_id', $room->id);
            })
            ->join('bills', 'payments.bill_id', '=', 'bills.id')
            ->sum('bills.amount');

        /*
        |--------------------------------------------------------------------------
        | PERMINTAAN PENDING
        |--------------------------------------------------------------------------
        */

        $permintaanPending = RoomMember::where('room_id', $room->id)
            ->where('status', RoomMember::STATUS_PENDING)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Data dashboard',
            'data' => [
                'total_saldo_kas' => (float) $totalSaldoKas,
                'total_warga' => $totalWarga,
                'pemasukan_bulan_ini' => (float) $pemasukanBulanIni,
                'permintaan_pending' => $permintaanPending
            ]
        ]);
    }
}
