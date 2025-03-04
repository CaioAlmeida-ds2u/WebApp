<?php
// dashboard_admin.php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/admin_functions.php';
require_once __DIR__ . '/includes/layout_admin.php';
require_once __DIR__ . '/includes/db.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens (Sucesso/Erro) ---
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
$itens_por_pagina = 10;

// --- Obtenção dos Usuários ---
$usuarios_data = getUsuarios($conexao, $_SESSION['usuario_id'], $pagina_atual, $itens_por_pagina);
$usuarios = $usuarios_data['usuarios'];
$paginacao = $usuarios_data['paginacao'];


$title = "ACodITools - Dashboard do Administrador";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <h1>Dashboard do Administrador</h1>

    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab" aria-controls="usuarios" aria-selected="true">Usuários</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="solicitacoes-acesso-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-acesso" type="button" role="tab" aria-controls="solicitacoes-acesso" aria-selected="false">Solicitações de Acesso</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="solicitacoes-reset-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-reset" type="button" role="tab" aria-controls="solicitacoes-reset" aria-selected="false">Solicitações de Reset</button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabContent">
        <div class="tab-pane fade show active" id="usuarios" role="tabpanel" aria-labelledby="usuarios-tab">
            <h2>Gestão de Usuários</h2>
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
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Data de Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <script src="' . BASE_URL . 'assets/js/scripts.js"></script>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?= htmlspecialchars($usuario['id']) ?></td>
                                <td><?= htmlspecialchars($usuario['nome']) ?></td>
                                <td><?= htmlspecialchars($usuario['email']) ?></td>
                                <td><?= htmlspecialchars($usuario['perfil']) ?></td>
                                <td><?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?></td>
                                <td><?= htmlspecialchars((new DateTime($usuario['data_cadastro']))->format('d/m/Y H:i:s')) ?></td>
                                <td>
                                    <a href="editar_usuario.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <?php if ($usuario['ativo']): ?>
                                        <a href="desativar_usuario.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-warning">Desativar</a>
                                    <?php else: ?>
                                        <a href="ativar_usuario.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-success">Ativar</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-userid="<?= $usuario['id'] ?>" data-action="excluir_usuario.php">
                                        Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Paginação">
                <ul class="pagination">
                    <?php if ($paginacao['pagina_atual'] > 1): ?>
                        <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] - 1 ?>">Anterior</a></li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $paginacao['total_paginas']; $i++): ?>
                        <li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?>
                        <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] + 1 ?>">Próxima</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Tem certeza que deseja excluir este usuário?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Excluir</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="tab-pane fade" id="solicitacoes-acesso" role="tabpanel" aria-labelledby="solicitacoes-acesso-tab">
            <h2>Solicitações de Acesso Pendentes</h2>
            <?php $solicitacoes = getSolicitacoesAcessoPendentes($conexao);
            if ($solicitacoes): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Empresa</th>
                                <th>Motivo</th>
                                <th>Data da Solicitação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <script src="' . BASE_URL . 'assets/js/scripts.js"></script>
                            <?php foreach ($solicitacoes as $solicitacao): ?>
                                <tr>
                                    <td><?= htmlspecialchars($solicitacao['id']) ?></td>
                                    <td><?= htmlspecialchars($solicitacao['nome_completo']) ?></td>
                                    <td><?= htmlspecialchars($solicitacao['email']) ?></td>
                                    <td><?= htmlspecialchars($solicitacao['empresa_nome']) ?></td>
                                    <td><?= htmlspecialchars($solicitacao['motivo']) ?></td>
                                    <td><?= htmlspecialchars((new DateTime($solicitacao['data_solicitacao']))->format('d/m/Y H:i:s')) ?></td>
                                    <td>
                                        <a href="aprovar_acesso.php?id=<?= $solicitacao['id'] ?>" class="btn btn-sm btn-success">Aprovar</a>
                                        <a href="rejeitar_acesso.php?id=<?= $solicitacao['id'] ?>" class="btn btn-sm btn-danger">Rejeitar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhuma solicitação de acesso pendente.</p>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="solicitacoes-reset" role="tabpanel" aria-labelledby="solicitacoes-reset-tab">
            <h2>Solicitações de Reset de Senha Pendentes</h2>

            <?php
            $solicitacoesReset = getSolicitacoesResetPendentes($conexao);

            if ($solicitacoesReset): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>E-mail</th>
                                <th>Data da Solicitação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitacoesReset as $solicitacao): ?>
                                <tr>
                                    <td><?= htmlspecialchars($solicitacao['id']) ?></td>
                                    <td><?= htmlspecialchars($solicitacao['nome_usuario']) ?></td>
                                    <td><?= htmlspecialchars($solicitacao['email']) ?></td>
                                    <td><?= htmlspecialchars((new DateTime($solicitacao['data_solicitacao']))->format('d/m/Y H:i:s')) ?></td>
                                    <td>
                                        <a href="redefinir_senha_admin.php?id=<?= $solicitacao['id'] ?>" class="btn btn-sm btn-primary">Redefinir Senha</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhuma solicitação de reset de senha pendente.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php echo getFooterAdmin(); ?>