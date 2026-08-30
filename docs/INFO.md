# Operação — logs, arquivos e manutenção

Consulta do dia a dia com o serviço já no ar. Para subir pela primeira vez, veja
[`DEPLOY.md`](DEPLOY.md); para a referência de variáveis e dimensionamento,
[`DEPLOYMENT.md`](DEPLOYMENT.md).

Todos os comandos rodam no console do container, a partir de `/var/www/html`.

---

## 1. Onde ficam os arquivos

Tudo que persiste vive em `/var/lib/data2md/`, o volume montado no EasyPanel:

```
/var/lib/data2md/
├── md/                  markdown convertido — o produto, permanente
│   └── <hash>.md
├── db/
│   └── mappings.sqlite  hash → nome original, formato, tamanho, data
├── logs/
│   ├── app.jsonl        operacional, sem dados pessoais
│   └── audit.jsonl      nome do arquivo e IP completo
├── tmp/
│   └── slot-N.lock      locks do semáforo de conversão
└── ratelimit/           um arquivo por endereço, nome hasheado
```

Dois pontos que valem entender:

**`tmp/` deve conter apenas arquivos `.lock`.** O arquivo enviado é apagado logo
após a conversão, inclusive quando ela falha. Qualquer `.original` ali é sinal de
que uma conversão foi interrompida de forma anormal — o coletor os remove depois
de duas vezes o tempo limite de conversão.

**O nome do arquivo no disco é o hash, nunca o nome original.** É o
`mappings.sqlite` que faz a tradução. Nenhum dado pessoal aparece em nome de
arquivo.

O **código não está no volume**: fica em `/var/www/html/` e vem da imagem, que é
imutável. Alterações feitas ali pelo console desaparecem no próximo deploy.

---

## 2. Ver os logs

### Pelo painel

O canal operacional é espelhado em stdout, então aparece no visualizador de logs
do EasyPanel sem nenhuma configuração. É o caminho mais rápido para olhar o que
está acontecendo agora.

### Pelo comando

```bash
bin/data2md logs --tail=50                    # operacional
bin/data2md logs --channel=audit --tail=20    # auditoria
```

### Pelos arquivos, quando precisar filtrar

```bash
cd /var/lib/data2md/logs

grep convert.fail    app.jsonl | tail -20    # falhas de conversão
grep convert.rejected app.jsonl | wc -l      # recusas por fila cheia
grep ratelimit.block app.jsonl | wc -l       # bloqueios por limite de uso
grep gc.fail         app.jsonl               # falhas do coletor
```

O último merece atenção especial: uma falha do coletor **não interrompe a
requisição** e só deixa rastro nesse evento. Se ele aparecer de forma recorrente,
o volume passa a crescer sem que nada mais acuse.

### A diferença entre os dois canais

| | `app.jsonl` | `audit.jsonl` |
|---|---|---|
| Endereço IP | truncado (`203.0.113.0`) | completo |
| Nome do arquivo | não registra | registra |
| User-agent | não registra | registra |
| Espelha em stdout | sim | não |

Ambos os arquivos são gravados em `0640`. O que separa os canais não é a
permissão, é o conteúdo e o espelhamento: o operacional pode ir para o
visualizador de logs sem expor nada, o de auditoria não sai do volume.

Para diagnosticar um problema, `app.jsonl` basta e expõe menos. Para responder
*quem converteu o quê e quando*, é o `audit.jsonl` — que por isso tem prazo de
retenção mais curto.

---

## 3. Ver as conversões guardadas

```bash
bin/data2md list
bin/data2md list --limit=50
```

```
HASH                 UPLOAD   MARKDOWN  CREATED          NAME
90913bab89fb133       602 B       44 B  2026-08-22 15:26 relatorio.pdf
```

A coluna `MARKDOWN` mostra **`MISSING`** quando existe registro mas não existe
arquivo — inconsistência que vale investigar. O rodapé traz `showing N of M`, que
dá o total armazenado.

Ler uma conversão específica:

```bash
cat /var/lib/data2md/md/<hash>.md
```

---

## 4. Espaço em disco

O markdown convertido é permanente: não há expiração automática, então o volume
só cresce. **Configure um alerta de espaço em disco na VPS** — é a única proteção
contra o disco encher.

```bash
du -sh /var/lib/data2md/*        # consumo por área
ls -lhS /var/lib/data2md/md/ | head -10   # os maiores markdowns
ls /var/lib/data2md/md/ | wc -l           # quantas conversões existem
```

---

## 5. Remover conversões

```bash
bin/data2md delete <hash>              # remove uma
bin/data2md prune --older-than=90      # remove tudo acima de 90 dias
```

Ambos apagam o arquivo e o registro juntos, e **nenhum roda sozinho** — não
existe rotina que apague conversões por conta própria.

`prune` sem `--older-than` recusa por construção: um valor padrão silencioso ali
removeria conversões que ninguém mandou remover.

---

## 6. Manutenção

```bash
bin/data2md gc            # órfãos, contadores vencidos, logs rotacionados
bin/data2md health        # os mesmos checks de GET /health
bin/data2md config:check  # valida as variáveis de ambiente
```

O `gc` **não remove markdown que tenha registro** — ele só limpa o que ninguém
reclama. Também roda sozinho, com baixa probabilidade, a cada conversão.

---

## 7. Consultar o registro diretamente

O container não traz o cliente `sqlite3`. Para um filtro que o `list` não
oferece, use PHP:

```bash
php -r '
require "vendor/autoload.php";
$db = new Data2Md\Storage\Database("/var/lib/data2md/db/mappings.sqlite");
$sql = "SELECT hash, original_name, size_bytes,
               datetime(created_at, \"unixepoch\") AS created
          FROM mappings ORDER BY created_at DESC LIMIT 10";
foreach ($db->pdo()->query($sql) as $r) {
    printf("%s  %8d B  %s  %s\n",
        $r["hash"], $r["size_bytes"], $r["created"], $r["original_name"]);
}'
```

---

## 8. Permissões

Tudo em `/var/lib/data2md/` pertence a `www-data`. Arquivos criados como root
pelo console ficam inacessíveis para a aplicação:

```bash
chown -R www-data:www-data /var/lib/data2md
```

Os dois arquivos de log são gravados em `0640`. A página de Privacidade declara
essa permissão para o registro de acesso — se ela mudar, a declaração deixa de
ser verdadeira.
