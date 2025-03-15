<?php
// admin/usuarios.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/layout_admin_dash.php';
require_once __DIR__ . '/../../includes/db.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

$sucesso = '';
$erro = '';

if (isset($_SESSION['sucesso'])) {
    $sucesso = $_SESSION['sucesso'];
    unset($_SESSION['sucesso']); // Limpa a mensagem da sessão
}

if (isset($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']); // Limpa a mensagem da sessão
}

// --- Paginação ---
$pagina_atual = $_GET['pagina'] ?? 1;
$pagina_atual = is_numeric($pagina_atual) ? (int)$pagina_atual : 1;
$itens_por_pagina = 10;  // Você pode ajustar isso

// --- Obtenção dos Usuários ---
// Chamada da função de empresas.
$empresa_data = dbGetEmpresas($conexao, $pagina_atual, $itens_por_pagina);  // Recebe os dados das empresas e paginação
$empresa = $empresa_data['empresa'];  // Obtém os dados das empresas
$paginacao = $empresa_data['paginacao'];  // Obtém as informações de paginação

$title = "ACodITools - Gestão de Usuários";
echo getHeaderAdmin($title);

// Definir qual aba está ativa (default é 'empresas')
$aba_ativa = $_GET['aba'] ?? 'empresas';
?>

<ul class="nav nav-tabs" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= ($aba_ativa === 'empresas') ? 'active' : '' ?>" id="empresas-tab" data-bs-toggle="tab" href="?aba=empresas" role="tab" aria-controls="empresas" aria-selected="true">Gestão de Empresas</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= ($aba_ativa === 'registrar-empresa') ? 'active' : '' ?>" id="registrar-empresa-tab" data-bs-toggle="tab" href="?aba=registrar-empresa" role="tab" aria-controls="registrar-empresa" aria-selected="false">Registrar Empresa</a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade <?= ($aba_ativa === 'empresas') ? 'show active' : '' ?>" id="empresas" role="tabpanel" aria-labelledby="empresas-tab">
        <h2>Gestão de Empresas</h2>

        <!-- Exibição de Mensagens de Sucesso ou Erro -->
        <?php if ($sucesso): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>CNPJ</th>
                        <th>Razão Social</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresa as $empresa): ?>
                        <tr>
                            <td><?= htmlspecialchars($empresa['id']) ?></td>
                            <td><?= htmlspecialchars($empresa['nome']) ?></td>
                            <td><?= htmlspecialchars($empresa['cnpj']) ?></td>
                            <td><?= htmlspecialchars($empresa['razao_social']) ?></td>
                            <td><?= htmlspecialchars($empresa['email']) ?></td>
                            <td><?= htmlspecialchars($empresa['telefone']) ?></td>
                            <td>
                                <a href="editar_empresa.php?id=<?= $empresa['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal<?= $empresa['id'] ?>" data-empresaid="<?= $empresa['id'] ?>" data-action="excluir_empresa.php">
                                    Excluir
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>            
        <!-- Paginação, apenas na aba "Gestão de Empresas" -->
        <nav aria-label="Paginação">
            <ul class="pagination">
                <?php if ($paginacao['pagina_atual'] > 1): ?>
                    <li class="page-item"><a class="page-link" href="?aba=empresas&pagina=<?= $paginacao['pagina_atual'] - 1 ?>">Anterior</a></li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $paginacao['total_paginas']; $i++): ?>
                    <li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>">
                        <a class="page-link" href="?aba=empresas&pagina=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?>
                    <li class="page-item"><a class="page-link" href="?aba=empresas&pagina=<?= $paginacao['pagina_atual'] + 1 ?>">Próxima</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php $mensagem = isset($_GET['mensagem']) ? $_GET['mensagem'] : ''; ?> 
    <!-- Aba Registrar Empresa -->
    <div class="tab-pane fade <?= ($aba_ativa === 'registrar-empresa') ? 'show active' : '' ?>" id="registrar-empresa" role="tabpanel" aria-labelledby="registrar-empresa-tab">
        <h2>Registrar Nova Empresa</h2>
                    
        <?php if ($mensagem): ?>
            <div class="alert alert-info">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>
        
        <form action="criar.php" method="POST">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>
            <div class="mb-3">
                <label for="cnpj" class="form-label">CNPJ</label>
                <input type="text" class="form-control" id="cnpj" name="cnpj" required>
            </div>
            <div class="mb-3">
                <label for="razao_social" class="form-label">Razão Social</label>
                <input type="text" class="form-control" id="razao_social" name="razao_social" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="contato" class="form-label">Contato</label>
                <input type="text" class="form-control" id="contato" name="contato" required>
            </div>
            <div class="mb-3">
                <label for="telefone" class="form-label">Telefone</label>
                <input type="text" class="form-control" id="telefone" name="telefone" required>
            </div>
            <button type="submit" class="btn btn-success">Registrar Empresa</button>
        </form>
    </div>
</div>
