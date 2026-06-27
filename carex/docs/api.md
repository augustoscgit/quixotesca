# API HTTP CAREX

Todos os endpoints ficam abaixo de `public/api/`.

## Convencoes

- Respostas sao JSON UTF-8.
- Erros usam o formato:

```json
{"error":"Mensagem"}
```

- Consultas devem ser `GET`.
- Operacoes administrativas `POST` exigem autenticacao, role adequada, CSRF e ficam bloqueadas quando `DB_ALLOW_WRITES=false`.
- Paginacao usa `page` e `per_page`.
- Endpoints internos exigem sessao autenticada por `Auth::requireApiLogin()`.

## Desenvolvimento

### `GET /public/api/tables.php`

Lista objetos consultaveis e suas colunas.

Requer `role=admin`.

### `GET /public/api/rows.php`

Consulta dados paginados de tabela, view ou materialized view validada.

Requer `role=admin`.

Parametros:

| Parametro | Descricao |
| --- | --- |
| `table` | Nome do objeto validado por allowlist. |
| `page` | Pagina, padrao `1`. |
| `per_page` | Itens por pagina, limitado no backend. |
| `q` | Busca textual. |
| `sort` | Coluna real validada. |
| `dir` | `asc` ou `desc`. |
| `filters` | JSON com filtros por coluna validada. |

### `GET /public/api/unique_values.php`

Lista valores unicos de uma coluna validada para filtros dinamicos genericos.

Requer `role=admin`.

### `GET /public/api/development/objects.php`

Lista objetos tecnicos do schema: relacoes, views, materialized views, triggers, rotinas, sequencias, indices, constraints e tipos.

Requer `role=admin`.

### `GET /public/api/development/doc_content.php`

Retorna o conteudo de arquivo Markdown validado do projeto para renderizacao no modulo Desenvolvimento.

Requer `role=admin`.

## Administrativo

### `GET /public/api/admin/usuarios.php`

Lista usuarios cadastrados em `users`.

Requisitos:

- Sessao autenticada.
- `role=admin`.

Resposta principal:

```json
{
  "schema": "carex",
  "usuarios": []
}
```

### `GET /public/api/admin/materialized_views.php`

Lista materialized views do schema atual, estimativa de linhas, indices, estado de populacao e comentario.

### `POST /public/api/admin/refresh_materialized_view.php`

Endpoint administrativo para refresh individual de materialized view.

Requisitos:

- Sessao autenticada.
- `role=admin`.
- Metodo `POST`.
- Token CSRF valido.
- `DB_ALLOW_WRITES=true`.
- Nome da view presente na allowlist do catalogo.

Estado atual seguro:

- Bloqueado por padrao quando `DB_ALLOW_WRITES=false`.
- Nao deve ser usado em producao sem backup, janela operacional e autorizacao explicita.

## Trabalho

### `GET /public/api/work/matrizes.php`

Lista matrizes e indicadores iniciais.

Cada matriz inclui `total_anos_rais`, calculado como a quantidade de anos unicos em `mvw_rais_serie_ocup_subc_n_vinc`.

### `GET /public/api/work/classificacoes.php`

Lista itens da matriz com classificacao direta e classificacao final.

Parametros:

| Parametro | Descricao |
| --- | --- |
| `id_matriz` | Codigo da matriz. |
| `page` | Pagina. |
| `per_page` | Itens por pagina. |
| `q` | Busca textual. |
| `filters` | JSON com filtros dinamicos. |

Campos importantes:

| Campo | Descricao |
| --- | --- |
| `no_classificacao` | Classificacao direta de `tb_matriz_classificacao`. |
| `no_classificacao_herdada` | Classificacao final da view herdada quando aplicavel. |
| `co_nivel_classificacao_herdada` | Nivel da classificacao final (`n1` a `n5`, `nc`). |
| `classificacao_herdada_origem` | `Herdada`, `Direta no item`, `Sem heranca` ou `Nao classificada`. |

### `GET /public/api/work/unique_values.php`

Lista valores unicos para filtros da tela de matriz.

### `GET /public/api/work/vinculos_estimativas.php`

Lista estimativas de vinculos por criterio e classificacao.

Fonte: `mvw_matriz_classificacao_conciliada_vinculos`.

As quantidades de vinculos sao medias anuais. O denominador fica em `total_anos_rais`, calculado pela quantidade de anos unicos em `mvw_rais_serie_ocup_subc_n_vinc`.

Cada criterio tambem retorna uma matriz 3x3 em `criteria[].matrix`, cruzando CBO x CNAE para classes `0`, `1` e `2`. Cada celula informa classificacao resultante, media anual estimada de vinculos e percentual medio dentro do universo 3x3 daquele criterio.

Cuidados:

- Consulta somente leitura.
- Carregada sob demanda na aba.
- Usa timeout para proteger producao.
