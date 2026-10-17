<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les numéros déjà saisis perdent leurs espaces et tirets : la recherche
     * d'un membre par son numéro se fait sur leur fin.
     */
    public function up(): void
    {
        foreach (['users', 'tontine_members', 'group_members'] as $table) {
            DB::table($table)->whereNotNull('phone')->orderBy('id')->each(function ($row) use ($table) {
                $clean = Phone::clean($row->phone);
                if ($clean !== $row->phone) {
                    DB::table($table)->where('id', $row->id)->update(['phone' => $clean]);
                }
            });
        }
    }

    public function down(): void
    {
        // Les espaces d'origine ne se retrouvent pas, et n'apportaient rien.
    }
};
