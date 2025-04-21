<?php
// includes/atualizar_senha_primeiro_acesso.php

// Configurar cabeçalho JSON
header('Content-Type: application/json; charset=utf-8');

// Desativar exibição de erros e ativar log
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Ajuste o caminho conforme necessário

// Iniciar buffer para capturar saídas indesejadas
ob_start();

// Incluir arquivos necessários
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/admin_functions.php';
    require_once __DIR__ . '/db.php';
} catch (Exception $e) {
    error_log("Erro ao carregar arquivos de configuração: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno no servidor.']);
    ob_end_flush();
    exit;
}

// Iniciar sessão
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    error_log("Sessão inválida: usuario_id não definido");
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso não autorizado.']);
    ob_end_flush();
    exit;
}

// Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método inválido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    ob_end_flush();
    exit;
}

// Obter dados do formulário
$nova_senha = $_POST['nova_senha'] ?? '';
$confirmar_senha = $_POST['confirmar_senha'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

// Validar campos
if (empty($nova_senha) || empty($confirmar_senha)) {
    error_log("Campos vazios: nova_senha ou confirmar_senha");
    http_response_code(400);
    echo json_encode(['erro' => 'Preencha todos os campos.']);
    ob_end_flush();
    exit;
}

if ($nova_senha !== $confirmar_senha) {
    error_log("Senhas não coincidem");
    http_response_code(400);
    echo json_encode(['erro' => 'As senhas não coincidem.']);
    ob_end_flush();
    exit;
}

// Validar força da senha (igual ao frontend)
$senhaRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/';
if (!preg_match($senhaRegex, $nova_senha)) {
    error_log("Senha não atende aos requisitos");
    http_response_code(400);
    echo json_encode(['erro' => 'A senha deve ter pelo menos 8 caracteres, incluindo maiúscula, minúscula e número.']);
    ob_end_flush();
    exit;
}

// Atualizar senha
try {
    if (atualizarSenhaPrimeiroAcesso($conexao, $usuario_id, $nova_senha)) {
        error_log("Senha atualizada com sucesso para usuario_id: $usuario_id");
        echo json_encode(['sucesso' => true]);
    } else {
        error_log("Nenhuma linha afetada ao atualizar senha para usuario_id: $usuario_id");
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao atualizar a senha. Tente novamente.']);
    }
} catch (Exception $e) {
    error_log("Erro ao atualizar senha: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno: ' . $e->getMessage()]);
}

// Liberar buffer e encerrar
ob_end_flush();
exit;

/**
 * Atualiza a senha do usuário e marca primeiro acesso como concluído.
 */
function atualizarSenhaPrimeiroAcesso(PDO $conexao, int $usuario_id, string $nova_senha): bool {
    try {
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET senha = ?, primeiro_acesso = 0 WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([$senha_hash, $usuario_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro no banco de dados: " . $e->getMessage());
        throw $e;
    }
}