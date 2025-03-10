<?php
// admin/dashboard_admin.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin_dash.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens (Sucesso/Erro) ---
$sucesso = '';
$erro = '';

if (isset($_SESSION['sucesso'])) {
    $sucesso = $_SESSION['sucesso'];
    unset($_SESSION['sucesso']);
}

if (isset($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']);
}

$title = "ACodITools - Dashboard do Administrador";
echo getHeaderAdmin($title); // Usando getHeaderAdmin()
?>

<div class="container mt-4">
    <div class="jumbotron">
        <h1 class="display-5">Bem-vindo à Dashboard do Administrador!</h1>
        <p class="lead">Aqui você pode gerenciar usuários, empresas, requisitos e monitorar solicitações do sistema.</p>
        <hr class="my-3">
        <p>Use o menu lateral para navegar pelas funcionalidades administrativas.</p>
    </div>
</div>

<?php echo getFooterAdmin(); ?>
