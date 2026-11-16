# Desafio por quadrado e Dia da prova (mapa beta)

Implementado em 16/11/2026 no mapa beta (só administradores). As regras em vigor estão no
`CLAUDE.md`, seção "Material de estudo"; este arquivo guarda o histórico da decisão.

## O que foi decidido (Ramon, 16/11/2026)

- O desafio do quadrado é o quinto passo dentro do quadrado (resumo maior e 6 questões).
- O Dia da prova fica onde estava o desafio da etapa e libera com os 4 quadrados concluídos.
- O Dia da prova é de qualquer usuário; o que será do Premium fica para depois.
- XP da prova = soma dos acertos. Errar não tira nada. Sem regra contra chute.
- Tempo: 15 s por certo/errado e 35 s por múltipla escolha. XP somado ao lado do relógio.
- Os materiais antigos serão apagados e refeitos pelo Ramon.

## O que ficou por conta da implementação (confirmar com o Ramon)

- XP por acerto na prova: 15 (o mesmo do desafio).
- Refazer a prova: cada questão dá XP uma vez só, no primeiro acerto.
- O desafio trava só o quadrado seguinte; material publicado depois não puxa ninguém de volta.
- O desafio do quadrado também ficou sem regra de assinante, já que a prova só tem questões dele.
- Na prova, o comentário de cada questão aparece só no resultado, para não gastar o tempo.

## O problema

O desafio da etapa junta os materiais de até 4 quadrados (160 blocos). Com a skill fazendo um
material a cada ~10 blocos, uma etapa chega a 11 a 16 materiais (resumo + até 4 questões cada),
cerca de 80 passos. A pessoa faz um material e o anel mal anda ("1/11").

## Como o mapa corta a lei (conferido no código)

- Fase = 10 blocos seguidos da lei (`BETA_BLOCKS_PER_PHASE`), contados do início da lei.
- Quadrado = 4 fases = 40 blocos. Etapa = até 4 quadrados = 160 blocos da mesma lei.
- A intercalação troca de lei a cada 16 fases, sempre em múltiplos de 16. Então o quadrado N de
  uma lei é sempre os blocos `40(N-1)+1` a `40N`, para qualquer usuário, quaisquer que sejam as
  leis escolhidas. Só a ordem entre as leis muda.
- Cuidado: se um bloco for inserido ou removido no meio da lei, todos os quadrados seguintes
  andam. O `content_hash` não pega isso (o texto dos blocos cobertos não muda).

## Consulta no banco local (16/11/2026)

Banco de desenvolvimento, não produção (o CPC da tela do celular não está nele). 50 materiais:

| Lei | Materiais | Blocos por material (mediana / máx.) | Materiais por quadrado | Atravessam quadrado |
| --- | --- | --- | --- | --- |
| CF/88 | 17 | 7 / 10 | 5, 5, 6, 1 | 3 |
| Improbidade | 20 | 13 / 16 | 3, 3, 3, 3, 3, 5 | 4 |
| Código Civil | 13 | 14 / 20 | 2, 3, 3, 3, 2 | 4 |

- De 2 a 6 materiais por quadrado: nunca 1, e varia.
- 11 dos 50 materiais começam num quadrado e terminam no outro.
- Média de 2 questões por material nesse banco (a skill pede 4; provavelmente versões antigas).

## Ideias do Ramon

1. **Um desafio por quadrado:** um resumo (maior) e 6 questões sobre os 40 blocos do quadrado.
2. **Dia da prova** no lugar do desafio da etapa (a fase paralela a cada 4 quadrados, onde está hoje):
   - Só questões já vistas nos quadrados da etapa. Sem resumo.
   - Cronômetro total = 25 s × número de questões (ex.: 24 questões = 10 min).
   - Errar não tira vida nem trava: a pessoa segue até acabar o tempo ou as questões.
   - XP proporcional a quanto ela avança: mais questões na prova, mais XP possível.
   - A barra de progresso mostra o XP acumulado na prova.

## Análise

### Ajuste fino da skill (um material por quadrado)

Viável, porque os quadrados são os mesmos para todo mundo. Mudanças:

- `/next` devolve só os blocos pendentes até o fim do quadrado, e o site recusa trecho que
  atravesse o quadrado. O agente não precisa saber de quadrado: o limite chega pronto.
- `MAX_BLOCKS` sobe de 20 para 40 e `MAX_QUESTIONS` de 4 para 6. Resumo de 500-900 para
  algo como 1000-1600 palavras, dividido por tema.
- O pulo vira "quadrado sem matéria" (quadrado inteiro revogado): o desafio fica `empty`.
- Fim da lei: o último quadrado pode ter menos de 40 blocos; vale igual.

Custos:

- Cobertura cai: hoje são até 16 questões por 40 blocos; passa a 6. Para o Dia da prova, uma
  etapa teria até 24 questões.
- 40 blocos às vezes misturam assuntos sem relação; o resumo vira uma lista de temas.
- Materiais já gravados (produção, CPC incluso) foram feitos com o corte antigo. Ou ficam como
  estão (um quadrado pode ter vários até serem refeitos), ou são apagados e refeitos.

### Dia da prova

- Cronômetro tem de ser do servidor: grava a hora de início e recusa resposta depois do prazo.
- 25 s é pouco para múltipla escolha com enunciado longo (FCC/FGV) e folgado para certo/errado.
  Alternativa: 20 s por certo/errado e 35 s por múltipla escolha, média perto de 25 s.
- XP por questão **respondida** deixa chutar tudo depressa (certo/errado acerta 50% no chute).
  Melhor XP por **acerto**, sem tirar nada no erro.
- Refazer a prova não pode virar fonte infinita de XP: dar XP só quando bate o próprio recorde
  (a diferença), ou uma vez por dia.
- Gabarito continua só depois de cada resposta.

## Decisões em aberto na época (resolvidas acima)

- O desafio do quadrado é um quinto passo dentro do quadrado ou fica ao lado, como hoje?
- O Dia da prova libera quando as fases da etapa acabam ou só depois dos desafios dos quadrados?
- XP por acerto e regra de refazer (recorde ou uma vez por dia).
- Materiais antigos: manter ou refazer.
