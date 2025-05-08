<?php
// includes/layout_auditor.php - Layout Padrão para a Área do AUDITOR

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    // Tenta determinar o caminho base de forma mais robusta
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Remove /includes ou /admin ou /gestor etc. se estiver neles
    $base_path = preg_replace('/\/includes$|\/admin$|\/gestor$|\/auditor$/', '', $script_dir);
    $base_path = rtrim($base_path, '/') . '/'; // Garante barra no final
    define('BASE_URL', $protocol . $host . $base_path);
}

/**
 * Gera o cabeçalho HTML completo para a área do Auditor.
 */
function getHeaderAuditor(string $title): string {
    // Dados da Sessão
    $nomeUsuario = $_SESSION['nome'] ?? 'Auditor';
    $fotoPathRelativa = $_SESSION['foto'] ?? null; // Caminho relativo da pasta raiz do projeto (ex: uploads/fotos_perfil/img.jpg)
    $nomeEmpresa = $_SESSION['empresa_nome'] ?? 'Empresa Auditoria';
    $logoPathRelativa = $_SESSION['empresa_logo'] ?? null; // Caminho relativo da pasta raiz

    // Monta URL da foto e verifica existência
    $fotoUrl = BASE_URL . 'assets/img/default_profile.png'; // Default placeholder
    if ($fotoPathRelativa) {
        $caminhoFisicoFoto = $_SERVER['DOCUMENT_ROOT'] . rtrim(BASE_URL, '/') . '/' . ltrim($fotoPathRelativa, '/');
        if (file_exists($caminhoFisicoFoto)) {
            $fotoUrl = BASE_URL . ltrim($fotoPathRelativa, '/');
        }
    }

    // Monta URL do logo e verifica existência
    $logoUrl = null;
    if ($logoPathRelativa) {
        $caminhoFisicoLogo = $_SERVER['DOCUMENT_ROOT'] . rtrim(BASE_URL, '/') . '/' . ltrim($logoPathRelativa, '/');
         if (file_exists($caminhoFisicoLogo)) {
             // Adiciona timestamp para cache busting se o arquivo existe
             $logoUrl = BASE_URL . ltrim($logoPathRelativa, '/') . '?t=' . filemtime($caminhoFisicoLogo);
         }
    }

    // Função para Marcar Página Ativa (Específica para Auditor)
    if (!function_exists('isActiveAuditorPage')) {
        function isActiveAuditorPage($target_script_name) {
            $current_script_name = basename($_SERVER['SCRIPT_NAME']);
            // Lógica de ativação
            if ($current_script_name === $target_script_name) {
                return 'active';
            }
            // Ativa 'minhas_auditorias' se estiver executando uma
            if ($target_script_name === 'minhas_auditorias_auditor.php' && $current_script_name === 'executar_auditoria.php') {
                return 'active';
            }
             // Adicionar mais regras se necessário
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
        <title><?= htmlspecialchars($title) ?> | Auditor | <?= htmlspecialchars($nomeEmpresa) ?></title>
        <?php /* Links CSS */ ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_auditor.css"> <?php /* <<< CSS ESPECÍFICO */ ?>
        <link rel="icon" href="<?= BASE_URL ?>assets/img/favicon.ico" type="image/x-icon">
        <style>
            /* Estilos inline mínimos para estrutura base */
            html, body { height: 100%; }
            body { display: flex; flex-direction: column; font-family: 'Poppins', sans-serif; padding-top: 65px; /* Altura da navbar ajustada */ }
            main { flex: 1 0 auto; } footer { flex-shrink: 0; }
        </style>
    </head>
    <body class="bg-body-tertiary">

        <!-- ===== Navbar Principal (Auditor) ===== -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm app-navbar-auditor">
            <div class="container-fluid">
                <!-- Brand (Logo e Nome da Empresa) -->
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>auditor/dashboard_auditor.php">
                    <?php if ($logoUrl): ?>
                         <img src="<?= $logoUrl ?>" alt="Logo <?= htmlspecialchars($nomeEmpresa) ?>" style="max-height: 38px; width: auto; object-fit: contain; margin-right: 8px;">
                    <?php else: // Placeholder se não houver logo ?>
                         <i class="fas fa-building fa-2x text-primary me-2"></i>
                    <?php endif; ?>
                    <span style="font-weight: 600; font-size: 1.1rem; color: var(--bs-primary);"><?= htmlspecialchars($nomeEmpresa) ?></span>
                </a>

                <!-- Toggler Mobile -->
                <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAuditor" aria-controls="navbarNavAuditor" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="fas fa-bars text-primary"></i>
                </button>

                <!-- Conteúdo Colapsável -->
                <div class="collapse navbar-collapse" id="navbarNavAuditor">
                     <!-- Links Principais do Auditor -->
                     <ul class="navbar-nav main-nav-links-auditor me-auto mb-2 mb-lg-0 ps-lg-4">
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveAuditorPage('dashboard_auditor.php') ?>" href="<?= BASE_URL ?>auditor/dashboard_auditor.php"><i class="fas fa-tachometer-alt fa-fw me-1"></i>Dashboard</a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link <?= isActiveAuditorPage('minhas_auditorias_auditor.php') ?>" href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php"><i class="fas fa-tasks fa-fw me-1"></i>Minhas Auditorias</a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link disabled" href="#" title="Meus Planos de Ação (Em breve)"><i class="fas fa-bullseye-pointer fa-fw me-1"></i>Planos de Ação</a>
                         </li>
                         <!-- <li class="nav-item">
                             <a class="nav-link disabled" href="#" title="Relatórios (Em breve)"><i class="fas fa-chart-bar fa-fw me-1"></i>Relatórios</a>
                         </li> -->
                     </ul>

                     <!-- Área do Usuário -->
                    <ul class="navbar-nav align-items-lg-center user-nav-area-auditor">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center py-1 pe-0" href="#" id="userDropdownAuditor" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <img src="<?= $fotoUrl ?>" alt="Foto Perfil" class="rounded-circle" style="width: 35px; height: 35px; object-fit: cover;">
                                 <span class="d-none d-lg-inline ms-2 small" style="font-weight: 500;"><?= htmlspecialchars($nomeUsuario) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-light mt-2" aria-labelledby="userDropdownAuditor">
                                <li><h6 class="dropdown-header small text-uppercase text-primary"><?= htmlspecialchars($nomeUsuario) ?> <small class="text-muted fw-normal">(Auditor)</small></h6></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/dashboard_auditor.php"><i class="fas fa-tachometer-alt fa-fw me-2 text-muted"></i> Dashboard</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php"><i class="fas fa-tasks fa-fw me-2 text-muted"></i> Minhas Auditorias</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>auditor/configuracoes_auditor.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i> Minha Conta</a></li>
                                <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2 text-danger"></i> Sair</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Conteúdo Principal -->
        <main class="main-content flex-grow-1 container-fluid px-lg-4 py-4">
    <?php
    return ob_get_clean();
}

/**
 * Gera o rodapé HTML completo para a área do Auditor.
 */
function getFooterAuditor(): string {
    if (!defined('BASE_URL')) { define('BASE_URL', '/'); } // Garante que BASE_URL existe
    ob_start();
    ?>
        </main> <?php /* Fecha <main> */ ?>

        <footer class="app-footer-auditor text-center py-3 border-top bg-light mt-auto">
             <span class="text-muted small">© <?= date("Y") ?> ACodITools - Auditoria & Conformidade.</span>
        </footer>

        <!-- Scripts Essenciais -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script> const BASE_URL = '<?= BASE_URL ?>'; </script>

        <!-- Script JS específico do Auditor (se necessário) -->
        <!-- <script src="<?= BASE_URL ?>assets/js/scripts_auditor.js"></script> -->

        <!-- Inicializador global de Tooltips -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>