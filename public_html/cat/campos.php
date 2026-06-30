<?php
require_once __DIR__ . '/../../acesso/src/bootstrap.php';
require_platform_admin();

/**
 * CAT - Matriz de Campos por Arquivo
 */
require_once __DIR__ . '/../../cat/src/db.php';

$db_status = "Desconectado";
$db_error = null;
$files = [];
$fields = [];
$matrix = [];

try {
    $db = getDBConnection();
    $db_status = "Conectado";

    $files = $db->query("
        SELECT id, nome, total_registros_documentados, total_campos_documentados, documentacao_atualizada_em
          FROM arquivos_importacao
         ORDER BY id DESC
    ")->fetchAll();

    $rows = $db->query("
        SELECT arquivo_importacao_id, campo, ocorrencias, preenchidos, total_registros, formatos_data, exemplos
          FROM campos_arquivo
         ORDER BY campo, arquivo_importacao_id
    ")->fetchAll();

    foreach ($rows as $row) {
        $fieldName = $row['campo'];
        if (!isset($fields[$fieldName])) {
            $fields[$fieldName] = [
                'campo' => $fieldName,
                'formatos_data' => [],
            ];
        }

        $formats = json_decode($row['formatos_data'] ?? '[]', true) ?: [];
        foreach ($formats as $format) {
            $fields[$fieldName]['formatos_data'][$format] = true;
        }

        $matrix[$fieldName][(int)$row['arquivo_importacao_id']] = [
            'ocorrencias' => (int)$row['ocorrencias'],
            'preenchidos' => (int)$row['preenchidos'],
            'total_registros' => (int)$row['total_registros'],
            'formatos_data' => $formats,
        ];
    }

    ksort($fields);
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="cat">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAT - Campos por Arquivo</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="../assets/js/theme-switcher.js?v=20260629-vanilla"></script>
    <link href="../assets/css/style.css?v=20260629-vanilla" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('cat', 'campos');
    ?>

    <main class="container-fluid py-5 px-4">
        <header class="mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="display-6 text-primary mb-2">Matriz de Campos por Arquivo</h1>
                    <p class="lead text-secondary mb-0">Compare presença, preenchimento e formatos de data entre os arquivos documentados da base CAT.</p>
                </div>
            </div>
        </header>

        <?php if ($db_error): ?>
            <div class="alert alert-danger card border-danger"><?= htmlspecialchars($db_error) ?></div>
        <?php endif; ?>

        <section class="row g-4 mb-4">
            <div class="col-6 col-md-3">
                <div class="card p-3">
                    <div class="text-muted small">Arquivos catalogados</div>
                    <div class="h4 mb-0"><?= number_format(count($files), 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-3">
                    <div class="text-muted small">Campos distintos</div>
                    <div class="h4 mb-0"><?= number_format(count($fields), 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card p-3">
                    <div class="text-muted small">Legenda</div>
                    <div><span class="badge text-bg-success">Presente</span> campo encontrado no arquivo documentado <span class="badge text-bg-secondary ms-2">Ausente</span> campo não encontrado no arquivo</div>
                </div>
            </div>
        </section>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-sm table-hover field-matrix mb-0">
                    <thead>
                        <tr>
                            <th class="field-col">Campo</th>
                            <th>Formatos de data detectados</th>
                            <?php foreach ($files as $file): ?>
                                <th class="file-col">
                                    <div class="fw-semibold">#<?= (int)$file['id'] ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($file['nome']) ?></div>
                                    <div class="cell-note text-muted">
                                        <?= number_format((int)$file['total_registros_documentados'], 0, ',', '.') ?> registros
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fields)): ?>
                            <tr>
                                <td colspan="<?= count($files) + 2 ?>" class="text-center text-muted py-5">Nenhuma documentação de campos encontrada. Use "Sincronizar Lista" no ETL para gerar a documentação.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($fields as $fieldName => $field): ?>
                            <tr>
                                <th class="field-col font-monospace"><?= htmlspecialchars($fieldName) ?></th>
                                <td>
                                    <?php $formats = array_keys($field['formatos_data']); ?>
                                    <?= $formats ? htmlspecialchars(implode(', ', $formats)) : '<span class="text-muted">-</span>' ?>
                                </td>
                                <?php foreach ($files as $file): ?>
                                    <?php $cell = $matrix[$fieldName][(int)$file['id']] ?? null; ?>
                                    <td>
                                        <?php if ($cell): ?>
                                            <span class="badge text-bg-success">Presente</span>
                                            <div class="cell-note text-muted mt-1">
                                                <?= number_format($cell['ocorrencias'], 0, ',', '.') ?> ocorrências<br>
                                                <?= number_format($cell['preenchidos'], 0, ',', '.') ?> preenchidos
                                                <?php if (!empty($cell['formatos_data'])): ?>
                                                    <br>Data: <?= htmlspecialchars(implode(', ', $cell['formatos_data'])) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Ausente</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
