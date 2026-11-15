<?php

use App\Models\User;
use Laravel\Cashier\Subscription;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// As fantasias exclusivas de assinante, lidas direto de config/avatar.php: o
// dataset é montado antes de o Laravel subir, quando config() ainda não existe.
dataset('fantasias premium', fn () => (require __DIR__.'/../config/avatar.php')['premium']['outfit']);

/**
 * Assinatura ativa no plano `default`, como o Cashier grava depois do Stripe.
 */
function giveActiveSubscription(User $user): void
{
    Subscription::query()->create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
}

/**
 * Avatar válido, montado a partir do primeiro id de cada grupo. Usado pelos
 * testes do editor da web e da API do app.
 *
 * @return array<string, string>
 */
function validAvatar(array $overrides = []): array
{
    $avatar = [];

    foreach (config('avatar.shapes') as $key => $allowed) {
        $avatar[$key] = $allowed[0];
    }

    foreach (config('avatar.colors') as $key) {
        $avatar[$key] = '#ABCDEF';
    }

    return array_merge($avatar, $overrides);
}

// ==================== Material de estudo ====================

/** Lei publicada com $blocks blocos, um artigo por bloco. */
function studyLegislation(int $blocks, string $title = 'Lei de Estudo'): App\Models\Legislation
{
    $legislation = App\Models\Legislation::create(['title' => $title, 'raw_text' => 'Art.', 'status' => 'published']);

    for ($i = 0; $i < $blocks; $i++) {
        App\Models\LegislationSegment::create([
            'legislation_id' => $legislation->id,
            'original_text' => "Art. {$i} A União zelará pelo dispositivo {$i}.",
            'char_start' => $i * 100,
            'char_end' => $i * 100 + 40,
            'segment_type' => 'caput',
            'article_reference' => (string) $i,
            'structural_marker' => "Art. {$i}",
            'position' => $i + 1,
            'is_block' => true,
        ]);
    }

    return $legislation;
}

function studyAdmin(array $attributes = []): User
{
    $user = User::factory()->createOne($attributes);
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

/** Ids dos blocos da lei, na ordem. */
function studyBlockIds(App\Models\Legislation $legislation): array
{
    return $legislation->segments()->where('is_block', true)->orderBy('char_start')->pluck('id')->all();
}

/** Uma questão Cebraspe e uma FGV, válidas. */
function studyQuestions(): array
{
    return [
        [
            'board' => 'cebraspe',
            'type' => 'true_false',
            'statement' => 'A União zelará pelo dispositivo.',
            'options' => null,
            'correct_answer' => 'certo',
            'explanation' => 'É o que diz o caput.',
            'legal_basis' => 'Art. 0',
        ],
        [
            'board' => 'fgv',
            'type' => 'multiple_choice',
            'statement' => 'João quer saber quem zelará pelo dispositivo. Assinale a correta.',
            'options' => ['A' => 'O Município', 'B' => 'O Estado', 'C' => 'A União', 'D' => 'O Distrito Federal', 'E' => 'Ninguém'],
            'correct_answer' => 'C',
            'explanation' => 'C está certa pelo caput; as demais trocam o ente.',
            'legal_basis' => 'Art. 1',
        ],
    ];
}

/**
 * Grava direto no banco um material cobrindo os blocos de $from a $to (posições na lei).
 */
function studyMaterial(App\Models\Legislation $legislation, int $from, int $to, string $status = 'published', ?array $covered = null): App\Models\StudyMaterial
{
    $ids = studyBlockIds($legislation);
    $range = array_slice($ids, $from, $to - $from + 1);
    $covered ??= $range;
    $service = app(App\Services\StudyMaterialService::class);
    $skipped = $status === 'skipped';

    $material = App\Models\StudyMaterial::create([
        'legislation_id' => $legislation->id,
        'first_block_id' => $range[0],
        'last_block_id' => end($range),
        'block_ids' => $skipped ? [] : $covered,
        'content_hash' => $skipped ? null : $service->contentHash($covered),
        'title' => $skipped ? null : "Material {$from}-{$to}",
        'summary' => $skipped ? null : "## Resumo\n\nTexto **importante**.",
        'status' => $status,
        'skip_reason' => $skipped ? 'Disposições transitórias esgotadas' : null,
        'published_at' => $status === 'published' ? now() : null,
    ]);

    if (! $skipped) {
        $service->replaceQuestions($material, $service->validateContent([
            'title' => 't', 'summary' => 's', 'questions' => studyQuestions(),
        ])['questions']);
    }

    return $material;
}
