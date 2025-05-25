<?php
// admin/requisitos/exportar_requisitos.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    http_response_code(403);
    // Não usar echo aqui, pois corrompe o CSV se o header já foi enviado
    // Pode-se logar ou simplesmente sair.
    exit;
}

// --- Obter Filtros (se aplicável para a exportação) ---
// Se você quiser que a exportação respeite os filtros da página requisitos_index.php,
// você pegaria os parâmetros GET aqui, da mesma forma que em requisitos_index.php
$filtro_status_export = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_REGEXP, ['options' => ['default' => 'todos', 'regexp' => '/^(todos|ativos|inativos)$/']]);
$termo_busca_export = trim(filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_categoria_export = trim(filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filtro_norma_export = trim(filter_input(INPUT_GET, 'norma', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
// Adicionar aqui qualquer outro filtro que sua função getAllRequisitosAuditoria e sua UI suportem.
// Ex: filtro por plano de assinatura, se implementado:
// $filtro_plano_id_export = filter_input(INPUT_GET, 'plano_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

$filtros_aplicados_log = http_build_query(array_filter(compact(
    'filtro_status_export', 'termo_busca_export', 'filtro_categoria_export', 'filtro_norma_export'
    // Adicionar outras variáveis de filtro aqui se usadas
)));

dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_requisitos_iniciada', 1, 'Filtros: ' . ($filtros_aplicados_log ?: 'Nenhum'), $conexao);

// --- Obter Requisitos ---
$requisitos = getAllRequisitosAuditoria(
    $conexao,
    $filtro_status_export,
    $termo_busca_export,
    $filtro_categoria_export,
    $filtro_norma_export
    // Passar outros filtros para a função se ela os aceitar:
    // ['disponibilidade_plano_id' => $filtro_plano_id_export]
);

// --- Configurar Headers para Download CSV ---
$nomeArquivo = 'export_requisitos_globais_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

// --- Cabeçalho do CSV (com os novos campos) ---
fputcsv($output, [
    'ID',
    'Codigo',
    'Nome',
    'Descricao',
    'Categoria',
    'Norma_Referencia',
    'Versao_Norma_Aplicavel',
    'Data_Ultima_Revisao_Norma',
    'Guia_Evidencia',
    'Objetivo_Controle',
    'Tecnicas_Sugeridas',
    'Peso',
    'Disponibilidade_Planos_IDs', // Será uma string JSON ou formatada
    'Ativo', // Sim/Nao
    'Data_Criacao',
    // 'Global_Ou_Empresa_ID' // Provavelmente sempre NULL para esta exportação
], ';');

// --- Dados do CSV ---
if (!empty($requisitos)) {
    // Para exibir nomes dos planos em vez de IDs
    $planos_map_export = [];
    if (function_exists('listarPlanosAssinatura')) {
        $todos_planos = listarPlanosAssinatura($conexao, false); // Pegar todos
        foreach ($todos_planos as $pl_exp) {
            $planos_map_export[$pl_exp['id']] = $pl_exp['nome_plano'];
        }
    }

    foreach ($requisitos as $req) {
        $planos_nomes_str = 'Todos'; // Default
        if (!empty($req['disponibilidade_plano_ids_json'])) {
            $plano_ids_array = json_decode($req['disponibilidade_plano_ids_json'], true);
            if (is_array($plano_ids_array) && !empty($plano_ids_array)) {
                $nomes_temp = [];
                foreach ($plano_ids_array as $pid_exp) {
                    $nomes_temp[] = $planos_map_export[$pid_exp] ?? "ID:{$pid_exp}";
                }
                $planos_nomes_str = implode('|', $nomes_temp); // Separador para CSV
            } elseif (is_array($plano_ids_array) && empty($plano_ids_array)){
                 $planos_nomes_str = 'Nenhum Específico'; // Se o JSON é um array vazio '[]'
            }
        }


        fputcsv($output, [
            $req['id'],
            $req['codigo'] ?? '',
            $req['nome'] ?? '',
            $req['descricao'] ?? '',
            $req['categoria'] ?? '',
            $req['norma_referencia'] ?? '',
            $req['versao_norma_aplicavel'] ?? '',
            $req['data_ultima_revisao_norma'] ?? '',
            $req['guia_evidencia'] ?? '',
            $req['objetivo_controle'] ?? '',
            $req['tecnicas_sugeridas'] ?? '',
            $req['peso'] ?? '1', // Default se nulo no DB (mas não deveria ser)
            $planos_nomes_str, // Nomes dos planos separados por pipe
            $req['ativo'] ? 'Sim' : 'Nao',
            $req['data_criacao'] ?? '',
            // $req['global_ou_empresa_id'] ?? '', // Provavelmente sempre vazio/NULL aqui
        ], ';');
    }
}

fclose($output);

dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_requisitos_concluida', 1, 'Exportação CSV concluída. ' . count($requisitos) . ' requisitos exportados. Filtros: ' . ($filtros_aplicados_log ?: 'Nenhum'), $conexao);
exit;
?>