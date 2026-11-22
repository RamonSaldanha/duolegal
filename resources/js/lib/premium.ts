// O plano anual como as telas do jogo o oferecem (convite do fim de fase e tela sem energia):
// R$ 107,91 (R$ 8,99/mês, 25% abaixo de 12x o mensal de R$ 11,99). Na página de assinatura o
// preço vem do Stripe. O app tem os mesmos valores em src/components/trail/PremiumPieces.tsx.
export const YEARLY = 'R$ 107,91';
export const YEARLY_PER_MONTH = 'R$ 8,99';
export const YEARLY_OFF = '-25%';
export const YEARLY_URL = '/subscription?plan=yearly';

// As vantagens, curtas e sem descrição (PerkList.vue). O app tem as mesmas em PremiumPieces.tsx.
export const PERKS = ['Energia e vidas infinitas', 'Fantasias exclusivas', 'Sem anúncios', 'Todas as legislações', 'Garantia de 7 dias'] as const;
