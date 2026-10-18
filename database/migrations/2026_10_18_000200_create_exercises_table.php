<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            // Kunci dari App\Support\ExerciseCatalog (divalidasi di aplikasi, bukan enum DB, agar mudah ditambah).
            $table->string('muscle_group', 20);
            $table->string('equipment', 20);
            $table->string('kind', 20);
            $table->text('notes')->nullable();
            // Diarsipkan, bukan dihapus: riwayat set tetap merujuk gerakan ini.
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'muscle_group']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "exercises" ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
