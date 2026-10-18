<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jeda sesi: waktu jeda tidak dihitung ke durasi latihan. Aditif, aman dijalankan sebelum deploy. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('ended_at'); // terisi = sedang dijeda
            $table->unsignedInteger('paused_seconds')->default(0)->after('paused_at'); // total jeda yang sudah lewat
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'paused_seconds']);
        });
    }
};
