# Uso da OpenCNPJ no modulo CAT

## Objetivo

O agregador de CNPJs do modulo CAT usa a OpenCNPJ para enriquecer, sob demanda, dados cadastrais de empregadores presentes nos registros de Comunicacao de Acidente de Trabalho.

A integracao nao faz varredura massiva. A tabela de acidentes por CNPJ continua sendo carregada pela base local `cnpj_agregados`; a OpenCNPJ entra como enriquecimento progressivo, cacheado e acionado explicitamente pelo usuario.

## Fonte externa

- Documentacao: https://opencnpj.org/
- Endpoint usado: `GET https://api.opencnpj.org/{CNPJ}?dataset=receita`
- Autenticacao: sem chave ou token, conforme documentacao publica.
- Status esperados: `200` quando encontrado, `404` quando nao encontrado.

## Componentes locais

- `cat/src/opencnpj.php`
  - Normaliza e valida CNPJ, incluindo digitos verificadores.
  - Controla cache e TTL.
  - Executa chamada HTTP com host fixo e HTTPS.
  - Armazena o JSON bruto completo em `cnpj_cache_opencnpj.dados_json`.
  - Extrai campos resumidos para listagem e filtros.

- `cat/api_etl.php`
  - `cnpj_aggregates`: lista CNPJs agregados com filtros.
  - `cnpj_cache_status`: consulta somente cache local.
  - `fetch_opencnpj`: consulta um CNPJ via POST.
  - `fetch_opencnpj_batch`: consulta lote pequeno via POST.
  - `refresh_cnpj_aggregates`: atualiza a tabela local agregada por CNPJ.

- `cat/cnpjs.php`
  - Lista CNPJs com acidentes registrados.
  - Filtra por busca livre, estado, municipio, matriz, filial, razao social/fantasia e situacao.
  - Mostra cache OpenCNPJ quando ja existe.
  - Permite selecionar CNPJs e atualizar dados na API em lotes pequenos.

- `cat/cnpj.php`
  - Exibe pagina de detalhe de um CNPJ.
  - Mostra resumo local de CATs, dados cacheados da OpenCNPJ, dados completos extraidos do JSON e o JSON completo armazenado.
  - Permite atualizar o CNPJ na API pelo menu de acoes do canto superior direito.

## Tabelas

### `cnpj_agregados`

Tabela local de apoio para navegacao por empresa.

Campos principais:

- `cnpj_digits`
- `matriz`
- `filial`
- `tipo_empregador`
- `cnae_codigo`
- `cnae_descricao`
- `municipio_empregador`
- `uf_empregador`
- `acidentes`
- `obitos`
- `primeira_ocorrencia`
- `ultima_ocorrencia`
- `atualizado_em`

### `cnpj_cache_opencnpj`

Cache por CNPJ e dataset.

Campos principais:

- `cnpj_digits`
- `dataset`
- `status_http`
- `dados_json`
- `razao_social`
- `nome_fantasia`
- `situacao`
- `atividade_principal`
- `municipio`
- `uf`
- `consultado_em`
- `expira_em`
- `erro`
- `tentativas`

TTL usado:

- `200`: 30 dias
- `404`: 7 dias
- erro transitorio ou HTTP inesperado: 2 horas

### `cnpj_opencnpj_log`

Auditoria minima de chamadas externas.

Nao guarda payload completo. Registra:

- CNPJ
- dataset
- status HTTP
- duracao
- origem
- erro resumido
- data/hora

## Cache e atualizacao

O cache e sempre prioritario na interface:

1. A listagem `cnpjs.php` carrega primeiro a tabela local `cnpj_agregados`.
2. Para os CNPJs visiveis, consulta apenas o cache local em blocos de ate 25.
3. A chamada externa nao ocorre automaticamente ao abrir a listagem.
4. Para atualizar a API, o usuario marca CNPJs na tabela e usa o botao de nuvem.
5. Cada lote externo e limitado a 5 CNPJs.
6. Na pagina `cnpj.php`, o botao de nuvem atualiza o CNPJ aberto e recarrega a tela para mostrar o JSON completo atualizado.

Nao ha botao de "usar cache": o comportamento padrao e cache first. Quando a API falha, dados expirados podem ser usados como fallback seguro pelo backend.

## Dados armazenados e exibidos

A resposta completa da OpenCNPJ e armazenada em `dados_json` como JSONB. A interface exibe:

- razao social;
- nome fantasia;
- situacao cadastral;
- atividade principal;
- municipio e UF;
- endereco quando presente;
- natureza juridica;
- porte;
- data de abertura;
- capital social;
- conjunto completo de CNAEs secundarios disponiveis na resposta;
- quadro societario quando disponivel;
- JSON completo armazenado para auditoria.

Como a OpenCNPJ pode variar a estrutura dos campos, `cnpj.php` usa caminhos alternativos para extrair dados comuns, mantendo o JSON bruto como fonte integral.

## Seguranca

As chamadas a OpenCNPJ sao feitas apenas pelo backend.

Regras implementadas:

- CNPJ precisa ter 14 digitos validos e passar pelos digitos verificadores.
- Corpo JSON dos endpoints internos limitado a 8 KB e analisado com `JSON_THROW_ON_ERROR`.
- Resposta externa limitada a 1 MB; respostas maiores sao recusadas.
- Resposta externa e saneada antes de gravar no cache: strings tem caracteres de controle removidos, tamanho limitado e profundidade/quantidade de campos restringidas.
- Chamadas cURL usam HTTPS, verificacao TLS ativa, redirects desativados, proxy desativado, timeout curto e host fixo.
- Host externo fixo: `api.opencnpj.org`.
- Sem envio de cookies, sessao, tokens ou cabecalhos internos.
- Respostas com marcadores inesperados de script/codigo sao recusadas.
- Ha limite de taxa global, por CNPJ e para atualizacoes forcadas, baseado em `cnpj_opencnpj_log`.
- Endpoints que chamam a API externa aceitam apenas `POST`.
- Lote externo limitado a 5 CNPJs por chamada.
- A API JSON local envia `X-Content-Type-Options: nosniff`.
- Retorno externo e tratado como dado nao confiavel e renderizado com escape no frontend.

## Endpoints internos

### Listar agregados

```http
GET cat/api_etl.php?action=cnpj_aggregates&q=tupy&estado=SC&municipio=Joinville&matriz=84683374&situacao=Ativa&limit=50&offset=0
```

Filtros aceitos:

- `q`: busca livre por CNPJ, matriz, filial, CNAE, municipio, UF, razao social ou fantasia.
- `estado`
- `municipio`
- `matriz`
- `filial`
- `razao_social`
- `situacao`
- `limit`
- `offset`
- `sort`
- `dir`

### Sugerir estado/UF ou municipio para filtros

```http
GET cat/api_etl.php?action=cnpj_filter_options&type=estado&q=San
GET cat/api_etl.php?action=cnpj_filter_options&type=municipio&estado=Santa%20Catarina&q=Joi
```

Regras:

- `q` precisa ter ao menos 3 caracteres.
- `type=municipio` exige `estado`.
- Municipio e dependente de estado/UF.
- A consulta usa apenas a base local `cnpj_agregados`, derivada das CATs, para funcionar mesmo quando nao ha cache OpenCNPJ.

### Consultar cache

```http
GET cat/api_etl.php?action=cnpj_cache_status&cnpjs=60701190000104,84683374000300
```

Limite: 25 CNPJs por chamada.

### Consultar um CNPJ

```http
POST cat/api_etl.php?action=fetch_opencnpj
Content-Type: application/json

{
  "cnpj": "60701190000104",
  "force": false,
  "allow_stale": true
}
```

### Consultar lote pequeno

```http
POST cat/api_etl.php?action=fetch_opencnpj_batch
Content-Type: application/json

{
  "cnpjs": ["60701190000104", "84683374000300"],
  "force": true,
  "allow_stale": true
}
```

Limite: 5 CNPJs por chamada.

## Boas praticas operacionais

- Nao usar essa integracao para enriquecer todos os CNPJs da base.
- Para carga massiva, preferir datasets/BigQuery indicados pela OpenCNPJ.
- Manter lotes pequenos e acionados pelo usuario.
- Se houver erros `429` ou instabilidade, aguardar o cache curto expirar antes de tentar novamente.
- Usar a listagem local `cnpj_agregados` para navegacao analitica; usar OpenCNPJ apenas para enriquecimento cadastral.
