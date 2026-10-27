<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Perluas enum order_type di transactions menambah nilai gofood, shopeefood, grabfood
        // Kolom sudah ada dengan enum('in_store', 'takeaway', 'delivery), jadi gunakan ALTER TABLE
        Schema::table('transactions', function (Blueprint $table) {
            // MySQL: Mengubah enum column untuk menambah nilai baru
            // Kolom tetap nullable seperti semula
            $table->enum('order_type', [
                'in_store', 'takeaway', 'delivery',
                'gofood', 'shopeefood', 'grabfood'
            ])->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Kembali ke enum original (hanya in_store, takeaway, delivery)
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('order_type', ['in_store', 'takeaway', 'delivery'])->nullable()->default(null)->change();
        });
    }
};