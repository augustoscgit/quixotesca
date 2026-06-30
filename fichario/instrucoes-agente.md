# Instruções para o Agente Especializado - Módulo Fichário Acadêmico

Este documento orienta o desenvolvimento e manutenção do módulo **Fichário Acadêmico** (Sistema pessoal de fichamento e catalogação de artigos acadêmicos) de forma integrada e harmonizada com a plataforma principal.

---

## 1. Escopo do Módulo
O Fichário Acadêmico consiste em uma aplicação de cadastro bibliográfico e tags de referência em Saúde do Trabalhador. O agente deste módulo deve focar no desenvolvimento das seguintes frentes:
1. **Cadastro de Referências**: Inclusão de novos artigos, referências de texto completo, comentários e anotações dinâmicas.
2. **Nuvem de Tags**: Visualização inteligente de tags temáticas baseadas na quantidade de artigos associados.
3. **Mecanismo de Parse e Busca**: Autocomplete de termos, processamento de BibTeX (`parse_bibtex.php`) e extratores de URL.

> [!IMPORTANT]
> **Limite de Escopo**: O repositório da Plataforma gerencia apenas a página inicial de apresentação do módulo (`index.php`) e suas diretrizes estéticas básicas. Toda a lógica de cadastro bibliográfico, busca semântica, parsing de BibTeX, gerenciamento de banco de dados PostgreSQL remoto (esquema `fichario`), autenticação, rotas e APIs são de responsabilidade e escopo exclusivo do desenvolvimento deste módulo (Fichário Acadêmico).


---

## 2. Padroes de Estilo e Identidade Visual (Obrigatorio)

O guia oficial de estilo, UX, interface, tema, navbar, botoes, tabelas e filtros da plataforma fica em:

`../docs/identidade-visual-ux.md`, `../docs/tema-css-bootstrap-modulos.md` e `../docs/desenvolvimento-seguranca.md`

Arquivos historicos de paleta ou design local, quando reaparecerem em migrações antigas, devem ser tratados apenas como legado. Regras visuais antigas deste modulo nao devem orientar novas telas quando conflitarem com o guia central.

---

## 3. Logotipo e Link de Retorno
Para garantir uma experiência de navegação integrada e fluida:
- O logotipo exibido no cabeçalho principal (`assets/logo-fundo-escuro-vertical.png`) **deve** estar envolvido por um link apontando de volta para a landing page da plataforma principal:
  ```html
  <a href="../"><img src="assets/logo-fundo-escuro-vertical.png" alt="Fichário Acadêmico" style="height: 160px; width: auto; transition: transform 0.3s ease;"></a>
  ```
- **Nota**: Manter o caminho de retorno relativo `../` (ou `../index.html`), garantindo que o redirecionamento funcione tanto no ambiente de homologação quanto em produção, de forma isolada de portas ou domínios locais.

---

## 4. Banco de Dados e Conexão
- O banco de dados utilizado é **PostgreSQL** (verificar arquivo `bootstrap.php` e função `db()`).
- As configurações de conexão ficam no arquivo `secrets/.env`.
- A contagem de artigos, tags e citações na página inicial (`index.php`) deve ser lida de forma segura usando PDO.



