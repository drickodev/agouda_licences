<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_key_id')->nullable()->constrained('license_keys')->nullOnDelete();
            $table->string('event');
            $table->string('machine_fingerprint')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->boolean('success')->default(true);
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['license_key_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_logs');
    }
};
