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
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nama');
            $table->enum('jenis', [
                'Frozen',
                'Buah',
                'Sayur',
                'Masakan jadi',
                'Minuman Kaleng',
                'Susu',
                'Jus',
                'Snack'
            ]);
            $table->date('tanggal_beli');
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->integer('jumlah');
            $table->enum('status_penggunaan', [
                'tersedia',
                'habis',
                'dibuang'
            ])->default('tersedia');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
