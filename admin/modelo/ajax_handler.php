<?php
// admin/modelo/ajax_handler.php - Processa requisições AJAX para modelos

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

// Define tipo de resposta como JSON
header('Content-Type: application/json');

// Proteção Básica
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

// Valida CSRF Token
if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ajax_modelo_csrf_fail', 0, 'Token inválido.', $conexao);
    echo json_encode(['success' => false, 'message' => 'Erro de validação de segurança.']);
    exit;
}

$action = $_POST['action'] ?? null;
$response = ['success' => false, 'message' => 'Ação inválida.']; // Resposta padrão

switch ($action) {
    case 'salvar_ordem':
        $modelo_id = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT);
        $secao = isset($_POST['secao']) && $_POST['secao'] !== 'null' ? trim($_POST['secao']) : null; // Trata 'null' string
        $ordemIdsJson = $_POST['ordem_ids'] ?? '[]';
        $ordemIds = json_decode($ordemIdsJson, true);

        if ($modelo_id && is_array($ordemIds) && !empty($ordemIds)) {
            // Chama a função para salvar a ordem
            if (salvarOrdemItensModelo($conexao, $modelo_id, $ordemIds, $secao)) {
                $response = ['success' => true, 'message' => 'Ordem salva com sucesso.'];
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ordem_modelo_sucesso_ajax', 1, "Mod: $modelo_id, Sec: ".($secao ?? 'Geral'), $conexao);
            } else {
                $response['message'] = 'Erro ao salvar a ordem no banco de dados.';
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ordem_modelo_falha_ajax', 0, "Mod: $modelo_id, Sec: ".($secao ?? 'Geral'), $conexao);
            }
        } else {
            $response['message'] = 'Dados inválidos recebidos para salvar ordem.';
             dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ordem_modelo_dados_invalidos', 0, "Mod: $modelo_id", $conexao);
        }
        break;

    // Adicionar outros cases para ações AJAX futuras (ex: adicionar/remover item via AJAX)

    default:
        $response['message'] = 'Ação AJAX desconhecida.';
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ajax_modelo_acao_desconhecida', 0, "Ação: $action", $conexao);
        break;
}

// Regenerar CSRF token após processar ação AJAX POST
$_SESSION['csrf_token'] = gerar_csrf_token();
// Você pode incluir o novo token na resposta se o JS precisar dele
// $response['novo_csrf'] = $_SESSION['csrf_token'];

echo json_encode($response);
exit;
?>