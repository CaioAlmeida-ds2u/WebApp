<?php
// admin/relatorios/exportar_logs.php - COM FILTROS

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php'; // Ou db.php

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { http_response_code(403); exit("Acesso Negado."); }

// --- Obter Filtros ---
$filtro_data_inicio = filter_input(INPUT_GET, 'data_inicio', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}$/']]);
$filtro_data_fim = filter_input(INPUT_GET, 'data_fim', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}$/']]);
$filtro_usuario_id = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT);
// Permitir status '' (todos), '0' (falha), '1' (sucesso)
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^(|0|1)$/']]);
// Adicionar filtros de ação e busca livre se a função getAllLogsComFiltro os suportar
// $filtro_acao = filter_input(INPUT_GET, 'acao', FILTER_SANITIZE_SPECIAL_CHARS);
// $filtro_busca = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS);

// --- Buscar Dados USANDO a função com filtros ---
$logs = getAllLogsComFiltro(
    $conexao,
    $filtro_data_inicio ?: '',
    $filtro_data_fim ?: '',
    $filtro_usuario_id ?: null, // Passa null se não for válido
    // $filtro_acao ?: '', // Descomentar se implementar
    $filtro_status // Passa '', '0' ou '1'
    // $filtro_busca ?: '' // Descomentar se implementar
);

$filtros_usados = http_build_query(array_filter(compact('filtro_data_inicio', 'filtro_data_fim', 'filtro_usuario_id', 'filtro_status'/*, 'filtro_acao', 'filtro_busca'*/)));
dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_logs', 1, "Exportando ".count($logs)." logs. Filtros: $filtros_usados", $conexao);

// --- Configurar Headers ---
$nomeArquivo = 'relatorio_logs_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Pragma: no-cache'); header('Expires: 0');

// --- Gerar CSV ---
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho
fputcsv($output, ['ID_Log', 'Data_Hora', 'ID_Usuario', 'Nome_Usuario', 'Email_Usuario', 'IP', 'Acao', 'Status', 'Detalhes'], ';');

// Dados
if (!empty($logs)) {
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'], $log['data_hora'] ?? '', $log['usuario_id'] ?? '',
            $log['nome_usuario'] ?? 'N/A', $log['email_usuario'] ?? 'N/A',
            $log['ip_address'] ?? '', $log['acao'] ?? '',
            isset($log['sucesso']) ? ($log['sucesso'] ? 'Sucesso' : 'Falha') : '',
            $log['detalhes'] ?? ''
        ], ';');
    }
}
fclose($output);
exit;
?>