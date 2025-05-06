<?php
// includes/download_handler.php

// Este script precisa ter acesso a $conexao e às funções de sessão e UPLOADS_BASE_PATH_ABSOLUTE
// É uma boa prática incluir o config.php no topo
require_once __DIR__ . '/config.php'; // Caminho relativo de volta para config.php
require_once __DIR__ . '/funcoes_download.php'; // Onde processarDownloadSeguro está

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Proteção básica - Usuário deve estar logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_empresa_id'])) {
    http_response_code(403);
    echo "Acesso negado (não autenticado).";
    exit;
}

$usuario_id_logado = (int)$_SESSION['usuario_id'];
$empresa_id_logado = (int)$_SESSION['usuario_empresa_id'];

// Validação CSRF para downloads GET
$csrf_token_get = $_GET['csrf_token'] ?? '';
if (!validar_csrf_token($csrf_token_get)) {
    http_response_code(403);
    echo "Erro de segurança (CSRF).";
    // Log da tentativa de download com CSRF inválido
     if (function_exists('dbRegistrarLogAcesso') && isset($conexao)) {dbRegistrarLogAcesso($usuario_id_logado, $_SERVER['REMOTE_ADDR'], 'download_csrf_fail', 0, "Tipo: ".($_GET['tipo']??'N/A').", ID: ".($_GET['id']??'N/A'), $conexao);}
    exit;
}


$tipo_download = $_GET['tipo'] ?? null;
$documento_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$tipo_download || !$documento_id) {
    http_response_code(400);
    echo "Parâmetros inválidos para download.";
    exit;
}

// Chamar a função centralizada para processar o download
// A função processarDownloadSeguro fará as verificações de permissão e servirá o arquivo
processarDownloadSeguro($conexao, $tipo_download, $documento_id, $empresa_id_logado, $usuario_id_logado);

// A função processarDownloadSeguro chama exit() no final.
?>