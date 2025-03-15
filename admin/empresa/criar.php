<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/db.php';

protegerPagina();

$erro = '';
$sucesso = '';
$nome = $_POST['nome'] ?? '';
$cnpj = $_POST['cnpj'] ?? '';
$razao_social = $_POST['razao_social'] ?? '';
$contato = $_POST['contato'] ?? '';
$telefone = $_POST['telefone'] ?? '';
$email = $_POST['email'] ?? '';

$errors = [];

if (empty($nome)) {
    $errors[] = "O campo Nome é obrigatório.";
}

if (!empty($cnpj) && !validarCNPJ($cnpj)) {
    $errors[] = "CNPJ inválido.";
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Formato de e-mail inválido.";
}

if (empty($errors)) {
    $dados = [
        'nome' => $nome,
        'cnpj' => $cnpj,
        'razao_social' => $razao_social,
        'contato' => $contato,
        'telefone' => $telefone,
        'email' => $email
    ];

    // Função para criar a empresa no banco de dados
    $resultado = criarEmpresa($conexao, $dados);

    if ($resultado === true) {
        $_SESSION['sucesso'] = "Empresa criada com sucesso!";
    } else {
        $_SESSION['erro'] = "Erro ao criar a empresa: " . $resultado;
    }
} else {
    $_SESSION['erro'] = implode("<br>", $errors);
}

// Redirecionar de volta para a aba "Registrar Empresa" da página de empresas
header('Location: empresa_index.php?aba=registrar-empresa');
exit;
