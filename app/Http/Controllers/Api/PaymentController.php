<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Http\Request;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;

class PaymentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR — SETUP MIDTRANS CONFIG
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        MidtransConfig::$serverKey = config('midtrans.server_key');
        MidtransConfig::$isProduction = config('midtrans.is_production');
        MidtransConfig::$isSanitized = config('midtrans.is_sanitized');
        MidtransConfig::$is3ds = config('midtrans.is_3ds');
    }

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
    | STORE — BUAT PEMBAYARAN + SNAP TOKEN
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | VALIDASI INPUT
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'bill_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ID USER
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
        | CARI TAGIHAN DI ROOM YANG SAMA
        |--------------------------------------------------------------------------
        */

        $bill = Bill::where('id', $request->bill_id)
            ->where('room_id', $roomId)
            ->where('status', Bill::STATUS_ACTIVE)
            ->first();

        if (!$bill) {

            return response()->json([
                'success' => false,
                'message' => 'Tagihan tidak ditemukan atau sudah ditutup'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK APAKAH USER SUDAH PUNYA PEMBAYARAN PENDING/SETTLEMENT
        |--------------------------------------------------------------------------
        */

        $existingPayment = Payment::where('bill_id', $bill->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Payment::STATUS_PENDING,
                Payment::STATUS_SETTLEMENT
            ])
            ->first();

        if ($existingPayment) {

            // Jika sudah settlement, tolak
            if ($existingPayment->status === Payment::STATUS_SETTLEMENT) {

                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah membayar tagihan ini'
                ], 422);
            }

            // Jika masih pending dan snap_token masih ada, kembalikan token lama
            if ($existingPayment->snap_token) {

                return response()->json([
                    'success' => true,
                    'message' => 'Gunakan token pembayaran yang sudah ada',
                    'data' => [
                        'payment_id' => $existingPayment->id,
                        'snap_token' => $existingPayment->snap_token
                    ]
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | BUAT RECORD PAYMENT
        |--------------------------------------------------------------------------
        */

        $payment = Payment::create([
            'bill_id' => $bill->id,

            'user_id' => $user->id,

            'notes' => $request->notes,

            'status' => Payment::STATUS_PENDING
        ]);

        /*
        |--------------------------------------------------------------------------
        | REQUEST SNAP TOKEN KE MIDTRANS
        |--------------------------------------------------------------------------
        */

        $params = [
            'transaction_details' => [
                'order_id' => 'GROWSAVE-PAY-' . $payment->id,
                'gross_amount' => (int) $bill->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => 'BILL-' . $bill->id,
                    'price' => (int) $bill->amount,
                    'quantity' => 1,
                    'name' => $bill->title,
                ]
            ],
        ];

        try {

            $snapToken = Snap::getSnapToken($params);

        } catch (\Exception $e) {

            // Hapus payment jika gagal dapat snap token
            $payment->forceDelete();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat transaksi Midtrans: ' . $e->getMessage()
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | SIMPAN SNAP TOKEN
        |--------------------------------------------------------------------------
        */

        $payment->update([
            'snap_token' => $snapToken
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dibuat',
            'data' => [
                'payment_id' => $payment->id,
                'snap_token' => $snapToken
            ]
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | WEBHOOK — NOTIFICATION HANDLER MIDTRANS
    |--------------------------------------------------------------------------
    */

    public function webhook(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | AMBIL DATA DARI MIDTRANS
        |--------------------------------------------------------------------------
        */

        $orderId = $request->order_id;
        $statusCode = $request->status_code;
        $grossAmount = $request->gross_amount;
        $transactionStatus = $request->transaction_status;
        $paymentType = $request->payment_type;
        $transactionId = $request->transaction_id;
        $signatureKey = $request->signature_key;

        /*
        |--------------------------------------------------------------------------
        | VERIFIKASI SIGNATURE KEY
        |--------------------------------------------------------------------------
        */

        $serverKey = config('midtrans.server_key');

        $expectedSignature = hash('sha512',
            $orderId . $statusCode . $grossAmount . $serverKey
        );

        if ($signatureKey !== $expectedSignature) {

            return response()->json([
                'success' => false,
                'message' => 'Signature key tidak valid'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI PAYMENT BERDASARKAN ORDER ID
        |--------------------------------------------------------------------------
        */

        // Format order_id: GROWSAVE-PAY-{payment_id}
        $paymentId = str_replace('GROWSAVE-PAY-', '', $orderId);

        $payment = Payment::find($paymentId);

        if (!$payment) {

            return response()->json([
                'success' => false,
                'message' => 'Payment tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS BERDASARKAN TRANSACTION STATUS
        |--------------------------------------------------------------------------
        */

        $newStatus = null;
        $paidAt = null;

        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {

            $newStatus = Payment::STATUS_SETTLEMENT;
            $paidAt = now();

        } elseif ($transactionStatus === 'pending') {

            $newStatus = Payment::STATUS_PENDING;

        } elseif ($transactionStatus === 'deny' || $transactionStatus === 'cancel') {

            $newStatus = Payment::STATUS_CANCEL;

        } elseif ($transactionStatus === 'expire') {

            $newStatus = Payment::STATUS_EXPIRE;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE PAYMENT
        |--------------------------------------------------------------------------
        */

        if ($newStatus) {

            $updateData = [
                'status' => $newStatus,
                'transaction_id' => $transactionId,
                'payment_type' => $paymentType,
            ];

            if ($paidAt) {
                $updateData['paid_at'] = $paidAt;
            }

            $payment->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook berhasil diproses'
        ]);
    }
}
