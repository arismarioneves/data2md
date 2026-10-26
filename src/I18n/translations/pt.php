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
    'footer.text'       => '<strong>%s</strong> — solte um PDF e receba Markdown que um modelo realmente lê.',
    'footer.app'        => 'App',
    'footer.api'        => 'API',
    'footer.about_md'   => 'Sobre o .md',
    'footer.home'       => 'Início',
    'footer.converter'  => 'Conversor',
    'footer.api.text'   => '<strong>%s</strong> API — um POST, Markdown estruturado de volta.',

    // ── Landing — meta ─────────────────────────────────────────
    'landing.title'     => '%s · Documentos, destilados em Markdown',
    'landing.meta.desc' => 'Solte um PDF. Receba Markdown limpo e pronto pra IA. Feito pra arquivos grandes, preserva a estrutura e é grátis.',
    'og.image.alt'      => 'O logo do Data2MD ao lado de uma amostra de Markdown convertido.',

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
    'auth.login.meta.desc'  => 'Entre no %s com um link de uso único enviado por e-mail. Sem senha pra lembrar.',
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
    'auth.mail.heading'     => 'Entrar no %s',
    'auth.mail.lead'        => 'Use o botão abaixo. Ele funciona uma vez e expira em %d minutos.',
    'auth.mail.button'      => 'Entrar',
    'auth.mail.fallback'    => 'Se o botão não funcionar, copie este endereço no seu navegador:',
    'auth.mail.ignore'      => 'Se você não pediu para entrar, ignore esta mensagem. Nada acontece até o link ser aberto.',
    'auth.mail.body'        => "Abra este link para entrar no %s:\n\n%s\n\nEle funciona uma vez e expira em %d minutos. Se você não pediu, ignore esta mensagem.",

    'nav.dashboard'         => 'Painel',
    'nav.signout'           => 'Sair',

    // ── Painel ─────────────────────────────────────────────────
    'dash.title'            => '%s · Painel',
    'dash.meta.desc'        => 'Seus documentos convertidos, prontos pra baixar ou compartilhar.',
    'dash.eyebrow'          => '// sua biblioteca',
    'dash.heading'          => 'Suas <em>conversões</em>.',

    'dash.account.head'     => 'Conta',
    'dash.account.since'    => 'Conta criada em %s',

    'dash.modal.cancel'     => 'Cancelar',

    'dash.bill.head'        => 'Plano',
    'dash.bill.plan.free'   => 'Gratuito',
    'dash.bill.plan.premium' => 'Premium',
    'dash.bill.renews'      => 'Renova em %s',
    'dash.bill.ends'        => 'Termina em %s',
    'dash.bill.active'      => 'Sua assinatura está ativa.',
    'dash.bill.cancelling'  => 'Cancelada. O acesso continua até o fim do período já pago.',
    'dash.bill.granted'     => 'Acesso premium concedido diretamente, sem assinatura.',
    'dash.bill.free.head'   => 'Plano gratuito',
    'dash.bill.free.sub'    => 'Até %s por arquivo, e sem acesso à API.',
    'dash.bill.subscribe'   => 'Assinar',
    'dash.bill.manage'      => 'Gerenciar cobrança',
    'dash.bill.compare'     => 'O que cada plano inclui está na página de %s.',
    'dash.bill.thanks'      => 'Obrigado. Sua assinatura está sendo confirmada; esta página atualiza assim que estiver.',
    'dash.bill.already'     => 'Esta conta já tem uma assinatura ativa. Use Gerenciar cobrança para alterá-la.',
    'dash.bill.nothing'     => 'Ainda não há nada cobrado nesta conta.',
    'dash.bill.error'       => 'Não foi possível abrir a página de cobrança. Tente de novo em instantes.',

    'dash.api.head'         => 'Acesso à API',
    'dash.api.limits'       => '%d chamada(s) / %d s · até %s',
    'dash.api.none'         => 'Nenhum token ainda',
    'dash.api.none.sub'     => 'É o token que permite um script chamar a API nesta conta.',
    'dash.api.create'       => 'Criar token',
    'dash.api.rotate'       => 'Gerar novo',
    'dash.api.rotate.confirm' => 'Gerar um novo token? O atual para de funcionar na hora.',
    'dash.api.revoke'       => 'Revogar',
    'dash.api.revoke.confirm' => 'Revogar este token? Tudo que o usa para de funcionar na hora.',
    'dash.api.issued'       => 'Token criado. Copie agora.',
    'dash.api.revoked'      => 'Token revogado.',
    'dash.api.once'         => 'Copie agora — ele é guardado como hash e não pode ser mostrado de novo.',
    'dash.api.copy'         => 'Copiar',
    'dash.api.created'      => 'Criado em %s',
    'dash.api.used'         => 'último uso em %s',
    'dash.api.unused'       => 'nunca usado',
    'dash.api.locked'       => 'A API faz parte da assinatura. O token funciona em %s de qualquer forma, mas as conversões respondem 403 enquanto esta conta não tiver uma.',
    'dash.api.docs'         => 'Endpoints, erros e exemplos estão na referência %s.',

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
    'app.helper.signedin' => 'Conectado',
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

    // ── Pricing (/pricing) ─────────────────────────────────────
    'nav.pricing'          => 'Planos',

    'price.title'          => '%s · Planos',
    'price.meta.desc'      => 'Gratuito para conversões manuais. A assinatura aumenta o limite de tamanho e abre a API HTTP.',
    'price.eyebrow'        => '// planos',
    'price.heading'        => 'Converta de <em>graça</em>. Assine para escalar.',
    'price.lead'           => 'O conversor deste site continua gratuito, sem precisar de conta. A assinatura é para documentos maiores e para chamar a API pelo seu próprio código.',

    'price.free.name'      => 'Gratuito',
    'price.free.amount'    => 'Sem custo',
    'price.free.sub'       => 'Sem cartão, e sem conta a menos que você queira suas conversões listadas.',
    'price.free.f1'        => 'Até %s por arquivo sem entrar',
    'price.free.f2'        => 'Até %s por arquivo com conta',
    'price.free.f3'        => 'Suas conversões listadas, para baixar e excluir',
    'price.free.f4'        => 'Sem acesso à API',

    'price.premium.badge'  => 'Assinatura',
    'price.premium.name'   => 'Premium',
    'price.premium.currency' => 'A cobrança sai na moeda do país onde o cartão foi emitido. Os dois valores aparecem aqui para não haver surpresa no checkout.',
    'price.premium.f1'     => 'Até %s por arquivo',
    'price.premium.f2'     => 'API HTTP, %d requisição(ões) a cada %d segundos',
    'price.premium.f3'     => 'Um token de API que você pode trocar ou revogar quando quiser',
    'price.premium.f4'     => 'Cancele quando quiser, na sua própria página de cobrança',

    'price.per.month'      => '/mês',

    'price.cta.subscribe'  => 'Assinar',
    'price.cta.note'       => 'O pagamento é processado pela Stripe. Os dados do cartão não passam por este site.',
    'price.cta.manage'     => 'Gerenciar cobrança',
    'price.cta.current'    => 'Este é o seu plano atual.',
    'price.cta.signin'     => 'Entre para assinar',
    'price.cta.signin.note' => 'A assinatura pertence a uma conta, então entrar vem primeiro.',

    'price.flash.cancelled' => 'O checkout foi cancelado. Nada foi cobrado.',
    'price.flash.error'    => 'Não foi possível abrir a página de pagamento. Tente de novo em instantes.',

    'price.faq.head'       => 'Antes de assinar',
    'price.faq.q1'         => 'Em qual moeda eu sou cobrado?',
    'price.faq.a1'         => 'A Stripe decide pelo país de emissão do seu cartão, não pelo idioma do site. Cartão brasileiro é cobrado em reais; cartões de fora, em dólares. Ler esta página em português não muda o que você paga.',
    'price.faq.q2'         => 'O que acontece se eu cancelar?',
    'price.faq.a2'         => 'O acesso continua até o fim do período já pago e depois a conta volta ao plano gratuito. Nada do que você converteu é apagado.',
    'price.faq.q3'         => 'Preciso de assinatura para converter arquivos?',
    'price.faq.a3'         => 'Não. O conversor deste site é gratuito. A assinatura aumenta o limite de tamanho e é o que abre a API HTTP.',
    'price.faq.q4'         => 'Para onde vão os dados do meu cartão?',
    'price.faq.a4'         => 'Para a Stripe, numa página hospedada por eles. Este site nunca vê nem guarda um número de cartão.',

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
    'app.error.toobig.anon' => 'Entre para %1$s, ou assine para %2$s.',
    'app.error.toobig.free' => 'Assine para converter arquivos de até %s.',
    'app.upsell.anon'       => 'Conectado você converte até %1$s, e a assinatura leva isso a %2$s.',
    'app.upsell.anon.free'  => 'Entre para converter arquivos de até %s.',
    'app.upsell.free'       => 'A assinatura leva seu limite para %s.',
    'app.upsell.cta'        => 'Ver planos',
    'app.error.busy'      => 'O servidor está convertendo outros documentos agora. Tentando de novo em %s s…',
    'app.status.queued'   => 'Na fila…',
    'app.status.retrying' => 'Tentando de novo (%s de %s)…',

    // ── Páginas legais ─────────────────────────────────────────
    'nav.terms'          => 'Termos',
    'nav.privacy'        => 'Privacidade',
    'legal.updated'      => 'Atualizado em 31 de agosto de 2026.',

    'legal.terms.title'   => '%s — Termos de uso',
    'legal.terms.meta'    => 'Condições para usar o serviço de conversão de documentos em Markdown.',
    'legal.terms.heading' => 'Termos de uso',

    'legal.terms.service.h' => 'O serviço',
    'legal.terms.service.b' => 'O %5$s converte documentos em texto Markdown. O conversor deste site é gratuito. Uma conta aumenta o limite de tamanho de arquivo; a assinatura paga aumenta mais e é o que abre a API HTTP.',
    'legal.terms.service.granted.b' => 'O %5$s converte documentos em texto Markdown. O conversor deste site é gratuito. Uma conta aumenta o limite de tamanho de arquivo; o plano premium, concedido por quem opera este site, aumenta mais e é o que abre a API HTTP.',

    'legal.terms.accounts.h' => 'Contas',
    'legal.terms.accounts.b' => 'A conta é opcional. Entrar funciona por um link de uso único enviado ao seu e-mail, então não há senha para escolher nem perder. A conta lista as conversões feitas enquanto você estava logado, para baixar, compartilhar ou excluir. Como é o link que prova que a conta é sua, manter o acesso a essa caixa de e-mail é responsabilidade sua. Para encerrar uma conta, escreva para <a href="mailto:%4$s">%4$s</a>.',

    'legal.terms.conduct.h' => 'Sua responsabilidade',
    'legal.terms.conduct.b' => 'Você é responsável pelo que envia. Ao usar o serviço, você confirma que tem o direito de processar aquele conteúdo. Não envie material ilícito nem conteúdo que você não está autorizado a manipular.',

    'legal.terms.limits.h' => 'Limites de uso',
    'legal.terms.limits.b' => 'O tamanho do arquivo e a frequência de conversão dependem de você estar logado e de assinar. Sem conta, o limite é %1$s por arquivo; com conta gratuita, %2$s; com assinatura, %3$s. Também há limite de frequência, contado por endereço no uso anônimo e por conta nos demais casos. Esses limites mantêm o serviço disponível para todos e podem mudar sem aviso.',
    'legal.terms.limits.granted.b' => 'O tamanho do arquivo e a frequência de conversão dependem de você estar logado e do seu plano. Sem conta, o limite é %1$s por arquivo; com conta gratuita, %2$s; com o plano premium, %3$s. Também há limite de frequência, contado por endereço no uso anônimo e por conta nos demais casos. Esses limites mantêm o serviço disponível para todos e podem mudar sem aviso.',

    'legal.terms.subscription.h' => 'Assinatura e pagamento',
    'legal.terms.subscription.b' => 'A assinatura é mensal e renova automaticamente até ser cancelada. O pagamento é processado pela Stripe, numa página hospedada por eles: os dados do cartão nunca são enviados para este site nem guardados por ele. A cobrança sai na moeda do país onde o cartão foi emitido, e os dois valores aparecem na página de planos antes de você começar.',

    'legal.terms.cancel.h' => 'Cancelamento',
    'legal.terms.cancel.b' => 'Você pode cancelar quando quiser, na página de cobrança indicada no seu painel. Cancelar interrompe as renovações futuras; os recursos da assinatura continuam disponíveis até o fim do período já pago, e depois a conta volta ao plano gratuito. Nada do que você converteu é apagado pelo cancelamento. Para qualquer questão sobre uma cobrança específica, escreva para <a href="mailto:%4$s">%4$s</a>.',

    'legal.terms.availability.h' => 'Disponibilidade e resultados',
    'legal.terms.availability.b' => 'O serviço é fornecido como está, sem garantia de disponibilidade contínua nem de fidelidade da conversão. Documentos sem camada de texto — imagens escaneadas, por exemplo — não podem ser convertidos. Confira o resultado antes de depender dele em algo que exija exatidão.',

    'legal.terms.api.h' => 'A API',
    'legal.terms.api.b' => 'A API HTTP faz parte da assinatura e é usada com um token criado no seu painel. Trate o token como uma senha: o que for feito com ele conta como feito pela sua conta. Você pode substituí-lo ou revogá-lo quando quiser, e o token anterior para de funcionar na hora.',
    'legal.terms.api.granted.b' => 'A API HTTP faz parte do plano premium e é usada com um token criado no seu painel. Trate o token como uma senha: o que for feito com ele conta como feito pela sua conta. Você pode substituí-lo ou revogá-lo quando quiser, e o token anterior para de funcionar na hora.',

    'legal.terms.removal.h' => 'Remoção de conteúdo e de contas',
    'legal.terms.removal.b' => 'Podemos remover qualquer conversão a qualquer momento, inclusive por motivos operacionais, sem aviso prévio. Também podemos suspender uma conta usada de forma que ameace a disponibilidade do serviço para os demais.',

    'legal.terms.changes.h' => 'Alterações nestes termos',
    'legal.terms.changes.b' => 'Estes termos podem mudar. A versão em vigor é sempre a publicada nesta página. Dúvidas: <a href="mailto:%4$s">%4$s</a>.',

    'legal.privacy.title'   => '%s — Privacidade',
    'legal.privacy.meta'    => 'O que o serviço guarda, por quanto tempo, e como pedir a exclusão.',
    'legal.privacy.heading' => 'Privacidade',

    'legal.privacy.controller.h' => 'Quem é o responsável',
    'legal.privacy.controller.b' => 'O %5$s é responsável pelo tratamento dos dados descritos nesta página. Contato: <a href="mailto:%4$s">%4$s</a>.',

    'legal.privacy.upload.h' => 'O arquivo que você envia',
    'legal.privacy.upload.b' => '<strong>Ele é apagado do servidor logo após a conversão</strong>, inclusive quando a conversão falha. É usado apenas para extrair o texto e não é retido.',

    'legal.privacy.markdown.h' => 'O Markdown gerado',
    'legal.privacy.markdown.b' => 'Fica guardado e acessível pelo link de download <strong>até ser excluído</strong>. Ele não expira por conta própria.',

    'legal.privacy.filename.h' => 'O nome do arquivo',
    'legal.privacy.filename.b' => 'É guardado junto da conversão, porque é o que dá nome ao arquivo que você baixa. Se o nome do seu arquivo contém dados pessoais, esses dados ficam guardados enquanto a conversão existir.',

    'legal.privacy.account.h' => 'Dados da conta',
    'legal.privacy.account.b' => 'Se você criar uma conta, seu endereço de e-mail é guardado, junto com os registros que mantêm você logado e o registro de cada conversão feita enquanto logado. O endereço é usado para enviar os links de acesso e, quando for o caso, mensagens sobre sua assinatura. Não é usado para marketing nem compartilhado.',

    'legal.privacy.payment.h' => 'Dados de pagamento',
    'legal.privacy.payment.b' => 'Os pagamentos são processados pela Stripe, que atua como nossa operadora de pagamento. A Stripe recebe o necessário para efetuar a cobrança; nós recebemos de volta um identificador do seu registro de cliente e o estado da assinatura — situação, qual preço, e quando termina o período atual. Números de cartão nunca são enviados para este site nem guardados por ele. A Stripe trata o que recebe sob os próprios termos, publicados em <a href="https://stripe.com/privacy" rel="noopener noreferrer" target="_blank">stripe.com/privacy</a>.',

    'legal.privacy.token.h' => 'Token de API',
    'legal.privacy.token.b' => 'O token de API é guardado apenas como hash criptográfico, então o token em si não pode ser lido de volta dos nossos registros por ninguém, inclusive por nós. O que fica ao lado dele é suficiente para reconhecer de qual token se trata e insuficiente para usá-lo. Também registramos quando foi usado por último, para você saber se ainda está em uso.',

    'legal.privacy.ip.h' => 'Endereço IP',
    'legal.privacy.ip.b' => 'É registrado por segurança e para aplicar o limite de uso. No registro operacional o endereço é guardado de forma reduzida, sem a parte final. No registro de acesso o endereço completo fica por até %6$d dias e depois é apagado.',

    'legal.privacy.reach.h' => 'Quem consegue acessar sua conversão',
    'legal.privacy.reach.b' => 'Qualquer pessoa que tenha o link de download. O link contém um identificador aleatório e não é listado em lugar nenhum, mas <strong>não compartilhe links de documentos sensíveis</strong>.',

    'legal.privacy.cookies.h' => 'Cookies',
    'legal.privacy.cookies.b' => 'Usamos dois cookies: um para lembrar o idioma escolhido e outro para manter você logado, que existe somente enquanto há uma sessão de conta. Não há rastreamento, nem analytics comportamental, nem publicidade.',

    'legal.privacy.deletion.h' => 'Excluir seus dados',
    'legal.privacy.deletion.b' => 'Você pode excluir qualquer conversão pelo seu painel, o que remove o Markdown e o registro dele, e o link de download para de funcionar para todos que o tiverem. Para uma conversão feita sem conta, escreva para <a href="mailto:%4$s">%4$s</a> com o identificador que aparece no link de download. Para encerrar uma conta, escreva para o mesmo endereço: a conta e os registros de acesso são removidos, e <strong>as conversões dela são desvinculadas, não apagadas</strong> — então exclua o que quiser eliminar antes de encerrar a conta.',
];
