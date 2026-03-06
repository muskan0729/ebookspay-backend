<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('addresses', function (Blueprint $table) {
      $table->id();

      $table->foreignId('user_id')->constrained()->cascadeOnDelete();

      $table->string('label')->nullable(); // Home / Office / Other
      $table->string('full_name');
      $table->string('phone', 20);

      $table->string('address_line1');
      $table->string('address_line2')->nullable();
      $table->string('landmark')->nullable();

      $table->string('city');
      $table->string('state');
      $table->string('country')->default('India');
      $table->string('pincode', 12);

      $table->enum('address_type', ['home', 'office', 'other'])->default('home');

      $table->boolean('is_default')->default(false);

      $table->timestamps();

      $table->index(['user_id', 'is_default']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('addresses');
  }
};