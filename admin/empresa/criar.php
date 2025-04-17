<?php
// admin/empresas/criar.php

// Ajuste o path conforme a localização deste arquivo
require_once __DIR__ . '/../../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../../includes/admin_functions.php'; // criarEmpresa, validarCNPJ

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    // Não é admin, redireciona ou mostra acesso negado
    // Se esta página só pode ser acessada via POST de empresa_index,
    // a verificação lá já deveria ter barrado, mas é bom ter aqui também.
    $_SESSION['erro'] = "Acesso negado.";
    header('Location: ' . BASE_URL . 'admin/dashboard_admin.php'); // Redireciona para dashboard
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Se não for POST, redireciona para o formulário
    header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=registrar-empresa');
    exit;
}

// 1. Validar CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['erro'] = "Erro de validação da sessão. Por favor, tente enviar novamente.";
    // Logar falha CSRF
    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_empresa_csrf_falha', 0, 'Token CSRF inválido.', $conexao);
    // Redireciona de volta para o formulário, mas perde os dados digitados.
    // Alternativa: Salvar dados POST na sessão e repopular (mais complexo).
    header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=registrar-empresa');
    exit;
}

// Regenerar token CSRF após validação bem-sucedida
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// 2. Obter e Validar Dados do Formulário
$nome = trim($_POST['nome'] ?? '');
$cnpj = preg_replace('/[^0-9]/', '', trim($_POST['cnpj'] ?? '')); // Remove não-números do CNPJ
$razao_social = trim($_POST['razao_social'] ?? '');
$endereco = trim($_POST['endereco'] ?? ''); // Adicionar campo endereço se não existir no form
$contato = trim($_POST['contato'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email = trim($_POST['email'] ?? '');

$errors = [];

if (empty($nome)) {
    $errors[] = "O campo Nome Fantasia é obrigatório.";
}
if (empty($cnpj)) {
     $errors[] = "O campo CNPJ é obrigatório.";
} elseif (!validarCNPJ($cnpj)) { // Usa a função de admin_functions.php
    $errors[] = "CNPJ inválido.";
}
if (empty($razao_social)) {
    $errors[] = "O campo Razão Social é obrigatório.";
}
if (empty($email)) {
     $errors[] = "O campo E-mail é obrigatório.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Formato de e-mail inválido.";
}
if (empty($contato)) {
    $errors[] = "O campo Contato é obrigatório.";
}
if (empty($telefone)) {
    $errors[] = "O campo Telefone é obrigatório.";
}
// Adicionar validação para o campo endereço se ele existir

// 3. Chamar Função de Criação se não houver erros
if (empty($errors)) {
    $dadosEmpresa = [
        'nome' => $nome,
        'cnpj' => $cnpj,
        'razao_social' => $razao_social,
        'endereco' => $endereco, // Incluir endereço
        'contato' => $contato,
        'telefone' => $telefone,
        'email' => $email
    ];

    // Função para criar a empresa no banco de dados (de admin_functions.php)
    // Ela retorna true ou uma string de erro (ex: duplicidade)
    $resultado = criarEmpresa($conexao, $dadosEmpresa);

    if ($resultado === true) {
        $_SESSION['sucesso'] = "Empresa '".htmlspecialchars($nome)."' criada com sucesso!";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_empresa_sucesso', 1, 'Empresa criada: ' . $nome, $conexao);
        // Redirecionar para a lista de empresas após sucesso
        header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=empresas');
        exit;
    } else {
        // Erro retornado pela função criarEmpresa (ex: CNPJ duplicado)
        $_SESSION['erro_registrar'] = "Erro ao criar a empresa: " . htmlspecialchars($resultado); // Usar chave diferente para erro específico do registro
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_empresa_falha_db', 0, 'Falha DB/Regra: ' . $resultado, $conexao);
         // Salvar dados submetidos na sessão para repopular o formulário
         $_SESSION['form_data_empresa'] = $_POST;
    }
} else {
    // Erros de validação
    $_SESSION['erro_registrar'] = "<strong>Foram encontrados os seguintes erros:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_empresa_falha_valid', 0, 'Erro de validação.', $conexao);
     // Salvar dados submetidos na sessão para repopular
    $_SESSION['form_data_empresa'] = $_POST;
}

// Redirecionar de volta para a aba "Registrar Empresa" em caso de erro
header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=registrar-empresa');
exit;

?>