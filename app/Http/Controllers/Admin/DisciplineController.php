<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discipline;
use App\Models\Legislation;
use Illuminate\Http\Request;
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

    public function index(): Response
    {
        $disciplines = Discipline::withCount('legislations')
            ->orderBy('name')
            ->get();

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
        ]);

        $discipline->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? 'Scale',
            'color' => $validated['color'] ?? '#EAB308',
        ]);

        $discipline->legislations()->sync($validated['legislation_ids'] ?? []);

        return redirect()->route('admin.disciplines.index')
            ->with('success', 'Disciplina atualizada com sucesso.');
    }

    public function destroy(Discipline $discipline)
    {
        $discipline->delete();

        return redirect()->route('admin.disciplines.index')
            ->with('success', 'Disciplina excluída com sucesso.');
    }
}
