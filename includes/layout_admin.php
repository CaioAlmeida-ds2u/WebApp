<?php
// includes/layout_admin.php
// Atualizado para linkar o logo e modificar o dropdown de ferramentas.
// ESTA VERSÃO NÃO INCLUI SIDEBAR.

function getHeaderAdmin($title) {
    // --- Obter dados da sessão ---
    $nome_admin = isset($_SESSION['nome']) ? htmlspecialchars($_SESSION['nome']) : 'Usuário';
    $perfilUsuario = $_SESSION['perfil'] ?? null; // Pega o perfil
    $foto_admin = (isset($_SESSION['foto']) && !empty($_SESSION['foto']))
                    ? BASE_URL . $_SESSION['foto']
                    : BASE_URL . 'assets/img/default_profile.png';

    // Garante que BASE_URL foi definida
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/'); // Fallback
        error_log("ALERTA: BASE_URL não definida em config.php, usando fallback '/' em layout_admin.php");
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <!-- <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css"> -->
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
            <div class="container-fluid">
                <?php /* ==== LOGO/NOME AGORA É UM LINK PARA O DASHBOARD ==== */ ?>
                <a class="navbar-brand d-flex align-items-center"
                   href="<?= ($perfilUsuario === 'admin') ? (BASE_URL . 'admin/dashboard_admin.php') : (BASE_URL . 'auditor/dashboard_auditor.php'); ?>">
                    <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="ACodITools Logo" style="max-height: 35px; margin-right: 10px;" class="d-inline-block align-text-top">
                    <span>ACodITools</span>
                </a>
                <?php /* ==== FIM DA MODIFICAÇÃO DO LOGO ==== */ ?>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavContent" aria-controls="navbarNavContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNavContent">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <?php if (isset($_SESSION['usuario_id'])): ?>
                            <li class="nav-item d-none d-lg-block">
                                <span class="nav-link text-light pe-2">Olá, <?= $nome_admin ?></span>
                            </li>
                            <li class="nav-item">
                                 <img src="<?= $foto_admin ?>" alt="Foto Perfil" width="32" height="32" class="rounded-circle me-2 align-middle" style="object-fit: cover;">
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userToolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                    <span class="d-lg-none ms-1"> </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userToolsDropdown">
                                    <?php /* ==== REMOVIDO Link Dashboard (logo já faz isso) ==== */ ?>
                                    <?php /*
                                    <li><a class="dropdown-item" href="<?= ($perfilUsuario === 'admin') ? (BASE_URL . 'admin/dashboard_admin.php') : (BASE_URL . 'auditor/dashboard_auditor.php'); ?>">
                                        <i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a></li>
                                    */ ?>

                                    <?php /* Links específicos do Admin */ ?>
                                    <?php if ($perfilUsuario === 'admin'): ?>
                                        <?php /* ==== ADICIONADO Link Modelos de Auditoria ==== */ ?>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/modelos_auditoria.php">
                                            <i class="fas fa-clipboard-list fa-fw me-2"></i>Modelos de Auditoria</a></li>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/configuracoes_admin.php"><i class="fas fa-user-cog fa-fw me-2"></i>Configurações</a></li>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/logs.php"><i class="fas fa-clipboard-list fa-fw me-2"></i>Logs de Acesso</a></li>
                                    <?php endif; ?>

                                     <?php /* Links específicos do Auditor (se houver) */ ?>
                                    <?php if ($perfilUsuario === 'auditor'): ?>
                                         <li><a class="dropdown-item" href="<?= BASE_URL ?>auditor/configuracoes_auditor.php"><i class="fas fa-user-cog fa-fw me-2"></i>Minha Conta</a></li>
                                         <?php /* Adicionar outros links do auditor aqui */ ?>
                                    <?php endif; ?>


                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Sair</a></li>
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

        <?php /* Abre a área de conteúdo principal. */ ?>
        <main class="container mt-4">
    <?php
    return ob_get_clean();
}

// Função getFooterAdmin permanece a mesma
function getFooterAdmin() {
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/');
    }
    ob_start();
    ?>
        </main>

        <footer class="bg-light text-center py-3 mt-auto">
             <p class="mb-0">© <?= date("Y") ?> ACodITools. Todos os direitos reservados.</p>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script>

        <script>
          /* Scripts adicionais ou ativação de componentes JS aqui, se necessário */
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>