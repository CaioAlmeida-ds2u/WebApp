<?php
// includes/config.php

// Inicia a sessão (se ainda não foi iniciada) - deve ser uma das primeiras coisas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            case 'gestor':
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

// --- (Outras funções utilitárias globais podem ser adicionadas aqui) ---

?>