# 💳 Sistema de Pagamento Stripe - Guia Completo

> **Status**: ✅ Configurado e funcionando | **Última atualização**: 2025-01-18

---

## 🚀 Início Rápido (3 Passos)

### 1. Build do Frontend
```bash
npm run build
```

### 2. Iniciar Servidor
```bash
php artisan serve
```

### 3. Testar Assinatura
- **URL**: `http://localhost:8000/subscription`
- **Cartão de Teste**: `4242 4242 4242 4242`
- **Data**: `12/25` | **CVC**: `123` | **CEP**: `12345`

✅ Após assinar, você terá **vidas infinitas** no jogo!

---

## 📋 Configuração Atual

### Variáveis de Ambiente (.env)

```env
# Stripe Test Keys (configure com suas credenciais)
STRIPE_KEY=pk_test_sua_chave_publica_aqui
STRIPE_SECRET=sk_test_sua_chave_secreta_aqui
STRIPE_WEBHOOK_SECRET=
STRIPE_PRICE_ID=price_seu_price_id_aqui
VITE_STRIPE_KEY="${STRIPE_KEY}"
```

> **Nota**: As credenciais reais estão no arquivo `.env` local (não versionado).

### Produto/Preço Configurado

| Campo | Valor |
|-------|-------|
| **Price ID** | `price_1SJh6VRFvMiw5HPNEZpFCi12` |
| **Valor** | R$ 89,90 |
| **Intervalo** | Anual (yearly) |
| **Moeda** | BRL |

> ⚠️ **Importante**: O preço está configurado como **anual**, não mensal. Veja [Como Mudar o Preço](#como-mudar-o-preço) para alterar.

---

## 🧪 Cartões de Teste

Use estes cartões para testar diferentes cenários:

| Cenário | Número do Cartão | Resultado |
|---------|------------------|-----------|
| ✅ **Sucesso** | `4242 4242 4242 4242` | Pagamento aprovado |
| 🔐 **3D Secure** | `4000 0025 0000 3155` | Requer autenticação adicional |
| ❌ **Recusado** | `4000 0000 0000 9995` | Cartão negado (fundos insuficientes) |
| ❌ **Expirado** | `4000 0000 0000 0069` | Cartão expirado |

**Dados complementares** (use qualquer valor):
- Data de validade: `12/25`
- CVC: `123`
- CEP: `12345`

---

## ✅ Verificar se Funcionou

### Via Interface
1. Acesse `/subscription`
2. Após assinar, deve aparecer: **"Assinatura Ativa"**
3. No jogo, as vidas devem ser **infinitas** (símbolo ∞)

### Via Tinker
```bash
php artisan tinker

$user = User::find(1);  # Substitua pelo seu ID
$user->subscribed('default');           # true = assinatura ativa
$user->hasInfiniteLives();              # true = vidas infinitas
$user->hasActiveSubscription();         # true = tudo funcionando
```

### Via Dashboard Stripe
Acesse [Dashboard Stripe (Teste)](https://dashboard.stripe.com/test/subscriptions) e veja a assinatura criada.

---

## 🔧 Como Mudar o Preço

### Cenário 1: Criar Preço Mensal (R$ 9,90/mês)

**1. No Dashboard do Stripe:**
- Acesse: [Dashboard Stripe > Products](https://dashboard.stripe.com/test/products)
- Encontre ou crie o produto "Memorize Direito Premium"
- Clique em **"Add another price"**
- Configure:
  - **Tipo**: Recurring (recorrente)
  - **Valor**: `9.90`
  - **Moeda**: BRL
  - **Intervalo**: Monthly (mensal)
- Clique em **"Add price"**
- **Copie o Price ID** (ex: `price_abc123...`)

**2. Atualize o .env:**
```env
STRIPE_PRICE_ID=price_seu_novo_price_id_mensal
```

**3. Limpe o cache:**
```bash
php artisan config:clear
```

**Pronto!** Agora o sistema usa o preço mensal.

### Cenário 2: Oferecer Planos Mensal + Anual

**1. Crie dois preços no Stripe** (mensal e anual)

**2. Atualize o .env:**
```env
STRIPE_PRICE_MONTHLY=price_abc123
STRIPE_PRICE_YEARLY=price_xyz789
```

**3. Atualize o código** para permitir escolha do plano (requer customização da UI)

---

## 🔍 Troubleshooting

### Problema: "Stripe.js não foi carregado"

**Solução:**
1. Verifique se o script está no HTML:
   ```html
   <script src="https://js.stripe.com/v3/"></script>
   ```
2. Execute:
   ```bash
   npm run build
   php artisan view:clear
   ```

### Problema: "Invalid API Key provided"

**Solução:**
```bash
# Verificar .env
grep STRIPE .env

# Limpar cache
php artisan config:clear

# Testar
php artisan tinker
config('cashier.key');
```

### Problema: "No such price"

**Causa:** Price ID não existe na sua conta Stripe

**Solução:**
1. Acesse [Dashboard > Products](https://dashboard.stripe.com/test/products)
2. Verifique se o Price ID existe
3. Copie o ID correto
4. Atualize no `.env`: `STRIPE_PRICE_ID=price_correto`
5. Execute: `php artisan config:clear`

### Problema: Vidas infinitas não aparecem

**Diagnóstico:**
```bash
php artisan tinker

$user = User::find(1);
$user->subscribed('default');           # Deve ser true
$user->hasInfiniteLives();              # Deve ser true

# Ver detalhes da assinatura
$subscription = $user->subscription('default');
echo $subscription->stripe_status;      # Deve ser "active"
```

**Soluções:**
- Se `subscribed()` retorna false: a assinatura não foi criada, tente assinar novamente
- Se `stripe_status` não é "active": verifique no Dashboard do Stripe o que aconteceu
- Execute: `$user = $user->fresh();` para recarregar dados

### Problema: Formulário de cartão não aparece

**Diagnóstico (Console do navegador F12):**
```javascript
console.log(typeof Stripe);                      // Deve ser "function"
console.log(import.meta.env.VITE_STRIPE_KEY);   // Deve mostrar pk_test_...
```

**Solução:**
```bash
# Verificar .env
grep VITE_STRIPE_KEY .env

# Rebuild
npm run build
```

### Script de Diagnóstico Completo

Execute este script para validar toda a configuração:

```bash
php test-stripe.php
```

Deve mostrar:
```
✅ Todos os testes passaram!
✓ Verificando configurações...
✓ Testando conexão com Stripe API...
✓ Verificando Price ID configurado...
✓ Verificando tabelas do banco de dados...
✓ Testando criação de Customer no Stripe...
```

---

## 🏭 Configuração para Produção

### Passo 1: Obter Credenciais de Produção

1. Acesse [Dashboard do Stripe](https://dashboard.stripe.com)
2. **Desative o modo de teste** (toggle no canto superior direito)
3. Vá em **Developers > API Keys**
4. Copie:
   - Publishable key (`pk_live_...`)
   - Secret key (`sk_live_...`)

### Passo 2: Criar Produto no Stripe Produção

1. Dashboard Stripe (produção) > **Products**
2. Crie produto "Memorize Direito Premium"
3. Adicione preço: **R$ 9,90/mês** (ou o valor desejado)
4. Copie o **Price ID** (`price_...`)

### Passo 3: Configurar .env de Produção

```env
# ATENÇÃO: Use no servidor de produção, NÃO no local!
STRIPE_KEY=pk_live_SUA_CHAVE_PUBLICA
STRIPE_SECRET=sk_live_SUA_CHAVE_SECRETA
STRIPE_WEBHOOK_SECRET=whsec_SUA_CHAVE_WEBHOOK
STRIPE_PRICE_ID=price_SEU_PRICE_ID_PRODUCAO
VITE_STRIPE_KEY="${STRIPE_KEY}"

APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com
```

### Passo 4: Configurar Webhooks no Stripe

1. Dashboard Stripe > **Developers > Webhooks**
2. Clique em **"Add endpoint"**
3. Configure:
   - **URL**: `https://seudominio.com/stripe/webhook`
   - **Eventos** (selecione):
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `customer.updated`
     - `customer.deleted`
     - `invoice.payment_action_required`
     - `invoice.payment_succeeded`
     - `invoice.payment_failed`
4. Copie o **Signing secret** (`whsec_...`)
5. Adicione ao `.env` como `STRIPE_WEBHOOK_SECRET`

### Passo 5: Deploy

```bash
# No servidor de produção
git pull origin main
composer install --optimize-autoloader --no-dev
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

### Passo 6: Verificar SSL/HTTPS

⚠️ Stripe **requer HTTPS** em produção:

```bash
# Testar certificado SSL
curl -I https://seudominio.com
```

---

## 🧪 Webhooks Locais (Opcional)

Para testar webhooks durante desenvolvimento:

### 1. Instalar Stripe CLI

**Windows:**
```bash
choco install stripe-cli
```

**macOS:**
```bash
brew install stripe/stripe-cli/stripe
```

**Linux:**
```bash
wget https://github.com/stripe/stripe-cli/releases/download/v1.19.4/stripe_1.19.4_linux_x86_64.tar.gz
tar -xvf stripe_1.19.4_linux_x86_64.tar.gz
sudo mv stripe /usr/local/bin
```

### 2. Configurar

```bash
# Login
stripe login

# Forward webhooks
stripe listen --forward-to http://localhost:8000/stripe/webhook

# Copiar o webhook secret (whsec_...) que aparece
# Adicionar ao .env:
STRIPE_WEBHOOK_SECRET=whsec_seu_secret_aqui

# Limpar cache
php artisan config:clear
```

### 3. Testar

```bash
# Em outro terminal, teste disparando um evento
stripe trigger customer.subscription.created
```

> **Dica**: Mantenha `stripe listen` rodando em terminal separado durante desenvolvimento.

---

## 📚 Estrutura do Sistema

### Arquivos Principais

**Backend:**
- `app/Http/Controllers/SubscriptionController.php` - Controller de assinatura
- `app/Http/Controllers/StripeWebhookController.php` - Handler de webhooks
- `app/Models/User.php` - Trait Billable + vidas infinitas
- `config/cashier.php` - Configuração do Laravel Cashier

**Frontend:**
- `resources/js/Pages/Subscription/Index.vue` - UI de assinatura

**Database:**
- `database/migrations/*_create_customer_columns.php`
- `database/migrations/*_create_subscriptions_table.php`
- `database/migrations/*_create_subscription_items_table.php`

### Rotas Disponíveis

```
GET  /subscription              - Página de assinatura
POST /subscription              - Processar assinatura
POST /subscription/cancel       - Cancelar assinatura
POST /subscription/resume       - Reativar assinatura
POST /stripe/webhook            - Webhook do Stripe
```

### Como Funciona

1. **Usuário acessa** `/subscription`
2. **Frontend** captura dados do cartão via Stripe.js (seguro)
3. **Backend** cria assinatura via Laravel Cashier
4. **Stripe** processa pagamento
5. **Webhooks** sincronizam status automaticamente
6. **Usuário** recebe vidas infinitas imediatamente

---

## 🎯 Comandos Úteis

### Verificar Assinatura
```bash
php artisan tinker
$user = User::find(1);
$user->subscribed('default');
$user->hasInfiniteLives();
```

### Ver Assinaturas no Banco
```sql
SELECT
    users.name,
    users.email,
    subscriptions.stripe_status,
    subscriptions.ends_at
FROM users
LEFT JOIN subscriptions ON users.id = subscriptions.user_id
WHERE subscriptions.stripe_status = 'active';
```

### Cancelar Assinatura Manualmente
```bash
php artisan tinker
$user = User::find(1);
$user->subscription('default')->cancel();
```

### Reativar Assinatura
```bash
php artisan tinker
$user = User::find(1);
$user->subscription('default')->resume();
```

### Limpar Caches
```bash
php artisan optimize:clear
php artisan config:clear
npm run build
```

### Ver Logs em Tempo Real
```bash
tail -f storage/logs/laravel.log
```

---

## 📖 Recursos e Links

### Documentação Oficial
- [Laravel Cashier](https://laravel.com/docs/11.x/billing)
- [Stripe Documentation](https://stripe.com/docs)
- [Stripe Testing](https://stripe.com/docs/testing)
- [Stripe CLI](https://stripe.com/docs/stripe-cli)

### Dashboards
- [Stripe Dashboard (Teste)](https://dashboard.stripe.com/test)
- [Stripe Dashboard (Produção)](https://dashboard.stripe.com)

### Cartões de Teste Adicionais
- **3D Secure com sucesso**: `4000 0027 6000 3184`
- **Requer verificação**: `4000 0082 6000 0000`
- **Recusado (CVC incorreto)**: `4000 0000 0000 0127`
- **Recusado (processamento)**: `4000 0000 0000 0119`

---

## ❓ FAQ

### Preciso configurar SSL para testar localmente?
Não. SSL só é necessário em produção.

### Posso usar cartões reais em modo teste?
Não. Use apenas os cartões de teste fornecidos pelo Stripe.

### Como sei se estou em modo teste ou produção?
- **Teste**: Chaves começam com `pk_test_` e `sk_test_`
- **Produção**: Chaves começam com `pk_live_` e `sk_live_`

### O que acontece se o pagamento falhar?
O Stripe tenta automaticamente nos próximos dias. O webhook `invoice.payment_failed` notifica falhas.

### Posso oferecer trial gratuito?
Sim! No código, remova `.skipTrial()` e adicione `.trialDays(7)` por exemplo.

### Como emitir faturas?
Laravel Cashier gera faturas automaticamente. Acesse via:
```php
$user->invoices();
$user->downloadInvoice($invoiceId, ['vendor' => 'Memorize Direito']);
```

---

## 🆘 Checklist de Diagnóstico

Quando algo não funcionar, execute em ordem:

- [ ] Executar `php test-stripe.php` → deve passar todos testes
- [ ] Verificar `.env` → chaves devem começar com `pk_test_` e `sk_test_`
- [ ] Executar `php artisan config:clear`
- [ ] Executar `npm run build`
- [ ] Abrir console do navegador (F12) → não deve ter erros JavaScript
- [ ] Verificar logs: `tail -f storage/logs/laravel.log`
- [ ] Testar com cartão `4242 4242 4242 4242`
- [ ] Verificar Dashboard do Stripe → eventos devem aparecer lá

---

## 🎉 Resumo

**O sistema está 100% configurado e funcionando!**

### Para começar AGORA:
```bash
npm run build
php artisan serve
# Acesse: http://localhost:8000/subscription
```

### Para ir para produção:
1. Crie produto no Stripe (modo produção)
2. Obtenha chaves `pk_live_` e `sk_live_`
3. Configure webhooks no Dashboard
4. Atualize `.env` de produção
5. Deploy!

---

**Dúvidas?** Consulte as seções de [Troubleshooting](#troubleshooting) e [FAQ](#faq).

**Pronto para testar!** 🚀
