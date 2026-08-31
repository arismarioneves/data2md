# Deploy — do Git ao EasyPanel

A imagem é construída pelo GitHub Actions e publicada no GHCR. O EasyPanel apenas consome a imagem pronta.

Este é o passo a passo prático. Para a tabela completa das variáveis, a
aritmética de dimensionamento e a arquitetura, veja
[`DEPLOYMENT.md`](DEPLOYMENT.md).

```
commit → push em main → Actions roda a suíte → publica no GHCR
       → EasyPanel puxa a imagem → /health confirma a versão
```

---

## 1. Pré-requisitos

- Repositório no GitHub com Actions habilitado.
- Conta no EasyPanel com acesso à VPS.
- Domínio apontando para a VPS.

---

## 2. Publicar a imagem

O workflow `.github/workflows/image.yml` dispara em push para `main`.

```bash
git checkout main
git merge docker
git push origin main
```

Acompanhe:

```bash
gh run watch
```

A suíte de testes é o portão: se um teste falha, nenhuma imagem é publicada.

Ao final, a imagem estará em:

```
ghcr.io/arismarioneves/data2md:main
```

Cada publicação gera duas referências:

| Tag | Uso |
|---|---|
| `:main` | sempre o último commit de `main` |
| `:sha-<commit>` | imutável, aponta para um commit exato |

Fixe `:sha-<commit>` no EasyPanel quando quiser um alvo que não se move.

---

## 3. Liberar o acesso ao pacote

Pacotes do GHCR nascem privados. Escolha uma das duas opções.

**Pacote público** — em `github.com/users/<usuário>/packages/container/data2md/settings`, use *Change visibility* → Public. Apenas a imagem fica pública; o repositório não muda. A imagem não contém segredo: toda configuração sensível é variável de ambiente em runtime.

**Credencial de registry** — crie um Personal Access Token (classic) com escopo `read:packages` e cadastre-o no EasyPanel em *Settings → Registries*.

---

## 4. Criar o app no EasyPanel

Novo serviço do tipo **App**, origem **Docker Image**.

| Campo | Valor |
|---|---|
| Image | `ghcr.io/arismarioneves/data2md:main` |
| Port | `80` |
| Health check path | `/health` |

---

## 5. Variáveis de ambiente

Os padrões vêm do Dockerfile. Informe apenas estas duas:

```
APP_URL=https://seu-dominio.com.br
CONTACT_EMAIL=contato@seu-dominio.com.br
```

Confirme a faixa de rede do proxy antes de subir:

```bash
docker network inspect $(docker network ls --format '{{.Name}}' | grep -i -m1 'easypanel\|traefik') \
    --format '{{range .IPAM.Config}}{{.Subnet}}{{"\n"}}{{end}}'
```

Se o resultado não for `10.11.0.0/16`, ajuste:

```
TRUSTED_PROXIES=<faixa retornada>
```

Uma faixa errada não gera erro: todo o tráfego passa a contar como um único endereço e o limite de uso deixa de funcionar.

Demais variáveis e seus domínios estão em [`.env.example`](../.env.example).

---

## 6. Volume e recursos

**Volume** — obrigatório, senão os arquivos convertidos são perdidos a cada deploy:

| Mount path |
|---|
| `/var/lib/data2md` |

**Recursos:**

| Recurso | Valor |
|---|---|
| Memory | 2 GB |
| CPU | 1.0 |

O pico de memória não é só das conversões: cada worker do Apache é um processo mod_php residente, esteja convertendo, na fila do semáforo ou ocioso. A fórmula real é `CONVERT_CONCURRENCY × (PHP_MEMORY_LIMIT_MB + ~60 MB) + (MaxRequestWorkers − CONVERT_CONCURRENCY) × ~35 MB`, hoje `2 × (512 + 60) + 22 × 35 ≈ 1,9 GB` de 2 GB — sem margem real, e é por isso que `MaxRequestWorkers` (24, em `docker/apache/mpm.conf`) não pode ser aumentado para dar mais fôlego à fila. A aritmética completa está em [`DEPLOYMENT.md`](DEPLOYMENT.md#5-sizing-arithmetic). A conversão roda com prioridade baixa de CPU e I/O, cedendo espaço aos outros serviços da VPS.

---

## 7. Domínio

Em *Domains*, adicione o host de `APP_URL` e habilite HTTPS. O EasyPanel provisiona o certificado.

---

## 8. Verificar

```bash
curl -sS https://seu-dominio.com.br/health
```

Resposta esperada:

```json
{"status":"ok","version":"main-a1b2c3d","checks":{"pdftotext":true,"md_dir":true,"tmp_dir":true,"logs_dir":true,"database":true}}
```

O campo `version` identifica o commit em execução — é a forma de confirmar qual código está no ar.

Conversão de ponta a ponta. O `/api` exige token de assinante, então o teste do
navegador usa `/convert`:

```bash
curl -sS -X POST https://seu-dominio.com.br/convert \
    -F "file=@documento.pdf" -F "format=pdf"
```

Para o caminho da API, crie um token e conceda a assinatura:

```bash
data2md token:create voce@exemplo.com
data2md user:premium voce@exemplo.com --days=1

curl -sS -X POST https://seu-dominio.com.br/api \
    -H "Authorization: Bearer d2md_..." \
    -F "file=@documento.pdf" -F "format=pdf"
```

---

## 9. Atualizar

```bash
git push origin main
```

Quando o Actions concluir, use *Deploy* no EasyPanel para puxar a imagem nova. Confirme pelo `version` em `/health`.

Para deploy automático, crie o secret `EASYPANEL_DEPLOY_HOOK` no repositório com a URL de webhook do serviço.

---

## 10. Rollback

Troque a imagem no EasyPanel para o `sha` anterior e faça *Deploy*:

```
ghcr.io/arismarioneves/data2md:sha-a1b2c3d
```

Os `sha` disponíveis aparecem na página do pacote no GHCR, ou via
`gh api /user/packages/container/data2md/versions`.

---

## 11. Operação

Comandos disponíveis no container:

```bash
bin/data2md health          # mesmos checks de /health
bin/data2md config:check    # valida as variáveis de ambiente
bin/data2md list            # conversões guardadas, mais recentes primeiro
bin/data2md list --limit=50
bin/data2md logs --tail=50  # últimas entradas do log operacional
bin/data2md logs --channel=audit --tail=50
bin/data2md gc              # remove órfãos, contadores vencidos, logs rotacionados
bin/data2md delete <hash>   # remove uma conversão
bin/data2md prune --older-than=90
```

O log operacional também aparece no visualizador de logs do EasyPanel.

---

## 11b. Stripe

O produto e o preço são criados pelo CLI, nunca à mão no painel — assim a moeda,
o intervalo e a opção em reais saem sempre iguais, e ficam registrados aqui.

```bash
data2md billing:create-product --name="Data2MD Premium"          # mostra o payload
data2md billing:create-product --name="Data2MD Premium" --confirm
```

Depois, ponha o `price_...` devolvido em `STRIPE_PRICE_ID` e confirme:

```bash
data2md billing:validate-prices
```

**Um preço da Stripe não pode ser editado nem apagado, só arquivado.** Um valor
errado fica na conta para sempre, e a correção é sempre um preço novo mais uma
mudança de configuração. Por isso o comando exige `--confirm` e recusa quando já
existe um `STRIPE_PRICE_ID` configurado.

### Um preço, duas moedas

Há **um** preço: `usd 1900` como moeda padrão, com `currency_options[brl] 9500`.
Não são dois preços, porque a Stripe escolhe a moeda pelo país de emissão do
cartão e a moeda de um cliente fica travada na primeira assinatura dele.

Sem a opção em reais, **todo cartão brasileiro é recusado** com
`currency_not_supported`. Não é otimização, é requisito — e é o tipo de falha que
passa no teste com `4242 4242 4242 4242` em dólar e quebra no primeiro cliente
brasileiro real. O `billing:validate-prices` sai não-zero se a opção faltar.

Uma pegadinha da API que o validador já contorna: `currency_options` **não vem na
resposta** sem `expand[]=currency_options`. Uma leitura sem o expand mostra
`null` num preço perfeitamente configurado.

O toggle de idioma do site **não** muda a moeda. Um brasileiro lendo em inglês
continua pagando em reais. Trocar de moeda depois é operação de suporte — cliente
novo na Stripe —, não um recurso do produto.

### Webhook

Endpoint em `https://<dominio>/billing/webhook`, assinando quatro eventos:
`checkout.session.completed` e `customer.subscription.created|updated|deleted`.
Fixe a versão da API do endpoint na mesma que o app envia (`STRIPE_API_VERSION`),
senão os payloads mudam de forma sob os pés do parser.

O segredo de assinatura é devolvido **apenas na criação** do endpoint. Se ele se
perder, gere outro no painel; não há como reler pela API.

O endpoint fica de fora de tudo o que o resto do app atravessa: sem limite de
uso, sem CORS, sem CSRF, sem sessão, sem coleta de lixo. Um limite de uso ali
colocaria os poucos endereços de saída da Stripe num só contador, responderia
429, e a Stripe desabilitaria o endpoint depois de falhas repetidas. Ele também
responde com `ACCOUNTS_ENABLED=false`: desligar contas não pode fazer uma
assinatura paga deixar de ser registrada em silêncio.

### Do lado da Stripe, no painel

- **Customer Portal** ativado, com cancelamento permitido. É por onde o assinante
  cancela; o app só abre uma sessão do portal.
- **Descritor de fatura** que o cliente reconheça. Descritor confuso é causa
  direta de chargeback.
- Métodos de pagamento restritos a cartão. Com Pix ou boleto, o
  `checkout.session.completed` dispara com a cobrança ainda em trânsito, e
  conceder acesso nesse momento dá um mês grátis a quem começar e abandonar um
  pagamento. O app já pede apenas cartão na sessão; manter isso alinhado no
  painel evita surpresa.

---

## 12. Ciclo de vida dos dados

| Dado | Retenção |
|---|---|
| Arquivo enviado | descartado após a conversão |
| Markdown convertido | sem expiração; até `delete`, `prune`, ou exclusão pelo usuário no painel |
| Conta e sessões | até encerrar a conta; sessões expiram por inatividade |
| Link de acesso por e-mail | expira em minutos, uso único |
| Registro de eventos da Stripe | 30 dias |
| Log operacional | 90 dias |
| Registro de acesso | 30 dias |

O Markdown não expira sozinho, então o volume cresce continuamente. Configure um alerta de espaço em disco na VPS.

A coleta oportunista (`gc`) roda a cada requisição de conversão, mas uma falha recorrente é engolida e só aparece no log operacional como o evento `gc.fail` — o disco continua crescendo sem aviso até o alerta de espaço disparar. Monitore o evento `gc.fail` no visualizador de logs do EasyPanel; um coletor parado é o sintoma que precede o disco cheio, não uma consequência dele.

---

## 13. Problemas comuns

**O container não sobe.** O entrypoint valida a configuração antes de iniciar o servidor e nomeia a variável ausente ou inválida no log. Leia o log do serviço.

**HTTP 503 com frequência.** A fila de conversão está cheia. Aumente `CONVERT_CONCURRENCY` se houver CPU disponível, ou reduza `CONVERT_QUEUE_WAIT_SECONDS` para recusar mais rápido.

**HTTP 422 "requer OCR".** O documento não tem camada de texto — é uma imagem digitalizada. Comportamento esperado.

**HTTP 413.** Arquivo acima de `MAX_FILE_SIZE_MB`.

**Limite de uso não funciona, ou ninguém consegue entrar.** `TRUSTED_PROXIES` não corresponde à rede do proxy, então todo visitante aparece com o mesmo endereço. Antes disso era só um limite mal aplicado; com contas, o limite por endereço nos pedidos de link de acesso passa a atingir todos os visitantes juntos, e entrar deixa de funcionar. Confira conforme a seção 5.

**O link de acesso não chega.** Rode `bin/data2md mail:test <seu-endereco>` dentro do container: ele imprime o transporte que vai usar e diz se o relay aceitou. Aceitação pelo relay não é entrega na caixa — confira também a pasta de spam, e confirme SPF e DKIM do domínio remetente. Sem esse comando o problema é indiagnosticável de dentro do container.

**Todo webhook da Stripe responde 400.** Duas causas, ambas silenciosas em tudo o mais. A chave e o segredo de webhook podem estar em modos trocados (teste contra produção) — o `config:check` recusa subir nessa combinação, então verifique se alguém sobrescreveu no painel. Ou o relógio do container está fora: a assinatura carrega um timestamp e a tolerância da Stripe é de cinco minutos. Procure o evento `billing.webhook.signature_fail`.

**Alguém pagou e continua sem premium.** Procure `billing.reconcile.drift` (a resposta guardada estava errada, ou seja um webhook não chegou) e `billing.reconcile.recovered` (a assinatura foi encontrada sem nenhum webhook ter chegado). Ambos são nível de erro. Reentregue o evento pelo painel da Stripe, ou rode `bin/data2md billing:reconcile`. Se aparecer `billing.webhook.unmatched`, a assinatura não pertence a nenhuma conta — foi criada fora do checkout do app.

**Os hashes de download aparecem no registro de acesso do EasyPanel.** É esperado: o `combined` registra a linha de requisição inteira, e `/download?hash=...` leva o identificador. Quem tem o identificador tem o arquivo, então trate esse visualizador de logs como material sensível. A exceção é `/login`, cujo formato de log omite a query string justamente porque ela carrega o token.

**Falha ao puxar a imagem.** O pacote é privado e não há credencial cadastrada. Veja a seção 3.
