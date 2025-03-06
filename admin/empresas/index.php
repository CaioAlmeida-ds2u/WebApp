<?php
// admin/empresas/index.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_func1.php';
require_once __DIR__ . '/../../includes/admin_func2.php';
require_once __DIR__ . '/../../includes/layout_admin.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php'); // Redireciona se não for admin
    exit;
}

$sucesso = '';  // Mensagens de sucesso/erro (vindos de outras páginas, via sessão)
$erro = '';
if (isset($_SESSION['sucesso'])) {
    $sucesso = $_SESSION['sucesso'];
    unset($_SESSION['sucesso']);
}
if (isset($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']);
}

// --- Paginação ---
$pagina_atual = $_GET['pagina'] ?? 1;
$pagina_atual = is_numeric($pagina_atual) ? (int)$pagina_atual : 1;
$itens_por_pagina = 10; // Defina quantos itens por página você quer

// --- Busca ---
$busca = $_GET['busca'] ?? '';  // Obtém o termo de busca da URL (se houver)
$busca = trim($busca); // Remove espaços em branco do início e do fim

// --- Obtenção das Empresas (com paginação e busca) ---
$empresas_data = getEmpresas($conexao, $pagina_atual, $itens_por_pagina, $busca);
$empresas = $empresas_data['empresas'];
$paginacao = $empresas_data['paginacao'];


$title = "ACodITools - Lista de Empresas";
echo getHeaderAdmin($title);
?>

<h2>Lista de Empresas</h2>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<form method="GET" action="empresas/index.php" class="mb-3">
    <div class="input-group">
        <input type="text" class="form-control" name="busca" placeholder="Buscar por nome, CNPJ, razão social..." value="<?= htmlspecialchars($busca) ?>">
        <button class="btn btn-primary" type="submit">Buscar</button>
        <a href="empresas/index.php" class="btn btn-secondary">Limpar</a>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome Fantasia</th>
                <th>CNPJ</th>
                <th>Razão Social</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($empresas) > 0): ?>
                <?php foreach ($empresas as $empresa): ?>
                    <tr>
                        <td><?= htmlspecialchars($empresa['id']) ?></td>
                        <td><?= htmlspecialchars($empresa['nome']) ?></td>
                        <td><?= htmlspecialchars($empresa['cnpj'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($empresa['razao_social'] ?? 'N/A') ?></td>
                        <td>
                            <a href="empresas/editar.php?id=<?= $empresa['id'] ?>" class="btn btn-primary btn-sm">Editar</a>
                            <a href="empresas/excluir.php?id=<?= $empresa['id'] ?>" class="btn btn-danger btn-sm">Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">Nenhuma empresa encontrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($paginacao['total_paginas'] > 1): ?>
    <nav aria-label="Paginação">
        <ul class="pagination">
            <?php if ($paginacao['pagina_atual'] > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] - 1 ?>&busca=<?= htmlspecialchars($busca) ?>">Anterior</a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $paginacao['total_paginas']; $i++): ?>
                <li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>&busca=<?= htmlspecialchars($busca) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?>
                <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] + 1 ?>&busca=<?= htmlspecialchars($busca) ?>">Próxima</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
<a href="empresas/criar.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Adicionar Empresa
</a>
<?php
echo getFooterAdmin();
?>