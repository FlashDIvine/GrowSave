<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | UPDATE TABEL USERS
        |--------------------------------------------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('email');
            $table->string('house_block')->nullable()->after('whatsapp_number');
            $table->string('house_number')->nullable()->after('house_block');
        });

        /*
        |--------------------------------------------------------------------------
        | UPDATE TABEL ANNOUNCEMENTS
        |--------------------------------------------------------------------------
        */
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'house_block', 'house_number']);
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
