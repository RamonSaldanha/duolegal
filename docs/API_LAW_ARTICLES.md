# API de Cadastro de Artigos de Legislação

## Endpoint Principal

### POST `/api/law-articles`

Cadastra um novo artigo de legislação no sistema.

## Estrutura JSON Necessária

### Exemplo Completo

```json
{
  "legal_reference_id": 1,
  "original_content": "Art. 5º Todos são iguais perante a lei, sem distinção de qualquer natureza, garantindo-se aos brasileiros e aos estrangeiros residentes no País a inviolabilidade do direito à vida, à liberdade, à igualdade, à segurança e à propriedade.",
  "practice_content": "Art. 5º Todos são _____ perante a lei, sem distinção de qualquer natureza, garantindo-se aos brasileiros e aos estrangeiros residentes no País a inviolabilidade do direito à vida, à _____, à igualdade, à segurança e à propriedade.",
  "article_reference": "Art. 5º",
  "difficulty_level": 2,
  "position": 10,
  "is_active": true,
  "selected_words": [
    {
      "word": "iguais",
      "position": 3,
      "gap_order": 1
    },
    {
      "word": "liberdade",
      "position": 27,
      "gap_order": 2
    }
  ],
  "custom_options": [
    {
      "word": "diferentes"
    },
    {
      "word": "desiguais"
    },
    {
      "word": "escravidão"
    },
    {
      "word": "prisão"
    }
  ]
}
```

### Exemplo Mínimo

```json
{
  "legal_reference_id": 1,
  "original_content": "Art. 1º A República Federativa do Brasil é formada pela união indissolúvel dos Estados e Municípios.",
  "practice_content": "Art. 1º A República _____ do Brasil é formada pela união indissolúvel dos Estados e Municípios.",
  "selected_words": [
    {
      "word": "Federativa",
      "position": 4
    }
  ]
}
```

## Campos Obrigatórios

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `legal_reference_id` | integer | ID da referência legal (Constituição, Código Civil, etc.) |
| `original_content` | string | Texto original completo do artigo |
| `practice_content` | string | Texto com lacunas (usando _____ onde ficam as lacunas) |
| `selected_words` | array | Palavras que foram removidas do texto original |
| `selected_words[].word` | string | A palavra que foi removida |
| `selected_words[].position` | integer | Posição da palavra no texto original (contagem de palavras) |

## Campos Opcionais

| Campo | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `article_reference` | string | null | Referência do artigo (ex: "Art. 5º") |
| `difficulty_level` | integer | 1 | Nível de dificuldade (1-5) |
| `position` | integer | auto | Posição na sequência de aprendizado |
| `is_active` | boolean | true | Se o artigo está ativo |
| `selected_words[].gap_order` | integer | auto | Ordem da lacuna no exercício |
| `custom_options` | array | [] | Opções incorretas/distratoras |
| `custom_options[].word` | string | - | Palavra que será opção incorreta |

## Resposta de Sucesso (201)

```json
{
  "success": true,
  "message": "Artigo de legislação cadastrado com sucesso.",
  "data": {
    "article": {
      "uuid": "123e4567-e89b-12d3-a456-426614174000",
      "legal_reference_id": 1,
      "original_content": "Art. 5º Todos são iguais perante a lei...",
      "practice_content": "Art. 5º Todos são _____ perante a lei...",
      "article_reference": "Art. 5º",
      "difficulty_level": 2,
      "position": 10,
      "is_active": true,
      "legal_reference": {
        "uuid": "legal-ref-uuid",
        "name": "Constituição Federal",
        "type": "law"
      },
      "options": [
        {
          "uuid": "option-uuid-1",
          "word": "iguais",
          "is_correct": true,
          "position": 3,
          "gap_order": 1
        },
        {
          "uuid": "option-uuid-2", 
          "word": "diferentes",
          "is_correct": false,
          "position": null,
          "gap_order": null
        }
      ],
      "created_at": "2025-07-13T15:30:00.000000Z",
      "updated_at": "2025-07-13T15:30:00.000000Z"
    }
  }
}
```

## Resposta de Erro de Validação (422)

```json
{
  "success": false,
  "message": "Dados inválidos fornecidos.",
  "errors": {
    "legal_reference_id": [
      "O campo legal reference id é obrigatório."
    ],
    "original_content": [
      "O campo original content é obrigatório."
    ]
  }
}
```

## Resposta de Erro Interno (500)

```json
{
  "success": false,
  "message": "Erro interno do servidor ao cadastrar o artigo.",
  "error": "Mensagem de erro detalhada (apenas em modo debug)"
}
```

## Endpoint Auxiliar

### GET `/api/law-articles/legal-references`

Retorna todas as referências legais disponíveis para usar no `legal_reference_id`.

### Resposta

```json
{
  "success": true,
  "data": {
    "legal_references": [
      {
        "id": 1,
        "uuid": "cf-uuid",
        "name": "Constituição Federal",
        "description": "Constituição da República Federativa do Brasil de 1988",
        "type": "law",
        "is_active": true
      },
      {
        "id": 2,
        "uuid": "cc-uuid",
        "name": "Código Civil",
        "description": "Lei nº 10.406 de 2002",
        "type": "law",
        "is_active": true
      }
    ]
  }
}
```

## Como Funciona o Sistema de Lacunas

1. **Texto Original**: Texto completo do artigo
2. **Texto de Prática**: Mesmo texto, mas com `_____` substituindo as palavras que serão lacunas
3. **Palavras Selecionadas**: Array com as palavras que foram removidas e suas posições
4. **Opções Personalizadas**: Palavras incorretas que serão apresentadas como alternativas

## Exemplo de Uso com cURL

```bash
curl -X POST http://localhost:8000/api/law-articles \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "legal_reference_id": 1,
    "original_content": "Art. 5º Todos são iguais perante a lei.",
    "practice_content": "Art. 5º Todos são _____ perante a lei.",
    "article_reference": "Art. 5º",
    "selected_words": [
      {
        "word": "iguais", 
        "position": 3
      }
    ],
    "custom_options": [
      {"word": "diferentes"},
      {"word": "desiguais"}
    ]
  }'
```
