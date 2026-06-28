<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';

render_header('Verificacao de e-mail');
?>
<section class="auth-shell panel p-4">
    <div class="section-title mb-2">Mockup</div>
    <h1 class="h3 mb-3">Verificacao de e-mail</h1>
    <p class="muted">A verificacao de e-mail esta apenas preparada nesta fase. A tabela <code>email_verifications</code> ja existe, mas nenhum fluxo obrigatorio foi ativado.</p>

    <div class="panel p-3 mb-3">
        <strong>Fluxo futuro previsto</strong>
        <ol class="muted mt-2 mb-0">
            <li>Gerar token aleatorio e salvar apenas o hash.</li>
            <li>Enviar link unico por e-mail.</li>
            <li>Marcar <code>email_verified_at</code> apos validacao.</li>
            <li>Expirar tokens antigos ou ja usados.</li>
        </ol>
    </div>

    <a class="btn btn-light" href="login.php">Voltar ao login</a>
</section>
<?php render_footer(); ?>
