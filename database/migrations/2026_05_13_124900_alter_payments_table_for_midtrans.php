<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | HAPUS KOLOM LAMA
            |--------------------------------------------------------------------------
            */

            // Hapus foreign key approved_by terlebih dahulu
            $table->dropForeign(['approved_by']);
            $table->dropColumn('approved_by');

            $table->dropColumn('payment_proof');

            /*
            |--------------------------------------------------------------------------
            | TAMBAH KOLOM MIDTRANS
            |--------------------------------------------------------------------------
            */

            $table->string('snap_token')->nullable()->after('notes');

            $table->string('transaction_id')->nullable()->after('snap_token');

            $table->string('payment_type')->nullable()->after('transaction_id');
        });

        /*
        |--------------------------------------------------------------------------
        | UBAH ENUM STATUS
        |--------------------------------------------------------------------------
        */

        // Ubah enum status dari (pending, approved, rejected)
        // menjadi (pending, settlement, expire, cancel)
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'settlement', 'expire', 'cancel') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan enum status ke semula
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");

        Schema::table('payments', function (Blueprint $table) {

            // Hapus kolom Midtrans
            $table->dropColumn(['snap_token', 'transaction_id', 'payment_type']);

            // Kembalikan kolom lama
            $table->string('payment_proof')->after('notes');

            $table->foreignId('approved_by')
                ->nullable()
                ->after('paid_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
