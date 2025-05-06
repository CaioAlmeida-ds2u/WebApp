<?php
// gestor/auditoria/ajax_handler_auditoria.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/gestor_functions.php'; // Onde getMembrosDaEquipe e getSecoesDoModelo estarão

header('Content-Type: application/json');

// Proteção da página e CSRF
protegerPagina($conexao, true); // true para AJAX, não redireciona, só para a execução
if ($_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Erro de validação da sessão (CSRF).']);
    exit;
}

$action = $_POST['action'] ?? null;
$response = ['success' => false, 'message' => 'Ação inválida.'];
$empresa_id = $_SESSION['usuario_empresa_id']; // Para filtrar membros da equipe pela empresa do gestor

// Regenerar CSRF token para a próxima requisição AJAX ou submissão de formulário
$response['novo_csrf'] = gerar_csrf_token(); // Inclui novo token na resposta
$_SESSION['csrf_token'] = $response['novo_csrf'];


switch ($action) {
    case 'get_secoes_e_membros':
        $modelo_id = filter_input(INPUT_POST, 'modelo_id', FILTER_VALIDATE_INT);
        $equipe_id = filter_input(INPUT_POST, 'equipe_id', FILTER_VALIDATE_INT);

        if (!$modelo_id || !$equipe_id) {
            $response['message'] = 'ID do Modelo ou da Equipe inválido.';
            echo json_encode($response);
            exit;
        }

        // Função para buscar seções distintas de um modelo
        // Supondo que ela exista em gestor_functions.php
        $secoes = getSecoesDoModelo($conexao, $modelo_id);
        
        // Função para buscar membros (auditores) de uma equipe
        // Supondo que ela exista em gestor_functions.php e filtre por perfil 'auditor' e ativo
        $membros_equipe = getMembrosDaEquipeComPerfilAuditor($conexao, $equipe_id, $empresa_id);

        if ($secoes === false || $membros_equipe === false) {
            $response['message'] = 'Erro ao buscar dados do modelo ou da equipe.';
        } else {
            $response['success'] = true;
            $response['secoes'] = $secoes; // Array de strings
            $response['membros_equipe'] = $membros_equipe; // Array de objetos/arrays com id e nome
            $response['message'] = 'Dados carregados.';
        }
        break;
    
    default:
        $response['message'] = 'Ação AJAX desconhecida para auditoria.';
        break;
}

echo json_encode($response);
exit;