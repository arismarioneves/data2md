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
    'footer.text'       => '<strong>%s</strong> — o arquivo enviado é descartado após a conversão; o Markdown gerado fica guardado, sem prazo de expiração.',
    'footer.app'        => 'App',
    'footer.api'        => 'API',
    'footer.about_md'   => 'Sobre o .md',
    'footer.home'       => 'Início',
    'footer.converter'  => 'Conversor',
    'footer.api.text'   => '<strong>%s</strong> API — sem cadastro, sem chave.',

    // ── Landing — meta ─────────────────────────────────────────
    'landing.title'     => '%s · Documentos, destilados em Markdown',
    'landing.meta.desc' => 'Solte um PDF. Receba Markdown limpo e pronto pra IA. Feito pra arquivos grandes, preserva a estrutura e é grátis.',
    'og.image.alt'      => 'O logo do data2md ao lado de uma amostra de Markdown convertido.',

    // ── Landing — hero ─────────────────────────────────────────
    'landing.hero.eyebrow' => 'Grátis · Sem cadastro · Até %s',
    'landing.hero.eyebrow.signedin' => 'Conectado · Até %s',
    'landing.hero.title'   => 'Documentos, <em>destilados</em> em Markdown.',
    'landing.hero.sub'     => 'Solte um PDF e receba Markdown estruturado, pronto pra colar no ChatGPT, Claude ou qualquer modelo. Arquivos grandes são bem-vindos.',
    'landing.hero.cta'     => 'Converter um arquivo',
    'landing.hero.cta2'    => 'Entrar',
    'landing.hero.meta1'   => 'Sem cadastro',
    'landing.hero.meta2'   => 'Arquivos grandes sem travar',
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
    'landing.perf.lead'    => 'Documentos de qualquer tamanho são processados em fluxo contínuo, sem estourar memória e sem travar o navegador. O progresso do envio e da extração aparece em tempo real, com dicas enquanto você espera.',
    'landing.perf.stat1.lbl' => 'upload máximo',
    'landing.perf.stat2.lbl' => 'memória',
    'landing.perf.stat3.lbl' => 'travamentos',
    'landing.perf.bench.title' => 'Benchmarks de conversão',
    'landing.perf.bench.sub'   => 'tempos medidos de conversão',

    // ── Landing — sign in ──────────────────────────────────────
    'landing.signin.eyebrow' => '// contas',
    'landing.signin.title'   => 'Salve suas <em>conversões</em>.',
    'landing.signin.lead'    => 'Entre pra manter uma biblioteca dos seus documentos convertidos, compartilhar links de download e acessar de qualquer lugar.',
    'landing.signin.card.title' => 'Crie sua biblioteca',
    'landing.signin.card.lead'  => 'Informe seu e-mail e enviamos um link de uso único. Sem senha pra lembrar ou perder.',
    'landing.signin.btn'     => 'Continuar com e-mail',
    'landing.signin.or'      => 'ou',
    'landing.signin.alt'     => 'Continuar sem cadastro',

    // ── Entrar ─────────────────────────────────────────────────
    'auth.login.title'      => '%s · Entrar',
    'auth.login.meta.desc'  => 'Entre no data2md com um link de uso único enviado por e-mail. Sem senha pra lembrar.',
    'auth.login.eyebrow'    => '// entrar',
    'auth.login.heading'    => 'Entre com um <em>link</em>.',
    'auth.login.lead'       => 'Informe seu e-mail e enviamos um link de uso único. Não existe senha, e entrar pela primeira vez cria sua conta.',
    'auth.login.email'      => 'Endereço de e-mail',
    'auth.login.submit'     => 'Enviar o link',
    'auth.login.bad_email'  => 'Isso não parece um endereço de e-mail.',
    'auth.login.throttled'  => 'Muitos pedidos de link. Tente de novo em alguns minutos.',
    'auth.login.sent.head'  => 'Confira sua caixa de entrada',
    'auth.login.sent.body'  => 'Se esse endereço puder receber e-mail, um link de acesso está a caminho. Ele funciona uma vez e expira em %d minutos.',
    'auth.login.sent.again' => 'Usar outro endereço',

    'auth.confirm.head'     => 'Confirmar acesso',
    'auth.confirm.body'     => 'Este link entra como <strong>%s</strong>.',
    'auth.confirm.btn'      => 'Entrar como %s',
    'auth.confirm.other'    => 'Você já está conectado como <strong>%s</strong>. Saia primeiro para usar este link.',

    'auth.dead.head'        => 'Este link não funciona mais',
    'auth.dead.body'        => 'Um link de acesso funciona uma vez e expira depois de %d minutos.',
    'auth.dead.btn'         => 'Pedir um link novo',

    'auth.mail.subject'     => 'Entrar no %s',
    'auth.mail.body'        => "Abra este link para entrar no %s:\n\n%s\n\nEle funciona uma vez e expira em %d minutos. Se você não pediu, ignore esta mensagem.",

    'nav.dashboard'         => 'Painel',
    'nav.signout'           => 'Sair',

    // ── Painel ─────────────────────────────────────────────────
    'dash.title'            => '%s · Painel',
    'dash.meta.desc'        => 'Seus documentos convertidos, prontos pra baixar ou compartilhar.',
    'dash.eyebrow'          => '// sua biblioteca',
    'dash.heading'          => 'Suas <em>conversões</em>.',

    'dash.account.head'     => 'Conta',
    'dash.account.since'    => 'Na casa desde %s',

    'dash.files.head'       => 'Arquivos',
    'dash.files.count'      => '%d convertidos',
    'dash.files.note'       => 'Um link de download funciona pra quem tiver ele. Apague o arquivo pra derrubar o link.',
    'dash.empty.head'       => 'Ainda não tem nada aqui',
    'dash.empty.body'       => 'Converta um documento com a sessão aberta e ele aparece aqui.',
    'dash.empty.cta'        => 'Converter um documento',

    'dash.col.name'         => 'Arquivo',
    'dash.col.size'         => 'Tamanho',
    'dash.col.date'         => 'Convertido',
    'dash.download'         => 'Baixar',
    'dash.copy'             => 'Copiar link',
    'dash.copied'           => 'Copiado',
    'dash.delete'           => 'Apagar',
    'dash.delete.confirm'   => 'Apagar este arquivo? O link de download para de funcionar pra todo mundo que tiver ele.',
    'dash.deleted'          => 'Arquivo apagado.',
    'dash.delete.failed'    => 'Esse arquivo não é seu, ou não existe mais.',

    'dash.newer'            => 'Mais recentes',
    'dash.older'            => 'Mais antigos',
    'dash.page'             => 'Página %d de %d',

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

    'app.helper.anon'     => 'Sem cadastro',
    'app.helper.streamed' => 'Arquivos grandes sem travar',
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
    'api.meta.desc'  => 'Endpoint REST para converter PDF em Markdown: um POST autenticado, resposta em JSON e os limites de uso documentados por completo.',
    'api.eyebrow'    => '// rest api',
    'api.heading'    => 'Referência da <em>API</em>',
    'api.lead'       => 'Integre o %s no seu fluxo com um único HTTP POST. Incluída na assinatura; até %s por requisição.',

    'api.section.auth' => 'Autenticação',
    'api.auth.body'    => 'Toda requisição leva um token no cabeçalho <code>Authorization</code>. O token pertence a uma conta com assinatura ativa e é criado no seu <a href="/dashboard">painel</a>.',
    'api.auth.rotate'  => 'O token aparece uma única vez e é guardado apenas como hash, então trate-o como uma senha. Gerar um novo substitui o anterior na hora.',

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
    'api.error.note'        => 'Todo erro traz <code>success: false</code> e uma <code>message</code> legível. Onde houver <code>code</code>, decida pelo code e não pelo texto da mensagem.',
    'api.errors.col.status'  => 'Status',
    'api.errors.col.code'    => 'Code',
    'api.errors.col.meaning' => 'Significado',
    'api.error.token_required' => 'Sem cabeçalho <code>Authorization</code>, ou com um que não é bearer token.',
    'api.error.token_invalid'  => 'O token não existe — foi substituído, revogado, ou a conta dele foi excluída.',
    'api.error.plan_required'  => 'Token válido numa conta sem assinatura ativa.',
    'api.error.unprocessable'  => 'O arquivo foi recusado: tipo errado, acima do limite de tamanho, ou sem camada de texto para extrair.',
    'api.error.throttled'      => 'O limite de uso foi consumido. <code>Retry-After</code> diz quantos segundos esperar.',
    'api.error.unavailable'    => 'Todas as vagas de conversão estão ocupadas, ou esta instalação está com contas desligadas.',

    'api.section.rate'      => 'Limites de uso',
    'api.rate.body'         => '%d requisição(ões) a cada %d segundos, contadas por conta e separadas do que a mesma conta converte no site. Exceder retorna <code>429 Too Many Requests</code>.',
    'api.rate.headers'      => 'As respostas de conversão e de <code>/api/me</code> trazem <code>X-RateLimit-Limit</code>, <code>X-RateLimit-Remaining</code> e <code>X-RateLimit-Reset</code>, um timestamp Unix. Um <code>429</code> acrescenta <code>Retry-After</code> em segundos.',

    'api.section.me' => 'Conferir a conta',
    'api.me.body'    => 'Devolve o plano por trás do token, os limites que se aplicam a ele e quanto resta da janela atual.',
    'api.me.note'    => 'Funciona em qualquer plano e não entra no limite de uso, então um <code>403</code> numa conversão sempre pode ser explicado: leia <code>api_access</code> e <code>plan</code>.',

    'api.section.examples'  => 'Exemplos',
    'api.copy'              => 'Copiar',
    'api.copied'            => 'Copiado',

    // ── Fila / retry ────────────────────────────────────────────
    'app.error.toobig'      => 'Esse arquivo é maior que %s.',
    'app.error.toobig.anon' => 'Entre para um limite maior, ou assine para o maior de todos.',
    'app.error.toobig.free' => 'Assine para converter arquivos no maior tamanho.',
    'app.error.busy'      => 'O servidor está convertendo outros documentos agora. Tentando de novo em %s s…',
    'app.status.queued'   => 'Na fila…',
    'app.status.retrying' => 'Tentando de novo (%s de %s)…',

    // ── Páginas legais ─────────────────────────────────────────
    'nav.terms'          => 'Termos',
    'nav.privacy'        => 'Privacidade',
    'legal.updated'      => 'Atualizado em 18 de agosto de 2026.',

    'legal.terms.title'   => '%s — Termos de uso',
    'legal.terms.meta'    => 'Condições de uso do serviço de conversão de documentos em Markdown.',
    'legal.terms.heading' => 'Termos de uso',

    'legal.terms.s1.h' => 'O serviço',
    'legal.terms.s1.b' => 'O data2md converte documentos enviados em texto Markdown. Converter sem cadastro é gratuito. Uma conta aumenta os limites, e uma assinatura aumenta mais e libera a API HTTP.',

    'legal.terms.s2.h' => 'Sua responsabilidade',
    'legal.terms.s2.b' => 'Você é responsável pelo que envia. Ao usar o serviço, você declara ter o direito de processar aquele conteúdo. Não envie material ilegal nem conteúdo sobre o qual não tenha autorização.',

    'legal.terms.s3.h' => 'Limites de uso',
    'legal.terms.s3.b' => 'O tamanho do arquivo e a frequência de conversão dependem de você estar conectado e de assinar. Sem conta o limite é %s por arquivo; com conta gratuita, %s; com assinatura, %s. Também existe limite de frequência, contado por endereço no uso anônimo e por conta nos demais casos. Esses limites existem para manter o serviço disponível a todos e podem mudar sem aviso.',

    'legal.terms.s4.h' => 'Disponibilidade e resultado',
    'legal.terms.s4.b' => 'O serviço é oferecido como está, sem garantia de disponibilidade contínua nem de fidelidade da conversão. Documentos sem camada de texto — imagens digitalizadas, por exemplo — não podem ser convertidos. Confira o resultado antes de usá-lo para qualquer finalidade que dependa de exatidão.',

    'legal.terms.s5.h' => 'Remoção de conteúdo',
    'legal.terms.s5.b' => 'Podemos remover qualquer conversão a qualquer momento, inclusive por motivo operacional, sem aviso prévio.',

    'legal.terms.s6.h' => 'Mudanças nestes termos',
    'legal.terms.s6.b' => 'Estes termos podem ser alterados. A versão vigente é sempre a publicada nesta página. Dúvidas: <a href="mailto:%3$s">%3$s</a>.',

    'legal.privacy.title'   => '%s — Privacidade',
    'legal.privacy.meta'    => 'O que o serviço guarda, por quanto tempo e como pedir exclusão.',
    'legal.privacy.heading' => 'Privacidade',

    'legal.privacy.s1.h' => 'Quem é o responsável',
    'legal.privacy.s1.b' => 'O data2md é responsável pelo tratamento dos dados descritos nesta página. Contato: <a href="mailto:%3$s">%3$s</a>.',

    'legal.privacy.s2.h' => 'O arquivo que você envia',
    'legal.privacy.s2.b' => '<strong>É apagado do servidor logo após a conversão</strong>, inclusive quando a conversão falha. Ele é usado apenas para extrair o texto e não é guardado.',

    'legal.privacy.s3.h' => 'O Markdown gerado',
    'legal.privacy.s3.b' => 'Fica guardado e acessível pelo link de download <strong>até ser apagado</strong>. Não expira sozinho.',

    'legal.privacy.s4.h' => 'O nome do arquivo',
    'legal.privacy.s4.b' => 'É guardado junto da conversão, porque é ele que dá nome ao arquivo baixado. Se o nome do seu arquivo contiver dados pessoais, esses dados ficam guardados enquanto a conversão existir.',

    'legal.privacy.s5.h' => 'Endereço IP',
    'legal.privacy.s5.b' => 'É registrado para segurança e para aplicar o limite de uso. No registro operacional o endereço é guardado de forma reduzida, sem a parte final. No registro de acesso, o endereço completo é mantido por até 30 dias e depois apagado.',

    'legal.privacy.s6.h' => 'Quem consegue acessar sua conversão',
    'legal.privacy.s6.b' => 'Qualquer pessoa com o link de download. O link contém um identificador aleatório e não é listado em lugar nenhum, mas <strong>não compartilhe links de documentos sensíveis</strong>.',

    'legal.privacy.s7.h' => 'Cookies',
    'legal.privacy.s7.b' => 'Usamos dois cookies: um para lembrar o idioma que você escolheu e outro para manter você conectado, que só existe enquanto há sessão de conta. Não há rastreamento, análise de comportamento nem publicidade.',

    'legal.privacy.s8.h' => 'Como pedir exclusão',
    'legal.privacy.s8.b' => 'Escreva para <a href="mailto:%3$s">%3$s</a> informando o identificador que aparece no link de download. A conversão e o registro correspondente são apagados.',
];
