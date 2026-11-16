<?php

use App\Http\Controllers\BetaPlayController;
use App\Models\StudyMaterial;
use App\Models\StudyQuestion;
use App\Models\User;
use App\Models\UserBetaCheckpoint;
use App\Models\UserBetaExam;
use App\Models\XpTransaction;
use Inertia\Testing\AssertableInertia;

// Mesma lei dos testes do desafio: 170 blocos, a primeira etapa com os quadrados 0-39, 40-79,
// 80-119 e 120-159. Cada material tem uma questão de certo/errado (gabarito "certo") e uma de
// múltipla escolha (gabarito "C").

/** Marca o desafio do quadrado que termina em $lastBlock como feito. */
function finishChallenge(User $user, $legislation, int $lastBlock, StudyMaterial ...$materials): void
{
    UserBetaCheckpoint::create([
        'user_id' => $user->id,
        'legislation_segment_id' => studyBlockIds($legislation)[$lastBlock],
        'done_material_ids' => array_map(fn (StudyMaterial $material) => $material->id, $materials),
    ]);
}

/**
 * Etapa inteira concluída, com dois quadrados com material (4 questões no total).
 *
 * @return array{0: User, 1: string, 2: \App\Models\Legislation}
 */
function examReady(): array
{
    $legislation = studyLegislation(170);
    $first = studyMaterial($legislation, 0, 39);
    $second = studyMaterial($legislation, 40, 79);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 159);
    finishChallenge($user, $legislation, 39, $first);
    finishChallenge($user, $legislation, 79, $second);

    return [$user, checkpointKey($legislation, 159), $legislation];
}

/** Responde uma questão da prova com o gabarito (ou errado de propósito). */
function answerExam($test, User $user, string $key, array $question, bool $right = true)
{
    $correct = $question['type'] === 'true_false' ? 'certo' : 'C';
    $wrong = $question['type'] === 'true_false' ? 'errado' : 'A';

    return $test->actingAs($user)->postJson("/beta/play/prova/{$key}/answer", [
        'question' => $question['uuid'],
        'answer' => $right ? $correct : $wrong,
    ]);
}

test('o Dia da prova libera com a etapa e os desafios dela concluídos', function () {
    $legislation = studyLegislation(170);
    $first = studyMaterial($legislation, 0, 39);
    $second = studyMaterial($legislation, 40, 79);
    $user = betaPlayer();
    $key = checkpointKey($legislation, 159);

    completeBlocks($user, $legislation, 0, 159);
    finishChallenge($user, $legislation, 39, $first);

    // Falta o desafio do segundo quadrado
    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.exam.state', 'locked')
            ->where('sections.0.exam.key', $key)
            ->where('sections.0.exam.questions', 4)
            ->missing('sections.0.exam.material_ids')
            ->missing('sections.0.exam.anchor_block_id'));
    $this->actingAs($user)->get("/beta/play/prova/{$key}")->assertRedirect(route('beta.play.map'));
    $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")->assertForbidden();

    finishChallenge($user, $legislation, 79, $second);

    $seconds = 2 * BetaPlayController::EXAM_SECONDS_TRUE_FALSE + 2 * BetaPlayController::EXAM_SECONDS_MULTIPLE_CHOICE;

    // Sem assinatura: a prova é de todo mundo
    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.exam.state', 'available')
            ->where('sections.0.exam.seconds', $seconds)
            ->where('sections.0.exam.xp_per_correct', BetaPlayController::EXAM_XP_PER_CORRECT)
            ->where('sections.0.exam.in_progress', false)
            // A segunda etapa não tem material: a prova dela fica sem questões
            ->where('sections.1.exam.state', 'locked'));

    $this->actingAs($user)->get("/beta/play/prova/{$key}")
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('BetaPlay/Exam')
            ->where('exam.section_number', 1)
            ->where('exam.questions', 4)
            ->where('exam.seconds', $seconds)
            ->where('exam.attempts', 0)
            ->where('attempt', null));
});

test('etapa concluída sem material fica com a prova sem questões', function () {
    $legislation = studyLegislation(170);
    $user = betaPlayer();
    completeBlocks($user, $legislation, 0, 159);
    $key = checkpointKey($legislation, 159);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.exam.state', 'empty'));
    $this->actingAs($user)->get("/beta/play/prova/{$key}")->assertRedirect(route('beta.play.map'));
});

test('começar sorteia as questões sem gabarito e marca o prazo', function () {
    [$user, $key] = examReady();

    $attempt = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")
        ->assertSuccessful()
        ->assertJsonCount(4, 'questions')
        ->assertJsonPath('seconds_total', 100)
        ->assertJsonPath('xp', 0)
        ->assertJsonPath('answers', [])
        ->assertJsonMissingPath('questions.0.correct_answer')
        ->assertJsonMissingPath('questions.0.explanation')
        ->json();

    expect($attempt['seconds_left'])->toBeGreaterThan(95)
        ->and(collect($attempt['questions'])->pluck('uuid')->sort()->values()->all())
        ->toBe(StudyQuestion::pluck('uuid')->sort()->values()->all());

    // Começar de novo com a prova correndo devolve a mesma tentativa, na mesma ordem
    $this->travel(10)->seconds();
    $again = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")->json();
    expect(array_column($again['questions'], 'uuid'))->toBe(array_column($attempt['questions'], 'uuid'))
        ->and($again['seconds_left'])->toBeLessThanOrEqual(90);

    // Quem sai e volta cai direto na prova
    $this->actingAs($user)->get("/beta/play/prova/{$key}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('attempt.questions', 4));
    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.exam.in_progress', true));
});

test('o XP é a soma dos acertos e só é creditado na entrega', function () {
    [$user, $key, $legislation] = examReady();
    $xp = BetaPlayController::EXAM_XP_PER_CORRECT;

    $questions = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")->json('questions');

    answerExam($this, $user, $key, $questions[0])
        ->assertSuccessful()
        ->assertJsonPath('is_correct', true)
        ->assertJsonPath('xp', $xp)
        ->assertJsonPath('finished', false)
        ->assertJsonPath('result', null)
        ->assertJsonMissingPath('explanation');

    // Errar não tira nada, e vale a primeira resposta
    answerExam($this, $user, $key, $questions[1], right: false)
        ->assertJsonPath('is_correct', false)
        ->assertJsonPath('xp', $xp);
    answerExam($this, $user, $key, $questions[1])->assertJsonPath('is_correct', false);

    expect($user->fresh()->xp)->toBe(0)->and($user->fresh()->lives)->toBe(5);

    // Questão que não é da prova
    $this->actingAs($user)->postJson("/beta/play/prova/{$key}/answer", ['question' => (string) Str::uuid(), 'answer' => 'certo'])->assertNotFound();

    $this->actingAs($user)->postJson("/beta/play/prova/{$key}/finish")
        ->assertSuccessful()
        ->assertJsonPath('correct', 1)
        ->assertJsonPath('answered', 2)
        ->assertJsonPath('total', 4)
        ->assertJsonPath('xp_gained', $xp)
        ->assertJsonPath('best_correct', 1)
        ->assertJsonPath('user.xp', $xp)
        ->assertJsonCount(2, 'review')
        ->assertJsonPath('review.0.uuid', $questions[0]['uuid'])
        ->assertJsonPath('review.0.is_correct', true)
        ->assertJsonStructure(['review' => [['explanation', 'legal_basis', 'correct_answer', 'answer']]]);

    $transaction = XpTransaction::where('user_id', $user->id)->where('source_type', 'beta_exam')->sole();
    expect($transaction->amount)->toBe($xp)->and($transaction->legislation_id)->toBe($legislation->id);

    // Entregar de novo não credita outra vez
    $this->actingAs($user)->postJson("/beta/play/prova/{$key}/finish")->assertSuccessful()->assertJsonPath('xp_gained', $xp);
    expect(XpTransaction::where('source_type', 'beta_exam')->count())->toBe(1);

    // Depois de entregue não aceita mais resposta
    answerExam($this, $user, $key, $questions[2])->assertStatus(409);

    $this->actingAs($user)->get('/beta/play')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.exam.state', 'complete')
            ->where('sections.0.exam.best_correct', 1)
            ->where('sections.0.exam.in_progress', false));
});

test('refazer a prova só dá XP pelas questões ainda não acertadas', function () {
    [$user, $key] = examReady();
    $xp = BetaPlayController::EXAM_XP_PER_CORRECT;

    $questions = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")->json('questions');
    answerExam($this, $user, $key, $questions[0]);
    $this->actingAs($user)->postJson("/beta/play/prova/{$key}/finish")->assertJsonPath('xp_gained', $xp);

    // Segunda tentativa: acerta as quatro; a última resposta já entrega a prova
    $questions = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")
        ->assertJsonPath('answers', [])
        ->assertJsonPath('xp', 0)
        ->json('questions');

    foreach (array_slice($questions, 0, 3) as $question) {
        answerExam($this, $user, $key, $question)->assertJsonPath('finished', false);
    }

    answerExam($this, $user, $key, $questions[3])
        ->assertSuccessful()
        ->assertJsonPath('finished', true)
        ->assertJsonPath('xp', 3 * $xp)
        ->assertJsonPath('result.correct', 4)
        ->assertJsonPath('result.xp_gained', 3 * $xp)
        ->assertJsonPath('result.best_correct', 4)
        ->assertJsonPath('result.user.xp', 4 * $xp);

    $exam = UserBetaExam::where('user_id', $user->id)->sole();
    expect($exam->attempts)->toBe(2)->and($exam->rewardedIds())->toHaveCount(4);

    // Terceira: tudo certo de novo, nada de XP
    $questions = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")->json('questions');
    foreach ($questions as $question) {
        $last = answerExam($this, $user, $key, $question);
    }
    $last->assertJsonPath('result.xp_gained', 0);
    expect($user->fresh()->xp)->toBe(4 * $xp);
});

test('resposta depois do prazo não entra e a prova é entregue', function () {
    [$user, $key] = examReady();
    $xp = BetaPlayController::EXAM_XP_PER_CORRECT;

    $questions = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")->json('questions');
    answerExam($this, $user, $key, $questions[0]);

    // 100 segundos de prova, mais a folga de rede
    $this->travel(110)->seconds();

    answerExam($this, $user, $key, $questions[1])
        ->assertStatus(409)
        ->assertJsonPath('expired', true)
        ->assertJsonPath('result.correct', 1)
        ->assertJsonPath('result.answered', 1)
        ->assertJsonPath('result.xp_gained', $xp);

    expect($user->fresh()->xp)->toBe($xp)
        ->and(UserBetaExam::where('user_id', $user->id)->sole()->attempts)->toBe(1);
});

test('prova esquecida aberta é entregue quando a pessoa volta', function () {
    [$user, $key] = examReady();
    $xp = BetaPlayController::EXAM_XP_PER_CORRECT;

    $questions = $this->actingAs($user)->postJson("/beta/play/prova/{$key}/start")->json('questions');
    answerExam($this, $user, $key, $questions[0]);
    answerExam($this, $user, $key, $questions[1]);

    $this->travel(1)->hours();

    $this->actingAs($user)->get("/beta/play/prova/{$key}")
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attempt', null)
            ->where('exam.attempts', 1)
            ->where('exam.best_correct', 2));

    expect($user->fresh()->xp)->toBe(2 * $xp);
});
