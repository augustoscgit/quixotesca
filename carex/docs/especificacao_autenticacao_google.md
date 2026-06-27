# Especificação Técnica: Autenticação Google via Antigravity

## 1. Visão Geral da Arquitetura
* **Ambiente de Hospedagem:** Compartilhada (Locaweb) rodando exclusivamente **PHP**.
* **Método de Autenticação:** Google OAuth 2.0 (OpenID Connect).
* **Fluxo OAuth:** *Authorization Code Flow*. O backend PHP gerencia a troca do código de autorização pelo token de acesso de forma direta e segura com a API do Google, evitando a exposição de tokens no frontend.

## 2. Estrutura de Banco de Dados (Tabela `users`)
A estrutura a seguir define o esquema inicial necessário para suportar o armazenamento de perfis e regras de sessão.

| Campo | Tipo Sugerido | Descrição e Regras |
| :--- | :--- | :--- |
| `id` | INT / UUID (PK) | Identificador interno e único do usuário na aplicação. |
| `google_id` | VARCHAR | Identificador único retornado pelo Google (`sub`). Deve ser um campo do tipo UNIQUE. |
| `name` | VARCHAR | Nome completo do usuário. |
| `email` | VARCHAR | Endereço de e-mail retornado pelo Google (UNIQUE). |
| `profile_picture` | VARCHAR | URL da foto de perfil fornecida pela conta Google. |
| `role` | VARCHAR | Nível de acesso. **Padrão:** `usuario`. Outros possíveis: `especialista`, `admin`. |
| `status` | VARCHAR | Situação do cadastro. **Padrão:** `ativo`. Alternativo: `desligado`. |
| `remember_token` | VARCHAR / TEXT | Hash seguro para validar o "Manter-me conectado" após a sessão expirar. |

*(Nota: Campos adicionais específicos da aplicação serão incorporados a esta tabela em atualizações futuras, conforme necessidade do negócio).*

## 3. Regras de Negócio e Acesso

### 3.1. Criação e Autenticação (Porteira Aberta)
* Qualquer conta Google (`@gmail.com` ou domínios Workspace) possui permissão para tentar o login.
* Se for o **primeiro acesso** (o `google_id` não existe no banco), o sistema deve criar o usuário automaticamente atribuindo a `role` padrão (`usuario`) e `status` padrão (`ativo`).

### 3.2. Sincronização de Dados de Perfil
* A cada login bem-sucedido, o sistema deve atualizar os campos `name` e `profile_picture` no banco de dados com a carga de dados mais recente fornecida pelo Google.

### 3.3. Regra de Super Administrador (Hardcoded)
* Durante o processo de autenticação (seja na criação ou em logins subsequentes), o sistema deve sempre interceptar e verificar o e-mail: **se o e-mail for `augustosc@gmail.com`, a `role` do usuário deve ser obrigatoriamente forçada/mantida como `admin`**.

### 3.4. Gestão de Perfis Especializados
* Roles avançadas, como a de `especialista`, **não** podem ser obtidas automaticamente. Estas só poderão ser designadas aos usuários através de intervenção manual de um usuário com role `admin`.

### 3.5. Tratamento de Usuários Desligados
* Durante a tentativa de login, se o banco apontar que o `status` do usuário logado é `desligado`, o sistema **deve interromper o fluxo**, destruir qualquer variável de sessão em construção e redirecionar o usuário para uma tela de erro contendo a mensagem: *"Aviso de desligamento. Por favor, entre em contato com o administrador."*
* A mensagem não deve conter hiperlinks ou e-mails de contato visíveis.

## 4. Gestão de Sessão e Cookies
* **Sessão Padrão:** Utilizar os mecanismos nativos do PHP (`$_SESSION`) para controle de estado enquanto o navegador estiver aberto.
* **Manter-me Conectado:** 
  * O frontend deve possuir um *checkbox* perguntando se a pessoa deseja ser lembrada.
  * Caso marcado, durante a efetivação do login, o backend deverá gerar uma string aleatória segura, gravá-la no campo `remember_token` e enviar ao navegador do usuário como um cookie `HttpOnly` e `Secure`.
  * Na expiração da sessão do PHP, o sistema deve verificar a presença e a validade deste cookie para reautenticar o usuário transparentemente.

## 5. Pré-requisitos de Infraestrutura Externa
* Criação de um projeto no **Google Cloud Console**.
* Habilitação da API do Google Identity e configuração da tela de consentimento solicitando os escopos: `openid`, `profile` e `email`.
* Geração do `Client ID` e `Client Secret` a serem injetados nas variáveis de ambiente do PHP na Locaweb.
* Definição exata da **URI de Redirecionamento (Callback)** autorizada no painel do Google para coincidir com o endpoint PHP hospedado (ex: `https://seusite.com.br/auth/google/callback`).
