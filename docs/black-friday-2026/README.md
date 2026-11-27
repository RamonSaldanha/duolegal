# Black Friday 2026

O e-mail da Black Friday do Plus, enviado pelo Brevo em lotes diários.

## A oferta

Fica em `config/subscription.php` (`seasonal_offer`) e vale para quem não assina, aplicada sozinha em `/subscription` e anunciada numa faixa no topo do mapa:

- 30% em todos os planos: mensal R$ 11,99 por R$ 8,39, trimestral R$ 30,57 por R$ 21,40, anual R$ 107,91 por R$ 75,54.
- De 27/11 a quarta, 2/12, 23h59 de Brasília. Só a primeira cobrança: a renovação volta ao preço normal.
- O site usa um cupom próprio (`BLACKFRIDAY26-30`), criado no Stripe na primeira assinatura que o usar. O código `BLACK30` do Stripe continua valendo para quem digitar, mas vence segunda, 30/11, às 23h59 (o Stripe não deixa mudar essa data).

## O e-mail

- `node docs/black-friday-2026/email/build.mjs` gera `saida/memorize-direito-black-friday-2026.yaml` (para o editor do Brevo) e `saida/previa.html`. Os textos ficam no `build.mjs`.
- Os links levam a `/subscription?plan=yearly`, `?plan=quarterly` ou `?plan=monthly`, com UTM `black-friday-2026`.

### Assunto e pré-visualização por dia

| Dia | Assunto | Pré-visualização |
| --- | --- | --- |
| Sexta, 27/11 | Black Friday: 30% off em todo o Plus | O anual sai por R$ 75,54 (R$ 6,30 por mês) até quarta, 2/12. Sem cupom. |
| Sábado, 28/11 | Art. 1º Fica concedido 30% no Plus | Energia infinita e todas as legislações por R$ 6,30 por mês. Até quarta. |
| Domingo, 29/11 | 30% off no Plus até quarta-feira | Mensal por R$ 8,39, trimestral por R$ 21,40 e anual por R$ 75,54. |
| Segunda, 30/11 | Faltam 3 dias para os 30% do Plus | A Black Friday do Memorize vai até quarta, 2/12. O desconto já vem aplicado. |
| Terça, 1/12 | Amanhã acaba: 30% off no Plus | Última chance de pegar o anual por R$ 75,54. A oferta termina quarta, às 23h59. |
| Quarta, 2/12 | Último dia: 30% off no Plus | A Black Friday do Memorize acaba hoje, às 23h59. Depois, o preço volta ao normal. |
