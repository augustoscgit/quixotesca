# InstruÃ§Ãµes para o Agente Especializado - MÃ³dulo CAT

Este documento orienta o desenvolvimento e manutenÃ§Ã£o do mÃ³dulo **CAT** (ComunicaÃ§Ã£o de Acidente de Trabalho) de forma integrada e harmonizada com a plataforma principal.

---

## 1. Escopo do MÃ³dulo
O CAT consiste em um sistema de controle de comunicaÃ§Ãµes de acidente de trabalho que extrai e processa dados abertos pÃºblicos do INSS via ETL. O agente deste mÃ³dulo deve focar nas seguintes frentes:
1. **Listagem e Status do ETL**: VisualizaÃ§Ã£o dos arquivos de dados abertos disponÃ­veis na API CKAN do INSS com situaÃ§Ã£o de extraÃ§Ã£o e carga no banco de dados.
2. **Processamento em Lotes (Batching)**: Pipeline AJAX incremental para extrair, ler, converter linhas de CSV em JSON normalizado e carregar na tabela de staging.
3. **Gerenciamento de Cache**: EliminaÃ§Ã£o automÃ¡tica de arquivos temporÃ¡rios ZIP/CSV do servidor local apÃ³s a carga bem-sucedida.

> [!IMPORTANT]
> **Limite de Escopo**: O repositÃ³rio da Plataforma gerencia apenas a pÃ¡gina inicial de apresentaÃ§Ã£o do mÃ³dulo (`index.php`) e suas diretrizes estÃ©ticas bÃ¡sicas. Toda a lÃ³gica de extraÃ§Ã£o de ZIP, parsing de CSV, normalizaÃ§Ã£o de campos para lowercase snake_case, banco de dados remoto PostgreSQL (esquema `cat`), autenticaÃ§Ã£o e rotas internas sÃ£o de responsabilidade e escopo exclusivo do desenvolvimento deste mÃ³dulo (CAT).

---

## 2. Padroes de Estilo e Identidade Visual (Obrigatorio)

O guia oficial de estilo, UX, interface, tema, navbar, botoes, tabelas e filtros da plataforma fica em:

`docs/identidade-visual-ux.md` e `docs/desenvolvimento-seguranca.md`

As regras especificas do modulo CAT ficam em `README.md` e `docs/agregadores_hierarquicos.md`, apenas para fluxos, agregadores, vocabularios e comportamento analitico do modulo. Regras visuais antigas deste modulo sao historicas e nao devem orientar novas telas quando conflitarem com o guia central.

---

## 3. Logotipo e Link de Retorno
Para garantir uma experiÃªncia de navegaÃ§Ã£o integrada e fluida:
- O logotipo horizontal oficial (`assets/logo-fundo-escuro-horizontal.png`) deve estar envolvido por um link apontando de volta para a landing page da plataforma principal:
  ```html
  <a class="navbar-brand d-flex align-items-center gap-3" href="../">
      <img src="../assets/logo-fundo-escuro-horizontal.png" alt="Plataforma Renast Online" style="height: 32px; width: auto;">
      <span class="text-white-50">|</span>
      <span style="font-weight: 700; letter-spacing: 0.5px; font-size: 1.1rem;">CAT <span class="text-muted" style="font-weight: 300; font-size: 0.85rem;">| ETL de Dados Abertos</span></span>
  </a>
  ```

---

## 4. Banco de Dados e ConexÃ£o
- O banco de dados utilizado Ã© **PostgreSQL** (verificar arquivo `src/db.php` e funÃ§Ã£o `getDBConnection()`).
- O schema PostgreSQL padrÃ£o do mÃ³dulo Ã© `cat`.
- VariÃ¡veis de ambiente sÃ£o lidas de `secrets/.env`.
- Em caso de falha de conexÃ£o com a base de dados, a landing page principal (`index.php`) deve tratar o erro silenciosamente via `try-catch`, exibindo o status de conexÃ£o "Desconectado" e os contadores zerados para que a navegaÃ§Ã£o do usuÃ¡rio nÃ£o seja interrompida.
---

## 5. Regras Funcionais Atuais

- O arquivo `inspecao.php` existe por historico, mas a entidade de interface deve ser tratada como **CAT**.
- Registros importados devem manter `registro_origem_id`, composto por id do arquivo e numero da linha, para rastreabilidade.
- `hash_extended` deve ser calculado sobre o JSON bruto completo do registro.
- Campos duplicados devem ser ocultados na visualizacao da CAT; quando houver codigo e descricao duplicados, preferir o campo de codigo e incorporar a descricao via dicionario.
- A CAT individual deve agrupar campos em dados do trabalhador, acidente, empresa, unidade administrativa e outros.
- Blocos semanticos de CAT devem ser empilhados, com diagramacao interna responsiva para leitura.
- O JSON bruto da CAT deve abrir em modal por botao icon-only.

---

## 6. Agregador por CNPJ

- `cnpjs.php` lista CNPJs com CATs registradas a partir da tabela local `cnpj_agregados`.
- A listagem deve permitir filtros por busca livre, estado/UF, municipio, matriz, filial, razao social/fantasia e situacao.
- Filtros de estado/UF e municipio devem seguir o padrao do projeto: dropdown com busca a partir de 3 caracteres; municipio sempre dependente de estado/UF.
- Busca livre deve cruzar CNPJ, matriz, filial, CNAE, territorio e dados resumidos cacheados da OpenCNPJ.
- A tabela deve permitir ordenacao por acidentes e obitos.
- A interface deve mostrar intervalo exibido e total de resultados.
- Cada linha deve ter checkbox para selecao de CNPJs.
- Atualizacao na OpenCNPJ deve ser explicita, em lotes pequenos, acionada pelos CNPJs selecionados.
- O cache e sempre prioritario. Nao deve haver botao visivel de "usar cache".
- `cnpj.php` mostra detalhe da empresa, resumo de CATs, lista paginada de CATs, relacao com matriz/filial e dados completos cacheados da OpenCNPJ.
- A UX de fluxos hierarquicos deve manter item de menu para Territorio, CNAE, CBO e CNPJ, preferencialmente agrupados em dropdown `Fluxos`.

---

## 7. OpenCNPJ

- A integracao esta documentada em `docs/opencnpj.md`.
- Chamadas externas devem ocorrer apenas no backend.
- CNPJ deve ser validado com digitos verificadores antes de chamada externa.
- O JSON completo retornado pela API deve ser armazenado em `cnpj_cache_opencnpj.dados_json`.
- Campos resumidos podem ser extraidos para filtros, listagem e leitura rapida, mas nao substituem o JSON bruto.
- A interface `cnpj.php` deve exibir endereco, natureza juridica, porte, abertura, capital social, CNAEs secundarios, quadro societario quando presente e JSON completo armazenado.
- Usar lotes leves e progressivos. Nao fazer enriquecimento massivo da base via interface.

---

## 8. Documentacao

- `README.md` e o indice geral do modulo.
- `docs/opencnpj.md` documenta a API externa, cache, seguranca, endpoints internos e fluxo operacional.
- `docs/agregadores_hierarquicos.md` define o padrao para agregadores por CNPJ, CNAE, CBO, CID, territorio e outros vocabularios controlados.
- Novas regras de interface ou comportamento recorrente devem ser registradas neste arquivo ou no `README.md`, conforme o publico: manutencao por agente ou documentacao geral do modulo.



