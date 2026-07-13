<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_recovery_codes', function (Blueprint $table) {
            $table->id();
            // Déblocage de compte LOCAL du logiciel client (mot de passe oublié),
            // sans rapport avec les clés de licence. Lié à une activation précise
            // (donc à une empreinte machine) : un code intercepté ne peut pas être
            // utilisé sur une autre machine. Seul le hash du code est stocké,
            // même logique que les clés de licence (§7.3.2).
            $table->foreignId('activation_id')->constrained('activations')->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_from_ip')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_recovery_codes');
    }
};
