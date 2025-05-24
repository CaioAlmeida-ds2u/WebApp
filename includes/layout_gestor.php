<?php
// includes/layout_gestor.php - Layout para a Área do Gestor da Empresa Cliente

/**
 * Gera o cabeçalho HTML completo para a área do Gestor.
 */
function getHeaderGestor(string $title): string {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!defined('BASE_URL')) {
        // (Sua lógica para definir BASE_URL, pode ser simplificada se config.php já a define)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // Robusto para subdiretórios ou raiz
        $script_path = dirname($_SERVER['SCRIPT_NAME']);
        $base_path = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']), '/');
        if (strpos($_SERVER['REQUEST_URI'], $base_path) === 0) {
             $base_path_final = $base_path . '/';
        } else {
            // Fallback mais simples se a lógica acima falhar em cenários complexos
            $base_path_final = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\') . '/';
            if ($base_path_final === '//' || $base_path_final === '\\\\') $base_path_final = '/'; // Caso raiz
        }
        define('BASE_URL', $protocol . $host . $base_path_final);
    }

    $nomeUsuario = $_SESSION['nome'] ?? 'Gestor';
    $perfilUsuarioSessao = $_SESSION['perfil'] ?? ''; // Para checagens no layout se necessário
    $fotoPath = $_SESSION['foto'] ?? ''; // Caminho relativo já com 'uploads/fotos/'
    $nomeEmpresa = $_SESSION['empresa_nome'] ?? 'Minha Empresa';
    $logoPath = $_SESSION['empresa_logo'] ?? ''; // Caminho relativo já com 'uploads/logos/'

    $fotoUrl = BASE_URL . 'assets/img/default_profile.png';
    if (!empty($fotoPath) && file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_URL . $fotoPath)) {
        $fotoUrl = BASE_URL . $fotoPath . '?v=' . filemtime($_SERVER['DOCUMENT_ROOT'] . BASE_URL . $fotoPath);
    }

    $logoUrl = null;
    if (!empty($logoPath) && file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_URL . $logoPath)) {
        $logoUrl = BASE_URL . $logoPath . '?v=' . filemtime($_SERVER['DOCUMENT_ROOT'] . BASE_URL . $logoPath);
    }

    if (!function_exists('isActiveGestorPage')) {
        function isActiveGestorPage($target_scripts_array) {
            $current_script_name = basename($_SERVER['SCRIPT_NAME']);
            if (!is_array($target_scripts_array)) {
                $target_scripts_array = [$target_scripts_array];
            }
            foreach ($target_scripts_array as $target_script) {
                if ($current_script_name === $target_script) {
                    return 'active';
                }
            }
            // Lógica para páginas "filhas"
            if (in_array('minhas_auditorias.php', $target_scripts_array) && in_array($current_script_name, ['criar_auditoria.php', 'editar_auditoria.php', 'detalhes_auditoria.php', 'revisar_auditoria.php'])) {
                return 'active';
            }
            if (in_array('gerenciar_auditores.php', $target_scripts_array) && $current_script_name === 'solicitar_auditor.php') { // Supondo que solicitar_auditor seja filho de gerenciar
                return 'active';
            }
            if (in_array('gerenciar_equipes.php', $target_scripts_array) && in_array($current_script_name, ['criar_equipe.php', 'editar_equipe.php'])) {
                return 'active';
            }
             if (in_array('gestao_consolidada_planos_de_acao.php', $target_scripts_array) && $current_script_name === 'detalhes_plano_acao.php') { // Exemplo
                return 'active';
            }
            return '';
        }
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br" data-bs-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> | Gestor | <?= htmlspecialchars($nomeEmpresa) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_gestor.css">
        <link rel="icon" href="<?= BASE_URL ?>assets/img/favicon.ico" type="image/x-icon">
        <style>
            body { padding-top: 68px; /* Ajuste para nova altura da navbar */ }
            .navbar-brand img.logo-empresa-nav { max-height: 40px; border-radius: 4px; object-fit: contain; }
            .user-avatar-nav-gestor { width: 38px; height: 38px; }
            .main-nav-links-gestor .nav-link { font-size: 0.9rem; }
            .main-nav-links-gestor .nav-link i { font-size: 0.95em; }
            .dropdown-menu-gestor { min-width: 260px; }
        </style>
    </head>
    <body class="bg-body-tertiary gestor-body"> <?php /* Adicionar classe específica */ ?>

        <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm app-navbar-gestor py-2">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>gestor/dashboard_gestor.php">
                    <?php if ($logoUrl): ?>
                         <img src="<?= $logoUrl ?>" alt="Logo <?= htmlspecialchars($nomeEmpresa) ?>" class="logo-empresa-nav">
                    <?php else: ?>
                         <i class="fas fa-building fa-2x text-primary opacity-75 me-2"></i>
                    <?php endif; ?>
                    <span class="nome-empresa-nav ms-2"><?= htmlspecialchars($nomeEmpresa) ?></span>
                </a>
                <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavGestor" aria-controls="navbarNavGestor" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="fas fa-bars text-primary"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavGestor">
                     <ul class="navbar-nav main-nav-links-gestor me-auto mb-2 mb-lg-0 ps-lg-3">
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveGestorPage('dashboard_gestor.php') ?>" href="<?= BASE_URL ?>gestor/dashboard_gestor.php"><i class="fas fa-tachometer-alt fa-fw me-1"></i>Dashboard</a>
                         </li>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= isActiveGestorPage(['minhas_auditorias.php', 'solicitar_nova_auditoria_baseada_em_risco.php', 'meu_plano_de_auditoria_anual.php']) ?>" href="#" id="auditoriasDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-clipboard-list fa-fw me-1"></i>Auditorias
                            </a>
                            <ul class="dropdown-menu shadow-sm border-light" aria-labelledby="auditoriasDropdown">
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/auditoria/minhas_auditorias.php">Minhas Auditorias</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/auditoria/criar_auditoria.php">Planejar Nova Auditoria</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/auditoria/solicitar_nova_auditoria_baseada_em_risco.php">Auditoria Baseada em Risco</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/auditoria/meu_plano_de_auditoria_anual.php">Plano Anual de Auditorias</a></li>
                            </ul>
                        </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveGestorPage('gestao_consolidada_planos_de_acao.php') ?>" href="<?= BASE_URL ?>gestor/auditoria/gestao_consolidada_planos_de_acao.php"><i class="fas fa-tasks-alt fa-fw me-1"></i>Planos de Ação</a>
                         </li>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= isActiveGestorPage(['gerenciar_auditores.php', 'gerenciar_equipes.php']) ?>" href="#" id="recursosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-users-cog fa-fw me-1"></i>Recursos
                            </a>
                            <ul class="dropdown-menu shadow-sm border-light" aria-labelledby="recursosDropdown">
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/auditor/gerenciar_auditores.php">Gerenciar Auditores</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/equipe/gerenciar_equipes.php">Gerenciar Equipes</a></li>
                            </ul>
                        </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveGestorPage('documentacao_central_da_area.php') ?>" href="<?= BASE_URL ?>gestor/documentacao_central_da_area.php" title="Políticas e Procedimentos da Empresa"><i class="fas fa-folder-open fa-fw me-1"></i>Documentação</a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveGestorPage('relatorio_de_tendencias_de_nao_conformidades.php') ?>" href="<?= BASE_URL ?>gestor/relatorio_de_tendencias_de_nao_conformidades.php" title="Relatórios de Tendências e Conformidade"><i class="fas fa-chart-pie fa-fw me-1"></i>Relatórios</a>
                         </li>
                     </ul>

                    <ul class="navbar-nav align-items-lg-center user-nav-area-gestor">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center user-dropdown-toggle-gestor py-1 pe-0" href="#" id="userDropdownGestor" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <img src="<?= $fotoUrl ?>" alt="Foto Perfil" class="rounded-circle user-avatar-nav-gestor">
                                 <span class="user-name-nav-gestor d-none d-lg-inline ms-2 small"><?= htmlspecialchars($nomeUsuario) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-light mt-2 dropdown-menu-gestor" aria-labelledby="userDropdownGestor">
                                <li><h6 class="dropdown-header small text-uppercase text-primary"><?= htmlspecialchars($nomeUsuario) ?> <small class="text-muted fw-normal">(Gestor - <?= htmlspecialchars($nomeEmpresa) ?>)</small></h6></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/dashboard_gestor.php"><i class="fas fa-tachometer-alt fa-fw me-2 text-muted"></i> Meu Painel</a></li>
                                <?php if (isset($_SESSION['is_empresa_admin_cliente']) && $_SESSION['is_empresa_admin_cliente'] == 1): ?>
                                     <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/gerenciar_usuarios_empresa.php"><i class="fas fa-users-cog fa-fw me-2 text-muted"></i> Gerenciar Usuários da Empresa</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/configuracoes_gestor.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i> Minha Conta</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/solicitar_suporte_plataforma.php"><i class="fas fa-headset fa-fw me-2 text-muted"></i> Suporte AcodITools</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Sair</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="main-content flex-grow-1 <?= (str_contains($_SERVER['REQUEST_URI'], 'dashboard_gestor.php')) ? 'container-fluid px-lg-4 py-4' : 'container my-4' ?>">
    <?php
    return ob_get_clean();
}

function getFooterGestor(): string {
    // ... (código do footer mantido, mas com o script JS para o modal de primeiro acesso se necessário)
    if (!defined('BASE_URL')) { define('BASE_URL', '/WebApp/'); } // Fallback
    ob_start();
    ?>
        </main>
        <footer class="app-footer-gestor text-center py-3 border-top bg-light mt-auto">
             <span class="text-muted small">© <?= date("Y") ?> <?= htmlspecialchars($_SESSION['empresa_nome'] ?? 'AcodITools Cliente') ?>. Powered by AcodITools.</span>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script> const BASE_URL = '<?= BASE_URL ?>'; </script>
        <?php /* O script do modal de primeiro acesso é o mesmo do admin, pode estar em um JS global ou scripts_admin.js */ ?>
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script> <!-- Inclui o script do modal -->
        <script src="<?= BASE_URL ?>assets/js/scripts_gestor.js"></script> <!-- JS específico do gestor, se houver -->
        <script>
            document.addEventListener('DOMContentLoaded',function(){
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(e){ return new bootstrap.Tooltip(e); });
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>