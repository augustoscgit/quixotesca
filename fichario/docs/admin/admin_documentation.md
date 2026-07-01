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
- Quando uma decisao de interface for geral, registrar em `../../../docs/identidade-visual-ux.md` ou `../../../docs/tema-css-bootstrap-modulos.md`; se for apenas do Fichario, registrar no README do modulo apontando para os guias centrais.
- Usar "marcação" como termo padrao para o conjunto formado por citacao, observacao e tags.
- Registrar no README do modulo mudancas de fluxo bibliografico, campos persistentes novos e migracoes automaticas.
- Manter a especificacao de exportacao para agente de IA em `project_agent_export.md` e as orientacoes padrao do agente em `default_agent_instructions.md`; ambos sao editaveis pelo painel de documentacao administrativa.
