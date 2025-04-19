<?php
// admin/requisitos/exportar_requisitos.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php'; // Para getAllRequisitosAuditoria

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    // Poderia redirecionar ou apenas dar exit com erro 403
    http_response_code(403);
    echo "Acesso Negado.";
    exit;
}

// Log da ação de exportação
dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_requisitos', 1, 'Iniciada exportação de requisitos para CSV.', $conexao);

// --- Obter TODOS os requisitos (sem paginação) ---
// Poderia adicionar filtros GET aqui se quisesse exportar um subconjunto
$requisitos = getAllRequisitosAuditoria($conexao);

// --- Configurar Headers para Download CSV ---
$nomeArquivo = 'export_requisitos_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// --- Abrir stream de saída ---
$output = fopen('php://output', 'w');

// --- Adicionar BOM para compatibilidade UTF-8 com Excel ---
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// --- Escrever Cabeçalho ---
fputcsv($output, [
    'ID',
    'Codigo',
    'Nome',
    'Descricao',
    'Categoria',
    'Norma_Referencia',
    'Ativo', // Será 'Sim' ou 'Nao'
    'Data_Criacao'
], ';'); // Usando ponto e vírgula como delimitador

// --- Escrever Dados ---
if (!empty($requisitos)) {
    foreach ($requisitos as $req) {
        fputcsv($output, [
            $req['id'],
            $req['codigo'] ?? '',
            $req['nome'] ?? '',
            $req['descricao'] ?? '',
            $req['categoria'] ?? '',
            $req['norma_referencia'] ?? '',
            $req['ativo'] ? 'Sim' : 'Nao', // Formata o status
            $req['data_criacao'] ?? ''
        ], ';');
    }
}

// --- Fechar stream ---
fclose($output);

// Log de conclusão
dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'exportar_requisitos', 1, 'Exportação CSV concluída. ' . count($requisitos) . ' requisitos exportados.', $conexao);

exit; // Garante que nada mais seja enviado

?>