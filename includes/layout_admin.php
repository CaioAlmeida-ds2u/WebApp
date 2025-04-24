<?php
// includes/layout_admin.php
// Versão com dropdown corrigido (sem seta manual)

function getHeaderAdmin($title) {
    $nome_admin = isset($_SESSION['nome']) ? htmlspecialchars($_SESSION['nome']) : 'Usuário';
    $perfilUsuario = $_SESSION['perfil'] ?? null;
    $foto_admin = (isset($_SESSION['foto']) && !empty($_SESSION['foto'])) ? BASE_URL . $_SESSION['foto'] : BASE_URL . 'assets/img/default_profile.png';
    if (!defined('BASE_URL')) { define('BASE_URL', '/'); error_log("ALERTA: BASE_URL não definida..."); }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> - ACodITools Admin</title> <?php /* Título Padrão Admin */ ?>
        <?php /* Links CSS */ ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css">
        <link rel="icon" href="<?= BASE_URL ?>assets/img/favicon.ico" type="image/x-icon">
        <style> /* Estilos básicos layout */
            html, body { height: 100%; } body { display: flex; flex-direction: column; font-family: 'Poppins', sans-serif; }
            main.container, .main-content-fluid { flex: 1 0 auto; padding-top: 1.5rem; padding-bottom: 3rem; } footer { flex-shrink: 0; }
            .navbar .dropdown-toggle::after { display: none; } .user-avatar { width: 32px; height: 32px; object-fit: cover; border: 1px solid rgba(255,255,255,0.2); }
            .dropdown-menu { min-width: 240px; } .dropdown-menu .small i.fa-fw { opacity: 0.7; }
        </style>
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-1">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>admin/dashboard_admin.php"> <?php /* Admin sempre vai para dashboard admin */ ?>
                    <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="Logo" style="max-height: 30px; margin-right: 10px;"><span style="font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: 1.1rem;">ACodITools</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavContent" aria-controls="navbarNavContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarNavContent">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <?php if (isset($_SESSION['usuario_id']) && $perfilUsuario === 'admin'): // Garante que é admin ?>
                            <li class="nav-item d-none d-lg-block"><span class="nav-link text-light pe-2 small">Olá, <?= $nome_admin ?></span></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center py-1" href="#" id="userToolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                     <img src="<?= $foto_admin ?>" alt="Foto Perfil" class="rounded-circle user-avatar me-1">
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userToolsDropdown">
                                    <li><h6 class="dropdown-header small text-uppercase text-muted">Gerenciamento</h6></li>
                                    <?php /* ==== LINK CORRETO PARA MODELOS ==== */ ?>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/modelo/modelo_index.php"><i class="fas fa-clipboard-list fa-fw me-2 text-muted"></i>Modelos de Auditoria</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php"><i class="fas fa-tasks fa-fw me-2 text-muted"></i>Requisitos Mestre</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/usuarios.php"><i class="fas fa-users fa-fw me-2 text-muted"></i>Usuários</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/empresas/empresa_index.php"><i class="fas fa-building fa-fw me-2 text-muted"></i>Empresas</a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                     <li><h6 class="dropdown-header small text-uppercase text-muted">Sistema</h6></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/relatorios/index.php"><i class="fas fa-file-alt fa-fw me-2 text-muted"></i>Relatórios</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/logs.php"><i class="fas fa-history fa-fw me-2 text-muted"></i>Logs</a></li>
                                     <li><hr class="dropdown-divider my-1"></li>
                                     <li><h6 class="dropdown-header small text-uppercase text-muted">Conta</h6></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/configuracoes_admin.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i>Minhas Configurações</a></li>
                                    <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Sair</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <?php // Se não for admin, ou não estiver logado, talvez redirecionar ou mostrar só Login ?>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <?php // Define container ou container-fluid para o <main> ?>
        <?php $useFluidContainer = str_contains($_SERVER['REQUEST_URI'], 'dashboard_admin.php'); ?>
        <main class="<?= $useFluidContainer ? 'container-fluid px-lg-4 py-4' : 'container my-4' ?> content-wrap">
    <?php
    return ob_get_clean();
}

function getFooterAdmin() {
    if (!defined('BASE_URL')) { define('BASE_URL', '/WebApp/'); }
    ob_start();
    ?>
        </main> <?php /* Fecha o <main> aberto no header */ ?>
        <footer class="bg-white text-center py-2 mt-auto border-top shadow-sm">
             <p class="mb-0 text-muted small">© <?= date("Y") ?> ACodITools.</p>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script> const BASE_URL = '<?= BASE_URL ?>'; </script>
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script>
        <script>document.addEventListener('DOMContentLoaded',function(){var t=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));var l=t.map(function(e){return new bootstrap.Tooltip(e)})});</script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>