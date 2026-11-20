# App Android completo, igual à web

Plano de 19/11/2026. Envolve dois repositórios: este (Laravel) e o `memorize-mobile` (Expo / React
Native).

## Decisão do Ramon (19/11/2026)

- O app passa a ter tudo o que a web tem para o aluno, incluindo o mapa novo e os badges com
  estrelas. Isso inclui o perfil público, que antes era só do site.
- Sem sistema de atualização fora da loja. O registro na Play Store vem depois.
- O app novo já vem com o mapa novo para todos, no lugar do mapa antigo. Sem chave no servidor:
  a barreira é instalar o APK novo. Na web, o beta segue só para administradores.

## Status (19/11/2026, fim do dia)

Passos 1 a 5 feitos, nas branches `claude/mobile-web-feature-comparison-*` dos dois
repositórios. API: Pest passa (268; os 2 que falham, exportar CSV do admin e dashboard, testam
código que não mudou). App: `tsc` e o pacote Android (`expo export`) passam. **Falta jogar no
celular**: nada disso foi aberto num aparelho ou emulador.

## Onde estava

- **App:** a `main` parou em 4/6. O trabalho mais novo (avatar, onboarding, oferta de boas-vindas,
  "aprova errando até 1 lacuna") está na branch `avatar-mobile` (22/10).
- **Mapa novo:** só existe na web, em `/beta/play`, só para administradores e em Inertia. A API
  do app não tem rota dele.
- **Progresso:** os dois mapas gravam em `UserSegmentProgress`, bloco a bloco. Trocar de mapa não
  perde nada, só reagrupa (o mapa atual corta 15 blocos por fase; o novo, 10).

## O que a web tem e o app não tem

| Recurso | API | App |
|---|---|---|
| Mapa novo: quadrados, desafio do quadrado, Dia da prova | criar `/trail` | telas novas |
| Conquistas com até 5 estrelas | pronta (`stars`, `badge_url`) | tela |
| Perfil público pelo ranking | criar `/profiles/{uuid}` e o `uuid` no ranking | tela |
| Descrição (bio), conquistas escondidas, e-mails de lembrete | criar `/settings` | Perfil |
| Excluir conta | pronta (`DELETE /profile`) | Perfil |
| Lacunas numeradas, banco de palavras com a cara da lacuna, animação até a lacuna, nota de vetado/revogado | pronta | `ActiveChallenge` |

Fora do app de propósito: páginas públicas de leis (SEO), página do presente e descadastro por
e-mail (abrem no navegador), desafios entre amigos (não aparecem no menu da web).

## Quem vê o mapa novo

No app novo, todo mundo: é o único mapa dele, e o mapa antigo saiu do app. As rotas `/trail` da API
abrem para qualquer aluno logado. Builds antigas seguem no `/play`, que continua no servidor. Na
web nada muda: `/beta/play` só para administradores. (Chegou a existir uma chave `TRAIL_AUDIENCE`
para isso; saiu no mesmo dia, por não ser necessária.)

## Ordem

1. **App:** mesclar `avatar-mobile`.
2. **API do mapa novo** (`routes/api.php`, `v1`, `auth:sanctum`):

   | Rota | Faz |
   |---|---|
   | `GET /trail?before&after` | etapas, `has_more_above`, `has_more_below`, `window` |
   | `GET /trail/phases/{id}` | a fase |
   | `POST /trail/fail` | erro: tira a vida na hora |
   | `POST /trail/finish` | fim da fase: grava os blocos e dá o XP |
   | `GET /trail/challenges/{uuid}` | desafio do quadrado |
   | `POST /trail/challenges/{uuid}/summary` e `/answer` | resumo lido, resposta |
   | `GET /trail/exams/{uuid}` | Dia da prova |
   | `POST /trail/exams/{uuid}/start`, `/answer`, `/finish` | a prova |

   Os controllers da API herdam os do beta e usam os mesmos métodos que montam os dados, para a
   regra ficar num lugar só. Onde a web redireciona, a API responde com código (`no_lives`,
   `unavailable`). O resumo vai também em `summary_blocks` para o app desenhar e fazer a
   leitura guiada.
3. **API do resto:** perfil público, `uuid` no ranking, `/settings`.
4. **App, mapa novo:** mapa por quadrados, fase com o fluxo novo (vida sai na hora, progresso
   gravado no fim), desafio com leitura guiada, Dia da prova com o relógio do servidor.
5. **App, o resto:** estrelas, perfil público, Perfil com descrição, conquistas, e-mails e excluir
   conta, `ActiveChallenge` igual ao da web.

## Antes da Play Store

- **Assinatura:** o app abre a página de assinatura do site. A Play exige o faturamento do Google
  para conteúdo digital vendido dentro do app; ver a regra em vigor no Brasil antes de publicar.
- **Excluir conta:** a Play exige; entra no passo 5.
- Política de privacidade já existe em `/privacy-policy`.
