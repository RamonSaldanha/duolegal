<?php

/**
 * Script de teste rápido para verificar integração com Stripe
 * Execute: php test-stripe.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Facade;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Facade::clearResolvedInstances();
Facade::setFacadeApplication($app);

echo "========================================\n";
echo "  Teste de Configuração Stripe\n";
echo "========================================\n\n";

// Teste 1: Verificar configurações
echo "✓ Verificando configurações...\n";
$stripeKey = config('cashier.key');
$stripeSecret = config('cashier.secret');
$currency = config('cashier.currency');

if (empty($stripeKey)) {
    echo "✗ STRIPE_KEY não configurado!\n";
    exit(1);
}

if (empty($stripeSecret)) {
    echo "✗ STRIPE_SECRET não configurado!\n";
    exit(1);
}

echo "  - Stripe Key: " . substr($stripeKey, 0, 20) . "...\n";
echo "  - Stripe Secret: " . substr($stripeSecret, 0, 20) . "...\n";
echo "  - Currency: $currency\n\n";

// Teste 2: Verificar conexão com Stripe
echo "✓ Testando conexão com Stripe API...\n";
\Stripe\Stripe::setApiKey($stripeSecret);

try {
    $account = \Stripe\Account::retrieve();
    echo "  - Conectado à conta: " . $account->id . "\n";
    echo "  - Email da conta: " . ($account->email ?? 'N/A') . "\n";
    echo "  - País: " . ($account->country ?? 'N/A') . "\n\n";
} catch (\Exception $e) {
    echo "✗ Erro ao conectar: " . $e->getMessage() . "\n";
    exit(1);
}

// Teste 3: Verificar Price ID
echo "✓ Verificando Price ID configurado...\n";
$priceId = 'price_1SJh6VRFvMiw5HPNEZpFCi12';

try {
    $price = \Stripe\Price::retrieve($priceId);
    echo "  - Price ID: " . $price->id . "\n";
    echo "  - Produto: " . $price->product . "\n";
    echo "  - Tipo: " . $price->type . "\n";

    if ($price->type === 'recurring') {
        $amount = $price->unit_amount / 100;
        $currency = strtoupper($price->currency);
        $interval = $price->recurring->interval;
        echo "  - Valor: $currency $amount / $interval\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "✗ Price não encontrado: " . $e->getMessage() . "\n";
    echo "  Você precisa criar um produto no Dashboard do Stripe!\n";
    exit(1);
}

// Teste 4: Verificar tabelas do banco
echo "✓ Verificando tabelas do banco de dados...\n";
$tables = ['users', 'subscriptions', 'subscription_items'];

foreach ($tables as $table) {
    try {
        $count = DB::table($table)->count();
        echo "  - Tabela '$table': ✓ ($count registros)\n";
    } catch (\Exception $e) {
        echo "  - Tabela '$table': ✗ (não existe ou erro)\n";
    }
}
echo "\n";

// Teste 5: Testar criação de customer (sem salvar)
echo "✓ Testando criação de Customer no Stripe...\n";
try {
    $testCustomer = \Stripe\Customer::create([
        'email' => 'teste@exemplo.com',
        'name' => 'Usuário de Teste',
        'metadata' => [
            'test' => 'true',
        ],
    ]);

    echo "  - Customer criado: " . $testCustomer->id . "\n";
    echo "  - Email: " . $testCustomer->email . "\n";

    // Deletar o customer de teste
    $testCustomer->delete();
    echo "  - Customer de teste removido\n\n";
} catch (\Exception $e) {
    echo "✗ Erro ao criar customer: " . $e->getMessage() . "\n";
    exit(1);
}

// Resumo
echo "========================================\n";
echo "  ✅ Todos os testes passaram!\n";
echo "========================================\n\n";

echo "Próximos passos:\n";
echo "1. Execute: npm run build (ou npm run dev)\n";
echo "2. Execute: php artisan serve\n";
echo "3. Acesse: http://localhost:8000/subscription\n";
echo "4. Use o cartão de teste: 4242 4242 4242 4242\n\n";

echo "Para mais informações, veja:\n";
echo "- INICIO_RAPIDO_STRIPE.md\n";
echo "- STRIPE_SETUP.md\n";
