<?php

namespace App\Services;

class LegalVocabulary
{
    /**
     * Palavras jurídicas para uso como distratores.
     * Portado do workflow N8N (PALAVRAS_JURIDICAS).
     */
    public const WORDS = [
        // Conceitos fundamentais
        'direito', 'dever', 'obrigação', 'faculdade', 'prerrogativa', 'competência',
        'jurisdição', 'soberania', 'cidadania', 'nacionalidade', 'personalidade',

        // Direito Constitucional
        'constituição', 'emenda', 'revisão', 'princípio', 'norma', 'dispositivo',
        'artigo', 'parágrafo', 'inciso', 'alínea', 'república', 'federação',
        'estado', 'município', 'união', 'distrito', 'território', 'autonomia',

        // Poderes e Órgãos
        'executivo', 'legislativo', 'judiciário', 'presidente', 'governador',
        'prefeito', 'senado', 'câmara', 'assembleia', 'tribunal', 'ministério',
        'procuradoria', 'defensoria', 'advocacia', 'magistratura',

        // Direitos Fundamentais
        'liberdade', 'igualdade', 'fraternidade', 'dignidade', 'vida', 'honra',
        'intimidade', 'privacidade', 'imagem', 'propriedade', 'herança',
        'manifestação', 'expressão', 'comunicação', 'informação', 'petição',

        // Processo e Procedimento
        'processo', 'procedimento', 'ação', 'recurso', 'apelação', 'embargos',
        'habeas', 'mandado', 'liminar', 'cautelar', 'antecipação', 'execução',
        'citação', 'intimação', 'notificação', 'audiência', 'sentença', 'acórdão',

        // Direito Civil
        'pessoa', 'capacidade', 'domicílio', 'residência', 'ausência', 'morte',
        'nascimento', 'filiação', 'parentesco', 'tutela', 'curatela', 'casamento',
        'divórcio', 'sucessão', 'testamento', 'legado',

        // Direito Penal
        'crime', 'contravenção', 'delito', 'pena', 'multa', 'reclusão', 'detenção',
        'prisão', 'fiança', 'sursis', 'livramento', 'reincidência',
        'antecedentes', 'atenuantes', 'agravantes', 'excludentes', 'culpabilidade',

        // Direito Administrativo
        'administração', 'servidor', 'funcionário', 'cargo', 'função', 'emprego',
        'concurso', 'nomeação', 'posse', 'exercício', 'licença', 'afastamento',
        'aposentadoria', 'pensão', 'licitação', 'contrato', 'convênio', 'termo',

        // Direito Tributário
        'tributo', 'imposto', 'taxa', 'contribuição', 'empréstimo', 'receita',
        'arrecadação', 'lançamento', 'cobrança', 'parcelamento',
        'anistia', 'remissão', 'transação', 'compensação', 'restituição',

        // Direito Trabalhista
        'trabalho', 'empregado', 'empregador', 'salário', 'remuneração',
        'jornada', 'descanso', 'férias', 'décimo', 'fundo', 'garantia', 'aviso',
        'rescisão', 'demissão', 'sindicato', 'greve',

        // Termos Processuais
        'inicial', 'contestação', 'reconvenção', 'exceção', 'impugnação',
        'agravo', 'revista', 'especial', 'extraordinário', 'rescisória', 'monitória',
        'possessória', 'reivindicatória', 'declaratória', 'condenatória',

        // Adjetivos e Advérbios Jurídicos
        'legal', 'ilegal', 'legítimo', 'ilegítimo', 'lícito', 'ilícito', 'válido',
        'inválido', 'nulo', 'anulável', 'inexistente', 'eficaz', 'ineficaz',
        'público', 'privado', 'individual', 'coletivo', 'geral',
        'ordinário', 'comum', 'federal', 'estadual',
        'municipal', 'nacional', 'internacional', 'interno', 'externo', 'anterior',
        'posterior', 'simultâneo', 'sucessivo', 'definitivo', 'provisório',

        // Conectivos e Preposições Jurídicas
        'mediante', 'perante', 'conforme', 'segundo', 'salvo', 'exceto', 'ressalvado',
        'observado', 'respeitado', 'garantido', 'assegurado', 'vedado', 'proibido',
        'permitido', 'autorizado', 'facultado', 'obrigatório', 'necessário',

        // Verbos Jurídicos
        'aplicar', 'executar', 'cumprir', 'observar', 'respeitar', 'garantir',
        'assegurar', 'proteger', 'defender', 'tutelar', 'preservar', 'manter',
        'estabelecer', 'determinar', 'fixar', 'definir', 'regular', 'disciplinar',

        // Direito Ambiental
        'ambiente', 'sustentabilidade', 'poluição', 'desmatamento', 'licenciamento',
        'preservação', 'conservação', 'degradação', 'fauna', 'flora', 'ecossistema',

        // Direito do Consumidor
        'consumidor', 'fornecedor', 'produto', 'propaganda', 'publicidade',
        'defeito', 'vício', 'indenização', 'abusividade', 'vulnerabilidade',

        // Direito Eleitoral
        'eleição', 'voto', 'sufrágio', 'candidato', 'partido', 'coligação',
        'urna', 'apuração', 'diplomação', 'mandato', 'reeleição', 'plebiscito',

        // Direito Internacional
        'tratado', 'convenção', 'protocolo', 'ratificação',
        'extradição', 'asilo', 'refúgio', 'diplomacia', 'fronteira',

        // Direito Empresarial
        'empresa', 'sociedade', 'sócio', 'capital', 'patrimônio', 'falência',
        'concordata', 'registro', 'marca', 'patente', 'franquia',

        // Princípios e Institutos Jurídicos
        'inviolabilidade', 'irretroatividade', 'imprescritibilidade', 'imunidade',
        'investidura', 'deliberação', 'promulgação', 'sanção',
        'decreto', 'portaria', 'resolução', 'instrução', 'regulamento',
        'jurisprudência', 'doutrina', 'costume', 'analogia', 'equidade',
        'moralidade', 'impessoalidade', 'eficiência', 'legalidade',
        'proporcionalidade', 'razoabilidade', 'subsidiariedade', 'tipicidade',

        // Direitos Sociais
        'educação', 'saúde', 'moradia', 'lazer', 'previdência',
        'assistência', 'alimentação', 'transporte', 'saneamento',
    ];

    /**
     * Palavras excluídas da seleção de lacunas (stop words).
     * Portado do workflow N8N (palavrasExcluidas).
     */
    public const STOP_WORDS = [
        // Artigos
        'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas',
        // Preposições simples
        'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas',
        'por', 'para', 'com', 'sem', 'sob', 'sobre', 'ante', 'após', 'até',
        'ao', 'aos', 'à', 'às',
        // Conjunções simples
        'e', 'ou', 'mas', 'porém', 'contudo', 'entretanto',
        // Pronomes
        'que', 'se', 'como', 'quando', 'onde', 'qual', 'quais',
        // Verbos comuns
        'é', 'são', 'foi', 'foram', 'será', 'serão', 'tem', 'têm',
        // Demonstrativos
        'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas',
        'aquele', 'aquela', 'aqueles', 'aquelas', 'seu', 'sua', 'seus', 'suas',
        // Numerais romanos
        'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x',
        'xi', 'xii', 'xiii', 'xiv', 'xv', 'xvi', 'xvii', 'xviii', 'xix', 'xx',
        // Termos estruturais
        'art', 'artigo', '§', 'paragrafo', '[...]',
        // Outros
        'não', 'mais', 'também', 'já', 'ainda', 'mesmo', 'só', 'apenas',
    ];

    /**
     * Substantivos jurídicos de alta relevância (pontuação extra na seleção de lacunas).
     */
    public const HIGH_VALUE_NOUNS = [
        'constituição', 'república', 'federação', 'estado', 'união', 'direito',
        'liberdade', 'igualdade', 'dignidade', 'soberania', 'cidadania', 'justiça',
        'lei', 'norma', 'princípio', 'garantia', 'proteção', 'segurança',
        'propriedade', 'vida', 'privacidade', 'intimidade', 'manifestação',
        'educação', 'saúde', 'trabalho', 'moradia', 'lazer', 'previdência',
        'assistência', 'alimentação', 'transporte', 'democracia', 'sufrágio',
    ];
}
