<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Perfil público: a descrição escrita pela pessoa e o uuid usado na URL. Com o id
     * na URL, dava para percorrer /perfil/1, /perfil/2... e listar todos os usuários.
     *
     * O uuid continua nullable no banco: mudar a coluna recriaria `users` no SQLite, e
     * isso apagaria em cascata as tabelas ligadas a ela. Quem garante o valor é o User.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('bio', 160)->nullable()->after('avatar_config');
        });

        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'bio']);
        });
    }
};
