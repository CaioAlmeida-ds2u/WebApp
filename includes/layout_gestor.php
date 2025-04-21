<?php
// includes/layout_gestor.php - Layout Independente para Gestor (v2 - Logo Empresa Corrigido)

/**
 * Gera o cabeçalho HTML completo para a área do Gestor.
 * Mostra LOGO (se existir) E NOME DA EMPRESA na navbar.
 * Usa um placeholder genérico se o logo da empresa não existir.
 *
 * @param string $title O título da página.
 * @return string O HTML do cabeçalho.
 */
function getHeaderGestor(string $title): string {
    // --- Checagens Iniciais e Dados da Sessão ---
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    if (!defined('BASE_URL')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $base_path = str_replace($doc_root, '', $script_dir);
        if (strpos($base_path, '/includes') !== false) { $base_path = dirname($base_path); }
        $base_path = rtrim($base_path, '/') . '/';
        define('BASE_URL', $protocol . $host . $base_path);
        error_log("ALERTA: layout_gestor.php - BASE_URL definida automaticamente como: " . BASE_URL);
    }

    // Dados do Usuário (Gestor)
    $nomeUsuario = $_SESSION['nome'] ?? 'Gestor';
    $fotoPath = $_SESSION['foto'] ?? 'assets/img/default_profile.png';
    $fotoUrl = BASE_URL . ltrim($fotoPath, '/');
    if (!empty($_SESSION['foto']) && !file_exists(__DIR__.'/../'.ltrim($fotoPath, '/'))) {
         $fotoUrl = BASE_URL . 'assets/img/default_profile.png';
         error_log("AVISO layout_gestor: Foto do usuário não encontrada em '$fotoPath'");
    }

    // Dados da Empresa do Gestor
    $nomeEmpresa = $_SESSION['empresa_nome'] ?? 'Minha Empresa'; // Padrão mais genérico
    $logoPath = $_SESSION['empresa_logo'] ?? null; // Caminho relativo vindo da sessão (ex: 'uploads/logos/logo_empresa_X...')
    $logoUrl = null;
    if ($logoPath) {
        $fullLogoPathCheck = __DIR__.'/../'.ltrim($logoPath, '/'); // Caminho absoluto para checar
        if (file_exists($fullLogoPathCheck)) {
            $logoUrl = BASE_URL . ltrim($logoPath, '/') . '?t=' . filemtime($fullLogoPathCheck); // URL com cache bust
        } else {
             error_log("AVISO layout_gestor: Logo da empresa '$nomeEmpresa' não encontrado no caminho físico '$logoPath'");
             // Não define $logoUrl, vai usar o placeholder
        }
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($nomeEmpresa) ?></title>
        <?php /* Links CSS */ ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css"> <?php /* Reutiliza base admin */ ?>
        <!-- <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_gestor.css"> --> <?php /* Específico gestor se necessário */ ?>
        <link rel="icon" href="<?= BASE_URL ?>assets/img/favicon.ico" type="image/x-icon">
        <style>
            html, body { height: 100%; }
            body { display: flex; flex-direction: column; font-family: 'Poppins', sans-serif; }
            main { flex: 1 0 auto; } footer { flex-shrink: 0; }
            /* Estilo para o logo e nome da empresa na navbar */
            .navbar-brand img.logo-empresa-nav {
                max-height: 35px; /* Ajuste conforme necessário */
                height: auto;
                margin-right: 0.6rem;
                object-fit: contain; /* Para não distorcer */
                vertical-align: middle;
            }
             .navbar-brand .logo-placeholder-nav { /* Estilo para o ícone placeholder */
                 font-size: 1.4em; /* Tamanho do ícone */
                 margin-right: 0.6rem;
                 vertical-align: middle;
                 opacity: 0.7;
            }
            .navbar-brand span.nome-empresa-nav {
                font-family: 'Montserrat', sans-serif;
                font-weight: 600;
                font-size: 1.1rem;
                color: var(--bs-navbar-brand-color, #fff);
                vertical-align: middle;
            }
            /* Outros estilos do layout anterior mantidos */
             .navbar .dropdown-toggle::after { display: none; }
            .user-avatar { width: 32px; height: 32px; object-fit: cover; border: 1px solid rgba(255,255,255,0.2); }
            .dropdown-menu { min-width: 220px; } .dropdown-menu .small i.fa-fw { opacity: 0.7; }
        </style>
    </head>
    <body class="bg-body-tertiary">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-1">
            <div class="container-fluid">
                <?php /* ==== NAVBAR BRAND CORRIGIDO ==== */ ?>
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>gestor/dashboard_gestor.php">
                    <?php if ($logoUrl): // Verifica se $logoUrl foi definido (implica que arquivo existe) ?>
                         <img src="<?= $logoUrl ?>" alt="Logo <?= htmlspecialchars($nomeEmpresa) ?>" class="p-1 bg-white rounded logo-empresa-nav">
                    <?php else: ?>
                         <?php // Placeholder: Ícone genérico de prédio ?>
                         <i class="fas fa-building fa-fw logo-placeholder-nav text-white-50"></i>
                    <?php endif; ?>
                    <?php // Nome da empresa sempre exibido ?>
                    <span class="nome-empresa-nav"><?= htmlspecialchars($nomeEmpresa) ?></span>
                </a>
                <?php /* ==== FIM NAVBAR BRAND CORRIGIDO ==== */ ?>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavGestor" aria-controls="navbarNavGestor" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="navbarNavGestor">
                    <ul class="navbar-nav ms-auto align-items-center">
                         <li class="nav-item d-none d-lg-block"><span class="nav-link text-white-50 pe-2 small">Olá, <?= $nomeUsuario ?></span></li>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center py-1" href="#" id="userDropdownGestor" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <img src="<?= $fotoUrl ?>" alt="Foto Perfil" class="rounded-circle user-avatar me-1">
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userDropdownGestor">
                                <li><h6 class="dropdown-header small text-uppercase text-muted">Gestor</h6></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/dashboard_gestor.php"><i class="fas fa-tachometer-alt fa-fw me-2 text-muted"></i>Dashboard</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/minhas_auditorias.php"><i class="fas fa-clipboard-list fa-fw me-2 text-muted"></i>Minhas Auditorias</a></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/gerenciar_auditores.php"><i class="fas fa-users-cog fa-fw me-2 text-muted"></i>Gerenciar Auditores</a></li>
                                <li><a class="dropdown-item small disabled" href="#"><i class="fas fa-file-alt fa-fw me-2 text-muted"></i>Relatórios da Empresa</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><h6 class="dropdown-header small text-uppercase text-muted">Conta</h6></li>
                                <li><a class="dropdown-item small" href="<?= BASE_URL ?>gestor/configuracoes_gestor.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i>Configurações</a></li>
                                <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Sair</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <main class="container-fluid px-lg-4 py-4"> <?php /* Abre <main> aqui */ ?>
    <?php
    return ob_get_clean();
}

/**
 * Gera o rodapé HTML para a área do Gestor. Fecha <main>.
 */
function getFooterGestor(): string {
    if (!defined('BASE_URL')) { define('BASE_URL', '/'); }
    ob_start();
    ?>
        </main> <?php /* Fecha o <main> aberto no header */ ?>
        <footer class="bg-white text-center py-2 mt-auto border-top shadow-sm">
             <p class="mb-0 text-muted small">© <?= date("Y") ?> ACodITools.</p>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script> const BASE_URL = '<?= BASE_URL ?>'; </script>
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script> <?php /* Script com modal 1º acesso */ ?>
        <script> document.addEventListener('DOMContentLoaded',function(){ var tt=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')); var tl=tt.map(function(e){ return new bootstrap.Tooltip(e) }) }); </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>