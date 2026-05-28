<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\RoomMember;
use App\Models\User;
use App\Models\Transaction;
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
            ->first();

        if (!$bill) {

            return response()->json([
                'success' => false,
                'message' => 'Tagihan tidak ditemukan'
            ], 404);
        }

        // Tolak jika iuran sudah diselesaikan / ditutup secara manual oleh Admin
        if ($bill->is_completed || $bill->status === Bill::STATUS_CLOSED) {

            return response()->json([
                'success' => false,
                'message' => 'Iuran ini sudah diselesaikan/ditutup oleh Admin'
            ], 400);
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
                'gross_amount' => (int) $bill->required_amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => 'BILL-' . $bill->id,
                    'price' => (int) $bill->required_amount,
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

        // Log the incoming request details for diagnostic visibility
        \Illuminate\Support\Facades\Log::info('Midtrans Webhook: Request received', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $transactionStatus,
            'payment_type' => $paymentType,
            'transaction_id' => $transactionId,
            'signature_key' => $signatureKey
        ]);

        /*
        |--------------------------------------------------------------------------
        | VERIFIKASI SIGNATURE KEY
        |--------------------------------------------------------------------------
        */

        $serverKey = config('midtrans.server_key');

        // Midtrans gross_amount is formatted as decimal (e.g. "10000.00").
        // To prevent signature failures, we test against raw string, formatted decimal, and integer.
        $grossAmountFormatted = number_format((float)$grossAmount, 2, '.', '');
        
        $expectedSignatureRaw = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        $expectedSignatureDec = hash('sha512', $orderId . $statusCode . $grossAmountFormatted . $serverKey);
        $expectedSignatureInt = hash('sha512', $orderId . $statusCode . (int)$grossAmount . $serverKey);

        if ($signatureKey !== $expectedSignatureRaw && 
            $signatureKey !== $expectedSignatureDec && 
            $signatureKey !== $expectedSignatureInt) {

            \Illuminate\Support\Facades\Log::error('Midtrans Webhook: Invalid signature key', [
                'order_id' => $orderId,
                'received_signature' => $signatureKey,
                'expected_raw' => $expectedSignatureRaw,
                'expected_decimal' => $expectedSignatureDec,
                'expected_integer' => $expectedSignatureInt
            ]);

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

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS BERDASARKAN TRANSACTION STATUS (ATOMIC & IDEMPOTENT)
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

        if ($newStatus) {
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($paymentId, $newStatus, $transactionId, $paymentType, $paidAt) {
                    // Pessimistic row locking to prevent race conditions
                    $payment = Payment::where('id', $paymentId)->lockForUpdate()->first();

                    if (!$payment) {
                        throw new \Exception("Payment dengan ID {$paymentId} tidak ditemukan");
                    }

                    // Idempotency check: if already settled, skip duplicate operations
                    if ($payment->status === Payment::STATUS_SETTLEMENT) {
                        \Illuminate\Support\Facades\Log::info("Midtrans Webhook: Payment {$paymentId} is already settled (idempotent block). Bypassing.");
                        return;
                    }

                    $oldStatus = $payment->status;
                    $updateData = [
                        'status' => $newStatus,
                        'transaction_id' => $transactionId,
                        'payment_type' => $paymentType,
                    ];

                    if ($paidAt) {
                        $updateData['paid_at'] = $paidAt;
                    }

                    $payment->update($updateData);

                    // Create a local transaction record when payment transitions to settlement
                    if ($newStatus === Payment::STATUS_SETTLEMENT && $oldStatus !== Payment::STATUS_SETTLEMENT) {
                        $bill = $payment->bill;

                        // Increment collected_amount on the bill (safe float conversion)
                        $bill->increment('collected_amount', (float) $bill->required_amount);

                        // Ensure a transaction for this payment doesn't already exist to prevent duplicates
                        $exists = Transaction::where('room_id', $bill->room_id)
                            ->where('user_id', $payment->user_id)
                            ->where('category', 'Pembayaran Tagihan')
                            ->where('description', 'like', "%Pembayaran iuran: {$bill->title}. Source: bill payment, Reference ID: {$payment->id}%")
                            ->exists();

                        if (!$exists) {
                            Transaction::create([
                                'room_id' => $bill->room_id,
                                'user_id' => $payment->user_id,
                                'type' => 'in', // mutated to 'in' and serialized as 'income'
                                'category' => 'Pembayaran Tagihan',
                                'amount' => (float) $bill->required_amount,
                                'transaction_date' => $paidAt ?? now(),
                                'description' => "Pembayaran iuran: {$bill->title}. Source: bill payment, Reference ID: {$payment->id}"
                            ]);
                        }
                    }
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Midtrans Webhook: Transaction processing failed', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memproses transaksi: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook berhasil diproses'
        ]);
    }
}
