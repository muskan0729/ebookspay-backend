<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cart_id')
                ->constrained()
                ->cascadeOnDelete();

            // ebook reference
            $table->unsignedBigInteger('ebook_id');

            $table->foreign('ebook_id')
                ->references('id')
                ->on('ebooks')
                ->cascadeOnDelete();

            // snapshot data
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->default(0.00);

            $table->timestamps();

            // ✅ PREVENT SAME EBOOK DUPLICATE IN SAME CART
            $table->unique(['cart_id', 'ebook_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};