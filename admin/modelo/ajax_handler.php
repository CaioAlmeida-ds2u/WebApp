<?php
// admin/modelo/ajax_handler.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Autenticação necessária.']);
    exit;
}

if (!validar_csrf_token($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null)) { // Checa POST e GET para flexibilidade
    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ajax_modelo_csrf_fail', 0, 'Token CSRF inválido.', $conexao);
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Erro de validação de segurança (CSRF).']);
    exit;
}

// Determinar a ação (pode vir de POST ou GET para algumas ações simples como remover)
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$response = ['success' => false, 'message' => 'Ação inválida ou não especificada.'];
$admin_id = $_SESSION['usuario_id'];

switch ($action) {
    case 'salvar_ordem':
        $modelo_id = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT);
        $secao_raw = $_POST['secao'] ?? null;
        // Tratar a string 'Itens Gerais' ou string vazia/null como NULL para o banco
        $secao = ($secao_raw === 'Itens Gerais' || $secao_raw === '' || $secao_raw === null || $secao_raw === 'null') ? null : trim($secao_raw);
        $ordemIdsJson = $_POST['ordem_ids'] ?? '[]';
        $ordemIds = json_decode($ordemIdsJson, true);

        if (!$modelo_id || $modelo_id <= 0) {
            $response['message'] = 'ID do modelo inválido para salvar ordem.';
        } elseif ($ordemIds === null || !is_array($ordemIds)) {
            $response['message'] = 'Dados de ordenação inválidos (formato JSON incorreto).';
            error_log("AJAX salvar_ordem: Erro ao decodificar ordem_ids JSON. Recebido: " . $ordemIdsJson);
        } else {
            $ordemIdsInt = array_map('intval', array_filter($ordemIds, 'is_numeric')); // Garante array de inteiros
            if (count($ordemIdsInt) !== count($ordemIds) && !empty($ordemIds)) { // Verifica se algum ID foi perdido/inválido
                 $response['message'] = 'Dados de ordenação contêm IDs de item não numéricos.';
            } else {
                if (salvarOrdemItensModelo($conexao, $modelo_id, $ordemIdsInt, $secao)) {
                    $response = ['success' => true, 'message' => 'Ordem dos itens salva com sucesso.'];
                    dbRegistrarLogAcesso($admin_id, $_SERVER['REMOTE_ADDR'], 'salvar_ordem_modelo_ajax', 1, "Modelo ID: $modelo_id, Seção: ".($secao ?? 'GERAL').", Itens: ".count($ordemIdsInt), $conexao);
                } else {
                    $response['message'] = 'Erro ao salvar a ordem dos itens no banco de dados.';
                    dbRegistrarLogAcesso($admin_id, $_SERVER['REMOTE_ADDR'], 'salvar_ordem_modelo_ajax_falha', 0, "Modelo ID: $modelo_id, Seção: ".($secao ?? 'GERAL'), $conexao);
                }
            }
        }
        break;

    case 'adicionar_itens_modelo': // Renomeado para plural
        $modelo_id_add = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT);
        // `requisitos_ids` deve ser um array de IDs
        $requisitos_ids_add_raw = $_POST['requisitos_ids'] ?? [];
        $secao_add_raw = $_POST['secao_add'] ?? null;
        $secao_add = ($secao_add_raw === 'Itens Gerais' || $secao_add_raw === '' || $secao_add_raw === null) ? null : trim($secao_add_raw);

        if (!$modelo_id_add || $modelo_id_add <= 0) {
            $response['message'] = 'ID do modelo inválido para adicionar itens.';
        } elseif (!is_array($requisitos_ids_add_raw) || empty($requisitos_ids_add_raw)) {
            $response['message'] = 'Nenhum ID de requisito fornecido para adicionar.';
        } else {
            $requisitos_ids_add = array_map('intval', array_filter($requisitos_ids_add_raw, 'is_numeric'));
            if (empty($requisitos_ids_add)) {
                $response['message'] = 'Nenhum ID de requisito válido fornecido.';
            } else {
                $adicionados_count = 0;
                $erros_add = [];
                $novos_itens_html = []; // Para retornar HTML dos itens adicionados, se desejar

                foreach ($requisitos_ids_add as $req_id) {
                    if ($req_id <= 0) continue; // Pula IDs inválidos que passaram pelo filtro
                    $resultado_add_item = adicionarRequisitoAoModelo($conexao, $modelo_id_add, $req_id, $secao_add);
                    if ($resultado_add_item === true) {
                        $adicionados_count++;
                        // Opcional: buscar o item recém-adicionado para retornar seus dados/HTML
                        // $item_adicionado_info = getItemDoModeloRecemAdicionado($conexao, $modelo_id_add, $req_id, $secao_add);
                        // if ($item_adicionado_info) {
                        //     $novos_itens_html[] = construirHtmlItemModelo($item_adicionado_info); // Função auxiliar
                        // }
                    } elseif (is_string($resultado_add_item)) {
                        $erros_add[$req_id] = $resultado_add_item;
                    } else {
                        $erros_add[$req_id] = "Erro desconhecido ao adicionar requisito ID $req_id.";
                    }
                }

                if ($adicionados_count > 0) {
                    $response['success'] = true;
                    $response['message'] = "$adicionados_count requisito(s) adicionado(s) com sucesso.";
                    if (!empty($novos_itens_html)) $response['novos_itens_html'] = $novos_itens_html; // Para atualizar a UI
                    dbRegistrarLogAcesso($admin_id, $_SERVER['REMOTE_ADDR'], 'add_itens_modelo_ajax', 1, "Modelo ID: $modelo_id_add, Adicionados: $adicionados_count, Seção: " .($secao_add ?? 'GERAL'), $conexao);
                }

                if (!empty($erros_add)) {
                    $response['message'] = ($response['success'] ? $response['message'] . " " : "") . "Alguns itens falharam: ";
                    $erros_msg_arr = [];
                    foreach ($erros_add as $rid => $msg) { $erros_msg_arr[] = "Req. ID $rid: $msg"; }
                    $response['message'] .= implode('; ', $erros_msg_arr);
                    if (!$response['success']) $response['success'] = false; // Garante que success é false se houve apenas erros
                    dbRegistrarLogAcesso($admin_id, $_SERVER['REMOTE_ADDR'], 'add_itens_modelo_ajax_falha', 0, "Modelo ID: $modelo_id_add, Falhas: ".count($erros_add).", Detalhes: ".implode('; ',$erros_msg_arr), $conexao);
                }
            }
        }
        break;

    case 'remover_item_modelo':
        // Pode ser POST ou GET para remoção, mas GET é mais simples para um botão/link
        $modelo_item_id_rem = filter_input(INPUT_POST, 'modelo_item_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'modelo_item_id', FILTER_VALIDATE_INT);

        if ($modelo_item_id_rem && $modelo_item_id_rem > 0) {
            if (removerRequisitoDoModelo($conexao, $modelo_item_id_rem)) {
                $response = ['success' => true, 'message' => 'Item removido do modelo com sucesso.'];
                // Para o log, seria bom ter o modelo_id, mas removerRequisitoDoModelo não o retorna facilmente.
                // Podemos inferir o modelo_id se necessário para o log, ou logar apenas o item_id.
                dbRegistrarLogAcesso($admin_id, $_SERVER['REMOTE_ADDR'], 'rem_item_modelo_ajax', 1, "ModeloItem ID: $modelo_item_id_rem", $conexao);
            } else {
                $response['message'] = 'Erro ao remover o item do modelo no banco de dados.';
                dbRegistrarLogAcesso($admin_id, $_SERVER['REMOTE_ADDR'], 'rem_item_modelo_ajax_falha', 0, "ModeloItem ID: $modelo_item_id_rem", $conexao);
            }
        } else {
            $response['message'] = 'ID do item do modelo inválido para remoção.';
        }
        break;

    default:
        $response['message'] = 'Ação AJAX desconhecida ou não especificada: ' . htmlspecialchars($action ?? 'N/A');
        dbRegistrarLogAcesso($admin_id, $_SERVER['REMOTE_ADDR'], 'ajax_modelo_acao_desconhecida', 0, "Ação: " . htmlspecialchars($action ?? 'N/A'), $conexao);
        break;
}

// Regenerar CSRF token e incluir na resposta
$_SESSION['csrf_token'] = gerar_csrf_token();
$response['novo_csrf_token'] = $_SESSION['csrf_token'];

echo json_encode($response);
exit;
?>