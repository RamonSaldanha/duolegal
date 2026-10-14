<?php

namespace App\Http\Resources\Api;

use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'lives' => $this->lives,
            'has_infinite_lives' => $this->hasInfiniteLives(),
            'xp' => $this->xp,
            'current_streak' => $this->effectiveStreak(),
            'longest_streak' => $this->longestStreak(),
            'is_admin' => $this->is_admin,
            'email_verified_at' => $this->email_verified_at,
            // Nulo para quem nunca editou o avatar: o app cai nas iniciais.
            'avatar_config' => $this->publicAvatarConfig(),
            // Onboarding do app: pendente só para quem se cadastrou pelo app novo.
            'needs_onboarding' => $this->onboarding_completed_at === null,
            // Oferta de boas-vindas (15% no anual), para o banner do mapa. Nula fora das 24h.
            'welcome_offer' => app(OnboardingService::class)->welcomeOffer($this->resource),
        ];
    }
}
