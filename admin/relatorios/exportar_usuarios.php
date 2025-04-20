<?php
// admin/relatorios/exportar_usuarios.php - COM FILTROS

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { http_response_code(403); exit("Acesso Negado."); }

// --- Obter Filtros da URL ---
$filtro_status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_REGEXP, ['options' => ['default'=>'todos', 'regexp'=>'/^(todos|ativos|inativos)$/']]);
$filtro_perfil = filter_input(INPUT_GET, 'perfil', FILTER_VALIDATE_REGEXP, ['options' => ['default'=>'', 'regexp'=>'/^(admin|auditor|gestor)?$/']]);

// --- Buscar Dados USANDO a função com filtros ---
$usuarios = getAllUsuariosComFiltro($conexao, $filtro_status, $filtro_perfil);

$filtros_usados = http_build_query(array_filter(compact('filtro_status', 'filtro_perfil')));
dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_usuarios', 1, "Exportando ".count($usuarios)." usuários. Filtros: $filtros_usados", $conexao);

// --- Configurar Headers ---
$nomeArquivo = 'relatorio_usuarios_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Pragma: no-cache'); header('Expires: 0');

// --- Gerar CSV ---
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho (Adicionado Nome Empresa)
fputcsv($output, ['ID', 'Nome', 'Email', 'Perfil', 'Status', 'Data_Cadastro', 'Empresa_ID', 'Nome_Empresa'], ';');

// Dados
if (!empty($usuarios)) {
    foreach ($usuarios as $u) {
        fputcsv($output, [
            $u['id'],
            $u['nome'] ?? '',
            $u['email'] ?? '',
            $u['perfil'] ?? '',
            isset($u['ativo']) ? ($u['ativo'] ? 'Ativo' : 'Inativo') : '',
            $u['data_cadastro'] ?? '',
            $u['empresa_id'] ?? '',
            $u['nome_empresa'] ?? '' // Nome da empresa vindo do JOIN na função
        ], ';');
    }
}
fclose($output);
exit;
?>