# Modulo CAT

## Visao geral

O modulo CAT organiza dados de Comunicacao de Acidente de Trabalho a partir de arquivos publicos, com ETL local, inspecao de registros individuais, painel analitico e agregacao por CNPJ, matriz e filial.

O schema padrao no PostgreSQL e `cat`. A conexao e configurada em `cat/src/db.php`, lendo variaveis de `cat/secrets/.env`.

## Paginas principais

- `index.php`: landing page do modulo com dashboard sintetico.
- `etl.php`: acompanhamento de extracao, carga e documentacao dos arquivos.
- `campos.php`: documentacao de campos presentes/ausentes por arquivo importado.
- `inspecao.php`: pagina de CAT individual e navegacao por registros. Historicamente o arquivo se chama `inspecao.php`, mas a interface deve tratar a entidade como CAT.
- `cnpjs.php`: agregador por CNPJ com filtros, cache OpenCNPJ e atualizacao selecionada.
- `cnpj.php`: detalhe de um CNPJ, com dados locais de CAT, dados OpenCNPJ, lista paginada de CATs e links para matriz/filial.
- `matriz.php`: visao da matriz com lista de filiais e agregados relacionados.
- `territorios.php`: base do fluxo hierarquico por territorio.
- `cnaes.php`: base do fluxo hierarquico por CNAE.
- `cbos.php`: base do fluxo hierarquico por CBO.
- `api_etl.php`: endpoints JSON internos usados pelas telas do modulo.

## Padroes de agregacao

Agregadores hierarquicos e vocabularios controlados devem seguir o padrao documentado em `cat/docs/agregadores_hierarquicos.md`.

O modelo de referencia e o agregador por CNPJ, com listagem, pagina de detalhe, hierarquia, metricas sumarizadas e navegacao para CATs. A UX de fluxos hierarquicos do CAT deve incluir itens de menu para Territorio, CNAE, CBO e CNPJ. O mesmo desenho pode orientar CID e outros vocabularios do projeto.

## ETL e rastreabilidade

Cada registro importado deve manter rastreabilidade ate o arquivo e linha de origem:

- `arquivo_importacao_id`
- `numero_linha_arquivo`
- `registro_origem_id`, composto pelo id do arquivo e numero da linha

Cada registro bruto tambem possui `hash_extended`, calculado a partir do JSON bruto completo, para identificacao de duplicatas e buscas de alta performance.

A documentacao de campos gerada pelo ETL deve registrar:

- numero de registros por arquivo;
- campos presentes;
- campos ausentes;
- formatos detectados para campos de data;
- frequencias de problemas de preenchimento quando aplicavel.

## CAT individual

A pagina de CAT deve:

- exibir apenas campos nao duplicados;
- preferir campos de codigo quando houver campo descritivo duplicado;
- incorporar descricoes de dicionarios CBO, CID, CNAE e territorio dentro dos blocos semanticos;
- agrupar campos em dados do trabalhador, acidente, empresa, unidade administrativa e outros;
- manter blocos semanticos empilhados;
- oferecer botao icon-only para abrir o JSON bruto em modal;
- navegar corretamente para o registro especifico quando a origem for uma lista geral ou a lista de CATs de um CNPJ.

Quando uma CAT individual for aberta e houver CNPJ valido sem dados cacheados, a aplicacao pode aproveitar para buscar/enriquecer esse CNPJ respeitando os controles de seguranca e cache.

## Agregador CNPJ

O agregador por CNPJ usa a tabela local `cnpj_agregados` como base de navegacao e estatisticas. A listagem deve permitir filtros por:

- busca livre;
- estado/UF;
- municipio;
- matriz;
- filial;
- razao social ou nome fantasia;
- situacao cadastral.

A busca livre deve cruzar dados locais da CAT e campos resumidos cacheados da OpenCNPJ.

A interface deve:

- mostrar total de resultados e intervalo exibido;
- permitir ordenacao por acidentes e obitos;
- exibir CNAE pelo rotulo do dicionario/campo agregado, sem codigo visivel na tabela;
- exibir territorio sem codigo, mantendo municipio e UF legiveis;
- usar dropdown de sugestoes para estado/UF e municipio, exibindo opcoes apenas a partir de 3 caracteres;
- manter municipio dependente de estado/UF selecionado;
- permitir selecionar CNPJs e atualizar dados na API em lote pequeno;
- priorizar sempre o cache local;
- nao fazer varredura massiva na API externa.

## Dicionarios

Os dicionarios locais ficam em `cat/src/dicionarios`.

Para CNAE, a aplicacao usa a hierarquia:

- `dict_cnae_seca.txt`: secao;
- `dict_cnae_divi.txt`: divisao;
- `dict_cnae_grup.txt`: grupo;
- `dict_cnae_class.txt`: classe;
- `dict_cnae_subc.txt`: subclasse;
- `dict_cnae_divi_seca.txt`: relacao divisao -> secao;
- `dict_seca_divi.txt`: relacao secao -> divisoes.

Na tabela de CNPJs, o CNAE deve exibir apenas o melhor rotulo resolvido pela hierarquia. A hierarquia completa pode aparecer em `title`/tooltip, sem codigo visivel na celula.

## OpenCNPJ

A integracao com OpenCNPJ esta documentada em `cat/docs/opencnpj.md`.

Resumo operacional:

- o backend valida CNPJ antes de qualquer chamada externa;
- endpoints externos sao acionados apenas por `POST` interno;
- `dados_json` armazena o JSON bruto completo;
- campos resumidos sao extraidos para filtros e listagens;
- a pagina `cnpj.php` mostra dados completos relevantes e o JSON armazenado para auditoria;
- atualizacoes sao progressivas e leves para a API.

## Dashboard e qualidade

O dashboard deve apresentar distribuicoes antes de qualidade dos campos.

Na qualidade dos campos:

- separar campos unicos de data;
- agrupar invalido, nao preenchido, ignorado, nao classificado, indeterminado e nao informado quando fizer sentido analitico;
- mostrar graficos em porcentagem;
- deixar frequencias absolutas em `title`/tooltip;
- remover campos sem variacao analitica, como especie do beneficio quando todos os registros informados usam o mesmo valor.

## Diretrizes de interface

As regras gerais de visual, UX, tema, botoes, filtros, tabelas, skeleton e menu ficam no guia central:

`../docs/definicao-padroes.md`

No CAT, permanecem como especificas do modulo:

- fluxo hierarquico por Territorio, CNAE, CBO e CNPJ;
- agregadores com sumarizacao e navegacao para CATs;
- CAT individual com blocos semanticos empilhados;
- incorporacao de dicionarios CBO, CID, CNAE e territorio na leitura dos registros;
- comportamento documentado em `docs/agregadores_hierarquicos.md`.
## Validacao rapida

Com PHP disponivel no ambiente:

```powershell
php -l cat\api_etl.php
php -l cat\cnpjs.php
php -l cat\cnpj.php
php -l cat\inspecao.php
php -l cat\etl.php
```

Endpoints uteis para teste local:

```http
GET http://localhost/quixotesca/public_html/cat/api_etl.php?action=cnpj_aggregates&limit=10
GET http://localhost/quixotesca/public_html/cat/cnpjs.php
GET http://localhost/quixotesca/public_html/cat/cnpj.php?cnpj=84683374000300
GET http://localhost/quixotesca/public_html/cat/inspecao.php
```

