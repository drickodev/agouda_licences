<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stocke la clé en clair chiffrée (APP_KEY, cast Eloquent `encrypted`),
     * en plus du hash déjà présent, pour permettre à l'administrateur de la
     * revoir depuis le panel en cas de perte. Si la base fuit seule (sans
     * APP_KEY), les clés restent inutilisables ; le hash reste la source de
     * vérité pour le lookup (§7.3.2).
     */
    public function up(): void
    {
        Schema::table('license_keys', function (Blueprint $table) {
            $table->text('key_encrypted')->nullable()->after('key_last4');
        });
    }

    public function down(): void
    {
        Schema::table('license_keys', function (Blueprint $table) {
            $table->dropColumn('key_encrypted');
        });
    }
};
