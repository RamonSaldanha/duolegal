<?php

namespace App\Http\Controllers;

use App\Mail\ReengagementMail;
use App\Models\ReengagementEmail;
use App\Services\ReengagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página do presente que vai no lembrete por e-mail. Abre sem login: quem tem
 * o link (com o uuid do lembrete) é a dona do e-mail. Abrir é um POST, para
 * leitor de link de antivírus não abrir o presente sozinho.
 */
class GiftController extends Controller
{
    public function __construct(private ReengagementService $reengagement) {}

    public function show(Request $request, ReengagementEmail $reengagementEmail): Response
    {
        $user = $reengagementEmail->user;

        return Inertia::render('Gift/Show', [
            'gift' => [
                'uuid' => $reengagementEmail->uuid,
                'type' => $reengagementEmail->gift,
                'status' => $this->reengagement->giftStatus($reengagementEmail),
                'lives' => ReengagementService::GIFT_LIVES,
                'boost_days' => ReengagementService::BOOST_DAYS,
                'boost_percent' => (int) round((ReengagementService::BOOST_MULTIPLIER - 1) * 100),
                'boost_until' => $user->hasXpBoost() ? $user->xp_boost_until->toIso8601String() : null,
            ],
            'name' => ReengagementMail::firstName($user->name),
            'justOpened' => (bool) $request->session()->get('gift_opened'),
        ]);
    }

    public function claim(ReengagementEmail $reengagementEmail): RedirectResponse
    {
        $opened = $this->reengagement->claim($reengagementEmail);

        return redirect()
            ->route('gift.show', $reengagementEmail->uuid)
            ->with('gift_opened', $opened);
    }
}
