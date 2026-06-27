<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

render_header('Cadastro', 'cadastro');
?>
<section class="auth-shell panel p-4">
    <div class="section-title mb-2">Mockup</div>
    <h1 class="h3 mb-3">Solicitar cadastro</h1>
    <p class="muted">O auto-cadastro publico ainda nao esta ativo. Esta tela registra a forma prevista para desenvolvimento futuro, sem gravar dados no banco nesta fase.</p>

    <form class="d-grid gap-3" aria-disabled="true">
        <div>
            <label class="form-label" for="name">Nome completo</label>
            <input class="form-control" id="name" placeholder="Nome da pessoa interessada" disabled>
        </div>
        <div>
            <label class="form-label" for="email">E-mail institucional</label>
            <input class="form-control" id="email" type="email" placeholder="usuario@exemplo.org" disabled>
        </div>
        <div>
            <label class="form-label" for="username">Usuario desejado</label>
            <input class="form-control" id="username" placeholder="usuario" disabled>
        </div>
        <div>
            <label class="form-label" for="reason">Justificativa de acesso</label>
            <textarea class="form-control" id="reason" rows="4" disabled></textarea>
        </div>
        <button class="btn btn-secondary" type="button" disabled>Enviar solicitacao</button>
    </form>

    <hr class="border-secondary my-4">
    <h2 class="h6">Orientacao futura</h2>
    <p class="muted mb-0">Quando ativado, este fluxo deve criar usuario pendente, gerar token em <code>email_verifications</code>, enviar confirmacao por e-mail e aguardar aprovacao administrativa antes de conceder papeis.</p>
</section>
<?php render_footer(); ?>
