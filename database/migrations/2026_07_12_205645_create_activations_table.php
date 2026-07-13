<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_key_id')->constrained('license_keys')->cascadeOnDelete();
            $table->string('machine_fingerprint');
            $table->string('machine_name')->nullable();
            $table->enum('status', ['active', 'released'])->default('active');
            $table->ipAddress('last_ip')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['license_key_id', 'machine_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activations');
    }
};
