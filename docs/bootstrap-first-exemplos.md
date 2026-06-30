# Plataforma RENAST - Exemplos Bootstrap-first

Este arquivo reune exemplos curtos para novas telas e refatoracoes. Os exemplos devem ser copiados como ponto de partida conceitual, ajustando caminhos, textos e permissoes conforme o modulo.

## Estrutura de pagina

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../acesso/src/bootstrap.php';

render_header('Titulo da pagina');
?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Titulo</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <p class="text-body-secondary small text-uppercase mb-1">Modulo</p>
            <h1 class="h3 mb-1">Titulo da pagina</h1>
            <p class="text-body-secondary mb-0">Descricao objetiva da tarefa desta tela.</p>
        </div>
        <a href="novo.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
            Novo registro
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            Conteudo
        </div>
    </div>
</div>

<?php render_footer(); ?>
```

## Card de resumo

```html
<div class="card h-100">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <h2 class="h6 mb-0">Registros</h2>
            <span class="badge text-bg-secondary">Atualizado</span>
        </div>
        <p class="display-6 mb-1">128</p>
        <p class="text-body-secondary mb-0">Total disponivel para consulta.</p>
    </div>
</div>
```

## Barra de acoes

```html
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="novo.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
        Novo
    </a>
    <button type="button" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
        Atualizar
    </button>
    <button type="button" class="btn btn-outline-secondary">
        <i class="bi bi-download me-1" aria-hidden="true"></i>
        Exportar
    </button>
</div>
```

## Filtros

```html
<form class="card mb-3" method="get">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="q" class="form-label">Busca</label>
                <input type="search" class="form-control" id="q" name="q" value="">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1" aria-hidden="true"></i>
                    Filtrar
                </button>
            </div>
        </div>
    </div>
</form>
```

## Tabela

```html
<div class="table-responsive border rounded">
    <table class="table table-sm table-striped table-hover align-middle mb-0">
        <thead>
            <tr>
                <th scope="col">Nome</th>
                <th scope="col">Status</th>
                <th scope="col">Atualizacao</th>
                <th scope="col" class="text-end">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Registro exemplo</td>
                <td><span class="badge text-bg-success">Ativo</span></td>
                <td><time datetime="2026-06-29">29/06/2026</time></td>
                <td class="text-end">
                    <a href="editar.php?id=1" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        <span class="visually-hidden">Editar</span>
                    </a>
                </td>
            </tr>
        </tbody>
    </table>
</div>
```

## Estado vazio

```html
<div class="text-center py-5">
    <i class="bi bi-inbox fs-1 text-body-secondary" aria-hidden="true"></i>
    <h2 class="h5 mt-3">Nenhum registro encontrado</h2>
    <p class="text-body-secondary mb-3">Ajuste os filtros ou cadastre um novo item.</p>
    <a href="novo.php" class="btn btn-primary">Cadastrar</a>
</div>
```

## Alertas

```html
<div class="alert alert-info d-flex align-items-start gap-2" role="alert">
    <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
    <div>Mensagem objetiva sobre o estado atual da tela.</div>
</div>
```

## Abas

```html
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" type="button" role="tab">Resumo</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" type="button" role="tab">Dados</button>
    </li>
</ul>
```

## Progresso

```html
<div class="progress" role="progressbar" aria-label="Progresso da carga" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar" data-progress-percent="65">65%</div>
</div>
```

## Modal

```html
<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#confirmModal">
    Abrir modal
</button>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="confirmModalTitle">Confirmar acao</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Revise as informacoes antes de continuar.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary">Confirmar</button>
            </div>
        </div>
    </div>
</div>
```

## Checklist antes de finalizar uma tela

1. Confirmar que a tela permanece em tema claro.
2. Verificar mobile e desktop.
3. Verificar foco por teclado em botoes, links, filtros e abas.
4. Conferir contraste de badges e alertas.
5. Remover classes visuais antigas.
6. Remover CSS novo que pode ser substituido por utilitario Bootstrap.
