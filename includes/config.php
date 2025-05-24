<?php
// includes/config.php

// Inicia a sessão (se ainda não foi iniciada) - deve ser uma das primeiras coisas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//Configuração de constantes e variáveis globais
// Definindo constantes para o ambiente de desenvolvimento

define('UPLOADS_FOTO', __DIR__ . '/../../uploads/'); // Caminho relativo para uploads de fotos
define('UPLOADS_BASE_PATH', __DIR__ . '/../uploads/'); // Caminho absoluto para uploads
define('MAX_UPLOAD_SIZE_MB', 10); // Tamanho máximo de upload em MB
define('MAX_UPLOAD_SIZE_BYTES', MAX_UPLOAD_SIZE_MB * 1024 * 1024); // Tamanho máximo em bytes
define('UPLOADS_BASE_PATH_ABSOLUTE', $_SERVER['DOCUMENT_ROOT'] . '/WebApp/uploads/');


// --- Configuração Base URL ---
// !! AJUSTE CONFORME SEU AMBIENTE !!
// Se o app está em http://localhost/WebApp/, BASE_URL é '/WebApp/'
// Se o app está em http://localhost/, BASE_URL é '/'
define('BASE_URL', '/WebApp/'); // ** VERIFIQUE E AJUSTE ESTE VALOR **

// --- Configurações do Banco de Dados ---
// !! ALERTA DE SEGURANÇA !!
// Em produção, NUNCA use 'root', senha em branco, ou deixe credenciais no código.
// Use VARIÁVEIS DE AMBIENTE do servidor ou um arquivo .env seguro.

define('DB_HOST', 'localhost');
define('DB_NAME', 'acoditools');
define('DB_USER', 'root');      // Trocar em produção!
define('DB_PASS', '');          // Trocar em produção!

// --- Conexão com o Banco de Dados (PDO) ---
try {
    $conexao = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false // Boa prática para segurança
        ]
    );
} catch (PDOException $e) {
    error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
    // Em produção, NÃO exiba detalhes do erro para o usuário final.
    die("Ocorreu um erro crítico na aplicação. Por favor, tente novamente mais tarde ou contate o suporte.");
}

// --- Geração e Armazenamento do Token CSRF ---
// Gera um token CSRF para a sessão se não existir um.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Disponibiliza o token para ser usado nas páginas
$csrf_token = $_SESSION['csrf_token'];


// --- Includes Essenciais (após conexão e CSRF) ---
// Inclui funções de banco de dados aqui, pois são usadas por funções abaixo.
require_once __DIR__ . '/db.php';
// Funções de upload podem ser necessárias globalmente se usadas fora do admin
require_once __DIR__ . '/funcoes_upload.php';
// Funções de admin podem não ser necessárias globalmente, mas vamos manter por enquanto
// Se alguma função de config precisar delas, mantenha. Senão, pode mover para includes do admin.
// require_once __DIR__ . '/admin_functions.php'; // Avaliar necessidade global

// --- Funções de Autenticação e Utilitárias ---

/**
 * Verifica se o usuário está logado. Se não, redireciona para o login.
 * Depende de $conexao para logar a tentativa de acesso negado.
 */
function protegerPagina(PDO $conexao) { // Passar $conexao explicitamente
    if (!isset($_SESSION['usuario_id'])) {
        redirecionarParaLogin($conexao); // Chama a função que já faz o log
    }
}

/**
 * Se o usuário já está logado, redireciona para o dashboard apropriado.
 */
function redirecionarUsuarioLogado() {
    // Verifica se existe um ID de usuário na sessão
    if (isset($_SESSION['usuario_id'])) {

        // Obtém o perfil da sessão
        $perfilLogado = $_SESSION['perfil'] ?? null;
        $redirect_url = ''; // Inicializa URL

        // Determina o destino com base no perfil
        switch ($perfilLogado) {
            case 'admin':
                $redirect_url = BASE_URL . 'admin/dashboard_admin.php';
                break;
            case 'gestor_empresa':
                // !! VERIFIQUE O CAMINHO CORRETO !!
                $redirect_url = BASE_URL . 'gestor/dashboard_gestor.php'; // Assumindo pasta 'gestor'
                break;
            case 'auditor':
                 // !! VERIFIQUE O CAMINHO CORRETO !!
                 $redirect_url = BASE_URL . 'auditor/dashboard_auditor.php'; // Assumindo pasta 'auditor'
                 break;
            // --- OPCIONAL: Adicionar um perfil 'usuario' comum ---
            /*
            case 'usuario':
                 // !! VERIFIQUE O CAMINHO CORRETO !!
                 $redirect_url = BASE_URL . 'usuario/dashboard_user.php'; // Assumindo pasta 'usuario'
                 break;
            */
            // --- FIM OPCIONAL ---
            default:
                // Perfil desconhecido ou inválido - Melhor redirecionar para login ou uma página de erro segura
                // Evita redirecionar para um local inesperado. Logar o erro é uma boa ideia.
                error_log("redirecionarUsuarioLogado: Perfil inválido/nulo encontrado na sessão: " . ($perfilLogado ?? 'NULL') . " para usuário ID: " . $_SESSION['usuario_id']);
                // Redirecionar para o login pode ser uma opção segura, forçando re-autenticação.
                // Ou, se houver uma página padrão para todos, use-a.
                // Por segurança, vamos redirecionar para o logout que limpará a sessão.
                $redirect_url = BASE_URL . 'logout.php?erro=perfil_invalido_sessao';
                break;
        }

        // Executa o redirecionamento se uma URL válida foi definida
        if (!empty($redirect_url)) {
            header('Location: ' . $redirect_url);
            exit; // ESSENCIAL para parar a execução do script atual
        }
        // Se $redirect_url ficou vazia (improvável com o default), o script continua,
        // o que pode ser um risco de segurança. O default agora redireciona para logout.

    }
    // Se não há 'usuario_id' na sessão, não faz nada, permitindo que a página atual continue.
}

/**
 * Verifica se há um usuário logado na sessão.
 * @return bool True se logado, False caso contrário.
 */
function usuarioEstaLogado(): bool {
    return isset($_SESSION['usuario_id']);
}

/**
 * Registra um log de acesso negado e redireciona para a página de login.
 */
function redirecionarParaLogin(PDO $conexao) { // Passar $conexao explicitamente
    // Usar a função de log já existente em db.php
    dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'acesso_negado', 0, 'Tentativa de acesso à página restrita sem login.', $conexao);
    header('Location: ' . BASE_URL . 'index.php?erro=acesso_negado');
    exit;
}
function formatarDataRelativa(?string $dIso): string {
    if (empty($dIso)) return 'N/D';
    try {
        $ts = strtotime($dIso);
        if ($ts === false) return 'Inválida';
        $n = time();
        $diff = $n - $ts;
        if ($diff < 0) return date('d/m/Y', $ts);
        if ($diff < 60) return 'agora';
        $m = round($diff / 60);
        if ($m < 60) return $m . ' min';
        $h = round($diff / 3600);
        if ($h < 24) return $h . 'h';
        $d = round($diff / 86400);
        if ($d < 7) return $d . 'd';
        $w = round($diff / 604800);
        if ($w <= 4) return $w . ' sem';
        $mes = round($diff / (86400 * 30.44));
        if ($mes < 12) return $mes . ' mes';
        return date('d/m/Y', $ts);
    } catch (Exception $e) {
        return 'Erro';
    }
}

function definir_flash_message(string $t, string $m): void {
    if (session_status() != PHP_SESSION_ACTIVE) return;
    if (!isset($_SESSION['flash_messages'])) $_SESSION['flash_messages'] = [];
    $_SESSION['flash_messages'][$t] = $m;
}

function obter_flash_message(string $t): ?string {
    if (session_status() != PHP_SESSION_ACTIVE || !isset($_SESSION['flash_messages'][$t])) return null;
    $m = $_SESSION['flash_messages'][$t];
    unset($_SESSION['flash_messages'][$t]);
    if (empty($_SESSION['flash_messages'])) unset($_SESSION['flash_messages']);
    return $m;
}

function gerar_csrf_token(): string {
    if (session_status() != PHP_SESSION_ACTIVE) return '';
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid(rand(), true) . microtime());
        }
    }
    return $_SESSION['csrf_token'];
}

function validar_csrf_token(?string $t): bool {
    if (session_status() != PHP_SESSION_ACTIVE || empty($t) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $t);
}

if (!function_exists('formatarDataCompleta')) {
    function formatarDataCompleta(?string $dataHoraIso, string $formato = 'd/m/Y H:i', string $default = 'N/D'): string {
        if (empty($dataHoraIso)) return $default;
        try {
            $dt = new DateTime($dataHoraIso);
            return $dt->format($formato);
        } catch (Exception $e) {
            return $default; // Ou $dataHoraIso se preferir mostrar o original em caso de erro
        }
    }
}

if (!function_exists('formatarDataSimples')) {
    function formatarDataSimples(?string $dataIso, string $formato = 'd/m/Y', string $default = 'N/D'): string {
        if (empty($dataIso)) return $default;
        try {
            $dt = new DateTime($dataIso);
            return $dt->format($formato);
        } catch (Exception $e) {
            return $default;
        }
    }
}


if (!function_exists('formatarTamanhoArquivo')) {
    function formatarTamanhoArquivo($bytes) {
        if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2) . ' GB'; }
        elseif ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
        elseif ($bytes >= 1024) { return number_format($bytes / 1024, 2) . ' KB'; }
        elseif ($bytes > 1) { return $bytes . ' bytes'; }
        elseif ($bytes == 1) { return $bytes . ' byte'; }
        else { return '0 bytes'; }
    }
}

if (!function_exists('getIconePorTipoMime')) {
    function getIconePorTipoMime($mime_type) {
        if (empty($mime_type)) return 'fa-file';
        if (str_starts_with($mime_type, 'image/')) return 'fa-file-image text-success';
        if (str_starts_with($mime_type, 'audio/')) return 'fa-file-audio text-info';
        if (str_starts_with($mime_type, 'video/')) return 'fa-file-video text-purple';
        switch (strtolower($mime_type)) {
            case 'application/pdf': return 'fa-file-pdf text-danger';
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return 'fa-file-word text-primary';
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return 'fa-file-excel text-success';
            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                return 'fa-file-powerpoint text-warning';
            case 'application/zip': case 'application/x-rar-compressed': case 'application/x-7z-compressed':
                return 'fa-file-archive text-secondary';
            case 'text/plain': return 'fa-file-alt text-muted'; // ou fa-file-lines
            case 'text/csv': return 'fa-file-csv text-info';
            default: return 'fa-file text-muted';
        }
    }
}

if (!function_exists('exibirBadgeStatusItem')) {
    function exibirBadgeStatusItem($status) {
        $badge_class = 'bg-secondary-subtle text-secondary-emphasis';
        switch ($status) {
            case 'Pendente': $badge_class = 'bg-light text-dark border'; break;
            case 'Conforme': $badge_class = 'bg-success-subtle text-success-emphasis border-success-subtle'; break;
            case 'Não Conforme': $badge_class = 'bg-danger-subtle text-danger-emphasis border-danger-subtle'; break;
            case 'Parcial': $badge_class = 'bg-warning-subtle text-warning-emphasis border-warning-subtle'; break;
            case 'N/A': $badge_class = 'bg-info-subtle text-info-emphasis border-info-subtle'; break;
        }
        return '<span class="badge rounded-pill ' . $badge_class . ' x-small fw-semibold">' . htmlspecialchars($status) . '</span>';
    }
}

if (!function_exists('exibirBadgeStatusAuditoria')) {
    function exibirBadgeStatusAuditoria($status) {
        $badgeClass = 'bg-dark'; // Default mais escuro para 'Pausada' ou outros inesperados
        if ($status == 'Planejada') $badgeClass = 'bg-light text-dark border';
        elseif ($status == 'Em Andamento') $badgeClass = 'bg-primary';
        elseif ($status == 'Concluída (Auditor)') $badgeClass = 'bg-warning text-dark';
        elseif ($status == 'Em Revisão') $badgeClass = 'bg-info text-dark'; // Cor diferente para Em Revisão
        elseif ($status == 'Aprovada') $badgeClass = 'bg-success';
        elseif ($status == 'Rejeitada') $badgeClass = 'bg-danger';
        elseif ($status == 'Cancelada') $badgeClass = 'bg-secondary';
        return '<span class="badge rounded-pill ' . $badgeClass . ' fs-6 px-3 py-1">' . htmlspecialchars($status) . '</span>';
    }
}

if (!function_exists('exibirBadgeStatusPlanoAcao')) {
    function exibirBadgeStatusPlanoAcao($status) {
        $badge_class = 'bg-secondary'; // Default
        switch ($status) {
            case 'Pendente': $badge_class = 'bg-warning text-dark'; break;
            case 'Em Andamento': $badge_class = 'bg-info text-dark'; break;
            case 'Concluída': $badge_class = 'bg-success'; break;
            case 'Cancelada': $badge_class = 'bg-dark'; break;
            case 'Atrasada': $badge_class = 'bg-danger'; break;
            case 'Verificada': $badge_class = 'bg-primary'; break; // Verificada (implica eficácia)
        }
        return '<span class="badge rounded-pill ' . $badge_class . '">' . htmlspecialchars($status) . '</span>';
    }
}