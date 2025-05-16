<?php
// includes/layout_admin.php
// Versão para Admin da Acoditools (SaaS)

function getHeaderAdmin($title) {
    // Inicia a sessão se ainda não foi iniciada (importante para acesso a $_SESSION)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $nome_admin_plataforma = isset($_SESSION['nome']) ? htmlspecialchars($_SESSION['nome']) : 'Admin Plataforma';
    // O perfil do Admin da Acoditools deve ser consistente, ex: 'admin'
    $perfilUsuario = $_SESSION['perfil'] ?? null;
    $foto_admin_plataforma = (isset($_SESSION['foto']) && !empty($_SESSION['foto'])) ? BASE_URL . $_SESSION['foto'] : BASE_URL . 'assets/img/default_profile.png';

    if (!defined('BASE_URL')) {
        // Lógica para definir BASE_URL se não existir (mantida do seu código original)
        // Recomendo definir BASE_URL de forma central em config.php
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $script_dir = str_replace('\\', '/', dirname(dirname(__FILE__))); // Ajuste para subir um nível de 'includes'
        $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $base_path = str_replace($doc_root, '', $script_dir);
        $base_path = rtrim($base_path, '/') . '/';
        define('BASE_URL', $protocol . $host . $base_path);
        // error_log("BASE_URL definida em layout_admin.php: " . BASE_URL);
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br" data-bs-theme="light"> <?php /* Adicionado data-bs-theme para temas Bootstrap */ ?>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> - AcodITools (Admin Ferramenta)</title>
        <?php /* Links CSS (mantidos, mas verifique caminhos de theme.css e style_admin.css) */ ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_admin.css">
        <link rel="icon" href="<?= BASE_URL ?>assets/img/favicon.ico" type="image/x-icon">
        <style>
            html, body { height: 100%; margin: 0; } /* Removido padding-top e ajustado margin */
            body { display: flex; flex-direction: column; font-family: 'Poppins', sans-serif; }
            main.container-fluid, main.container { flex: 1 0 auto; /* Faz o main crescer e empurra o footer */ }
            .navbar { position: fixed; top: 0; width: 100%; z-index: 1030; } /* Garante navbar fixa no topo */
            .navbar .dropdown-toggle::after { display: none; }
            .user-avatar { width: 36px; height: 36px; object-fit: cover; border: 1px solid rgba(255,255,255,0.3); }
            .dropdown-menu {
                min-width: 280px; /* Mantido para consistência */
                max-height: 70vh; /* Limita a altura a 70% da altura da viewport */
                overflow-y: auto; /* Habilita rolagem vertical */
                scrollbar-width: thin; /* Para navegadores modernos */
            }
            .dropdown-menu::-webkit-scrollbar {
                width: 6px; /* Largura da barra de rolagem para navegadores WebKit */
            }
            .dropdown-menu::-webkit-scrollbar-thumb {
                background-color: rgba(0, 0, 0, 0.3); /* Cor da barra de rolagem */
                border-radius: 3px;
            }
            .dropdown-menu .small i.fa-fw { opacity: 0.7; width: 1.3em; text-align: center; }
            .dropdown-header { font-weight: 600; }
            .navbar-brand img { filter: brightness(0) invert(1); /* Deixa logo branca na navbar escura */ }
        </style>
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm py-1">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center py-2" href="<?= BASE_URL ?>admin/dashboard_admin.php">
                    <img src="<?= BASE_URL ?>assets/img/ACodITools_logo.png" alt="AcodITools Logo" style="max-height: 30px; margin-right: 10px;">
                    <span style="font-family: 'Montserrat', sans-serif; font-weight: 700; font-size: 1.15rem;">AcodITools</span>
                    <span class="badge bg-primary ms-2 small">Admin</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarContent" aria-controls="adminNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="adminNavbarContent">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <?php if (isset($_SESSION['usuario_id']) && $perfilUsuario === 'admin'): ?>
                            <li class="nav-item d-none d-lg-block">
                                <span class="nav-link text-light pe-2 small">
                                    <i class="fas fa-user-shield me-1"></i><?= $nome_admin_plataforma ?>
                                </span>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center py-1" href="#" id="adminUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="<?= $foto_admin_plataforma ?>" alt="Foto Perfil" class="rounded-circle user-avatar me-lg-2">
                                    <span class="d-lg-none small">Menu Admin</span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="adminUserDropdown">
                                    <li><h6 class="dropdown-header small text-uppercase text-primary">Gestão de Clientes</h6></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/admin_gerenciamento_contas_clientes.php"><i class="fas fa-city fa-fw me-2 text-muted"></i>Empresas Clientes</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_gestao_planos_assinatura.php"><i class="fas fa-file-invoice-dollar fa-fw me-2 text-muted"></i>Planos de Assinatura</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/usuarios.php?view=clientes"><i class="fas fa-users-cog fa-fw me-2 text-muted"></i>Usuários de Clientes</a></li>

                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><h6 class="dropdown-header small text-uppercase text-primary">Bibliotecas Globais</h6></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php"><i class="fas fa-tasks fa-fw me-2 text-muted"></i>Requisitos Mestre</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/modelo/modelo_index.php"><i class="fas fa-clipboard-list fa-fw me-2 text-muted"></i>Modelos de Auditoria</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_catalogos_globais.php"><i class="fas fa-tags fa-fw me-2 text-muted"></i>Catálogos (NC, Criticidade)</a></li>

                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><h6 class="dropdown-header small text-uppercase text-primary">Configuração da Plataforma</h6></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_config_metodologia_risco.php"><i class="fas fa-shield-alt fa-fw me-2 text-muted"></i>Metodologia de Risco</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_config_workflows_auditoria.php"><i class="fas fa-project-diagram fa-fw me-2 text-muted"></i>Workflows de Auditoria</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_gerenciamento_campos_personalizados.php"><i class="fas fa-puzzle-piece fa-fw me-2 text-muted"></i>Campos Personalizados</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_parametros_globais.php"><i class="fas fa-sliders-h fa-fw me-2 text-muted"></i>Parâmetros Gerais</a></li>

                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><h6 class="dropdown-header small text-uppercase text-primary">Monitoramento & Suporte</h6></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/plataforma_monitoramento_e_saude.php"><i class="fas fa-heartbeat fa-fw me-2 text-muted"></i>Saúde da Plataforma</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/logs.php"><i class="fas fa-history fa-fw me-2 text-muted"></i>Logs da Plataforma</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/admin_suporte_a_clientes.php"><i class="fas fa-headset fa-fw me-2 text-muted"></i>Suporte a Clientes</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/admin_comunicados_plataforma.php"><i class="fas fa-bullhorn fa-fw me-2 text-muted"></i>Comunicados</a></li>

                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><h6 class="dropdown-header small text-uppercase text-muted">Conta Admin</h6></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/usuarios.php?view=admins_plataforma"><i class="fas fa-user-shield fa-fw me-2 text-muted"></i>Outros Admins</a></li>
                                    <li><a class="dropdown-item small" href="<?= BASE_URL ?>admin/configuracoes_admin.php"><i class="fas fa-user-cog fa-fw me-2 text-muted"></i>Meu Perfil</a></li>
                                    <li><a class="dropdown-item small text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Sair</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <?php
            // Ajusta a classe do container principal baseado na página atual para dashboards.
            // Você pode adicionar outras URIs de dashboard aqui se necessário.
            $admin_dashboards_uris = [
                'admin/dashboard_admin.php',
                'admin/plataforma_monitoramento_e_saude.php'
            ];
            $useFluidContainer = false;
            foreach($admin_dashboards_uris as $uri_dash) {
                if (str_contains($_SERVER['REQUEST_URI'], $uri_dash)) {
                    $useFluidContainer = true;
                    break;
                }
            }
        ?>
        <main class="<?= $useFluidContainer ? 'container-fluid px-lg-4 py-4 mt-5' : 'container my-4 mt-5' ?>"> <!-- Adicionado mt-5 para espaço abaixo da navbar fixa -->
    <?php
    return ob_get_clean();
}

function getFooterAdmin() {
    if (!defined('BASE_URL')) { define('BASE_URL', '/WebApp/'); } // Fallback consistente
    ob_start();
    ?>
        </main> <?php /* Fecha o <main> aberto no header */ ?>
        <footer class="bg-dark text-white-50 text-center py-3 mt-auto shadow-top"> <?php /* Footer escuro */ ?>
             <p class="mb-0 small">© <?= date("Y") ?> AcodITools - Ferramenta de Auditoria & Conformidade.</p>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script>
            // Define BASE_URL para ser usada em scripts JS inline ou externos
            const BASE_URL = '<?= BASE_URL ?>';
        </script>
        <script src="<?= BASE_URL ?>assets/js/scripts_admin.js"></script> <?php /* Seu JS principal do admin */ ?>
        <script>
            // Inicializador global de Tooltips Bootstrap (opcional, mas bom ter)
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