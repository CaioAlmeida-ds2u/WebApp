<?php
// admin/relatorios/exportar_empresas.php - COM FILTROS

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php'; // Ou db.php
require_once __DIR__ . '/../../includes/db.php'; // Para dbRegistrarLogAcesso

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { http_response_code(403); exit("Acesso Negado."); }

// --- Obter Filtros ---
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_REGEXP, ['options' => ['default'=>'todos', 'regexp'=>'/^(todos|ativos|inativos)$/']]);

// --- Buscar Dados USANDO a função com filtros ---
$empresas = getAllEmpresasComFiltro($conexao, $filtro_status); // Chama a nova função

$filtros_usados = http_build_query(array_filter(compact('filtro_status')));
dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_empresas', 1, "Exportando ".count($empresas)." empresas. Filtros: $filtros_usados", $conexao);

// --- Configurar Headers ---
$nomeArquivo = 'relatorio_empresas_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Pragma: no-cache'); header('Expires: 0');

// --- Gerar CSV ---
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho
fputcsv($output, ['ID', 'Nome_Fantasia', 'CNPJ', 'Razao_Social', 'Endereco', 'Contato', 'Telefone', 'Email', 'Status'], ';');

// Dados
if (!empty($empresas)) {
    foreach ($empresas as $emp) {
        fputcsv($output, [
            $emp['id'], $emp['nome'] ?? '', $emp['cnpj'] ?? '', $emp['razao_social'] ?? '',
            $emp['endereco'] ?? '', $emp['contato'] ?? '', $emp['telefone'] ?? '', $emp['email'] ?? '',
            isset($emp['ativo']) ? ($emp['ativo'] ? 'Ativa' : 'Inativa') : ''
        ], ';');
    }
}
fclose($output);
exit;
?>