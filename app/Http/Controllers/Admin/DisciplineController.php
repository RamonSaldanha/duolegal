<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discipline;
use App\Models\Legislation;
use App\Services\DisciplineStarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DisciplineController extends Controller
{
    private const AVAILABLE_ICONS = [
        // Constitucional, Geral, Processual
        'Scale', 'Gavel', 'Landmark', 'BookOpen', 'ScrollText', 'FileText',
        // Penal, Militar, Segurança
        'Siren', 'ShieldAlert', 'Lock', 'Swords', 'Shield', 'ShieldCheck',
        // Civil, Família, Consumidor
        'Users', 'Handshake', 'Heart', 'Home', 'ShoppingCart', 'Receipt',
        // Trabalho, Empresarial, Tributário
        'Briefcase', 'HardHat', 'Factory', 'Building2', 'HandCoins', 'Banknote',
        // Digital, Tecnologia
        'Cpu', 'CircuitBoard', 'Monitor', 'Wifi', 'Fingerprint', 'Brain',
        // Ambiental, Agrário, Marítimo
        'Leaf', 'TreePine', 'Globe', 'Droplets', 'Wheat', 'Ship',
        // Internacional, Eleitoral, Imobiliário
        'Plane', 'Flag', 'Vote', 'Key', 'MapPin', 'Earth',
        // Saúde, Previdenciário, Educação
        'Stethoscope', 'HeartPulse', 'Umbrella', 'GraduationCap', 'Award', 'Crown',
        'Hammer', 'Baby', 'Megaphone', 'Calculator', 'Anchor', 'Store',
    ];

    private const AVAILABLE_COLORS = [
        '#475569', '#C2410C', '#4D7C0F', '#0E7490', '#1D4ED8',
        '#1E40AF', '#15803D', '#991B1B', '#A21CAF', '#6D28D9',
        '#0F766E', '#CA8A04',
        '#64748B', '#78716C', '#737373', '#0D9488', '#0284C7',
        '#7C3AED', '#BE185D', '#B45309', '#047857', '#4338CA',
        '#9F1239', '#854D0E',
    ];

    /** Ilustrações de resources/js/lib/badges/art.ts. */
    private const BADGE_ILLUSTRATIONS = [
        'penal', 'civil', 'const', 'consumidor', 'tributario', 'processo',
        'estatuto', 'previdencia', 'trabalho', 'administrativo', 'empresarial',
    ];

    /** Uma imagem para cada quantidade de estrelas, de 0 (bloqueado) a 5. */
    private const BADGE_IMAGE_COUNT = DisciplineStarService::MAX_STARS + 1;

    public function __construct(private DisciplineStarService $stars) {}

    public function index(): Response
    {
        $disciplines = Discipline::withCount('legislations')
            ->orderBy('name')
            ->get()
            ->map(fn (Discipline $discipline) => array_merge($discipline->toArray(), [
                'badge_url' => $this->stars->badgeUrl($discipline, DisciplineStarService::MAX_STARS),
            ]));

        return Inertia::render('admin/Disciplines/Index', [
            'disciplines' => $disciplines,
        ]);
    }

    public function create(): Response
    {
        $legislations = Legislation::orderBy('title')
            ->get(['id', 'uuid', 'title']);

        return Inertia::render('admin/Disciplines/Create', [
            'legislations' => $legislations,
            'availableIcons' => self::AVAILABLE_ICONS,
            'availableColors' => self::AVAILABLE_COLORS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'legislation_ids' => 'nullable|array',
            'legislation_ids.*' => 'exists:legislations,id',
            ...$this->badgeRules(required: true),
        ]);

        $discipline = Discipline::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'Scale',
            'color' => $validated['color'] ?? '#EAB308',
        ]);

        if (! empty($validated['legislation_ids'])) {
            $discipline->legislations()->sync($validated['legislation_ids']);
        }

        $this->saveBadge($discipline, $request, $validated);
        $this->stars->freezeThresholds($discipline);

        return redirect()->route('admin.disciplines.index')
            ->with('success', 'Disciplina criada com sucesso.');
    }

    public function edit(Discipline $discipline): Response
    {
        $discipline->load('legislations');

        $legislations = Legislation::orderBy('title')
            ->get(['id', 'uuid', 'title']);

        return Inertia::render('admin/Disciplines/Edit', [
            'discipline' => $discipline,
            'potentialXp' => $this->stars->potentials($discipline->id)[$discipline->id] ?? 0,
            'legislations' => $legislations,
            'selectedLegislationIds' => $discipline->legislations->pluck('id')->toArray(),
            'availableIcons' => self::AVAILABLE_ICONS,
            'availableColors' => self::AVAILABLE_COLORS,
        ]);
    }

    public function update(Request $request, Discipline $discipline)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'legislation_ids' => 'nullable|array',
            'legislation_ids.*' => 'exists:legislations,id',
            ...$this->badgeRules(required: false),
        ]);

        $discipline->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'Scale',
            'color' => $validated['color'] ?? '#EAB308',
        ]);

        $discipline->legislations()->sync($validated['legislation_ids'] ?? []);

        $this->saveBadge($discipline, $request, $validated);
        $this->stars->freezeThresholds($discipline);

        return redirect()->route('admin.disciplines.index')
            ->with('success', 'Disciplina atualizada com sucesso.');
    }

    public function destroy(Discipline $discipline)
    {
        Storage::disk('local')->deleteDirectory($discipline->badgeImagesDirectory());
        $discipline->delete();

        return redirect()->route('admin.disciplines.index')
            ->with('success', 'Disciplina excluída com sucesso.');
    }

    /** As imagens chegam prontas: o formulário as desenha no navegador, com a arte do badge. */
    private function badgeRules(bool $required): array
    {
        $presence = $required ? 'required' : 'required_with:badge_images';
        $images = $required ? 'required' : 'nullable';

        return [
            'badge_config' => [$presence, 'array'],
            'badge_config.icon' => [$presence, 'string', 'in:'.implode(',', self::BADGE_ILLUSTRATIONS)],
            'badge_config.text' => [$presence, 'string', 'max:20'],
            'badge_config.shield' => [$presence, 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badge_config.ribbon' => [$presence, 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badge_images' => [$images, 'array', 'size:'.self::BADGE_IMAGE_COUNT],
            'badge_images.*' => ['file', 'mimes:png', 'max:2048'],
        ];
    }

    private function saveBadge(Discipline $discipline, Request $request, array $validated): void
    {
        if (! $request->hasFile('badge_images')) {
            return;
        }

        foreach ($request->file('badge_images') as $stars => $image) {
            Storage::disk('local')->putFileAs($discipline->badgeImagesDirectory(), $image, "{$stars}.png");
        }

        $discipline->update([
            'badge_config' => $validated['badge_config'],
            'badge_version' => now()->timestamp,
        ]);
    }
}
