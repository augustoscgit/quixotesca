# Padrao de agregadores hierarquicos e vocabularios controlados

As regras gerais de visual, UX, menus, botoes, tabelas, filtros e tema ficam no guia central `../../docs/definicao-padroes.md`. Este documento detalha apenas o comportamento funcional especifico dos agregadores hierarquicos do modulo CAT.

## Objetivo

O modulo CAT deve adotar um padrao unico para entidades que tenham:

- uma chave de agregacao;
- uma hierarquia navegavel;
- dados sumarizados derivados das CATs;
- pagina de detalhe;
- navegacao de volta para as CATs que compoem o agregado.

O agregador por CNPJ e a referencia inicial. A UX de fluxos hierarquicos do CAT deve incluir, no minimo, quatro fluxos principais: Territorio, CNAE, CBO e CNPJ. O mesmo desenho pode orientar CID e outros vocabularios hierarquicos do projeto.

## Principio geral

Todo agregador hierarquico deve responder a quatro perguntas:

1. Onde estou na hierarquia?
2. Quantos eventos existem neste nivel?
3. Quais filhos ou relacionados posso abrir?
4. Quais CATs compoem este numero?

## Modelo funcional

Cada agregador deve ter:

- tabela de agregacao local;
- endpoint de listagem;
- pagina de lista;
- pagina de detalhe;
- links para pai, filhos e CATs relacionadas;
- filtros padronizados;
- paginacao completa;
- ordenacao por metricas relevantes;
- vocabulario/dicionario versionado;
- documentacao do vocabulario usado.

## Camadas recomendadas

### 1. Vocabulario

Fonte controlada que define codigos, rotulos e hierarquia.

Exemplos:

- CNPJ: matriz -> filial -> CNPJ completo.
- CNAE: secao -> divisao -> grupo -> classe -> subclasse.
- CBO: grande grupo -> subgrupo principal -> subgrupo -> familia -> ocupacao.
- CID: capitulo -> grupo -> categoria -> subcategoria.
- Territorio: regiao -> UF -> municipio.

Os arquivos de vocabulario local devem ficar em `cat/src/dicionarios`, ou em pasta equivalente do modulo quando o padrao for aplicado fora de CAT.

### 2. Normalizacao

Toda chave deve ser normalizada antes de agregacao:

- remover pontuacao quando o codigo for numerico;
- preservar zeros a esquerda;
- manter codigo bruto original quando for necessario para auditoria;
- guardar codigo normalizado e rotulo resolvido;
- resolver hierarquia completa sempre que houver vocabulario.

### 3. Agregacao

A tabela agregada deve conter, no minimo:

- codigo normalizado;
- nivel hierarquico;
- codigo do pai;
- rotulo;
- caminho hierarquico;
- total de CATs;
- total de obitos;
- primeira ocorrencia;
- ultima ocorrencia;
- data de atualizacao do agregado.

Campos adicionais podem ser incluidos conforme o dominio:

- para CNPJ: matriz, filial, situacao cadastral, atividade principal, municipio, UF;
- para CNAE: secao, divisao, grupo, classe, subclasse;
- para CBO: grande grupo, subgrupo principal, subgrupo, familia, ocupacao;
- para territorio: regiao, UF, municipio.

### 4. Listagem

A pagina de lista deve exibir:

- rotulo principal sem codigo visivel quando o codigo for apenas tecnico;
- codigo em `title`/tooltip quando for util para auditoria;
- total de CATs;
- obitos;
- periodo;
- nivel hierarquico;
- pai ou caminho resumido;
- acoes icon-only.

Obrigatorio:

- paginacao completa;
- texto `Exibindo x a y de z`;
- seletor de numero de linhas;
- ordenacao por acidentes e obitos quando fizer sentido;
- filtros com dropdown a partir de 3 caracteres para vocabularios extensos.

### 5. Detalhe

A pagina de detalhe deve exibir:

- cabecalho com rotulo, codigo em metadado e nivel;
- resumo de CATs;
- distribuicoes principais;
- qualidade dos campos quando relevante;
- pai direto;
- filhos diretos;
- entidades relacionadas;
- tabela paginada das CATs que compoem o agregado;
- menu de acoes no canto superior direito.

Para detalhe hierarquico, sempre mostrar a trilha:

```text
Secao > Divisao > Grupo > Classe > Subclasse
```

ou equivalente do vocabulario.

### 6. Navegacao

Toda pagina de detalhe deve permitir:

- abrir o pai;
- abrir cada filho;
- abrir CATs relacionadas;
- voltar para a listagem;
- preservar filtros quando viavel;
- abrir entidade relacionada, por exemplo CNPJ -> CNAE, CNPJ -> territorio, CAT -> CNPJ.

## Padrao visual

- Botoes operacionais sao icon-only.
- Rotulo do botao fica em `title` e `aria-label`.
- Acoes globais ficam no canto superior direito.
- O menu horizontal superior deve ter item para cada fluxo hierarquico principal: Territorios, CNAE, CBO e CNPJ. Eles podem ficar agrupados em um dropdown `Fluxos`, desde que cada fluxo tenha item proprio.
- Tabelas usam `.table-responsive`.
- Botoes de linha devem ficar lado a lado, com `display: inline-flex`, `gap` curto e `white-space: nowrap`.
- Codigos tecnicos nao devem poluir a celula principal; usar tooltip, badge pequeno ou area de metadados.

## Padrao de filtros

Filtros de vocabulario devem seguir este comportamento:

- dropdown de sugestoes;
- opcoes apenas a partir do terceiro caractere;
- dependencias hierarquicas obrigatorias quando existirem;
- municipio depende de UF;
- subclasse CNAE pode depender de classe/grupo/divisao quando filtrado por nivel superior;
- ocupacao CBO pode depender de familia/subgrupo quando filtrada por nivel superior.

Para filtros geograficos, usar dados da propria base agregada de CATs, nao cache externo, para funcionar mesmo sem enriquecimento.

## Padrao de endpoints

Usar nomes previsiveis:

```http
GET api_etl.php?action={entidade}_aggregates
GET api_etl.php?action={entidade}_filter_options
GET api_etl.php?action={entidade}_children
GET api_etl.php?action={entidade}_detail
GET api_etl.php?action={entidade}_cats
```

Exemplos:

```http
GET api_etl.php?action=cnae_aggregates
GET api_etl.php?action=cnae_children&codigo=24
GET api_etl.php?action=cnae_detail&codigo=24512
GET api_etl.php?action=cnae_cats&codigo=24512&page=1&per_page=25
```

## Padrao de dados JSON

Resposta de listagem:

```json
{
  "success": true,
  "total": 100,
  "limit": 25,
  "offset": 0,
  "rows": [
    {
      "codigo": "24512",
      "nivel": "classe",
      "rotulo": "Fundicao de ferro e aco",
      "pai_codigo": "245",
      "caminho": [
        {"nivel": "secao", "codigo": "C", "rotulo": "Industrias de transformacao"},
        {"nivel": "divisao", "codigo": "24", "rotulo": "Metalurgia"}
      ],
      "acidentes": 3025,
      "obitos": 0,
      "primeira_ocorrencia": "2023-04-03",
      "ultima_ocorrencia": "2026-05-11"
    }
  ]
}
```

Resposta de detalhe:

```json
{
  "success": true,
  "item": {
    "codigo": "24512",
    "nivel": "classe",
    "rotulo": "Fundicao de ferro e aco",
    "pai": {"codigo": "245", "rotulo": "Fundicao"},
    "filhos": [],
    "caminho": [],
    "metricas": {
      "acidentes": 3025,
      "obitos": 0
    }
  }
}
```

## Padrao para CNPJ

O agregador CNPJ ja implementa parte deste padrao:

- `cnpjs.php`: listagem;
- `cnpj.php`: detalhe;
- `matriz.php`: detalhe de nivel superior;
- `cnpj_agregados`: tabela local;
- OpenCNPJ como enriquecimento cadastral, sempre cache-first.

Hierarquia:

```text
Matriz > Filial > CNPJ
```

O CNPJ tem uma particularidade: parte dos dados cadastrais vem de API externa. O agregado local, filtros de territorio e metricas devem continuar vindo das CATs, nao do cache externo.

## Padrao para Territorio

Hierarquia:

```text
Regiao > UF > Municipio
```

Dicionarios atuais:

- `dict_regiao.txt`
- `dict_uf.txt`
- `dict_municipio.txt`

Paginas recomendadas:

- `territorios.php`: lista agregada por territorio.
- `territorio.php?codigo=420910&nivel=municipio`: detalhe.

Metricas minimas:

- total de CATs;
- obitos;
- CNPJs distintos;
- CNAEs distintos;
- CBOs distintos;
- periodo;
- lista paginada de CATs.

## Padrao para CNAE

Hierarquia:

```text
Secao > Divisao > Grupo > Classe > Subclasse
```

Dicionarios usados:

- `dict_cnae_seca.txt`
- `dict_cnae_divi.txt`
- `dict_cnae_grup.txt`
- `dict_cnae_class.txt`
- `dict_cnae_subc.txt`
- `dict_cnae_divi_seca.txt`
- `dict_seca_divi.txt`

Paginas recomendadas:

- `cnaes.php`: lista agregada por CNAE.
- `cnae.php?codigo=24512&nivel=classe`: detalhe.

Metricas minimas:

- total de CATs;
- obitos;
- CNPJs distintos;
- municipios distintos;
- periodo;
- distribuicao por tipo de acidente;
- distribuicao por sexo quando fizer sentido;
- lista paginada de CATs.

## Padrao para CBO

Hierarquia:

```text
Grande grupo > Subgrupo principal > Subgrupo > Familia > Ocupacao
```

Dicionarios atuais:

- `dict_cbo_gg.txt`
- `dict_cbo_sp.txt`
- `dict_cbo_sg.txt`
- `dict_cbo_fa.txt`
- `dict_cbo_oc.txt`

Paginas recomendadas:

- `cbos.php`: lista agregada por CBO.
- `cbo.php?codigo=784205&nivel=ocupacao`: detalhe.

Metricas minimas:

- total de CATs;
- obitos;
- CNPJs distintos;
- CNAEs distintos;
- municipios distintos;
- periodo;
- distribuicao por tipo de acidente;
- lista paginada de CATs.

## Padrao para vocabularios do projeto

Este padrao deve valer para todo o projeto, nao apenas CAT:

- vocabulario tem codigo, rotulo, nivel, pai e caminho;
- a interface mostra rotulo primeiro;
- codigo aparece apenas quando ajuda auditoria;
- filtros usam busca com dropdown a partir de 3 caracteres;
- filhos dependem do pai quando houver hierarquia;
- paginas de detalhe sempre mostram resumo, filhos, pai e registros relacionados;
- agregacoes devem ser locais e reproduziveis;
- enriquecimento externo nunca deve ser requisito para filtro essencial.

## Criterios de pronto

Um novo agregador hierarquico esta pronto quando:

- possui vocabulario versionado;
- resolve codigo para rotulo e caminho;
- tem tabela ou view agregada;
- tem listagem paginada e ordenavel;
- tem detalhe com pai, filhos e CATs;
- tem filtros com dropdown;
- respeita icon-only para botoes;
- esta documentado no README do modulo.
