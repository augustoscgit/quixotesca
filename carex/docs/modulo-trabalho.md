# Modulo de Trabalho CAREX

Este modulo sera a area operacional do CAREX. O primeiro corte apresenta a pagina inicial com boxes gerados a partir de `tb_matriz` e a pagina de detalhe de cada matriz.

## Tela inicial

Fonte principal:

- `tb_matriz.id_matriz`
- `tb_matriz.no_matriz`
- `tb_matriz_classificacao`
- `mvw_matriz_classificacao_herdada`

Indicadores auxiliares atuais:

- Total de itens em `tb_matriz_classificacao`.
- Total de itens classificados.
- Percentual classificado.
- Quantidade de tipos de objeto vinculados.
- Quantidade de anos unicos em `mvw_rais_serie_ocup_subc_n_vinc`.

## Estado atual

- Interface somente leitura.
- Cards de matriz como entrada visual do fluxo de trabalho.
- Botao "Abrir matriz" leva ao detalhe operacional da matriz.
- Aba de classificacoes lista itens, classificacao direta e classificacao final.
- Filtros dinamicos incluem origem, tipo, classificacao direta, classificacao final, nivel da classificacao final e origem da classificacao final.
- Aba de estimativas de vinculos mostra, por criterio, a distribuicao de vinculos RAIS estimados por classificacao e percentual.

## Detalhe da matriz

A pagina `/public/matriz.php?id_matriz={id}` exibe os itens de `tb_matriz_classificacao` e complementa os itens de nivel 5 com a classificacao herdada de `mvw_matriz_classificacao_herdada`.

Quando a classificacao herdada vem de nivel superior (`n1` a `n4`), a interface mostra uma unica tag com classificacao final e origem, como `Condicionalmente exposto - Herdada - Nivel 1`. Quando vem do proprio item (`n5`), mostra `Direta - Item`. Categorias CBO/CNAE sem heranca aparecem como `Sem heranca`.

A aba de estimativas usa `mvw_matriz_classificacao_conciliada_vinculos` para calcular a media anual de `rais_n_vinc` por criterio (`co_classificacao_conciliada_par_crit_1` a `co_classificacao_conciliada_par_crit_10`) e por classificacao. O denominador e a quantidade de anos unicos em `mvw_rais_serie_ocup_subc_n_vinc`. A consulta e somente leitura, carregada sob demanda e limitada por timeout para proteger a base de producao.

Na mesma aba, cada criterio aparece em um box proprio. O box combina estimativas gerais com barras horizontais e a tabela 3x3 alinhada ao lado em telas largas. Em telas menores, os criterios ficam empilhados e cada box empilha barras e 3x3 para preservar leitura. Cada tabela cruza CBO x CNAE para as classificacoes 0, 1 e 2, colorindo cada celula pela classificacao resultante e exibindo media anual de vinculos com percentual medio entre parenteses.

## Endpoint

| Endpoint | Metodo | Descricao |
| --- | --- | --- |
| `/public/api/work/matrizes.php` | GET | Lista matrizes e indicadores de classificacao. |
| `/public/api/work/classificacoes.php` | GET | Lista itens da matriz com classificacao direta e herdada. |
| `/public/api/work/unique_values.php` | GET | Lista valores unicos para filtros da tela de matriz. |
| `/public/api/work/vinculos_estimativas.php` | GET | Lista estimativas de vinculos e percentuais por criterio e classificacao. |
