# Documentacao administrativa

## Objetivo

Esta area permite manter documentos internos do Fichario a partir do painel administrativo, usando arquivos Markdown versionaveis em `docs/` e documentos selecionados de `system_md/`.

## Uso esperado

- A leitura do documento deve ser o modo padrao.
- A edicao deve ser acionada pelo botao Editar, transformando a area de leitura em um editor amplo.
- Salvar grava o Markdown no arquivo correspondente.
- Cancelar descarta mudancas nao salvas e retorna para a leitura.

## Documentos disponiveis

- Requisitos da aplicacao: ambiente, hospedagem, banco de dados, seguranca e versionamento.
- Documentacao administrativa: funcionamento desta area.
- Orientacoes de desenvolvedor: convencoes para manter e evoluir o sistema.
- Migracao para MySQL: proposta, riscos e planejamento para MySQL 5.7 ou superior.
- Seguranca no FTP: tratamento de pastas publicas, privadas e protegidas na hospedagem compartilhada.

## Cuidados

- Nao registrar senhas, tokens, chaves de API, credenciais de banco ou dados pessoais sensiveis.
- Manter os arquivos em UTF-8 sem BOM.
- Preferir textos objetivos, operacionais e uteis para manutencao.
- Quando uma decisao de interface for tomada durante o desenvolvimento, registrar o padrao nas orientacoes de desenvolvedor.
- Usar "nota" como termo padrao para o conjunto formado por citacao, observacao e tags.
