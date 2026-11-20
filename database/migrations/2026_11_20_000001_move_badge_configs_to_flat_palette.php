<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Como as artes de public/img/badges foram redesenhadas no estilo chapado. */
    private const ORIGINAL_CONFIGS = [
        'criminal' => ['icon' => 'penal', 'text' => 'Criminalista', 'shield' => '#4C7BE8', 'ribbon' => '#E8505B'],
        'civil' => ['icon' => 'civil', 'text' => 'Civilista', 'shield' => '#2E9D94', 'ribbon' => '#F0DFA0'],
        'tributario' => ['icon' => 'tributario', 'text' => 'Tributarista', 'shield' => '#E0805F', 'ribbon' => '#2E9D94'],
        'constitucional' => ['icon' => 'const', 'text' => 'Constitucionalista', 'shield' => '#3FB06A', 'ribbon' => '#F0DFA0'],
        'trabalhista' => ['icon' => 'trabalho', 'text' => 'Trabalhista', 'shield' => '#E8505B', 'ribbon' => '#4C7BE8'],
        'processo' => ['icon' => 'processo', 'text' => 'Processualista', 'shield' => '#9B5CF6', 'ribbon' => '#F0DFA0'],
        'administrativo' => ['icon' => 'administrativo', 'text' => 'Administrativista', 'shield' => '#F0DFA0', 'ribbon' => '#9B5CF6'],
    ];

    /** Cores da paleta antiga do editor => a mais próxima da paleta chapada. */
    private const PALETTE = [
        // brasão
        '#7d6cf2' => '#9B5CF6', '#2fc4b6' => '#2E9D94', '#f2583e' => '#E8505B', '#3cc45a' => '#3FB06A',
        '#ffa42b' => '#E0805F', '#3d8bf2' => '#4C7BE8', '#b8325a' => '#E8505B', '#b4bccf' => '#F0DFA0',
        // faixa
        '#f23d9c' => '#E8505B', '#ff7a1f' => '#E0805F', '#ffc21a' => '#F0DFA0', '#f0364a' => '#E8505B',
        '#22cfc0' => '#2E9D94', '#3b82f6' => '#4C7BE8', '#8b5cf6' => '#9B5CF6', '#2fbf6b' => '#3FB06A',
    ];

    public function up(): void
    {
        $disciplines = DB::table('disciplines')->whereNotNull('badge_config')->get(['id', 'badge_key', 'badge_version', 'badge_config']);

        foreach ($disciplines as $discipline) {
            $config = json_decode($discipline->badge_config, true);
            if (! is_array($config)) {
                continue;
            }

            // Sem imagem gerada no admin, a tela mostra a arte original: o config passa a descrevê-la.
            if ($discipline->badge_version === null && isset(self::ORIGINAL_CONFIGS[$discipline->badge_key])) {
                $config = self::ORIGINAL_CONFIGS[$discipline->badge_key];
            } else {
                foreach (['shield', 'ribbon'] as $field) {
                    $color = strtolower($config[$field] ?? '');
                    $config[$field] = self::PALETTE[$color] ?? $config[$field] ?? null;
                }
            }

            DB::table('disciplines')->where('id', $discipline->id)->update(['badge_config' => json_encode($config)]);
        }
    }

    public function down(): void
    {
        // As cores antigas não são restauradas: a arte antiga não existe mais em art.ts.
    }
};
