---
name: gerar-material-estudo
description: Gera material de estudo (um resumo em markdown e duas questões inéditas no estilo Cebraspe, FGV, FCC ou Vunesp) para os dispositivos de uma lei do Memorize Direito e grava no site pelas rotas REST do admin. Use quando o usuário pedir, por exemplo, "faça questões e resumos para a CF88", "gere material de estudo para o Código Penal" ou "continue o material da Constituição". Não use para criar ou editar lacunas, blocos ou o texto das leis.
---

# Gerar material de estudo

Versão da skill: **1.0.0** (envie este valor em `skill_version`).

Você vai ler os dispositivos de uma lei, escolher trechos que formem um assunto, escrever para
cada trecho um resumo e duas questões, e gravar tudo no site. O que você grava entra como
rascunho; um administrador revisa e publica. Você pode parar a qualquer momento: da próxima vez,
o site devolve o ponto em que você parou.

## Configuração

Leia duas variáveis de ambiente:

- `MEMORIZE_URL`: endereço do site, sem barra no fim (ex.: `https://memorizedireito.com.br`).
- `MEMORIZE_TOKEN`: token de acesso do administrador.

Se alguma faltar, pare e peça ao usuário para abrir, no site, **Admin → Material de estudo →
Instalar a skill** (`/admin/study-materials/instalar`), que gera o token e mostra como definir as
variáveis. **Nunca peça o token no chat** e nunca o escreva em arquivos, mensagens ou respostas.

Todas as rotas ficam em `$MEMORIZE_URL/api/v1/admin/study-materials` e exigem os cabeçalhos:

```
Authorization: Bearer $MEMORIZE_TOKEN
Accept: application/json
```

- `401`: token ausente ou inválido. `403`: o token não é de administrador ou não tem a habilidade
  `study-materials`. Nos dois casos, pare e mande o usuário à página de instalação.
- `422`: erro de validação. O corpo traz `message` e `errors` por campo, em português, dizendo o
  que era esperado. Corrija e reenvie; não desista no primeiro erro.
- `429`: muitas requisições. Espere um minuto e continue.

## Ciclo de trabalho

1. **Listar as leis:** `GET /legislations`.
2. **Identificar a lei pedida** pelo título ("CF88" é a Constituição Federal de 1988). Se houver
   mais de uma candidata, ou nenhuma, pergunte ao usuário qual é.
3. **Ver o andamento:** `GET /status?legislation={uuid}`. Guarde `questions_by_board` para
   equilibrar as bancas.
4. **Buscar os próximos blocos:** `GET /next?legislation={uuid}`. Se vier `"complete": true`, a
   lei terminou: avise o usuário e pare.
5. **Escolher o trecho** (regras abaixo).
6. **Gerar** o resumo e as questões, ou decidir pular o trecho.
7. **Conferir** cada gabarito contra o texto recebido.
8. **Gravar:** `POST /` (material) ou `POST /skip` (trecho pulado).
9. **Repetir** a partir do passo 4. Atualize de cabeça a contagem de bancas a cada material; a
   cada 10 materiais, chame `GET /status` de novo.

Trabalhe um trecho por vez: só busque o próximo depois que o atual foi gravado.

Ao parar, por qualquer motivo, informe: quantos materiais foram gravados, quantos trechos foram
pulados (com os motivos) e quantos blocos ainda faltam (`pending_blocks` da última resposta).

## Como escolher o trecho

`GET /next` devolve até 30 blocos pendentes em sequência. Cada bloco é um dispositivo (ou um
grupo pequeno deles) com `id`, `article_reference`, `structural_marker`, `segment_type`,
`heading_before` (título, capítulo ou seção que aparece logo antes dele, quando houver) e `text`.
O trecho é um intervalo contínuo desses blocos.

- **Comece sempre no primeiro bloco recebido** (`first_block_id` da resposta). O site recusa
  trecho que deixa buraco.
- **Títulos, capítulos e seções** podem vir em `heading_before` ou, o que é mais comum, no fim
  do `text` do bloco anterior (o art. 5º termina com "TÍTULO II / Competência Tributária"). Um
  cabeçalho no fim de um bloco anuncia o assunto do bloco seguinte, não o daquele.
- **Termine onde o assunto termina.** Use esses cabeçalhos, os marcadores e o conteúdo. Mire em
  cerca de **10 blocos**; o site aceita de **4 a 20**. Se todos os dispositivos sobre um tema
  somam 13 blocos, o trecho tem 13.
- **Não corte no meio de um artigo:** caput, incisos e parágrafos ficam juntos. A exceção é o
  artigo muito longo (como o art. 5º da Constituição), que pode ser dividido por tema.
- **Deixe fora de `block_ids`** os blocos do trecho que não têm matéria: revogados, vetados,
  meramente remissivos ou o cabeçalho da lei (ementa e preâmbulo, que costumam ser o bloco 1). Eles continuam dentro do intervalo, só não são cobertos pelo material.
- **Pule o trecho** (`POST /skip`), com um motivo curto, quando não houver matéria para questão:
  disposições transitórias já esgotadas, sequência de dispositivos revogados. O trecho pulado
  pode ter de 1 a 40 blocos.
- Se os blocos recebidos acabam no meio de um assunto, feche o trecho no último ponto de corte
  natural antes do fim; o restante volta na próxima chamada.
- No fim da lei podem sobrar menos de 4 blocos. O site aceita um trecho menor que 4 quando ele
  cobre todos os blocos pendentes que restam em sequência.

`previous_material_title` é o título do material anterior: use-o para manter a continuidade e não
repetir o mesmo título.

## Resumo

Campo `summary`, em **markdown**, com **250 a 450 palavras**. Sem HTML (o site remove).

- Use subtítulos (`##`), listas e **negrito** nos termos-chave. Tabela só quando comparar duas ou
  três figuras lado a lado.
- Explique os dispositivos com **casos concretos** ("Maria, servidora estadual, ...").
- Mostre **como o tema costuma ser cobrado** e as **pegadinhas mais comuns** (troca de prazo,
  de quórum, de competência, de "poderá" por "deverá").
- Termine com uma orientação prática de **como resolver** questões do tema.
- Cite os dispositivos pelo número ("art. 12, § 4º"), para o aluno localizar.
- Trate só do que está nos blocos cobertos. Não cite súmula, julgado ou doutrinador de memória.

`title`: curto e específico, com até 80 caracteres ("Nacionalidade: brasileiros natos e
naturalizados"). Sem o nome da lei e sem numeração de material.

## Questões

**Duas questões por material, de bancas diferentes entre si.** Alterne as quatro bancas ao longo
da lei: escolha as duas com menor contagem em `questions_by_board`. O site aceita de 1 a 4
questões, mas o padrão é 2.

| Banca | `board` | `type` | Estilo |
|---|---|---|---|
| Cebraspe | `cebraspe` | `true_false` | Item único para julgar certo ou errado; afirmação assertiva, com troca sutil de termos quando errado. |
| FGV | `fgv` | `multiple_choice` | Enunciado com caso concreto e personagens; 5 alternativas. |
| FCC | `fcc` | `multiple_choice` | Cobrança próxima da literalidade do texto legal; 5 alternativas. |
| Vunesp | `vunesp` | `multiple_choice` | Enunciado direto; 5 alternativas de tamanho parecido. |

Regras de formato, que o site valida:

- Cebraspe é sempre `true_false`: `options` é `null` e `correct_answer` é `"certo"` ou `"errado"`.
- FGV, FCC e Vunesp são sempre `multiple_choice`: `options` é um objeto com exatamente as chaves
  `"A"`, `"B"`, `"C"`, `"D"`, `"E"`, nessa ordem, e `correct_answer` é uma única letra.
- `explanation` e `legal_basis` são obrigatórios em toda questão.

Regras de qualidade:

- Toda questão é **respondível só com os dispositivos cobertos** e traz em `legal_basis` o
  dispositivo que fundamenta o gabarito ("CF, art. 12, I, a").
- O comentário (`explanation`) explica por que a correta está certa **e por que cada errada está
  errada**. Em certo/errado, aponta o termo trocado.
- **Não cite súmula, julgado ou doutrinador de memória.**
- Questões **inéditas**, nunca atribuídas a prova real: nada de "FGV 2023", "(Cebraspe/TJ-XX)" ou
  nome de concurso.
- **Varie a posição da alternativa correta** entre os materiais; não repita a mesma letra em
  sequência. Alterne também itens certos e errados no Cebraspe.
- Só uma alternativa pode estar correta. Evite "todas as anteriores", "nenhuma das anteriores" e
  alternativas absurdas.
- Não escreva a letra dentro do texto da alternativa ("A) ..."); a letra é a chave.

## Conferência antes de enviar

Antes de cada `POST /`:

1. Releia cada gabarito contra o `text` dos blocos recebidos, palavra por palavra nos pontos que a
   questão explora (prazos, números, sujeitos, verbos).
2. Confirme que cada alternativa errada está errada pelo texto, e não só "menos certa".
3. Confirme que o dispositivo de `legal_basis` está entre os blocos de `block_ids`.
4. Confirme que o resumo não afirma nada que os blocos não digam.

Se não conseguir sustentar um gabarito no texto, reescreva a questão.

## Rotas e formatos

Nos exemplos, `$BASE` é `$MEMORIZE_URL/api/v1/admin/study-materials`. No PowerShell, use
`curl.exe` e `$env:MEMORIZE_URL` / `$env:MEMORIZE_TOKEN`. Grave o JSON num arquivo temporário e
envie com `--data-binary @arquivo.json`, para não ter problema com aspas e acentos; apague o
arquivo depois.

### Listar leis

```bash
curl -s -H "Authorization: Bearer $MEMORIZE_TOKEN" -H "Accept: application/json" "$BASE/legislations"
```

```json
{ "legislations": [ { "uuid": "…", "title": "Constituição Federal de 1988", "total_blocks": 1840,
  "pending_blocks": 1650, "materials": { "draft": 12, "published": 6, "skipped": 1 } } ] }
```

### Andamento de uma lei

```bash
curl -s -H "Authorization: Bearer $MEMORIZE_TOKEN" -H "Accept: application/json" "$BASE/status?legislation=UUID_DA_LEI"
```

```json
{ "legislation": { "uuid": "…", "title": "…" }, "total_blocks": 1840, "pending_blocks": 1650,
  "complete": false, "materials": { "draft": 12, "published": 6, "skipped": 1 },
  "questions_by_board": { "cebraspe": 9, "fgv": 9, "fcc": 9, "vunesp": 9 } }
```

### Próximos blocos

```bash
curl -s -H "Authorization: Bearer $MEMORIZE_TOKEN" -H "Accept: application/json" "$BASE/next?legislation=UUID_DA_LEI"
```

```json
{ "legislation": { "uuid": "…", "title": "…" }, "total_blocks": 1840, "pending_blocks": 1650,
  "complete": false, "first_block_id": 5012, "previous_material_title": "Direitos sociais",
  "limits": { "material_min_blocks": 4, "material_target_blocks": 10, "material_max_blocks": 20, "skip_max_blocks": 40 },
  "blocks": [ { "id": 5012, "article_reference": "12", "structural_marker": "Art. 12",
    "segment_type": "caput", "heading_before": "CAPÍTULO III\nDA NACIONALIDADE", "text": "Art. 12. São brasileiros: …" } ] }
```

Quando a lei terminou: `{ "complete": true, "blocks": [], "message": "Lei completa: …" }`.

### Gravar um material

```bash
curl -s -X POST -H "Authorization: Bearer $MEMORIZE_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" --data-binary @material.json "$BASE"
```

```json
{
  "legislation": "UUID_DA_LEI",
  "first_block_id": 5012,
  "last_block_id": 5024,
  "block_ids": [5012, 5013, 5014, 5015, 5016, 5017, 5018, 5019, 5021, 5022, 5023, 5024],
  "title": "Nacionalidade: brasileiros natos e naturalizados",
  "summary": "## Quem é brasileiro nato\n\nO **art. 12, I** …",
  "generated_by_model": "nome-do-modelo-que-você-é",
  "skill_version": "1.0.0",
  "questions": [
    {
      "board": "cebraspe",
      "type": "true_false",
      "statement": "É brasileiro nato o nascido no estrangeiro, de pai brasileiro, …",
      "options": null,
      "correct_answer": "errado",
      "explanation": "Errado. O art. 12, I, b, exige que … O item troca …",
      "legal_basis": "CF, art. 12, I, b"
    },
    {
      "board": "fgv",
      "type": "multiple_choice",
      "statement": "Pierre, francês, reside no Brasil há … Nessa situação, é correto afirmar que",
      "options": {
        "A": "…",
        "B": "…",
        "C": "…",
        "D": "…",
        "E": "…"
      },
      "correct_answer": "D",
      "explanation": "D está correta porque … A erra ao … B erra ao … C erra ao … E erra ao …",
      "legal_basis": "CF, art. 12, II, b"
    }
  ]
}
```

- `first_block_id` é o `first_block_id` devolvido por `/next`; `last_block_id` é o último bloco
  do trecho que você escolheu.
- `block_ids` lista os blocos cobertos (no exemplo, o bloco 5020, revogado, ficou de fora).
- `generated_by_model` é o identificador do modelo que você é.

Resposta `201`: `{ "message": "…", "material": { "uuid": "…", "status": "draft", … }, "pending_blocks": 1637 }`.

### Pular um trecho

```bash
curl -s -X POST -H "Authorization: Bearer $MEMORIZE_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" --data-binary @pular.json "$BASE/skip"
```

```json
{
  "legislation": "UUID_DA_LEI",
  "first_block_id": 6200,
  "last_block_id": 6231,
  "skip_reason": "Disposições transitórias já esgotadas (ADCT, arts. 1º a 12)"
}
```

Resposta `201`: `{ "message": "Trecho marcado como pulado.", "material": { "status": "skipped", … }, "pending_blocks": 1605 }`.

## Erros comuns e como corrigir

- **"O trecho deve começar no primeiro bloco pendente"**: outro agente gravou antes, ou você
  reaproveitou blocos antigos. Chame `GET /next` de novo e refaça o trecho.
- **"O trecho se sobrepõe a outro já gravado"**: a mensagem diz o último bloco que o trecho pode
  ter. Termine nele ou antes.
- **"O trecho tem N blocos; o permitido é de 4 a 20"**: junte com o assunto vizinho ou divida.
- **"block_ids só pode ter blocos do trecho"**: há um id fora do intervalo; a mensagem lista
  quais.
- **Erro em `questions.N.*`**: `N` começa em 0. Ajuste banca, tipo, alternativas ou gabarito
  conforme a tabela de bancas.
