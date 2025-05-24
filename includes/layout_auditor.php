<?php
// includes/layout_auditor.php

/**
 * Gera o cabeçalho HTML completo para a área do Auditor.
 */
function getHeaderAuditor(string $title): string {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Definição robusta de BASE_URL (se não definida em config.php)
    if (!defined('BASE_URL')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        // CORREÇÃO AQUI: Usar dirname($_SERVER['SCRIPT_NAME']) diretamente ou atribuir a uma variável primeiro
        $script_name_path = dirname($_SERVER['SCRIPT_NAME']);
        $base_path_parts = explode('/', $script_name_path); // Agora $script_name_path está definido

        $app_root_parts = [];
        foreach ($base_path_parts as $part) {
            // Considerar se a aplicação pode estar em subdiretórios dos perfis
            if (in_array(strtolower($part), ['includes'])) { // Apenas 'includes' se for a pasta de onde deduzimos
                break;
            }
             // Se a estrutura for /WebApp/auditor/includes/, queremos /WebApp/
             // Se for /auditor/includes/, queremos /
             // Se for /includes/ (improvável para um layout de perfil), queremos /
            if (!empty($part)) {
                $app_root_parts[] = $part;
            }
        }
        
        // Ajuste para garantir que suba o suficiente na estrutura de pastas
        // Se o layout está em /auditor/includes/layout_auditor.php e a raiz é /
        // ou se está em /algumapp/auditor/includes/ e a raiz é /algumapp/
        // Esta lógica assume que 'includes' está sempre um nível abaixo da pasta do perfil,
        // e a pasta do perfil está um nível abaixo da raiz da aplicação, ou diretamente na raiz.
        // Se a estrutura for mais profunda, esta lógica de detecção de BASE_URL precisará ser mais robusta
        // ou, idealmente, BASE_URL ser definida fixamente em config.php.

        // Uma lógica de fallback mais simples, se a dedução for complexa:
        // $base_path_final = rtrim(dirname(dirname(dirname($_SERVER['PHP_SELF']))), '/\\') . '/'; // Sobe 3 níveis (de includes/layout -> perfil -> raiz)
        // if (str_ends_with($base_path_final, "//")) $base_path_final = "/"; // Caso raiz após subidas

        $base_path_final = "/" . implode('/', $app_root_parts);
        if (strlen($base_path_final) > 1 && substr($base_path_final, -1) !== '/') {
             $base_path_final .= '/';
        } elseif ($base_path_final === '') { // Caso de estar na raiz e includes ser o primeiro subdiretório
            $base_path_final = '/';
        }

        // Caso especial: Se script_name_path for apenas "/includes", significa que estamos em dominio.com/includes/
        // Nesse caso, a raiz é "/".
        if ($script_name_path === '/includes' || $script_name_path === '\\includes') {
            $base_path_final = '/';
        }


        define('BASE_URL', $protocol . $host . $base_path_final);
        // Para depuração:
        // error_log("layout_auditor.php - SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME']);
        // error_log("layout_auditor.php - dirname(SCRIPT_NAME): " . dirname($_SERVER['SCRIPT_NAME']));
        // error_log("layout_auditor.php - BASE_URL definida: " . BASE_URL);
    }

    // ... (resto da função getHeaderAuditor como estava antes) ...

    $nomeUsuario = $_SESSION['nome'] ?? 'Auditor';
    $perfilUsuarioSessao = $_SESSION['perfil'] ?? '';
    $fotoPath = $_SESSION['foto'] ?? '';
    $nomeEmpresa = $_SESSION['empresa_nome'] ?? 'Empresa Cliente';
    $logoPath = $_SESSION['empresa_logo'] ?? '';

    $fotoUrl = BASE_URL . 'assets/img/default_profile.png';
    if (!empty($fotoPath)) {
        $caminhoFisicoFotoAuditor = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim(str_replace(BASE_URL, '', BASE_URL . $fotoPath), '/');
        if (file_exists($caminhoFisicoFotoAuditor)) {
            $fotoUrl = BASE_URL . ltrim($fotoPath, '/') . '?v=' . @filemtime($caminhoFisicoFotoAuditor);
        }
    }
    
    $logoUrl = null;
    if (!empty($logoPath)) {
        $caminhoFisicoLogoEmpresa = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim(str_replace(BASE_URL, '', BASE_URL . $logoPath), '/');
        if (file_exists($caminhoFisicoLogoEmpresa)) {
            $logoUrl = BASE_URL . ltrim($logoPath, '/') . '?v=' . @filemtime($caminhoFisicoLogoEmpresa);
        }
    }
    

    if (!function_exists('isActiveAuditorPage')) {
        function isActiveAuditorPage($target_scripts_array) {
            $current_script_name = basename($_SERVER['SCRIPT_NAME']);
            if (!is_array($target_scripts_array)) {
                $target_scripts_array = [$target_scripts_array];
            }
            foreach ($target_scripts_array as $target_script) {
                if ($current_script_name === $target_script) {
                    return 'active';
                }
            }
            if (in_array('minhas_auditorias_auditor.php', $target_scripts_array) &&
                in_array($current_script_name, ['executar_auditoria.php', 'visualizar_relatorio_preliminar.php', 'auditor_revisao_e_consolidacao_equipe.php'])) {
                return 'active';
            }
            if (in_array('auditor_verificar_planos_acao.php', $target_scripts_array) &&
                $current_script_name === 'detalhes_plano_acao_auditor.php') {
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
        <title><?= htmlspecialchars($title) ?> | Auditor | <?= htmlspecialchars($nomeEmpresa) ?> - AcodITools</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_auditor.css">
        <link rel="icon" href="<?= BASE_URL ?>assets/img/favicon.ico" type="image/x-icon">
        <style> body { padding-top: 70px; } </style>
    </head>
    <body class="bg-body-tertiary auditor-body">

        <nav class="navbar navbar-expand-lg navbar-light fixed-top shadow-sm app-navbar-auditor py-2">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>auditor/dashboard_auditor.php">
                    <?php if ($logoUrl): ?>
                         <img src="<?= $logoUrl ?>" alt="Logo <?= htmlspecialchars($nomeEmpresa) ?>" class="logo-empresa-nav-auditor" style="max-height: 38px; border-radius: 4px; object-fit: contain;">
                    <?php else: ?>
                         <i class="fas fa-building fa-lg text-info me-2"></i>
                    <?php endif; ?>
                    <span class="nome-empresa-nav-auditor ms-2" style="font-weight: 600; font-size: 1.1rem; color: var(--bs-info);"><?= htmlspecialchars($nomeEmpresa) ?></span>
                     <span class="badge bg-info-subtle text-info-emphasis ms-2 rounded-pill">Auditor</span>
                </a>
                <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAuditorPortal" aria-controls="navbarNavAuditorPortal" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="fas fa-bars text-info"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavAuditorPortal">
                     <ul class="navbar-nav main-nav-links-auditor me-auto mb-2 mb-lg-0 ps-lg-4">
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveAuditorPage(['dashboard_auditor.php']) ?>" href="<?= BASE_URL ?>auditor/dashboard_auditor.php"><i class="fas fa-tachometer-alt fa-fw me-1"></i>Meu Painel</a>
                         </li>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= isActiveAuditorPage(['minhas_auditorias_auditor.php', 'executar_auditoria.php', 'visualizar_relatorio_preliminar.php', 'auditor_revisao_e_consolidacao_equipe.php']) ?>" href="#" id="auditoriasDropdownAuditor" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-clipboard-list fa-fw me-1"></i>Auditorias
                            </a>
                            <ul class="dropdown-menu shadow-sm border-light" aria-labelledby="auditoriasDropdownAuditor">
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php">Minhas Auditorias Atribuídas</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/auditor_revisao_e_consolidacao_equipe.php">Consolidar Relatórios (Líder)</a></li>
                            </ul>
                        </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveAuditorPage(['auditor_verificar_planos_acao.php']) ?>" href="<?= BASE_URL ?>auditor/auditor_verificar_planos_acao.php"><i class="fas fa-clipboard-check fa-fw me-1"></i>Verificar Planos de Ação</a>
                         </li>
                          <li class="nav-item">
                             <a class="nav-link <?= isActiveAuditorPage(['minhas_equipes_de_auditoria.php']) ?>" href="<?= BASE_URL ?>auditor/minhas_equipes_de_auditoria.php"><i class="fas fa-users fa-fw me-1"></i>Minhas Equipes</a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveAuditorPage(['auditor_biblioteca_referencia.php']) ?>" href="<?= BASE_URL ?>auditor/auditor_biblioteca_referencia.php" title="Consultar Modelos Globais, Requisitos e Documentos da Auditoria"><i class="fas fa-book-open fa-fw me-1"></i>Biblioteca de Referência</a>
                         </li>
                     </ul>

                    <ul class="navbar-nav align-items-lg-center user-nav-area-auditor">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center user-dropdown-toggle-auditor py-1 pe-0" href="#" id="userDropdownAuditorPortal" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <img src="<?= $fotoUrl ?>" alt="Foto Perfil" class="rounded-circle user-avatar-nav-auditor" style="width: 38px; height: 38px;">
                                 <span class="user-name-nav-auditor d-none d-lg-inline ms-2 small fw-medium"><?= htmlspecialchars($nomeUsuario) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-light mt-2 dropdown-menu-auditor" aria-labelledby="userDropdownAuditorPortal" style="min-width: 270px;">
                                <li><h6 class="dropdown-header small text-uppercase text-info"><?= htmlspecialchars($nomeUsuario) ?> <small class="text-muted fw-normal">(Auditor)</small></h6></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/dashboard_auditor.php"><i class="fas fa-tachometer-alt fa-fw me-2 text-muted"></i> Meu Painel de Controle</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/minhas_configuracoes_auditor.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i> Minha Conta e Preferências</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/ajuda_e_suporte_auditor.php" title="Ajuda sobre a plataforma AcodITools"><i class="fas fa-question-circle fa-fw me-2 text-muted"></i> Ajuda e Suporte AcodITools</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Sair</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="main-content main-content-auditor flex-grow-1 <?= (str_contains($_SERVER['REQUEST_URI'], 'dashboard_auditor.php')) ? 'container-fluid px-lg-4 py-4' : 'container my-4' ?>">
    <?php
    return ob_get_clean();
}

/**
 * Gera o rodapé HTML completo para a área do Auditor.
 */
function getFooterAuditor(): string {
    if (!defined('BASE_URL')) { define('BASE_URL', '/WebApp/'); }
    ob_start();
    ?>
        </main>
        <footer class="app-footer-auditor text-center py-3 border-top bg-light mt-auto shadow-top-sm">
             <p class="text-muted small mb-0">© <?= date("Y") ?> <strong class="text-info"><?= htmlspecialchars($_SESSION['empresa_nome'] ?? 'Sua Empresa') ?></strong>. Plataforma de Auditoria AcodITools.</p>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script> const BASE_URL = '<?= BASE_URL ?>'; </script>
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script> <?php /* Para o modal de primeiro acesso comum */ ?>
        <script src="<?= BASE_URL ?>assets/js/scripts_auditor.js"></script>
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