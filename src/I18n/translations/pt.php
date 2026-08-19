<?php

declare(strict_types=1);

return [
    // ── Comum ──────────────────────────────────────────────────
    'lang.code'         => 'pt',
    'lang.label.en'     => 'EN',
    'lang.label.pt'     => 'PT',
    'lang.aria'         => 'Idioma',

    'nav.about'         => 'Sobre',
    'nav.api'           => 'API',
    'nav.app'           => 'APP',
    'nav.signin'        => 'Entrar',
    'nav.why'           => 'Por que Markdown',
    'nav.performance'   => 'Performance',

    // ── Rodapé (landing) ───────────────────────────────────────
    'footer.text'       => '<strong>%s</strong>. Arquivos guardados sob identificadores anônimos.',
    'footer.app'        => 'App',
    'footer.api'        => 'API',
    'footer.about_md'   => 'Sobre o .md',
    'footer.home'       => 'Início',
    'footer.converter'  => 'Conversor',
    'footer.api.text'   => '<strong>%s</strong> API. Anônimo por design.',

    // ── Landing — meta ─────────────────────────────────────────
    'landing.title'     => '%s · Documentos, destilados em Markdown',
    'landing.meta.desc' => 'Solte um PDF. Receba Markdown limpo e pronto pra IA. Feito pra arquivos grandes, preserva a estrutura e é grátis.',
    'og.image.alt'      => 'O logo do data2md ao lado de uma amostra de Markdown convertido.',

    // ── Landing — hero ─────────────────────────────────────────
    'landing.hero.eyebrow' => 'Grátis · Sem cadastro · Até %s',
    'landing.hero.title'   => 'Documentos, <em>destilados</em> em Markdown.',
    'landing.hero.sub'     => 'Solte um PDF e receba Markdown estruturado, pronto pra colar no ChatGPT, Claude ou qualquer modelo. Arquivos grandes são bem-vindos.',
    'landing.hero.cta'     => 'Converter um arquivo',
    'landing.hero.cta2'    => 'Entrar',
    'landing.hero.meta1'   => 'Anônimo por design',
    'landing.hero.meta2'   => 'Streaming via pdftotext',
    'landing.hero.meta3'   => 'API REST inclusa',

    // ── Landing — por que markdown ─────────────────────────────
    'landing.why.eyebrow' => '// por que markdown',
    'landing.why.title'   => 'Texto puro do jeito que as <em>máquinas</em> deveriam ler.',
    'landing.why.lead'    => 'Markdown é o idioma comum das ferramentas de IA, controle de versão e documentação modernas. Converte uma vez, usa em qualquer lugar.',

    'landing.why.card1.title' => 'Feito pra modelos de IA.',
    'landing.why.card1.desc'  => 'LLMs entendem Markdown nativamente. Texto estruturado usa menos tokens, cabe em contextos maiores e gera respostas comprovadamente mais precisas que binário cru.',
    'landing.why.card1.tag'   => '~30% menos tokens vs PDF cru',

    'landing.why.card2.title' => 'Estrutura preservada.',
    'landing.why.card2.desc'  => 'Páginas, títulos e parágrafos sobrevivem à conversão. Seu documento mantém o fluxo lógico, legível por humanos e máquinas, sem ruído binário ou problemas de codificação.',
    'landing.why.card2.tag'   => 'extração com noção de página',

    'landing.why.card3.title' => 'Formato universal.',
    'landing.why.card3.desc'  => 'Markdown abre em qualquer editor de texto. Renderiza nativamente no GitHub, Notion, Obsidian e milhares de ferramentas. Guarde no Git e ganhe diffs linha-a-linha pra sempre.',
    'landing.why.card3.tag'   => '.md · em todo lugar',

    'landing.why.card4.title' => 'Diff amigável pra sempre.',
    'landing.why.card4.desc'  => 'Acompanhe cada mudança no nível da linha. Revise edições de documentos num pull request como você revisa código. Chega de comparar revisões de PDF lado a lado.',
    'landing.why.card4.tag'   => 'fluxo nativo do git',

    // ── Landing — performance ──────────────────────────────────
    'landing.perf.eyebrow' => '// arquivos grandes são bem-vindos',
    'landing.perf.title'   => 'Um PDF de <em>%s</em> não nos assusta.',
    'landing.perf.lead'    => 'Conversão por streaming via <code>pdftotext</code> mantém memória constante independente do tamanho. O navegador nunca trava. O progresso de upload e a extração no servidor são acompanhados em tempo real, com dicas amigáveis enquanto você espera.',
    'landing.perf.stat1.lbl' => 'upload máximo',
    'landing.perf.stat2.lbl' => 'memória',
    'landing.perf.stat3.lbl' => 'travamentos',
    'landing.perf.bench.title' => 'Benchmarks de conversão',
    'landing.perf.bench.sub'   => 'pdftotext · streaming',

    // ── Landing — sign in ──────────────────────────────────────
    'landing.signin.eyebrow' => '// em breve',
    'landing.signin.title'   => 'Salve suas <em>conversões</em>.',
    'landing.signin.lead'    => 'Entre pra manter uma biblioteca privada dos seus documentos convertidos, compartilhar links de download e acessar de qualquer lugar.',
    'landing.signin.card.title' => 'Crie sua biblioteca',
    'landing.signin.card.lead'  => 'Um clique com Google. Nunca compartilhamos seus dados e os arquivos seguem anônimos.',
    'landing.signin.btn'     => 'Continuar com Google',
    'landing.signin.tooltip' => 'Login com Google chega em breve',
    'landing.signin.soon'    => 'Autenticação chegando em breve',
    'landing.signin.or'      => 'ou',
    'landing.signin.alt'     => 'Continuar sem cadastro',

    // ── Conversor (/app) ───────────────────────────────────────
    'app.title'           => '%s · Converter',
    'app.meta.desc'       => 'Envie um PDF e receba Markdown limpo e estruturado. Sem conta, sem cadastro, e o arquivo convertido fica sempre disponível pra baixar.',
    'app.eyebrow'         => '// nova conversão',
    'app.heading'         => 'Solte um arquivo. Receba <em>Markdown</em>.',

    'app.dz.idle.title'   => 'Solte seu arquivo aqui',
    'app.dz.idle.sub'     => 'ou %s',
    'app.dz.idle.browse'  => 'escolha pra enviar',
    'app.dz.idle.hint'    => 'PDF · MÁX %s',
    'app.dz.sel.sub'      => 'Pronto · %s',
    'app.dz.sel.change'   => 'trocar arquivo',

    'app.format.label'    => 'Formato',
    'app.cta'             => 'Converter pra Markdown',

    'app.helper.anon'     => 'Anônimo por design',
    'app.helper.streamed' => 'Streaming via pdftotext',
    'app.helper.maxsize'  => 'Até %s',

    'app.result.badge'    => 'Convertido',
    'app.result.idlbl'    => 'ID',
    'app.result.copy'     => 'Copiar Markdown',
    'app.result.copying'  => 'Copiando…',
    'app.result.copied'   => 'Copiado!',
    'app.result.download' => 'Baixar .md',
    'app.result.preview.label' => 'Pré-visualização do Markdown',
    'app.result.preview.chars' => '%s caracteres',
    'app.result.another'  => 'Converter outro arquivo',

    // ── Overlay de loading (fallback server-side) ─────────────
    'app.loading.processing' => 'Processando',

    // ── Bundle JS (window.D2MD.i18n) ──────────────────────────
    'app.loading.uploading'   => 'Enviando…',
    'app.loading.extracting'  => 'Extraindo texto…',
    'app.loading.structuring' => 'Estruturando documento…',
    'app.loading.building'    => 'Montando Markdown…',
    'app.loading.finalising'  => 'Finalizando saída…',
    'app.loading.done'        => 'Pronto',

    'app.error.network'  => 'Erro de rede. Verifique sua conexão e tente novamente.',
    'app.error.timeout'  => 'A requisição expirou. O arquivo pode ser grande demais ou o servidor está ocupado.',
    'app.error.failed'   => 'Conversão falhou. Tente novamente.',
    'app.error.preview_truncated' => 'Pré-visualização limitada aos primeiros %s de %s caracteres. Use Baixar pra pegar o arquivo completo.',

    'app.tip.0' => 'Markdown usa muito menos tokens que HTML, ideal pra contextos grandes de IA.',
    'app.tip.1' => 'Claude, ChatGPT e Gemini leem Markdown nativamente, sem prompt extra.',
    'app.tip.2' => 'Um PDF de 50 MB pode virar um arquivo de texto pequeno que qualquer editor abre.',
    'app.tip.3' => 'Arquivos Markdown geram diffs limpos no Git, perfeito pra rastrear mudanças no documento ao longo do tempo.',
    'app.tip.4' => 'Títulos estruturados ajudam modelos de IA a localizar e citar seções específicas com precisão.',

    // ── API docs (/api) ────────────────────────────────────────
    'api.title'      => '%s · Referência da API',
    'api.meta.desc'  => 'Endpoint REST para converter PDF em Markdown: um POST, resposta em JSON e os limites de uso documentados por completo.',
    'api.eyebrow'    => '// rest api',
    'api.heading'    => 'Referência da <em>API</em>',
    'api.lead'       => 'Integre o %s no seu fluxo com um único HTTP POST. Conversão por streaming via <code>pdftotext</code>, máximo de %s por requisição.',

    'api.section.endpoint' => 'Endpoint',
    'api.endpoint.ct'      => 'Content-Type: <code>multipart/form-data</code>',

    'api.section.fields'   => 'Campos da requisição',
    'api.field.col.field'  => 'Campo',
    'api.field.col.type'   => 'Tipo',
    'api.field.col.required' => 'Obrigatório',
    'api.field.col.desc'   => 'Descrição',
    'api.field.required.yes' => 'Sim',
    'api.field.required.no'  => 'Não',
    'api.field.file.desc'  => 'O arquivo a converter. Máx %s.',
    'api.field.format.desc' => 'Formato do arquivo. Padrão: <code>pdf</code>. Suportado atualmente: <code>pdf</code>.',

    'api.section.success'   => 'Resposta de sucesso',
    'api.tag.success'       => '201 Created',
    'api.success.note'      => '<code>preview</code> traz até ~8 KB de Markdown cortado num final de linha. <code>preview_truncated</code> indica se o arquivo é maior; busque o documento completo via <code>download_url</code>.',
    'api.section.error'     => 'Resposta de erro',
    'api.tag.error'         => '4xx · 5xx',

    'api.section.rate'      => 'Limites de uso',
    'api.rate.body'         => '%d requisições por minuto por IP. Exceder retorna <code>429 Too Many Requests</code> com cabeçalho <code>Retry-After: %d</code>.',

    'api.section.examples'  => 'Exemplos',
    'api.copy'              => 'Copiar',
    'api.copied'            => 'Copiado',
];
