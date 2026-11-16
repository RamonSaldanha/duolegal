---
name: gerar-material-estudo
description: Gera material de estudo (um resumo em markdown e até seis questões inéditas e difíceis no estilo Cebraspe, FGV, FCC ou Vunesp) para os dispositivos de uma lei do Memorize Direito e grava no site pelas rotas REST do admin. Use quando o usuário pedir, por exemplo, "faça questões e resumos para a CF88", "gere material de estudo para o Código Penal" ou "continue o material da Constituição". Não use para criar ou editar lacunas, blocos ou o texto das leis.
---

# Gerar material de estudo

Versão da skill: **3.1.0** (envie este valor em `skill_version`).

Você vai ler os dispositivos de uma lei, um quadrado do mapa por vez (até 40 blocos), escrever
para cada quadrado um resumo e até seis questões, e gravar tudo no site. No jogo, o material é o
desafio que fecha o quadrado: o aluno memoriza os dispositivos nas fases de lacunas e depois lê o
resumo e responde as questões. Como ele já sabe a letra da lei, o resumo não a repete: explica a
teoria por trás dela e mostra a regra funcionando em casos. As mesmas questões voltam depois numa prova com tempo (o Dia da
prova). O que você grava entra **publicado** e aparece no mapa na hora, então cada material
precisa estar pronto para o aluno.
Se o usuário pedir para revisar antes de publicar, envie `"status": "draft"` em cada material. Você pode parar a qualquer momento: da próxima vez,
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
5. **Ler o trecho inteiro** e decidir o que ele cobre (regras abaixo).
6. **Escolher os temas** do resumo (seção "Resumo"); se tiver ferramenta de pesquisa na web,
   conferir a jurisprudência desses temas. **Gerar** o resumo e as questões, ou
   decidir pular o trecho.
7. **Conferir** cada gabarito contra o texto recebido.
8. **Gravar:** `POST /` (material) ou `POST /skip` (trecho pulado).
9. **Repetir** a partir do passo 4. Atualize de cabeça a contagem de bancas a cada material; a
   cada 10 materiais, chame `GET /status` de novo.

Trabalhe um trecho por vez: só busque o próximo depois que o atual foi gravado.

Ao parar, por qualquer motivo, informe: quantos materiais foram gravados, quantos trechos foram
pulados (com os motivos) e quantos blocos ainda faltam (`pending_blocks` da última resposta).

## O trecho é o quadrado

`GET /next` devolve o próximo quadrado do mapa: até 40 blocos pendentes em sequência. Cada bloco é
um dispositivo (ou um grupo pequeno deles) com `id`, `article_reference`, `structural_marker`,
`segment_type`, `heading_before` (título, capítulo ou seção que aparece logo antes dele, quando
houver) e `text`.

**Você não escolhe onde o trecho começa nem onde termina.** O trecho é exatamente o que veio: do
`first_block_id` ao `last_block_id` da resposta. O site recusa qualquer outro intervalo, porque
cada quadrado do mapa tem um único material. O corte segue o mapa, não o assunto, então o trecho
pode começar ou terminar no meio de um capítulo, ou até de um artigo longo.

- **Títulos, capítulos e seções** podem vir em `heading_before` ou, o que é mais comum, no fim
  do `text` do bloco anterior (o art. 5º termina com "TÍTULO II / Competência Tributária"). Um
  cabeçalho no fim de um bloco anuncia o assunto do bloco seguinte, não o daquele.
- **Trecho com mais de um assunto:** os temas do resumo saem dos vários assuntos, e
  as questões se distribuem entre eles.
- **Trecho que começa ou termina no meio de um artigo:** trate só dos blocos recebidos. Não
  adivinhe o texto que vem antes ou depois, e não faça questão sobre regra que depende de um
  dispositivo que não veio.
- **Deixe fora de `block_ids`** os blocos do trecho que não têm matéria: revogados, vetados,
  meramente remissivos ou o cabeçalho da lei (ementa e preâmbulo, que costumam ser o bloco 1). Eles continuam dentro do intervalo, só não são cobertos pelo material.
- **Pule o trecho** (`POST /skip`), com um motivo curto, só quando o quadrado inteiro não tiver
  matéria para questão: disposições transitórias já esgotadas, sequência de dispositivos
  revogados. O pulo também vai do `first_block_id` ao `last_block_id` recebidos.
- O último quadrado da lei pode ter poucos blocos; vale igual. O trecho também fica menor que o
  quadrado quando parte dele já tem material antigo.

`previous_material_title` é o título do material anterior: use-o para manter a continuidade e não
repetir o mesmo título.

## Resumo

Campo `summary`, em **markdown**, com **1000 a 1600 palavras** para um quadrado cheio (40 blocos);
trecho menor pede menos, na mesma proporção, com pelo menos 400. O limite do site é de 20000
caracteres. Sem HTML (o site remove).

### O que o resumo é

Quando chega ao resumo, o aluno acabou de memorizar a letra de cada dispositivo do quadrado nas
fases de lacunas. Agora ele quer **entender**: o que é aquele instituto, para que ele existe,
como funciona na prática. O resumo é uma **aula escrita para ser lida**, em texto corrido, como
um bom professor explicando a matéria. Não é ficha de revisão, não é lista de pegadinhas.

- **Não repita a lei.** Cite o dispositivo pelo número quando ajudar o aluno a se localizar e
  siga para a explicação. Nada de listar incisos, transcrever ou parafrasear artigo por artigo.
  Teste de cada parágrafo: se ele pudesse ser trocado pelo texto do artigo sem perda, reescreva.
- **Não fale do quadrado nem do estudo.** Nada de "neste trecho estudamos os arts. 31 a 37",
  "vimos que", "neste material". O resumo é um texto sobre o **assunto**, com título e abertura
  próprios, como um capítulo de livro.
- **Não dê o mesmo peso a tudo.** Escolha os 2 a 4 temas que mais precisam de explicação
  (os que têm conceito, lógica, exceções ou institutos que se confundem) e dê a eles quase todo
  o espaço. O que é simples entra numa frase, dentro do tema a que pertence, ou fica de fora.

### A ordem dentro de cada tema

Cada tema segue a ordem natural de uma aula:

1. **O que é.** Comece pelo conceito, em linguagem simples, como se o aluno nunca tivesse ouvido
   falar. A palavra comum vem antes do nome técnico, e todo termo técnico é explicado na
   primeira vez em que aparece. Nunca abra um tema com caso concreto, tabela ou lista: o aluno
   precisa saber do que se trata antes de ver a regra funcionando.
2. **Por que existe.** A razão de ser: que problema a regra resolve, o que ela protege, que
   tensão ela equilibra. É o que faz o aluno lembrar da regra e resolver o caso que nunca viu.
3. **Como funciona.** As regras do trecho explicadas pela lógica delas, com as exceções e o que
   as justifica. Aqui entram a natureza e a classificação, quando ajudam a entender (rol
   taxativo, julgamento político ou técnico, competência privativa ou concorrente).
4. **O caso concreto**, só **depois** da explicação, para mostrar a regra funcionando. Um
   personagem com nome, uma situação banal, o desfecho e o motivo. Quando houver exceção, mude
   um fato do mesmo caso e mostre o que muda no resultado. Escreva os casos em citação markdown
   (`> **Caso.** ...`). Dois casos por tema bastam; no máximo três.

### Perguntas que puxam o raciocínio

Use, com moderação, perguntas que o próprio texto responde logo em seguida, como faz o professor
em sala: "Mas por que a Constituição exige dois terços, e não maioria simples?", "E se o
Estado simplesmente se recusar a cumprir a decisão?". Elas abrem um parágrafo, despertam a
dúvida certa e preparam a explicação. Uma ou duas por tema, nunca uma lista de perguntas, e
nunca pergunta sem resposta.

### Doutrina

A explicação teórica é o centro do resumo: conceito, natureza, razão de ser e a diferença para o
instituto vizinho. Use só doutrina **pacífica**, a que qualquer manual traz. Fica fora:
história, direito comparado, corrente minoritária, classificação que nunca virou alternativa,
filosofia. Não cite nome de autor, salvo quando o próprio nome é cobrado ("escada ponteana"). Se
não tiver certeza de um conceito, deixe-o de fora.

O conceito pode depender de um dispositivo que está fora do trecho. Explique o conceito mesmo
assim, mas só escreva o número de um artigo que não veio nos blocos se o confirmou na fonte.

### Jurisprudência: só confirmada

Súmula, tese ou julgado **só entra se você tiver ferramenta de pesquisa na web e confirmar o
conteúdo e o número em fonte oficial** (site do tribunal, planalto.gov.br) nesta sessão. Faça
poucas buscas, dirigidas aos temas escolhidos. Entra no próprio tema, em texto corrido, no ponto
em que muda a leitura do dispositivo.

- Confirmou conteúdo e número: escreva em linguagem direta.
- Confirmou o conteúdo, mas não o número: descreva sem numerar ("o STF decidiu que...").
- Não confirmou, ou não tem ferramenta de pesquisa: **não escreva**. Nunca cite de memória.

Jurisprudência fica só no resumo. As questões continuam com gabarito sustentado pelo texto dos
blocos (ver "Regras de qualidade").

### Tabelas

Tabela entra **no meio da explicação**, quando o aluno já entendeu os elementos e ela organiza
uma comparação (dois ou três institutos lado a lado, os modos de uma mesma figura). Nunca na
abertura, nunca como lista de artigos, e no máximo duas por resumo. Células curtas, para caber
no celular. O raciocínio fica no texto, não na tabela.

### Estrutura do resumo

1. **Abertura** (sem subtítulo, um ou dois parágrafos): situa o assunto no direito, não no
   material. Diga qual é o grande tema e por que ele importa para entender o resto (por
   exemplo: numa federação, cada ente tem autonomia; o que acontece quando um deles abusa
   dela?). Nada de lista do que vem a seguir, nada de número de artigo, nada de tabela.
2. **Um `##` por tema**, com título que diz o assunto ("Intervenção federal: quando a União
   suspende a autonomia de um Estado"), seguindo a ordem acima: o que é, por que existe, como
   funciona, caso.
3. **Fecho** (sem subtítulo ou com `## Em resumo`): um parágrafo curto que amarra os temas e
   aponta a confusão mais comum do assunto.

Sem seção de pegadinhas, de "como a banca cobra" ou de roteiro: o espaço vai para explicar.
O resumo não tem questões; elas vêm depois, no campo próprio.

### Trecho-modelo

Um tema no padrão esperado (o trecho era o começo do Estatuto da Advocacia). Use para calibrar o
tom e a ordem, não para copiar frases.

```markdown
## Nulidade: quando o ato do advogado não vale

Um ato **nulo** é um ato que o direito trata como se não produzisse efeito: a petição existe no
papel, mas o processo não pode se apoiar nela. O Estatuto usa essa consequência para proteger a
própria ideia de advocacia como atividade reservada. Se qualquer pessoa pudesse peticionar em
nome de outra, a exigência de inscrição na OAB seria só uma formalidade.

Por isso a regra básica é direta: o ato privativo de advogado praticado por quem não é inscrito
é nulo, e a nulidade não apaga as outras consequências (a pessoa ainda responde nas esferas
civil, penal e administrativa).

Mas e o advogado inscrito que, por algum motivo, não podia atuar naquele momento? O Estatuto
estende a nulidade a ele, e aqui entra uma distinção que organiza todo o tema: a diferença entre
**impedimento** e **incompatibilidade**. Impedimento é uma proibição **parcial**: o advogado
continua advogando, menos em certo campo (o servidor público, por exemplo, contra o ente que o
remunera). Incompatibilidade é uma proibição **total**: enquanto exerce certa função, a pessoa
não advoga em causa nenhuma. Suspensão e licença também afastam o advogado por inteiro.

A nulidade acompanha o tamanho da proibição. Por isso só o impedido tem a ressalva "no âmbito
do impedimento": fora daquele campo ele está plenamente habilitado, e não haveria razão para
anular o que fez.

> **Caso.** Bruno é analista da prefeitura de Aurora e advogado. No mesmo dia, assina uma ação de
> cobrança contra o Município de Aurora e uma ação de divórcio. Só a primeira é nula: o
> impedimento dele alcança apenas as causas contra o ente que o remunera.

> **Se mudar um fato.** Bruno toma posse como delegado de polícia e assina outro divórcio. Agora
> o ato é nulo, embora a causa seja igual à anterior, porque a função de delegado é incompatível
> com a advocacia e a proibição passou a ser total.
```

Repare na ordem: primeiro o conceito (o que é nulidade), depois a razão (proteger a atividade
reservada), depois a distinção que explica a regra, e só então os casos.

## Estilo do texto

Vale para o resumo, os enunciados, as alternativas e os comentários.

- **Não use travessão (—), meia-risca (–) nem hífen para intercalar ou explicar.** Intercale com
  vírgulas ou parênteses; para introduzir uma explicação ou lista, use dois-pontos.
  - Errado: "O prazo — que é decadencial — conta da posse."
  - Certo: "O prazo, que é decadencial, conta da posse." ou "O prazo (decadencial) conta da posse."
- Hífen só onde a ortografia exige ("vice-presidente", "decreto-lei", "dar-se-á") ou numa
  referência legal ("art. 45-A").
- Em listas markdown, o marcador é `-` no começo da linha; dentro do item, a mesma regra vale.

`title`: curto e específico, com até 80 caracteres ("Nacionalidade: brasileiros natos e
naturalizados"). Sem o nome da lei e sem numeração de material.

## Questões

**Seis questões por material**, o máximo que o site aceita: uma de cada banca (Cebraspe, FGV, FCC
e Vunesp) e mais duas das bancas com menor contagem em `questions_by_board`. Só faça menos quando
o trecho não tiver matéria para seis questões que não se repitam (trecho curto, com poucos
dispositivos); nesse caso, faça quantas couberem, duas no mínimo, começando pelas bancas com menor
contagem. Cada questão cobra um ponto diferente, e juntas elas cobrem o trecho inteiro, não só o
começo dele.

As questões voltam no Dia da prova com tempo contado (15 segundos por item de certo/errado, 35 por
questão de múltipla escolha). Escreva enunciados e alternativas enxutos: sem contexto que não
decide a resposta.

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

## Dificuldade

O padrão é de prova de carreira (magistratura, Ministério Público, procuradorias, tribunais), não
de prova de nível médio. Questão que se resolve lendo só o caput, ou que tem a resposta óbvia
para quem nunca leu a lei, está fácil demais: reescreva.

- **Cobre o detalhe, não a ideia geral:** exceções, ressalvas ("salvo", "exceto", "ressalvado"),
  parágrafos e incisos, prazos, quóruns, legitimados, competência, a consequência exata de cada
  hipótese.
- **Combine dispositivos:** pelo menos três questões por material exigem ligar dois ou mais
  dispositivos do trecho (o caput com o parágrafo que o excepciona, dois incisos que se
  confundem, a regra geral e a especial).
- **Distratores plausíveis:** cada alternativa errada é algo que um aluno que leu a lei por cima
  marcaria. Troque um único elemento do texto (prazo, sujeito, verbo, "poderá" por "deverá",
  "ou" por "e", "maioria absoluta" por "dois terços"), use a regra de um dispositivo vizinho ou
  aplique a regra geral onde vale a exceção. Nada de alternativa absurda ou fora do assunto.
- **Casos concretos com mais de um fato relevante** (FGV e Vunesp): o aluno precisa enquadrar a
  situação antes de aplicar a regra, e um dos fatos pode ser irrelevante de propósito.
- **Cebraspe:** o item erra por um detalhe, nunca pela ideia toda; quando certo, use a redação
  menos óbvia do dispositivo (paráfrase fiel, não cópia literal).
- **FCC:** literalidade, mas com as cinco alternativas parecidas com o texto da lei, diferindo
  em uma palavra ou expressão.
- Prefira enunciados que pedem a alternativa **incorreta** ou que combinam afirmações (I, II e III)
  em parte das questões de múltipla escolha, como as bancas fazem.

Antes de gravar, pergunte para cada questão: um aluno que só leu o resumo por cima acertaria?
Se sim, aumente a dificuldade.

## Regras de qualidade

- Toda questão é **respondível só com os dispositivos cobertos** e traz em `legal_basis` o
  dispositivo que fundamenta o gabarito ("CF, art. 12, I, a").
- O comentário (`explanation`) explica por que a correta está certa **e por que cada errada está
  errada**. Em certo/errado, aponta o termo trocado.
- **Nas questões, nada de súmula, julgado ou doutrina:** o gabarito se sustenta só no texto dos
  blocos, mesmo quando o resumo trouxe jurisprudência confirmada. A questão pode (e deve) exigir
  o raciocínio que o resumo ensinou, aplicado a um caso novo.
- Questões **inéditas**, nunca atribuídas a prova real: nada de "FGV 2023", "(Cebraspe/TJ-XX)" ou
  nome de concurso.
- **Varie a posição da alternativa correta** entre os materiais; não repita a mesma letra em
  sequência. Alterne também itens certos e errados no Cebraspe.
- Só uma alternativa pode estar correta. Evite "todas as anteriores", "nenhuma das anteriores" e
  alternativas absurdas.
- Não escreva a letra dentro do texto da alternativa ("A) ..."); a letra é a chave.

## Conferência antes de enviar

Antes de cada `POST /` (o material vai direto para o mapa):

1. Releia cada gabarito contra o `text` dos blocos recebidos, palavra por palavra nos pontos que a
   questão explora (prazos, números, sujeitos, verbos).
2. Confirme que cada alternativa errada está errada pelo texto, e não só "menos certa".
3. Confirme que o dispositivo de `legal_basis` está entre os blocos de `block_ids`.
4. Releia o resumo parágrafo por parágrafo: o que puder ser trocado pelo texto do artigo sem
   perda sai ou é reescrito. Confirme que não há lista de incisos copiada, que cada ponto do
   tema segue a ordem conceito, razão de ser, funcionamento e caso, que nenhum tema abre com caso
   ou tabela, e que a abertura fala do assunto, não do material nem de números de artigo.
5. Confira cada caso: a resposta decorre do texto dos blocos e todo fato de que ela depende está
   na narrativa. Caso com resposta errada é o pior defeito do material.
6. Confira o que não veio dos blocos: a doutrina é pacífica, nenhum número de artigo de fora do
   trecho foi escrito sem confirmação, e toda súmula ou julgado foi confirmado em fonte oficial
   nesta sessão. O que não foi confirmado sai.
7. Procure "—", "–" e " - " no resumo, nos enunciados, nas alternativas e nos comentários, e
   reescreva com vírgulas, parênteses ou dois-pontos.
8. Confira a dificuldade (seção acima).

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
  "last_block_id": 5051, "limits": { "min_questions": 1, "max_questions": 6 },
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
  "last_block_id": 5051,
  "block_ids": [5012, 5013, 5014, 5015, 5016, 5017, 5018, 5019, 5021, 5022, 5023, 5024, "… até 5051"],
  "title": "Nacionalidade: brasileiros natos e naturalizados",
  "summary": "Todo Estado precisa dizer quem são os seus nacionais …\n\n## Brasileiro nato: o sangue e o solo\n\n…",
  "generated_by_model": "nome-do-modelo-que-você-é",
  "skill_version": "3.1.0",
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

- `first_block_id` e `last_block_id` são os devolvidos por `/next`, sem mudar.
- `block_ids` lista os ids (números) de todos os blocos cobertos. No exemplo a lista está
  abreviada e o bloco 5020, revogado, ficou de fora.
- `generated_by_model` é o identificador do modelo que você é.

- `status` é opcional: o padrão é `"published"`. Mande `"draft"` só se o usuário pedir para
  revisar antes de publicar.
- O exemplo mostra duas questões para ficar curto; o material normal tem seis.

Resposta `201`: `{ "message": "…", "material": { "uuid": "…", "status": "published", … }, "pending_blocks": 1637 }`.

### Pular um trecho

```bash
curl -s -X POST -H "Authorization: Bearer $MEMORIZE_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" --data-binary @pular.json "$BASE/skip"
```

```json
{
  "legislation": "UUID_DA_LEI",
  "first_block_id": 6200,
  "last_block_id": 6239,
  "skip_reason": "Disposições transitórias já esgotadas (ADCT, arts. 1º a 12)"
}
```

Resposta `201`: `{ "message": "Trecho marcado como pulado.", "material": { "status": "skipped", … }, "pending_blocks": 1605 }`.

## Erros comuns e como corrigir

- **"O trecho deve começar no primeiro bloco pendente"**: outro agente gravou antes, ou você
  reaproveitou blocos antigos. Chame `GET /next` de novo e refaça o trecho.
- **"O trecho deve terminar no bloco N"**: o trecho é o quadrado inteiro. Use o `last_block_id`
  devolvido por `GET /next`; o que não tem matéria fica fora de `block_ids`, não fora do trecho.
- **"O material precisa de 1 a 6 questões"**: mande no máximo seis.
- **"block_ids só pode ter blocos do trecho"**: há um id fora do intervalo; a mensagem lista
  quais.
- **Erro em `questions.N.*`**: `N` começa em 0. Ajuste banca, tipo, alternativas ou gabarito
  conforme a tabela de bancas.
