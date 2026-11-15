<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\StudyMaterialAgent;
use App\Models\Legislation;
use App\Models\LegislationSegment;
use App\Models\StudyMaterial;
use App\Services\StudyMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Revisão, edição e publicação do material de estudo gravado pelos agentes de IA, e a
 * página que ensina a instalar a skill.
 */
class StudyMaterialController extends Controller
{
    /** Pasta da skill no repositório; o .zip da página de instalação sai daqui. */
    public const SKILL_NAME = 'gerar-material-estudo';

    public function __construct(private StudyMaterialService $service) {}

    public function index(Request $request): Response
    {
        $legislations = Legislation::orderBy('title')->get(['id', 'uuid', 'title', 'status']);

        $counts = StudyMaterial::selectRaw('legislation_id, status, COUNT(*) as total')
            ->groupBy('legislation_id', 'status')
            ->get()
            ->groupBy('legislation_id');

        $selected = $legislations->firstWhere('uuid', $request->query('legislation'))
            ?? $legislations->first(fn ($legislation) => $counts->has($legislation->id))
            ?? $legislations->firstWhere('status', 'published')
            ?? $legislations->first();

        $materials = [];
        $progress = null;

        if ($selected) {
            $layout = $this->service->layout($selected);
            $rows = StudyMaterial::where('legislation_id', $selected->id)->withCount('questions')->get();

            $stale = array_flip($this->service->staleIds($rows));
            $squares = $this->service->squareNumbers($layout, $rows);
            $position = array_flip(array_column($layout, 'id'));

            $markers = LegislationSegment::whereIn('id', $rows->pluck('first_block_id')->merge($rows->pluck('last_block_id'))->filter())
                ->get(['id', 'structural_marker', 'article_reference'])
                ->keyBy('id');
            $label = function (?int $blockId) use ($markers) {
                $block = $blockId ? $markers->get($blockId) : null;

                return $block ? ($block->structural_marker ?: ($block->article_reference ? "Art. {$block->article_reference}" : "Bloco {$block->id}")) : null;
            };

            $materials = $rows
                ->sortBy(fn (StudyMaterial $material) => $position[$material->first_block_id] ?? PHP_INT_MAX)
                ->map(function (StudyMaterial $material) use ($stale, $squares, $position, $label) {
                    $first = $position[$material->first_block_id] ?? null;
                    $last = $position[$material->last_block_id] ?? null;

                    return [
                        'uuid' => $material->uuid,
                        'title' => $material->title,
                        'status' => $material->status,
                        'is_stale' => isset($stale[$material->id]),
                        'skip_reason' => $material->skip_reason,
                        'first_label' => $label($material->first_block_id),
                        'last_label' => $label($material->last_block_id),
                        'range_blocks' => $first !== null && $last !== null ? $last - $first + 1 : null,
                        'covered_blocks' => count($material->coveredBlockIds()),
                        'questions_count' => $material->questions_count,
                        'square' => $squares[$material->id] ?? null,
                        'updated_at' => $material->updated_at?->toIso8601String(),
                    ];
                })
                ->values();

            $progress = [
                'total_blocks' => count($layout),
                'pending_blocks' => $this->service->pendingCount($layout),
            ];
        }

        return Inertia::render('admin/StudyMaterials/Index', [
            'legislations' => $legislations->map(function ($legislation) use ($counts) {
                $byStatus = ($counts->get($legislation->id) ?? collect())->pluck('total', 'status');

                return [
                    'uuid' => $legislation->uuid,
                    'title' => $legislation->title,
                    'materials' => (int) $byStatus->sum(),
                ];
            })->values(),
            'selected' => $selected ? ['uuid' => $selected->uuid, 'title' => $selected->title] : null,
            'progress' => $progress,
            'materials' => $materials,
        ]);
    }

    public function edit(StudyMaterial $studyMaterial): Response|RedirectResponse
    {
        if ($studyMaterial->isSkipped()) {
            return redirect()->route('admin.study-materials.index', ['legislation' => $studyMaterial->legislation->uuid]);
        }

        $covered = array_flip($studyMaterial->coveredBlockIds());

        return Inertia::render('admin/StudyMaterials/Edit', [
            'legislation' => $studyMaterial->legislation->only(['uuid', 'title']),
            'material' => [
                'uuid' => $studyMaterial->uuid,
                'title' => $studyMaterial->title,
                'summary' => $studyMaterial->summary,
                'status' => $studyMaterial->status,
                'is_stale' => $this->service->isStale($studyMaterial),
                'generated_by_model' => $studyMaterial->generated_by_model,
                'skill_version' => $studyMaterial->skill_version,
                'published_at' => $studyMaterial->published_at?->toIso8601String(),
                'block_ids' => $studyMaterial->coveredBlockIds(),
                'questions' => $studyMaterial->questions->map(fn ($question) => [
                    'board' => $question->board,
                    'type' => $question->type,
                    'statement' => $question->statement,
                    'options' => $question->options,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'legal_basis' => $question->legal_basis,
                ])->values(),
            ],
            'summaryHtml' => $this->service->renderSummary($studyMaterial->summary),
            'blocks' => $this->rangeBlocks($studyMaterial)->map(fn (LegislationSegment $block) => [
                'id' => $block->id,
                'marker' => $block->structural_marker,
                'text' => $block->original_text,
                'covered' => isset($covered[$block->id]),
            ])->values(),
            'limits' => [
                'min_questions' => StudyMaterialService::MIN_QUESTIONS,
                'max_questions' => StudyMaterialService::MAX_QUESTIONS,
            ],
        ]);
    }

    public function update(Request $request, StudyMaterial $studyMaterial): RedirectResponse
    {
        abort_if($studyMaterial->isSkipped(), 404);

        $request->validate([
            'block_ids' => ['required', 'array'],
            'block_ids.*' => ['integer'],
        ], [
            'block_ids.required' => 'Marque ao menos um dispositivo coberto pelo material.',
        ]);

        $content = $this->service->validateContent($request->all());
        $range = $this->rangeBlocks($studyMaterial)->pluck('id')->all();
        $covered = $this->service->validateCoveredBlocks($range, $request->input('block_ids'));

        DB::transaction(function () use ($studyMaterial, $content, $covered) {
            $changedBlocks = $covered !== $studyMaterial->coveredBlockIds();

            $studyMaterial->update([
                'title' => $content['title'],
                'summary' => $content['summary'],
                'block_ids' => $covered,
                // O hash só é regravado quando os blocos cobertos mudam; para aceitar um
                // texto de lei que mudou existe o "revisar e confirmar".
                'content_hash' => $changedBlocks ? $this->service->contentHash($covered) : $studyMaterial->content_hash,
            ]);

            $this->service->replaceQuestions($studyMaterial, $content['questions']);
        });

        return back()->with('success', 'Material salvo.');
    }

    public function publish(StudyMaterial $studyMaterial): RedirectResponse
    {
        abort_if($studyMaterial->isSkipped(), 404);

        if ($this->service->isStale($studyMaterial)) {
            return back()->with('error', 'O texto da lei mudou depois que este material foi gerado. Revise e confirme antes de publicar.');
        }

        $studyMaterial->update(['status' => StudyMaterial::STATUS_PUBLISHED, 'published_at' => now()]);

        return back()->with('success', 'Material publicado.');
    }

    public function unpublish(StudyMaterial $studyMaterial): RedirectResponse
    {
        abort_if($studyMaterial->isSkipped(), 404);

        $studyMaterial->update(['status' => StudyMaterial::STATUS_DRAFT, 'published_at' => null]);

        return back()->with('success', 'Material voltou a ser rascunho.');
    }

    /**
     * "Revisar e confirmar": o admin conferiu o material contra o texto atual da lei.
     * Blocos cobertos que deixaram de existir saem do material.
     */
    public function confirm(StudyMaterial $studyMaterial): RedirectResponse
    {
        abort_if($studyMaterial->isSkipped(), 404);

        $surviving = LegislationSegment::whereIn('id', $studyMaterial->coveredBlockIds())
            ->where('is_block', true)
            ->orderBy('char_start')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (! $surviving) {
            return back()->with('error', 'Nenhum dispositivo coberto por este material existe mais. Apague o material para liberar o trecho.');
        }

        $studyMaterial->update([
            'block_ids' => $surviving,
            'first_block_id' => $studyMaterial->first_block_id ?? $surviving[0],
            'last_block_id' => $studyMaterial->last_block_id ?? end($surviving),
            'content_hash' => $this->service->contentHash($surviving),
        ]);

        return back()->with('success', 'Material confirmado com o texto atual da lei.');
    }

    public function destroy(StudyMaterial $studyMaterial): RedirectResponse
    {
        $legislation = $studyMaterial->legislation;
        $studyMaterial->delete();

        return redirect()
            ->route('admin.study-materials.index', ['legislation' => $legislation->uuid])
            ->with('success', 'Apagado. O trecho voltou a ser pendente.');
    }

    /** Pré-visualização do editor: markdown → HTML, com a mesma conversão da tela do jogador. */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate(['summary' => ['nullable', 'string', 'max:20000']]);

        return response()->json(['html' => $this->service->renderSummary($validated['summary'] ?? '')]);
    }

    // ==================== Instalar a skill ====================

    public function install(Request $request): Response
    {
        $skillFile = $this->skillPath().'/SKILL.md';

        return Inertia::render('admin/StudyMaterials/Install', [
            'siteUrl' => rtrim(url('/'), '/'),
            'skillName' => self::SKILL_NAME,
            'skillMarkdown' => File::exists($skillFile) ? File::get($skillFile) : null,
            'zipAvailable' => $this->zipAvailable(),
            'tokens' => $this->agentTokens($request)->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
            ])->values(),
            // Mostrado uma única vez, logo depois de criado
            'newToken' => $request->session()->get('study_material_token'),
        ]);
    }

    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['nullable', 'string', 'max:60']]);

        $token = $request->user()->createToken(
            trim($validated['name'] ?? '') ?: 'Agente de material de estudo',
            [StudyMaterialAgent::ABILITY],
        );

        return back()->with('study_material_token', $token->plainTextToken);
    }

    public function revokeToken(Request $request, int $token): RedirectResponse
    {
        $found = $this->agentTokens($request)->firstWhere('id', $token);
        abort_if(! $found, 404);

        $found->delete();

        return back()->with('success', 'Token revogado.');
    }

    public function downloadSkill(): BinaryFileResponse
    {
        abort_if(! $this->zipAvailable(), 404);

        $path = tempnam(sys_get_temp_dir(), 'skill');
        $zip = new \ZipArchive;
        abort_if($zip->open($path, \ZipArchive::OVERWRITE) !== true, 500);

        foreach (File::allFiles($this->skillPath()) as $file) {
            $zip->addFile($file->getPathname(), self::SKILL_NAME.'/'.str_replace('\\', '/', $file->getRelativePathname()));
        }
        $zip->close();

        return response()
            ->download($path, self::SKILL_NAME.'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    // ==================== PRIVATE METHODS ====================

    /** Blocos do trecho, do primeiro ao último, na ordem da lei. */
    private function rangeBlocks(StudyMaterial $material)
    {
        $query = LegislationSegment::where('legislation_id', $material->legislation_id)
            ->where('is_block', true)
            ->orderBy('char_start')
            ->orderBy('id');

        $bounds = LegislationSegment::whereIn('id', array_filter([$material->first_block_id, $material->last_block_id]))
            ->pluck('char_start', 'id');

        $start = $bounds->get($material->first_block_id);
        $end = $bounds->get($material->last_block_id);

        // Sem um dos blocos de fronteira, sobra o que ainda existe dos blocos cobertos
        if ($start === null || $end === null) {
            return $query->whereIn('id', $material->coveredBlockIds())->get();
        }

        return $query->whereBetween('char_start', [min($start, $end), max($start, $end)])->get();
    }

    /** Tokens do admin logado que servem para as rotas do agente. */
    private function agentTokens(Request $request)
    {
        return $request->user()->tokens()->latest()->get()
            ->filter(fn ($token) => in_array(StudyMaterialAgent::ABILITY, $token->abilities ?? [], true))
            ->values();
    }

    private function skillPath(): string
    {
        return base_path('.claude/skills/'.self::SKILL_NAME);
    }

    private function zipAvailable(): bool
    {
        return class_exists(\ZipArchive::class) && File::isDirectory($this->skillPath());
    }
}
