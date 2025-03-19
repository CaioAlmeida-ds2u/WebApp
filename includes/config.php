<?php
require_once __DIR__ . '/funcoes_upload.php'; 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_functions.php'; //Ficar depois do db.php
// includes/config.php

// Inicia a sessão (se ainda não foi iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Configurações do Banco de Dados ---
// Em produção, use VARIÁVEIS DE AMBIENTE do servidor!
define('DB_HOST', 'localhost');
define('DB_NAME', 'acoditools'); // Corrigido: usar o nome correto do banco
define('DB_USER', 'root');      // Use um usuário com as permissões adequadas, *NUNCA* 'root' em produção!
define('DB_PASS', '');          // Em produção, NUNCA deixe a senha em branco e use uma senha FORTE.
define('BASE_URL', '/WebApp/');
// --- Conexão com o Banco de Dados (PDO) ---
try {
    $conexao = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexao->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Boa prática para segurança

} catch (PDOException $e) {
    // Em desenvolvimento, exibe o erro completo.  Em produção, loga e exibe erro genérico.
    if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'development') { // Idealmente, use variável de ambiente
        die("Erro de conexão: " . $e->getMessage());
    } else {
        error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
        die("Ocorreu um erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde."); // Mensagem genérica
    }
}

// --- Funções de Autenticação ---

function protegerPagina() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /../index.php?erro=acesso_negado'); // Redireciona com mensagem
        exit;
    }
}

function redirecionarUsuarioLogado() {
    if (isset($_SESSION['usuario_id'])) {
        if ($_SESSION['perfil'] === 'admin') {
            header('Location: ./admin/dashboard_admin.php');
        } else {
            header('Location: /../auditor/dashboard_auditor.php'); // Ou outra página padrão
        }
        exit;
    }
}
//Inclui o arquivo db.php para as funções de banco
require_once __DIR__ . '/db.php';

// --- (Outras funções utilitárias podem ser adicionadas aqui) ---

function usuarioEstaLogado() {
    return isset($_SESSION['usuario_id']);
}

function redirecionarParaLogin($conexao) {
    dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'acesso_negado', 0, 'Tentativa de acesso à página restrita sem login.', $conexao);
  header('Location: ' . BASE_URL . 'index.php?erro=acesso_negado'); // Usar BASE_URL
  exit;
}