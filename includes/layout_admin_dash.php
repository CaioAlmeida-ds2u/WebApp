<?php
// includes/layout.php

function getHeaderAdmin($title) {
    
    $nomeUsuario = isset($_SESSION['nome']) ? htmlspecialchars($_SESSION['nome']) : 'Usuário';
    $fotoPerfil = isset($_SESSION['foto']) && !empty($_SESSION['foto']) ? BASE_URL . $_SESSION['foto'] : BASE_URL . 'assets/img/default_profile.png';
    $perfil = $_SESSION['perfil'] ?? '';  // Obtém o perfil do usuário da sessão

    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css">
        
        <?php // Inclusão condicional de CSS
        if (isset($_SESSION['usuario_id'])) {
            if ($perfil === 'admin') {
                echo '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/admin_style.css">';
            } elseif ($perfil === 'auditor') {
                echo '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/auditor_style.css">';
            }
        }
        ?>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="ACodITools Logo" style="max-height: 40px;" class="d-inline-block align-text-top">
                    ACodITools
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php if (isset($_SESSION['usuario_id'])): ?>
                            <li class="nav-item">
                                <span class="nav-link text-light">Olá, <?= $nomeUsuario ?></span>
                                <img src="<?= $fotoPerfil ?>" alt="Foto do Perfil" width="32" height="32" class="rounded-circle ms-2">
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i> Ferramentas
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="<?= ($perfil == 'admin') ? BASE_URL.'admin/dashboard_admin.php' : BASE_URL.'auditor/dashboard_auditor.php'; ?>">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

                                    <?php if ($perfil == 'admin'): ?>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/configuracoes_admin.php"><i class="fas fa-cogs"></i> Configurações</a></li>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/logs.php"><i class="fas fa-list-alt"></i> Logs de Acesso</a></li>
                                    <?php endif; ?>

                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= BASE_URL ?>index.php">Login</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <div class="row">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                        <div class="position-sticky pt-3">
                            <ul class="nav flex-column">
                                <?php if ($perfil === 'admin'): ?>
                                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/usuarios.php"><i class="fas fa-user"></i> Usuários</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/empresas/index.php"><i class="fas fa-building"></i> Empresas</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/requisitos.php"><i class="fas fa-list"></i> Requisitos</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </nav>
                <?php endif; ?>

                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <?php
    return ob_get_clean();
}

function getFooterAdmin() {
    ob_start();
    ?>
                </main>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="<?= BASE_URL ?>assets/js/scripts.js"></script>

        <?php if (isset($_SESSION['usuario_id']) && $_SESSION['perfil'] === 'admin'): ?>
            <script src="<?= BASE_URL ?>assets/js/admin_scripts.js"></script>
        <?php elseif (isset($_SESSION['usuario_id']) && $_SESSION['perfil'] === 'auditor'): ?>
            <script src="<?= BASE_URL ?>assets/js/auditor_scripts.js"></script>
        <?php endif; ?>
    
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>
