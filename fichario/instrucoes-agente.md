# InstruÃ§Ãµes para o Agente Especializado - MÃ³dulo FichÃ¡rio AcadÃªmico

Este documento orienta o desenvolvimento e manutenÃ§Ã£o do mÃ³dulo **FichÃ¡rio AcadÃªmico** (Sistema pessoal de fichamento e catalogaÃ§Ã£o de artigos acadÃªmicos) de forma integrada e harmonizada com a plataforma principal.

---

## 1. Escopo do MÃ³dulo
O FichÃ¡rio AcadÃªmico consiste em uma aplicaÃ§Ã£o de cadastro bibliogrÃ¡fico e tags de referÃªncia em SaÃºde do Trabalhador. O agente deste mÃ³dulo deve focar no desenvolvimento das seguintes frentes:
1. **Cadastro de ReferÃªncias**: InclusÃ£o de novos artigos, referÃªncias de texto completo, comentÃ¡rios e anotaÃ§Ãµes dinÃ¢micas.
2. **Nuvem de Tags**: VisualizaÃ§Ã£o inteligente de tags temÃ¡ticas baseadas na quantidade de artigos associados.
3. **Mecanismo de Parse e Busca**: Autocomplete de termos, processamento de BibTeX (`parse_bibtex.php`) e extratores de URL.

> [!IMPORTANT]
> **Limite de Escopo**: O repositÃ³rio da Plataforma gerencia apenas a pÃ¡gina inicial de apresentaÃ§Ã£o do mÃ³dulo (`index.php`) e suas diretrizes estÃ©ticas bÃ¡sicas. Toda a lÃ³gica de cadastro bibliogrÃ¡fico, busca semÃ¢ntica, parsing de BibTeX, gerenciamento de banco de dados PostgreSQL remoto (esquema `fichario`), autenticaÃ§Ã£o, rotas e APIs sÃ£o de responsabilidade e escopo exclusivo do desenvolvimento deste mÃ³dulo (FichÃ¡rio AcadÃªmico).


---

## 2. Padroes de Estilo e Identidade Visual (Obrigatorio)

O guia oficial de estilo, UX, interface, tema, navbar, botoes, tabelas e filtros da plataforma fica em:

`../assets/css/definicao-padroes.md`

Os arquivos locais `assets/definicao-padroes.md`, `system_md/DESIGN.md` e `system_md/palheta.md` existem apenas como pontes de compatibilidade. Regras visuais antigas deste modulo sao historicas e nao devem orientar novas telas quando conflitarem com o guia central.

---

## 3. Logotipo e Link de Retorno
Para garantir uma experiÃªncia de navegaÃ§Ã£o integrada e fluida:
- O logotipo exibido no cabeÃ§alho principal (`assets/logo-fundo-escuro-vertical.png`) **deve** estar envolvido por um link apontando de volta para a landing page da plataforma principal:
  ```html
  <a href="../"><img src="assets/logo-fundo-escuro-vertical.png" alt="FichÃ¡rio AcadÃªmico" style="height: 160px; width: auto; transition: transform 0.3s ease;"></a>
  ```
- **Nota**: Manter o caminho de retorno relativo `../` (ou `../index.html`), garantindo que o redirecionamento funcione tanto no ambiente de homologaÃ§Ã£o quanto em produÃ§Ã£o, de forma isolada de portas ou domÃ­nios locais.

---

## 4. Banco de Dados e ConexÃ£o
- O banco de dados utilizado Ã© **PostgreSQL** (verificar arquivo `bootstrap.php` e funÃ§Ã£o `db()`).
- As configuraÃ§Ãµes de conexÃ£o ficam no arquivo `secrets/.env`.
- A contagem de artigos, tags e citaÃ§Ãµes na pÃ¡gina inicial (`index.php`) deve ser lida de forma segura usando PDO.



