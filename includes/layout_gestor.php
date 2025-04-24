<?php
// includes/layout_gestor.php - Layout Refinado (Navbar Superior - v5 - Novos Links)

/**
 * Gera o cabeçalho HTML completo para a área do Gestor.
 */
function getHeaderGestor(string $title): string {
    // --- Checagens Iniciais e Dados da Sessão (mantidos) ---
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    if (!defined('BASE_URL')) {
        // (Lógica BASE_URL mantida)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST']; $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $base_path = str_replace($doc_root, '', $script_dir); if (strpos($base_path, '/includes') !== false) { $base_path = dirname($base_path); }
        $base_path = rtrim($base_path, '/') . '/'; define('BASE_URL', $protocol . $host . $base_path);
    }
    // (Busca nome, foto, logo mantida)
    $nomeUsuario = $_SESSION['nome'] ?? 'Gestor';
    $fotoPath = $_SESSION['foto'] ?? 'assets/img/default_profile.png';
    $fotoUrl = BASE_URL . ltrim($fotoPath, '/');
    if (!empty($_SESSION['foto']) && !file_exists(__DIR__.'/../'.ltrim($fotoPath, '/'))) { $fotoUrl = BASE_URL . 'assets/img/default_profile.png'; }
    $nomeEmpresa = $_SESSION['empresa_nome'] ?? 'Minha Empresa';
    $logoPath = $_SESSION['empresa_logo'] ?? null; $logoUrl = null;
    if ($logoPath) { $fullLogoPathCheck = __DIR__.'/../'.ltrim($logoPath, '/');
        if (file_exists($fullLogoPathCheck)) { $logoUrl = BASE_URL . ltrim($logoPath, '/') . '?t=' . filemtime($fullLogoPathCheck); }
    }
    // (Função isActivePage mantida, adicionado placeholder para equipes)
    if (!function_exists('isActivePage')) {
        function isActivePage($link_path) {
             $current_script = basename($_SERVER['SCRIPT_NAME']);
             $target_script = basename($link_path);
             if ($target_script === 'minhas_auditorias.php' && in_array($current_script, ['revisar_auditoria.php', 'criar_auditoria.php', 'editar_auditoria.php'])) { return 'active'; }
             if ($target_script === 'gerenciar_auditores.php' && $current_script === 'editar_auditor.php') { return 'active'; }
             // Adicione aqui verificações para páginas relacionadas a 'Equipes' ou 'Relatórios' se/quando forem criadas
             // Ex: if ($target_script === 'gerenciar_equipes.php' && $current_script === 'editar_equipe.php') { return 'active'; }
             return $current_script === $target_script ? 'active' : '';
        }
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br" data-bs-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($nomeEmpresa) ?></title>
        <?php /* Links CSS */ ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <!-- <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css"> --> <?php /* <<< COMENTADO */ ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_gestor.css"> <?php /* <<< ATIVO */ ?>
        <link rel="icon" href="<?= BASE_URL ?>assets/img/favicon.ico" type="image/x-icon">
        <style>
            /* Mínimo inline essencial */
            html, body { height: 100%; }
            body { display: flex; flex-direction: column; font-family: 'Poppins', sans-serif; padding-top: 60px; /* Ajuste conforme altura da navbar */ }
            main { flex: 1 0 auto; } footer { flex-shrink: 0; }
            /* Removido o .navbar-spacer, body padding-top faz o trabalho */
        </style>
    </head>
    <body class="bg-body-tertiary">

        <!-- ===== Navbar Principal ===== -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm app-navbar-gestor"> <?php /* Classe específica + bg-white */ ?>
            <div class="container-fluid">
                <!-- Brand -->
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>gestor/dashboard_gestor.php"> <?php /* Link padrão agora é auditorias? */ ?>
                    <?php if ($logoUrl): ?>
                         <img src="<?= $logoUrl ?>" alt="Logo <?= htmlspecialchars($nomeEmpresa) ?>" class="logo-empresa-nav">
                    <?php else: ?>
                         <i class="fas fa-building fa-fw logo-placeholder-nav text-primary"></i>
                    <?php endif; ?>
                    <span class="nome-empresa-nav ms-2"><?= htmlspecialchars($nomeEmpresa) ?></span>
                </a>

                <!-- Toggler Mobile -->
                <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavGestor" aria-controls="navbarNavGestor" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="fas fa-bars text-primary"></i>
                </button>

                <!-- Conteúdo Colapsável -->
                <div class="collapse navbar-collapse" id="navbarNavGestor">
                     <!-- Links Principais (Sem Dashboard) -->
                     <ul class="navbar-nav main-nav-links-gestor me-auto mb-2 mb-lg-0 ps-lg-4"> <?php /* Adicionado padding start large */ ?>
                         <li class="nav-item">
                             <a class="nav-link <?= isActivePage('minhas_auditorias.php') ?>" href="<?= BASE_URL ?>gestor/minhas_auditorias.php"><i class="fas fa-clipboard-list fa-fw me-1"></i>Auditorias</a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActivePage('gerenciar_auditores.php') ?>" href="<?= BASE_URL ?>gestor/gerenciar_auditores.php"><i class="fas fa-users-cog fa-fw me-1"></i>Auditores</a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActivePage('gerenciar_equipes.php') ?> disabled" href="#" title="Equipes (Em breve)"><i class="fas fa-object-group fa-fw me-1"></i>Equipes</a> <?php /* Ícone mudado */ ?>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link disabled" href="#" title="Relatórios (Em breve)"><i class="fas fa-chart-pie fa-fw me-1"></i>Relatórios</a> <?php /* Ícone mudado */ ?>
                         </li>
                     </ul>

                     <!-- Área do Usuário -->
                    <ul class="navbar-nav align-items-lg-center user-nav-area-gestor">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center user-dropdown-toggle-gestor" href="#" id="userDropdownGestor" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <img src="<?= $fotoUrl ?>" alt="Foto Perfil" class="rounded-circle user-avatar-nav-gestor">
                                 <span class="user-name-nav-gestor d-none d-lg-inline ms-2 small"><?= htmlspecialchars($nomeUsuario) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-light mt-2" aria-labelledby="userDropdownGestor">
                                <li><h6 class="dropdown-header small text-uppercase text-primary"><?= htmlspecialchars($nomeUsuario) ?> <small class="text-muted fw-normal">(Gestor)</small></h6></li>
                                <!-- Links do Dropdown Atualizados -->
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/minhas_auditorias.php"><i class="fas fa-clipboard-check fa-fw me-2 text-primary opacity-75"></i> Ver Auditorias</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/gerenciar_auditores.php"><i class="fas fa-users-cog fa-fw me-2 text-primary opacity-75"></i> Gerenciar Auditores</a></li>
                                <li><a class="dropdown-item small disabled" href="#" title="Adicionar Novo Auditor (Em breve)"><i class="fas fa-user-plus fa-fw me-2 text-muted"></i> Adicionar Auditor</a></li>
                                <!-- <li><a class="dropdown-item small disabled" href="#"><i class="fas fa-file-alt fa-fw me-2 text-muted"></i> Meus Relatórios</a></li> --> <?php /* Removido ou manter desabilitado */ ?>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/configuracoes_gestor.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i> Minha Conta</a></li>
                                <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2 text-danger"></i> Sair</a></li>
                            </ul>
                        </li>
                    </ul>
                </div> <!-- /collapse -->
            </div> <!-- /container-fluid -->
        </nav>
        <!-- ===== Fim Navbar ===== -->

        <!-- Conteúdo Principal -->
        <main class="main-content flex-grow-1 container-fluid px-lg-4 py-4"> <?php /* Main com container+padding */ ?>
    <?php
    return ob_get_clean();
} // Fim getHeaderGestor

function getFooterGestor(): string {
    if (!defined('BASE_URL')) { define('BASE_URL', '/'); }
    ob_start();
    ?>
        </main> <?php /* Fecha o <main> */ ?>

        <!-- Rodapé -->
        <footer class="app-footer-gestor text-center py-3 border-top bg-white mt-auto">
             <span class="text-muted small">© <?= date("Y") ?> ACodITools - Plataforma de Auditoria.</span>
        </footer>

        <!-- Scripts Essenciais (Mantidos) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script> const BASE_URL = '<?= BASE_URL ?>'; </script>

        <!-- Script Modal 1º Acesso (Importante) -->
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script>
        <!-- <script src="<?= BASE_URL ?>assets/js/scripts_gestor.js"></script> -->

        <!-- Inicializador de Tooltips (Mantido) -->
        <script> document.addEventListener('DOMContentLoaded',function(){ var tt=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')); var tl=tt.map(function(e){ return new bootstrap.Tooltip(e) }) }); </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
} // Fim getFooterGestor
?>