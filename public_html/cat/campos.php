<?php
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root, [data-bs-theme="light"] {
            --bg-color: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.7);
            --border-color: rgba(0, 0, 0, 0.08);
            --accent-color: #464B51;
            --accent-hover: #35383d;
            --text-muted: #64748b;
            --text-color: #1e293b;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.06);
            --navbar-bg: rgba(241, 245, 249, 0.85);
            --field-bg: #f8fafc;
        }

        [data-bs-theme="dark"] {
            --bg-color: #0b0f19;
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-color: #464B51;
            --accent-hover: #575d64;
            --text-muted: #94a3b8;
            --text-color: #f8fafc;
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --navbar-bg: rgba(11, 15, 25, 0.85);
            --field-bg: #111827;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; }

        .navbar {
            background-color: var(--navbar-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
        }

        .form-control,
        .form-select {
            background-color: var(--field-bg) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.78;
        }
        .form-control:focus,
        .form-select:focus {
            background-color: var(--field-bg) !important;
            color: var(--text-color) !important;
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 0 0.2rem rgba(168, 85, 247, 0.18);
        }
        .form-select option {
            background-color: var(--field-bg);
            color: var(--text-color);
        }
        [data-bs-theme="light"] input[type="date"] { color-scheme: light; }
        [data-bs-theme="dark"] input[type="date"] { color-scheme: dark; }

        .text-purple { color: var(--accent-color) !important; }
        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }
        .btn-icon.btn-sm {
            width: 34px;
            height: 34px;
        }

        .btn-purple {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: #fff;
            font-weight: 500;
        }

        .field-matrix {
            min-width: 1200px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .field-matrix th,
        .field-matrix td {
            border-color: var(--border-color);
            vertical-align: top;
        }

        .field-matrix thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--field-bg);
            color: var(--text-color);
        }

        .field-matrix .field-col {
            position: sticky;
            left: 0;
            z-index: 3;
            background: var(--field-bg);
            min-width: 260px;
        }

        .file-col { min-width: 190px; }
        .cell-note { font-size: 0.72rem; line-height: 1.25; }
    </style>
    <script src="../assets/js/theme-switcher.js"></script>
</head>
<body>
    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('cat', 'inspecao');
    ?>

    <main class="container-fluid py-5 px-4">
        <header class="mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="display-6 text-purple mb-2" style="font-weight: 800;">Matriz de Campos por Arquivo</h1>
                    <p class="lead text-secondary mb-0">Compare presença, preenchimento e formatos de data entre os arquivos documentados da base CAT.</p>
                </div>
            </div>
        </header>

        <?php if ($db_error): ?>
            <div class="alert alert-danger glass-card border-danger"><?= htmlspecialchars($db_error) ?></div>
        <?php endif; ?>

        <section class="row g-4 mb-4">
            <div class="col-6 col-md-3">
                <div class="glass-card p-3">
                    <div class="text-muted small">Arquivos catalogados</div>
                    <div class="h4 mb-0"><?= number_format(count($files), 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-card p-3">
                    <div class="text-muted small">Campos distintos</div>
                    <div class="h4 mb-0"><?= number_format(count($fields), 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="glass-card p-3">
                    <div class="text-muted small">Legenda</div>
                    <div><span class="badge text-bg-success">Presente</span> campo encontrado no arquivo documentado <span class="badge text-bg-secondary ms-2">Ausente</span> campo não encontrado no arquivo</div>
                </div>
            </div>
        </section>

        <div class="glass-card p-3">
            <div class="table-responsive" style="max-height: 70vh;">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
