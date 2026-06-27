# Codificacao de Caracteres no Desenvolvimento

Este documento registra variacoes de codificacao encontradas no projeto CAREX para correcao posterior. Ele existe para evitar que ajustes funcionais pequenos sejam misturados com uma normalizacao ampla de acentos.

## Sintomas observados

- Alguns textos exibem mojibake em arquivos PHP e JS, por exemplo `Critério`, `Classificação`, `Página` e `vínculos`.
- O conteudo funcional continua carregando, mas os textos podem aparecer visualmente incorretos na interface.
- Patches textuais podem falhar quando tentam casar linhas com caracteres acentuados ja corrompidos.
- Parte da documentacao foi escrita em ASCII para reduzir risco enquanto a normalizacao completa nao e feita.

## Causas provaveis

- Arquivos salvos em momentos diferentes com encodings distintos.
- Conversoes parciais entre UTF-8, Windows-1252 e interpretacoes incorretas de bytes.
- Edicoes sucessivas em ambiente Windows/PowerShell com saida de terminal que nem sempre preserva acentos.

## Riscos

- Corrigir acentos em massa pode gerar diffs grandes e misturar mudanca visual com mudanca funcional.
- Uma conversao automatica mal aplicada pode alterar templates, strings de JS ou documentacao sem revisao.
- O navegador pode renderizar textos diferentes do que o terminal mostra, entao a validacao precisa ser visual.

## Recomendacao de correcao posterior

1. Abrir uma tarefa exclusiva para normalizacao de codificacao.
2. Fazer backup do workspace antes da conversao.
3. Confirmar encoding atual de cada arquivo afetado.
4. Converter arquivos de aplicacao e documentacao para UTF-8 sem BOM.
5. Normalizar textos visiveis em PHP, JS e MD.
6. Validar com `php -l`, `node --check` e navegacao local.
7. Conferir visualmente paginas principais no navegador:
   - `/public/desenvolvimento.php`
   - `/public/matrizes.php`
   - `/public/matriz.php?id_matriz=slc`
   - `/public/administrativo.php`

## Orientacao temporaria para agentes de IA

- Nao fazer normalizacao global de acentos junto com mudancas funcionais.
- Em alteracoes pequenas, preferir strings ASCII quando o arquivo ja estiver com mojibake.
- Se for necessario corrigir texto visivel com acentos, validar no navegador depois.
- Registrar no resumo final quando uma alteracao manteve texto ASCII por seguranca de encoding.
