<?php
require_once __DIR__ . '/../../ldrt/src/db.php';

// Fetch stats
$cid_count = 0;
$agentes_count = 0;
$cnae_cbo_count = 0;
$relatos_count = 0;
$db_status = "Desconectado";

try {
    $db = getDBConnection();
    $db_status = "Conectado";
    
    $cid_count = $db->query("SELECT COUNT(*) FROM cid")->fetchColumn();
    $agentes_count = $db->query("SELECT COUNT(*) FROM agentes")->fetchColumn();
    $cnae_cbo_count = $db->query("SELECT COUNT(*) FROM cnae_cbo")->fetchColumn();
    $relatos_count = $db->query("SELECT COUNT(*) FROM relatos")->fetchColumn();
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-module="ldrt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDRT - Portal de Doenças Relacionadas ao Trabalho</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/favicon.png">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root, [data-bs-theme="light"] {
            --bg-color: var(--bs-body-bg);
            --card-bg: var(--bs-body-bg);
            --border-color: var(--bs-border-color);
            --accent-color: var(--bs-primary);
            --accent-hover: var(--primary-hover);
            --text-muted: var(--bs-secondary-color);
            --text-color: var(--bs-body-color);
            --navbar-bg: var(--bs-body-bg);
        }

        [data-bs-theme="dark"] {
            --bg-color: var(--bs-body-bg);
            --card-bg: var(--bs-body-bg);
            --border-color: var(--bs-border-color);
            --accent-color: var(--bs-primary);
            --accent-hover: var(--primary-hover);
            --text-muted: var(--bs-secondary-color);
            --text-color: var(--bs-body-color);
            --navbar-bg: var(--bs-body-bg);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bs-body-bg);
            background-image: none;
            color: var(--bs-body-color);
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5 {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            font-weight: 700;
        }

        .navbar {
            background-color: var(--navbar-bg);
            backdrop-filter: none;
            border-bottom: 1px solid var(--border-color);
        }

        .glass-card {
            background: var(--bs-body-bg);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            box-shadow: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-card {
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .nav-card:hover {
            transform: none;
            border-color: var(--accent);
            background: var(--bs-tertiary-bg);
            box-shadow: none;
        }

        .nav-card .icon-wrapper {
            width: 60px;
            height: 60px;
            background-color: var(--bs-tertiary-bg);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--accent);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: 1px solid var(--bs-border-color);
        }

        .nav-card:hover .icon-wrapper {
            background-color: var(--bs-tertiary-bg);
            color: var(--accent);
            transform: none;
        }

        .stat-card {
            border-radius: 8px;
            padding: 20px;
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .hero-title {
            font-size: clamp(3rem, 6vw, 5rem);
            font-weight: 800;
            letter-spacing: 0;
            color: var(--bs-body-color) !important;
            margin-bottom: 1.5rem;
            line-height: 1.05;
        }

        .hero-subtitle {
            font-size: clamp(1.18rem, 2vw, 1.45rem);
            color: var(--bs-secondary-color);
            max-width: 760px;
            line-height: 1.6;
        }

        .footer {
            border-top: 1px solid var(--bs-border-color);
            background-color: transparent;
            padding: 30px 0;
            margin-top: 80px;
        }

        .json-response {
            background-color: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.85rem;
            max-height: 350px;
            overflow: auto;
            color: #38bdf8;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../assets/js/theme-switcher.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="landing-page">

    <!-- Navbar -->
    <?php
    require_once __DIR__ . '/../../includes/navbar.php';
    render_platform_navbar('ldrt', 'inicio');
    ?>

    <!-- Hero Section -->
    <section class="container landing-hero py-5 my-4 text-center text-md-start">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <img src="../assets/img/logo-fundo-escuro-horizontal.png" alt="RENAST" class="platform-logo-img landing-hero-logo mb-4">
                <div class="landing-eyebrow mb-3">
                    <i class="fa-solid fa-sparkles me-1"></i> Portaria Ministerial de Atualização
                </div>
                <h1 class="hero-title">Lista de Doenças Relacionadas ao Trabalho</h1>
                <p class="hero-subtitle mb-4">
                    Ferramenta oficial de consulta estruturada à LDRT brasileira (Portaria GM/MS nº 1.999/2023). Relacione com facilidade patologias, agentes de risco ocupacional, atividades econômicas (CNAE) e ocupações (CBO) para fins epidemiológicos e clínicos.
                </p>
                <div class="landing-actions justify-content-center justify-content-md-start">
                    <a href="consulta.php" class="btn btn-lg btn-primary">
                        <i class="fa-solid fa-magnifying-glass me-2"></i>Começar Consulta
                    </a>
                    <a href="#explorar" class="btn btn-lg btn-outline-secondary">
                        <i class="fa-solid fa-compass me-2"></i>Explorar Tabelas
                    </a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h5 class="mb-4 text-center text-white-50">Estatísticas do Sistema</h5>
                    <?php if (isset($db_error)): ?>
                        <div class="alert alert-danger mb-0 small">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Erro ao conectar ao banco: <?php echo htmlspecialchars($db_error); ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number text-primary"><?php echo number_format($cid_count, 0, ',', '.'); ?></div>
                                    <div class="stat-label">CIDs Cadastrados</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number text-danger"><?php echo number_format($agentes_count, 0, ',', '.'); ?></div>
                                    <div class="stat-label">Agentes de Risco</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number text-success"><?php echo number_format($cnae_cbo_count, 0, ',', '.'); ?></div>
                                    <div class="stat-label">CNAEs & CBOs</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-number text-warning"><?php echo number_format($relatos_count, 0, ',', '.'); ?></div>
                                    <div class="stat-label">Casos (Relatos)</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation Cards Section -->
    <section class="container py-5" id="explorar">
        <h3 class="mb-4 text-center">Navegue pelas Ferramentas</h3>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            
            <!-- Card 1: Consulta Cruzada -->
            <div class="col">
                <a href="consulta.php" class="card glass-card nav-card landing-nav-card p-4 h-100">
                    <div class="icon-wrapper info-surface">
                        <i class="fa-solid fa-shuffle"></i>
                    </div>
                    <h4>Consulta Cruzada</h4>
                    <p class="text-muted small mb-0">
                        Busque relações combinando múltiplos campos. Digite doenças (CID-10), atividades da empresa (CNAE), profissões do trabalhador (CBO) ou fatores de risco (Agentes) e encontre as conexões oficiais.
                    </p>
                </a>
            </div>

            <!-- Card 2: Explorar CID-10 -->
            <div class="col">
                <a href="explorar_cid.php" class="card glass-card nav-card landing-nav-card p-4 h-100">
                    <div class="icon-wrapper success-surface">
                        <i class="fa-solid fa-folder-tree"></i>
                    </div>
                    <h4>Navegador CID-10</h4>
                    <p class="text-muted small mb-0">
                        Explore toda a árvore do CID-10 em tempo real (Capítulos, Grupos e Categorias). Selecione qualquer código para descobrir na hora quais agentes nocivos do trabalho estão associados.
                    </p>
                </a>
            </div>

            <!-- Card 3: Explorar CNAE e CBO -->
            <div class="col">
                <a href="explorar_cnae_cbo.php" class="card glass-card nav-card landing-nav-card p-4 h-100">
                    <div class="icon-wrapper warn-surface">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                    <h4>CNAE & CBO Explorer</h4>
                    <p class="text-muted small mb-0">
                        Navegue pelas estruturas oficiais de atividades econômicas e ocupações. Encontre códigos específicos e verifique relatos clínicos e relações com fatores ambientais de risco.
                    </p>
                </a>
            </div>

            <!-- Card 4: Tabelas LDRT -->
            <div class="col">
                <a href="lista_a.php" class="card glass-card nav-card landing-nav-card p-4 h-100">
                    <div class="icon-wrapper danger-surface">
                        <i class="fa-solid fa-table"></i>
                    </div>
                    <h4>Tabelas LDRT</h4>
                    <p class="text-muted small mb-0">
                        Acesse as tabelas completas em formato digitalizado (Lista A e Lista B), com capacidade de busca e filtragem instantânea nos termos e códigos conforme publicado no Diário Oficial.
                    </p>
                </a>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <p class="mb-1 text-muted small">LDRT Portal de Consulta &copy; 2026 - Em conformidade com a Portaria GM/MS 1.999/2023</p>
            <p class="mb-0 text-muted" style="font-size: 0.75rem;">Desenvolvido em PHP 8.2, Bootstrap 5 e PostgreSQL</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
