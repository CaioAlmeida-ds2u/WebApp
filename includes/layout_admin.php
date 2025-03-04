<?php
// includes/layout_admin.php

function getHeaderAdmin($title) {
    // Obtém o nome e a foto do usuário da sessão, com tratamento para caso não existam
    $nome_admin = isset($_SESSION['nome']) ? htmlspecialchars($_SESSION['nome']) : 'Administrador'; //Nome com tratamento.
    $foto_admin = isset($_SESSION['foto']) && !empty($_SESSION['foto']) ? BASE_URL . $_SESSION['foto'] : BASE_URL . 'assets/img/default_profile.png'; // Foto com tratamento e fallback

    ob_start(); // Inicia o buffer de saída
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css">
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
                            <span class="nav-link text-light">Olá, <?= $nome_admin ?></span>
                            <img src="<?= $foto_admin ?>" alt="Foto do Perfil" width="32" height="32" class="rounded-circle ms-2">
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cog"></i> Ferramentas
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="
                                    <?php
                                        if($_SESSION['perfil'] == 'admin'){
                                           echo "dashboard_admin.php";
                                        }else{
                                            echo "dashboard_auditor.php";
                                        }
                                     ?>
                                "><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a class="dropdown-item" href="configuracoes_admin.php"><i class="fas fa-cogs"></i> Configurações</a></li>
                                <li><a class="dropdown-item" href="logs.php"><i class="fas fa-list-alt"></i> Logs de Acesso</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container mt-4">

    <?php
    return ob_get_clean(); // Retorna o conteúdo do buffer
}

function getFooterAdmin() {
    ob_start(); // Inicia o buffer de saída
    ?>
     </main>
    <footer class="bg-light text-center py-3 mt-4">
        <p>&copy; <?= date("Y") ?> ACodITools. Todos os direitos reservados.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/script_admin.js"></script>
    </body>
    </html>
    <?php
    return ob_get_clean(); // Retorna o conteúdo do buffer
}