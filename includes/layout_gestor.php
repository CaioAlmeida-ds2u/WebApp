<?php
// includes/layout_gestor.php - Layout Específico para Gestor

function getHeaderGestor($title) {
    // Dados do usuário e da empresa da sessão
    $nome_gestor = $_SESSION['nome'] ?? 'Gestor';
    $foto_gestor = (isset($_SESSION['foto']) && !empty($_SESSION['foto'])) ? BASE_URL . $_SESSION['foto'] : BASE_URL . 'assets/img/default_profile.png';
    $nome_empresa = $_SESSION['empresa_nome'] ?? '';
    $logo_empresa = (isset($_SESSION['empresa_logo']) && !empty($_SESSION['empresa_logo'])) ? BASE_URL . $_SESSION['empresa_logo'] : null;
    $perfilUsuario = $_SESSION['perfil'] ?? 'gestor';

    if (!defined('BASE_URL')) { define('BASE_URL', '/'); error_log("ALERTA: layout_gestor.php - BASE_URL não definida..."); }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> <?= $nome_empresa ? '- ' . htmlspecialchars($nome_empresa) : '' ?></title>
        <?php /* Links CSS */ ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <?php /* Idealmente, ter um style_gestor.css, mas usamos admin por enquanto */ ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css">
        <style> html, body { height: 100%; } body { display: flex; flex-direction: column; } main { flex: 1 0 auto; } footer { flex-shrink: 0; } </style>
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-1 border-bottom"> <?php /* Navbar clara */ ?>
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>gestor/dashboard_gestor.php">
                    <?php if ($logo_empresa): ?>
                         <img src="<?= $logo_empresa ?>" alt="Logo <?= htmlspecialchars($nome_empresa) ?>" style="max-height: 35px; margin-right: 10px; object-fit: contain;">
                    <?php else: ?>
                         <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="Logo ACodITools" style="max-height: 30px; margin-right: 10px;">
                    <?php endif; ?>
                     <span style="font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: 1.1rem; color: var(--primary-color);"><?= htmlspecialchars($nome_empresa ?: 'ACodITools') ?></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavContentGestor" aria-controls="navbarNavContentGestor" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarNavContentGestor">
                    <ul class="navbar-nav ms-auto align-items-center">
                         <li class="nav-item d-none d-lg-block"><span class="nav-link text-dark pe-2 small">Olá, <?= $nome_gestor ?></span></li>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center py-1" href="#" id="userToolsDropdownGestor" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?= $foto_gestor ?>" alt="Foto Perfil" width="30" height="30" class="rounded-circle" style="object-fit: cover; border: 1px solid #ccc;">
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userToolsDropdownGestor">
                                <li><h6 class="dropdown-header small text-uppercase text-muted">Gestor</h6></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/dashboard_gestor.php"><i class="fas fa-tachometer-alt fa-fw me-2 text-muted"></i>Dashboard</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/minhas_auditorias.php"><i class="fas fa-clipboard-list fa-fw me-2 text-muted"></i>Auditorias</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/gerenciar_auditores.php"><i class="fas fa-users-cog fa-fw me-2 text-muted"></i>Auditores</a></li>
                                <li><a class="dropdown-item small" href="#"><i class="fas fa-file-alt fa-fw me-2 text-muted"></i>Relatórios</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><h6 class="dropdown-header small text-uppercase text-muted">Conta</h6></li>
                                <li><a class="dropdown-item small" href="#"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i>Configurações</a></li>
                                <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2 text-danger"></i>Sair</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="container my-4"> <?php /* Abre <main> para conteúdo */ ?>
    <?php
    return ob_get_clean();
}

function getFooterGestor() {
    if (!defined('BASE_URL')) { define('BASE_URL', '/'); }
    ob_start();
    ?>
        </main> <?php /* Fecha o <main> aberto no header */ ?>
        <footer class="bg-white text-center py-2 mt-auto border-top shadow-sm">
             <p class="mb-0 text-muted small">© <?= date("Y") ?> ACodITools.</p>
        </footer>
        <!-- Bootstrap -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <!-- Chart.js (se usar gráficos no gestor) -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <!-- Define BASE_URL para JS -->
        <script> const BASE_URL = '<?= BASE_URL ?>'; </script>
        <!-- ** CARREGA O SCRIPT ADMIN (QUE CONTÉM A LÓGICA DO MODAL) ** -->
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script>
        <!-- Poderia ter um scripts_gestor.js para lógica específica do gestor -->
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>