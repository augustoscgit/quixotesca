# Indice da Documentacao CAREX

Este indice orienta desenvolvedores e agentes de IA que precisam continuar o projeto CAREX.

## Leitura obrigatoria antes de alterar codigo

1. [Seguranca Operacional](seguranca.md)
2. [Guia para IA](guia-ia.md)
3. [Visao Geral da Aplicacao](visao-geral.md)
4. [Arquitetura](arquitetura.md)
5. [API HTTP](api.md)
6. [Banco de Dados](banco-dados.md)

## Modulos

- [Modulo de Trabalho / Matrizes](modulo-trabalho.md)
- [Modulo Administrativo](modulo-administrativo.md)
- [Modulo de Desenvolvimento](modulo-desenvolvimento.md)
- [Identidade Visual](identidade-visual.md)
- [Metodologia Carex-BR](carex_br_metodologia_especialistas.md)

## Autenticacao e producao

- [Especificacao de Autenticacao Google](especificacao_autenticacao_google.md)
- [Migracao para Producao](migracao_producao.md)

## Desenvolvimento

- [Guia de Desenvolvimento](guia-desenvolvimento.md)
- [Codificacao de Caracteres](desenvolvimento-codificacao.md)
- [Mapa de Arquivos](mapa-de-arquivos.md)
- [Preparacao para Git](git.md)
- [Decisoes e Pendencias](decisoes-e-pendencias.md)

## Regra principal

A base PostgreSQL conectada e de producao ou equivalente sensivel, e nao ha backup confirmado. Trate qualquer operacao de escrita como proibida ate que exista autorizacao explicita, backup validado e janela operacional.

Excecoes ja existentes no sistema, como autenticacao de usuarios e settings locais, devem permanecer delimitadas e nao justificam liberar escrita ampla no banco.
