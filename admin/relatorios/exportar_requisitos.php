<?php
// admin/relatorios/exportar_requisitos.php - COM FILTROS

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php'; // Para getAllRequisitosAuditoria

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { http_response_code(403); exit("Acesso Negado."); }

// --- Obter Filtros ---
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_REGEXP, ['options' => ['default'=>'todos', 'regexp'=>'/^(todos|ativos|inativos)$/']]);
// Adicionar outros filtros se a função getAllRequisitosAuditoria suportar (ex: categoria, norma, busca)
// $termo_busca = filter_input(INPUT_GET, 'busca', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
// $filtro_categoria = filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
// $filtro_norma = filter_input(INPUT_GET, 'norma', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// --- Buscar Dados USANDO a função com filtros ---
$requisitos = getAllRequisitosAuditoria(
    $conexao,
    $filtro_status
    //,$termo_busca, $filtro_categoria, $filtro_norma // Passar outros filtros aqui
);

$filtros_usados = http_build_query(array_filter(compact('filtro_status' /*, 'termo_busca', ...*/)));
dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_requisitos', 1, "Exportando ".count($requisitos)." requisitos. Filtros: $filtros_usados", $conexao);

// --- Configurar Headers ---
$nomeArquivo = 'relatorio_requisitos_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Pragma: no-cache'); header('Expires: 0');

// --- Gerar CSV ---
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho
fputcsv($output, ['ID', 'Codigo', 'Nome', 'Descricao', 'Categoria', 'Norma_Referencia', 'Ativo', 'Data_Criacao'], ';');

// Dados
if (!empty($requisitos)) {
    foreach ($requisitos as $req) {
        fputcsv($output, [
            $req['id'], $req['codigo'] ?? '', $req['nome'] ?? '', $req['descricao'] ?? '',
            $req['categoria'] ?? '', $req['norma_referencia'] ?? '',
            isset($req['ativo']) ? ($req['ativo'] ? 'Sim' : 'Nao') : '',
            $req['data_criacao'] ?? ''
        ], ';');
    }
}
fclose($output);
exit;
?>