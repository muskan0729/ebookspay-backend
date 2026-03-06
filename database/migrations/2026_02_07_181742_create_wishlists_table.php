<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('wishlists', function (Blueprint $table) {
      $table->id();

      $table->foreignId('user_id')->constrained()->cascadeOnDelete();
      $table->foreignId('ebook_id')->constrained('ebooks')->cascadeOnDelete();

      $table->timestamps();

      // one ebook only once per user
      $table->unique(['user_id', 'ebook_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wishlists');
  }
};