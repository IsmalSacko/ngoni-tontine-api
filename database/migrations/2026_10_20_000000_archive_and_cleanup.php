<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une caisse ou un groupe fini s'archive : il sort des listes, son
     * historique reste. Et un compte supprimé ne bloque plus sur les paiements
     * qu'il a validés dans les caisses des autres : ils restent, sans validateur.
     */
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('group_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('currency');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('validated_by')->nullable()->change();
            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tontines', fn (Blueprint $table) => $table->dropColumn('archived_at'));
        Schema::table('groups', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};
