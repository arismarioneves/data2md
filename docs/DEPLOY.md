# Deploy — do Git ao EasyPanel

A imagem é construída pelo GitHub Actions e publicada no GHCR. O EasyPanel apenas consome a imagem pronta.

Este é o passo a passo prático. Para a tabela completa das 19 variáveis, a
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

O workflow `.github/workflows/image.yml` dispara em push para `main` e em tags `v*`.

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

Para publicar uma versão fixa:

```bash
git tag v1.1.0
git push origin v1.1.0
```

Isso gera `:1.1.0` e `:1.1` além de `:main`.

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

Conversão de ponta a ponta:

```bash
curl -sS -X POST https://seu-dominio.com.br/api \
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

Troque a tag da imagem no EasyPanel para uma versão anterior e faça *Deploy*:

```
ghcr.io/arismarioneves/data2md:1.0.0
```

---

## 11. Operação

Comandos disponíveis no container:

```bash
bin/data2md health          # mesmos checks de /health
bin/data2md config:check    # valida as variáveis de ambiente
bin/data2md logs --tail=50  # últimas entradas do log operacional
bin/data2md logs --channel=audit --tail=50
bin/data2md gc              # remove órfãos, contadores vencidos, logs rotacionados
bin/data2md delete <hash>   # remove uma conversão
bin/data2md prune --older-than=90
```

O log operacional também aparece no visualizador de logs do EasyPanel.

---

## 12. Ciclo de vida dos dados

| Dado | Retenção |
|---|---|
| Arquivo enviado | descartado após a conversão |
| Markdown convertido | permanente, até `delete` ou `prune` |
| Log operacional | 90 dias |
| Registro de acesso | 30 dias |

O Markdown não expira sozinho, então o volume cresce continuamente. Configure um alerta de espaço em disco na VPS.

---

## 13. Problemas comuns

**O container não sobe.** O entrypoint valida a configuração antes de iniciar o servidor e nomeia a variável ausente ou inválida no log. Leia o log do serviço.

**HTTP 503 com frequência.** A fila de conversão está cheia. Aumente `CONVERT_CONCURRENCY` se houver CPU disponível, ou reduza `CONVERT_QUEUE_WAIT_SECONDS` para recusar mais rápido.

**HTTP 422 "requer OCR".** O documento não tem camada de texto — é uma imagem digitalizada. Comportamento esperado.

**HTTP 413.** Arquivo acima de `MAX_FILE_SIZE_MB`.

**Limite de uso não funciona.** `TRUSTED_PROXIES` não corresponde à rede do proxy. Confira conforme a seção 5.

**Falha ao puxar a imagem.** O pacote é privado e não há credencial cadastrada. Veja a seção 3.
