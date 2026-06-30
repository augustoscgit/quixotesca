<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
?>
<!doctype html>
<html lang="pt-br" data-module="fichario">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacidade e Cookies - Fichário Acadêmico</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="assets/app.css?v=20260629-vanilla" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>


    <?php render_navbar(''); ?>

    <main class="container py-4 main-container">
        <article class="card p-4 p-md-5">
            <h1 class="h3 text-body fw-bold mb-3">Privacidade e Cookies</h1>
            <p class="text-secondary">
                O Fichário Acadêmico utiliza cookies e armazenamento local apenas para funcionamento, segurança e experiência de uso da aplicação.
                Esta página resume o tratamento adotado em conformidade com boas práticas de transparência e com princípios da LGPD.
            </p>

            <section class="mt-4">
                <h2 class="h5 text-body fw-semibold">Cookies Necessários</h2>
                <p class="text-secondary mb-2">
                    A aplicação usa cookie de sessão PHP, como `PHPSESSID`, para manter login, proteger formulários contra CSRF e preservar a navegação autenticada.
                    Esses cookies são necessários para o funcionamento do sistema e não são usados para publicidade.
                </p>
                <p class="text-secondary mb-0">
                    O aviso de cookies é lembrado no navegador por `localStorage`, sem criar cookie adicional para essa finalidade.
                </p>
            </section>

            <section class="mt-4">
                <h2 class="h5 text-body fw-semibold">Serviços Externos</h2>
                <p class="text-secondary mb-0">
                    Quando recursos como Google reCAPTCHA, fontes externas ou bibliotecas CDN estiverem habilitados, esses provedores podem receber dados técnicos da navegação e aplicar suas próprias políticas.
                    Em ambientes de produção, mantenha apenas serviços realmente necessários.
                </p>
            </section>

            <section class="mt-4">
                <h2 class="h5 text-body fw-semibold">Retenção de Sessões</h2>
                <p class="text-secondary mb-0">
                    Sessões inativas expiram automaticamente. Arquivos de sessão antigos ou vazios são limpos periodicamente para reduzir exposição e acúmulo desnecessário.
                </p>
            </section>

            <section class="mt-4">
                <h2 class="h5 text-body fw-semibold">Indexação</h2>
                <p class="text-secondary mb-0">
                    A aplicação inclui metadados técnicos para mecanismos de busca, como descrição, URL canônica e dados estruturados.
                    A indexação pública é controlada por configuração do servidor, para evitar exposição indevida de áreas administrativas, dados pessoais ou conteúdo sensível.
                </p>
            </section>
        </article>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="assets/app.js?v=20260603c"></script>
</body>
</html>
