<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();
            // Seul le hash SHA-256 de la clé est stocké (§7.3.2) : si la base
            // fuit, les clés ne sont pas directement exploitables. key_last4
            // permet d'identifier une clé au support sans exposer sa valeur.
            $table->string('key_hash', 64)->unique();
            $table->string('key_last4', 4);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->enum('license_type', ['perpetual', 'subscription']);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_activations')->default(1);
            $table->boolean('revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_keys');
    }
};
