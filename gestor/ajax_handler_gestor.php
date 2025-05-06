<?php
// gestor/ajax_handler_gestor.php

// Ajustar require para a estrutura correta (subir um nível para pasta includes)
require_once __DIR__ . '/../includes/config.php'; // Contém $conexao, funções de sessão/csrf, etc.
require_once __DIR__ . '/../includes/gestor_functions.php'; // Onde estão getEquipesDaEmpresa, adicionar/removerMembroEquipe
// require_once __DIR__ . '/../includes/db.php'; // Se dbRegistrarLogAcesso não estiver em config.php ou gestor_functions.php

// Sempre retornar JSON
header('Content-Type: application/json');

// Proteção Básica e Verificação de Sessão
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'gestor' || !isset($_SESSION['usuario_empresa_id'])) {
    error_log("ajax_handler_gestor: Acesso não autorizado. Sessão: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado ou sessão inválida.']);
    exit;
}
$empresa_id_gestor = (int)$_SESSION['usuario_empresa_id'];
$gestor_id = (int)$_SESSION['usuario_id'];


// Validação CSRF AJAX
$csrf_token_recebido = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null; // Checa POST e GET por flexibilidade

if (!validar_csrf_token($csrf_token_recebido)) { // Usando a função global validar_csrf_token
     error_log("ajax_handler_gestor: Falha na validação CSRF. Token recebido: '" . ($csrf_token_recebido ?? 'NULO') . "', Token Sessão: '" . ($_SESSION['csrf_token'] ?? 'NULO') . "'");
     if (function_exists('dbRegistrarLogAcesso') && isset($conexao)) {
        dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'gestor_ajax_csrf_fail', 0, 'Token inválido AJAX. Recebido: '.($csrf_token_recebido ?? 'N/A'), $conexao);
     }
     echo json_encode(['success' => false, 'message' => 'Erro de segurança (CSRF). Recarregue a página e tente novamente.']);
     exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null; // Suportar action via GET também se necessário no futuro
$response = ['success' => false, 'message' => 'Ação inválida ou não especificada.']; // Resposta Padrão


// Regenerar token CSRF e incluir na resposta para sincronizar o cliente
// FAZER ISSO DEPOIS da validação e ANTES de enviar a resposta
$novo_csrf_token_gerado = gerar_csrf_token(); // Função global para gerar
$_SESSION['csrf_token'] = $novo_csrf_token_gerado; // Atualiza na sessão
$response['novo_csrf'] = $novo_csrf_token_gerado; // Envia para o cliente


// Processar a Ação Solicitada
switch ($action) {

    case 'get_equipes_para_auditor':
        $auditor_id = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT);

        if (!$auditor_id) {
            $response['message'] = 'ID do Auditor inválido.';
            error_log("ajax_handler_gestor (get_equipes): ID Auditor inválido/ausente.");
            break;
        }

        // Validar se o auditor pertence à empresa do gestor
        $stmtCheckAuditor = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE id = :auditor_id AND empresa_id = :empresa_id AND perfil = 'auditor'");
        $stmtCheckAuditor->execute([':auditor_id' => $auditor_id, ':empresa_id' => $empresa_id_gestor]);
        if ($stmtCheckAuditor->fetchColumn() == 0) {
             $response['message'] = 'Auditor não encontrado ou não pertence à sua empresa.';
             error_log("ajax_handler_gestor (get_equipes): Auditor ID $auditor_id não da empresa $empresa_id_gestor.");
             break;
        }

        try {
            // Buscar todas as equipes ATIVAS da empresa do GESTOR
            $todas_equipes_ativas = getEquipesDaEmpresa($conexao, $empresa_id_gestor); // Assumindo que esta função retorna apenas equipes ATIVAS

            // Buscar IDs das equipes (da empresa) às quais este auditor pertence
            $sqlEquipesDoAuditor = "SELECT em.equipe_id
                                    FROM equipe_membros em
                                    JOIN equipes eq ON em.equipe_id = eq.id
                                    WHERE em.usuario_id = :auditor_id AND eq.empresa_id = :empresa_id_gestor";
            $stmtEquipesDoAuditor = $conexao->prepare($sqlEquipesDoAuditor);
            $stmtEquipesDoAuditor->execute([':auditor_id' => $auditor_id, ':empresa_id_gestor' => $empresa_id_gestor]);
            $ids_equipes_auditor = $stmtEquipesDoAuditor->fetchAll(PDO::FETCH_COLUMN, 0);
            $ids_equipes_auditor_str = array_map('strval', $ids_equipes_auditor); // Garante strings para JS

            $response['success'] = true;
            $response['message'] = 'Dados das equipes carregados.';
            $response['equipes_empresa'] = $todas_equipes_ativas; // Array de objetos/arrays [{id, nome}, ...]
            $response['equipes_auditor'] = $ids_equipes_auditor_str; // Array de strings ['id1', 'id2', ...]

        } catch (PDOException $e) {
            error_log("Erro DB AJAX (get_equipes_para_auditor) Auditor $auditor_id: " . $e->getMessage());
            $response['message'] = 'Erro ao consultar o banco de dados (equipes).';
        }
        break;

     case 'salvar_associacoes_equipe_auditor':
        $auditor_id_save = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT);
        $equipes_selecionadas_json_save = $_POST['equipes_ids'] ?? '[]';
        $equipes_selecionadas_ids_save = json_decode($equipes_selecionadas_json_save, true);

        if (!$auditor_id_save || !is_array($equipes_selecionadas_ids_save)) {
             $response['message'] = 'Dados de entrada inválidos (Auditor ou Lista de Equipes).';
             error_log("ajax_handler_gestor (salvar_associacoes): Dados de entrada inválidos. Auditor: '$auditor_id_save', Equipes JSON: '$equipes_selecionadas_json_save'");
             break;
        }

        $stmtCheckAuditorSave = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE id = :id AND empresa_id = :empresa_id AND perfil = 'auditor'");
        $stmtCheckAuditorSave->execute([':id' => $auditor_id_save, ':empresa_id' => $empresa_id_gestor]);
        if ($stmtCheckAuditorSave->fetchColumn() == 0) {
             $response['message'] = 'Auditor especificado inválido ou não pertence à sua empresa.';
             error_log("ajax_handler_gestor (salvar_associacoes): Auditor ID $auditor_id_save não pertence à empresa $empresa_id_gestor.");
             break;
        }

        $equipes_ids_validados_save = array_filter(array_map('intval', $equipes_selecionadas_ids_save), fn($id) => $id > 0);

        $conexao->beginTransaction();
        try {
             $stmtAtuaisSave = $conexao->prepare("SELECT em.equipe_id FROM equipe_membros em JOIN equipes eq ON em.equipe_id = eq.id WHERE em.usuario_id = :auditor_id AND eq.empresa_id = :empresa_id_gestor");
             $stmtAtuaisSave->execute([':auditor_id' => $auditor_id_save, ':empresa_id_gestor' => $empresa_id_gestor]);
             $equipes_atuais_ids_db = array_map('intval', $stmtAtuaisSave->fetchAll(PDO::FETCH_COLUMN, 0));

             $para_adicionar_save = array_diff($equipes_ids_validados_save, $equipes_atuais_ids_db);
             $para_remover_save = array_diff($equipes_atuais_ids_db, $equipes_ids_validados_save);
             $erros_operacao_save = [];

             foreach ($para_adicionar_save as $eq_id_add) {
                  if (!adicionarMembroEquipe($conexao, $eq_id_add, $auditor_id_save, $empresa_id_gestor)) { $erros_operacao_save[] = "Erro ao adicionar à equipe ID $eq_id_add."; }
             }
             foreach ($para_remover_save as $eq_id_rem) {
                  if (!removerMembroEquipe($conexao, $eq_id_rem, $auditor_id_save)) { $erros_operacao_save[] = "Erro ao remover da equipe ID $eq_id_rem."; }
             }

             if (empty($erros_operacao_save)) {
                  $conexao->commit();
                  $response['success'] = true; $response['message'] = 'Associações de equipes do auditor foram atualizadas.';
                  if (function_exists('dbRegistrarLogAcesso')) {dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'gestor_assoc_equipes_aud_ok', 1, "Auditor: $auditor_id_save. Add:".implode(',',$para_adicionar_save).". Rem:".implode(',',$para_remover_save), $conexao);}
             } else {
                  $conexao->rollBack(); $response['message'] = 'Ocorreram erros: ' . implode(' ', $erros_operacao_save);
                   if (function_exists('dbRegistrarLogAcesso')) {dbRegistrarLogAcesso($gestor_id, $_SERVER['REMOTE_ADDR'], 'gestor_assoc_equipes_aud_fail', 0, "Auditor: $auditor_id_save. Erros: ".implode(';',$erros_operacao_save), $conexao);}
             }
         } catch (Exception $e) {
              if($conexao->inTransaction()) $conexao->rollBack();
              error_log("Erro (salvar_associacoes) Auditor $auditor_id_save: " . $e->getMessage());
              $response['message'] = 'Erro interno do servidor ao processar a solicitação.';
         }
        break;

    case 'get_auditorias_do_auditor':
        $auditor_id_disp = filter_input(INPUT_POST, 'auditor_id', FILTER_VALIDATE_INT);
        if (!$auditor_id_disp) { $response['message'] = 'ID do Auditor inválido.'; break; }

        $stmtCheckAuditorDisp = $conexao->prepare("SELECT COUNT(*) FROM usuarios WHERE id = :id AND empresa_id = :empresa_id AND perfil = 'auditor'");
        $stmtCheckAuditorDisp->execute([':id' => $auditor_id_disp, ':empresa_id' => $empresa_id_gestor]);
        if ($stmtCheckAuditorDisp->fetchColumn() == 0) { $response['message'] = 'Auditor não encontrado para sua empresa.'; break; }

        try {
            $auditorias_list = [];
            $status_relevantes_arr = ['Planejada', 'Em Andamento', 'Pausada', 'Concluída (Auditor)', 'Em Revisão'];
            $status_relevantes_sql_in = implode(',', array_map(fn($s) => $conexao->quote($s), $status_relevantes_arr));

            // Auditorias individuais
            $sqlIndividual = "SELECT id, titulo, status, data_inicio_planejada, data_fim_planejada
                              FROM auditorias
                              WHERE auditor_responsavel_id = :auditor_id AND empresa_id = :empresa_id AND status IN ($status_relevantes_sql_in)
                              ORDER BY data_inicio_planejada ASC, id DESC";
            $stmtInd = $conexao->prepare($sqlIndividual);
            $stmtInd->execute([':auditor_id' => $auditor_id_disp, ':empresa_id' => $empresa_id_gestor]);
            while ($row = $stmtInd->fetch(PDO::FETCH_ASSOC)) {
                $row['tipo_atribuicao'] = 'individual'; $row['secao_modelo_nome'] = null;
                $auditorias_list[$row['id']] = $row;
            }

            // Auditorias de equipe (seções)
            $sqlEquipe = "SELECT DISTINCT a.id, a.titulo, a.status, a.data_inicio_planejada, a.data_fim_planejada, GROUP_CONCAT(DISTINCT asr.secao_modelo_nome ORDER BY asr.secao_modelo_nome SEPARATOR ', ') as secoes_atribuidas
                          FROM auditorias a
                          JOIN auditoria_secao_responsaveis asr ON a.id = asr.auditoria_id
                          WHERE asr.auditor_designado_id = :auditor_id AND a.empresa_id = :empresa_id AND a.status IN ($status_relevantes_sql_in)
                          GROUP BY a.id, a.titulo, a.status, a.data_inicio_planejada, a.data_fim_planejada
                          ORDER BY a.data_inicio_planejada ASC, a.id DESC";
            $stmtEq = $conexao->prepare($sqlEquipe);
            $stmtEq->execute([':auditor_id' => $auditor_id_disp, ':empresa_id' => $empresa_id_gestor]);
            while ($row = $stmtEq->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($auditorias_list[$row['id']])) { // Só adiciona se não for uma auditoria individual já listada
                    $row['tipo_atribuicao'] = 'equipe';
                    $row['secao_modelo_nome'] = $row['secoes_atribuidas']; // Nome da coluna do GROUP_CONCAT
                    unset($row['secoes_atribuidas']); // Limpa coluna extra
                    $auditorias_list[$row['id']] = $row;
                } else {
                    // Auditoria já listada como individual, podemos adicionar as seções se desejado,
                    // ou simplesmente manter a indicação "individual" como principal.
                    // Para simplificar, se já é individual, não faremos nada aqui.
                    // Poderia-se adicionar uma lógica para indicar que ele TAMBÉM está em seções,
                    // mas isso pode poluir a exibição simples de "papel".
                    if ($auditorias_list[$row['id']]['tipo_atribuicao'] === 'equipe' && !empty($row['secoes_atribuidas'])) {
                        // Se já era de equipe e encontramos mais seções (improvável com GROUP_CONCAT, mas seguro)
                         if (strpos($auditorias_list[$row['id']]['secao_modelo_nome'], $row['secoes_atribuidas']) === false) {
                             $auditorias_list[$row['id']]['secao_modelo_nome'] .= ', ' . $row['secoes_atribuidas'];
                         }
                    }
                }
            }
            $auditorias_final = array_values($auditorias_list);
            usort($auditorias_final, fn($a, $b) => ($a['data_inicio_planejada'] ?? '9999') <=> ($b['data_inicio_planejada'] ?? '9999') ?: $a['id'] <=> $b['id']);

            $response['success'] = true;
            $response['auditorias'] = $auditorias_final;
        } catch (PDOException $e) {
            error_log("Erro DB (get_auditorias_do_auditor) Auditor $auditor_id_disp: " . $e->getMessage());
            $response['message'] = 'Erro ao buscar auditorias do auditor.';
        }
        break;

    default:
        $response['message'] = 'Ação AJAX [' . htmlspecialchars($action ?? 'N/A') . '] desconhecida.';
        error_log("ajax_handler_gestor: Ação desconhecida: " . ($action ?? 'N/A'));
        break;
}

echo json_encode($response);
exit;