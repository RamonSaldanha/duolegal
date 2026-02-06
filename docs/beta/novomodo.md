## Problema que o Memorize está enfrentando:

Atualmente usamos o N8N para adicionar novos desafios. 

O workflow do N8n funciona mais ou menos assim:

O usuário preenche um formulário com a URL de uma legislação; 
A automação pega o texto completo da lei e fragmenta em MUITAS partes tendo como delimitação o início e um fim de um artigo, parágrafo, inciso ou alínea, criando um loop para cada intervalo deste (vale a pena você investigar como isso é feito a partir do endpoint do webhook e também o arquivo que está na pasta n8n/);

O resultado pode ser algo do tipo:

"DESAFIO 1 - Art. 1º A República Federativa do Brasil, formada pela união indissolúvel dos Estados e Municípios e do Distrito Federal, constitui-se em Estado Democrático de Direito e tem como fundamentos: I - a ____; II - a cidadania; III - a _____ da pessoa humana;".

"DESAFIO 2 - Art. 1º A República Federativa do Brasil, formada pela união indissolúvel dos Estados e Municípios e do Distrito Federal, constitui-se em Estado Democrático de Direito e tem como fundamentos: [...] IV - os _____ sociais do trabalho e da livre iniciativa;V - o pluralismo ____".

Esse modelo atrapalha o aprendizado pelos seguintes motivos:

1. Se o artigo possui muitos incisos, você acaba sendo obrigado a ler o Caput MUITAS vezes;

2. O texto fica exageradamente longo e enfadonho;

3. Se o artigo contiver parágrafos, alíneas e incisos, o algoritmo que cria os desafios retira parcela do texto para evitar que ele fique muito longo. Isso faz com que o usuário veja o teor do inciso, mas não saiba em que contexto o inciso está inserido. Exemplo:

"DESAFIO 3 - [...] XVI - todos podem reunir-se pacificamente, sem armas, em locais ____ ao público, independentemente de autorização, desde que não frustrem outra reunião anteriormente ____ para o mesmo local, sendo apenas exigido prévio aviso à autoridade competente;".

No caso acima, o usuário vai resolver o inciso, mas não vai saber que ele decorre do artigo 5º da Constituição.

4. Ele cria muitos desafios com textos repetidos, isso impede que tenhamos uma legislação que apesar de ser fragmentada em pequenas partes, caso se opte por exibi-la por completo, bastaria pegar todos fragmentos no banco de dados.

5. A legislação já costuma ser longa, fica mais longa ainda quando os desafios possuem textos repetitivos, o que dificulta o progresso e desestimula o aprendizado.