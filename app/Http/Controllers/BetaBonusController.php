<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterial;
use App\Models\StudyQuestion;
use App\Models\User;
use App\Models\UserStudyMaterial;
use App\Services\StudyMaterialService;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Desafio bônus do mapa beta: um material de estudo (resumo + questões) escrito por um
 * agente de IA e publicado pelo admin. Só para assinantes, não consome vida e fica fora do
 * avanço das fases. O gabarito nunca vai para o navegador antes da resposta: a correção é
 * feita aqui.
 */
class BetaBonusController extends Controller
{
    /** XP da primeira conclusão de um material: base + um tanto por acerto. Refazer não dá XP. */
    const BONUS_XP_BASE = 10;

    const BONUS_XP_PER_CORRECT = 5;

    public function __construct(private StudyMaterialService $service) {}

    public function show(StudyMaterial $studyMaterial): Response|RedirectResponse
    {
        $user = Auth::user();

        abort_if(! $this->service->isPlayable($studyMaterial), 404);

        if (! $user->hasInfiniteLives()) {
            return redirect()->route('subscription.index');
        }

        if (! $this->service->isUnlockedFor($user, $studyMaterial)) {
            return redirect()->route('beta.play.map');
        }

        return Inertia::render('BetaPlay/Bonus', [
            'material' => ['uuid' => $studyMaterial->uuid, 'title' => $studyMaterial->title],
            'legislationTitle' => $studyMaterial->legislation->title,
            'summaryHtml' => $this->service->renderSummary($studyMaterial->summary),
            // Sem gabarito, comentário nem fundamento: chegam depois de cada resposta
            'questions' => $studyMaterial->questions->map(fn (StudyQuestion $question) => [
                'position' => $question->position,
                'board' => $question->board,
                'type' => $question->type,
                'statement' => $question->statement,
                'options' => $question->options,
            ])->values(),
            'alreadyCompleted' => UserStudyMaterial::where('user_id', $user->id)
                ->where('study_material_id', $studyMaterial->id)
                ->whereNotNull('completed_at')
                ->exists(),
            'xp' => ['base' => self::BONUS_XP_BASE, 'per_correct' => self::BONUS_XP_PER_CORRECT],
        ]);
    }

    /** Corrige uma questão e devolve o gabarito comentado. Não grava nada. */
    public function answer(Request $request, StudyMaterial $studyMaterial): JsonResponse
    {
        $this->authorizePlay(Auth::user(), $studyMaterial);

        $validated = $request->validate([
            'position' => ['required', 'integer'],
            'answer' => ['required', 'string', 'max:10'],
        ]);

        $question = $studyMaterial->questions->firstWhere('position', (int) $validated['position']);
        abort_if(! $question, 422, 'Questão inexistente.');

        return response()->json([
            'is_correct' => $this->isCorrect($question, $validated['answer']),
            'correct_answer' => $question->correct_answer,
            'explanation' => $question->explanation,
            'legal_basis' => $question->legal_basis,
        ]);
    }

    /** Conclusão: recalcula os acertos no servidor e dá o XP só na primeira vez. */
    public function finish(Request $request, StudyMaterial $studyMaterial): JsonResponse
    {
        $user = Auth::user();
        $this->authorizePlay($user, $studyMaterial);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'string', 'max:10'],
        ]);

        $questions = $studyMaterial->questions;
        $answers = [];
        $correct = 0;

        foreach ($questions as $question) {
            $answer = $validated['answers'][$question->position] ?? null;
            abort_if($answer === null, 422, 'Responda todas as questões.');

            $answers[$question->position] = $answer;
            $correct += $this->isCorrect($question, $answer) ? 1 : 0;
        }

        $xpGained = DB::transaction(function () use ($user, $studyMaterial, $answers, $correct) {
            $record = UserStudyMaterial::where('user_id', $user->id)
                ->where('study_material_id', $studyMaterial->id)
                ->lockForUpdate()
                ->first() ?? new UserStudyMaterial(['user_id' => $user->id, 'study_material_id' => $studyMaterial->id]);

            $first = $record->completed_at === null;

            $record->fill([
                'answers' => $answers,
                'correct_count' => $correct,
                'completed_at' => $record->completed_at ?? now(),
            ])->save();

            if (! $first) {
                return 0;
            }

            $xp = $user->boostedXp(self::BONUS_XP_BASE + self::BONUS_XP_PER_CORRECT * $correct);
            $user->addXp($xp, 'study_material', $studyMaterial->id, $studyMaterial->legislation_id);

            return $xp;
        });

        return response()->json([
            'correct_count' => $correct,
            'total' => $questions->count(),
            'xp_gained' => $xpGained,
            'first_completion' => $xpGained > 0,
            'discipline_level_up' => $xpGained > 0
                ? app(XpService::class)->detectDisciplineLevelUp($user, $studyMaterial->legislation_id, $xpGained)
                : null,
            'user' => ['xp' => $user->xp],
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    /** As mesmas checagens de show(), para as rotas que respondem em JSON. */
    private function authorizePlay(User $user, StudyMaterial $material): void
    {
        abort_if(! $this->service->isPlayable($material), 404);
        abort_if(! $user->hasInfiniteLives(), 403, 'O desafio bônus é exclusivo para assinantes.');
        abort_if(! $this->service->isUnlockedFor($user, $material), 403, 'Conclua os dispositivos deste material para liberar o desafio.');
    }

    private function isCorrect(StudyQuestion $question, string $answer): bool
    {
        return Str::lower(trim($answer)) === Str::lower($question->correct_answer);
    }
}
