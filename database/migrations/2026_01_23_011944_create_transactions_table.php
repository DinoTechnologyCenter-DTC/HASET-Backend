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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable(); // Can be firebase UID
            $table->string('doctor_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('TZS');
            $table->string('provider')->nullable(); // Mpesa, Airtel, etc
            $table->string('payment_account')->nullable(); // Phone number or card
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('external_reference')->nullable(); // Gateway Transaction ID
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
