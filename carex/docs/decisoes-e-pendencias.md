# Decisoes e Pendencias CAREX

## Decisoes tomadas

- A aplicacao e PHP puro, Bootstrap e HTML/JS, sem framework adicional.
- A base PostgreSQL deve operar em modo somente leitura por padrao.
- Credenciais reais ficam somente em `.env` ou variaveis de ambiente.
- O modulo Desenvolvimento concentra inventario tecnico e leitura de objetos.
- O modulo Trabalho e a entrada operacional por matriz.
- A classificacao final na matriz vem de `mvw_matriz_classificacao_herdada`.
- Categorias CBO/CNAE sem consolidacao aplicavel sao exibidas como `Sem heranca`.
- Estimativas de vinculos sao carregadas sob demanda para proteger producao.
- Refresh de materialized views existe como funcionalidade administrativa, mas fica bloqueado quando `DB_ALLOW_WRITES=false`.

## Pendencias tecnicas

1. Criar autenticacao e autorizacao por perfil.
2. Criar usuario PostgreSQL exclusivamente somente leitura para a aplicacao.
3. Rotacionar senha ja compartilhada em conversa e ambiente local.
4. Definir processo de backup antes de qualquer escrita ou refresh real.
5. Implementar modulo de Resultados Herdados.
6. Revisar encoding de alguns textos antigos que ainda aparecem com mojibake em partes da interface.
7. Avaliar indices nas materialized views grandes antes de novas consultas agregadas.
8. Definir pipeline Git/GitHub quando `git` estiver disponivel no ambiente.

## Nao fazer sem aprovacao explicita

- Habilitar `DB_ALLOW_WRITES=true`.
- Rodar refresh real de materialized view.
- Executar script SQL de permissao em producao.
- Alterar estrutura do banco.
- Remover timeouts ou read-only da conexao.
