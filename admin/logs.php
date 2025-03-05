<?php
// admin/logs.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin.php';

protegerPagina();

if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ../acesso_negado.php');
    exit;
}

// --- Filtros ---
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$usuario_id = $_GET['usuario_id'] ?? '';
$acao = $_GET['acao'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? ''; //Campo de busca.

// --- Paginação ---
$pagina_atual = $_GET['pagina'] ?? 1;
$pagina_atual = is_numeric($pagina_atual) ? (int)$pagina_atual : 1;
$itens_por_pagina = 20; // Ajuste conforme necessário

// --- Obtenção dos Logs (agora com filtros) ---
$logs_data = getLogsAcesso($conexao, $pagina_atual, $itens_por_pagina, $data_inicio, $data_fim, $usuario_id, $acao, $status, $search);
$logs = $logs_data['logs'];
$paginacao = $logs_data['paginacao'];

// --- Obtenção de Usuários para o Filtro (Opcional - Se for usar select) ---
$usuarios = getTodosUsuarios($conexao); // Função que você já tem!

// --- Obtenção de Ações Únicas para o Filtro (Opcional - Se for usar select) ---
// (Você pode criar uma função separada para isso, se quiser)
$stmt = $conexao->query("SELECT DISTINCT acao FROM logs_acesso ORDER BY acao");
$acoes = $stmt->fetchAll(PDO::FETCH_COLUMN); // Pega só a coluna 'acao'

$title = "ACodITools - Logs de Acesso";
echo getHeaderAdmin($title);
?>

<div class="container mt-5">
    <h1>Logs de Acesso</h1>

    <form method="GET" action="logs.php" class="mb-4">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="data_inicio" class="form-label">Data de Início:</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="data_fim" class="form-label">Data de Fim:</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="usuario_id" class="form-label">Usuário:</label>
                <select class="form-select" id="usuario_id" name="usuario_id">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= $usuario['id'] ?>" <?= $usuario_id == $usuario['id'] ? 'selected' : '' ?>><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="col-md-3 mb-3">
                <label for="search" class="form-label">Pesquisar</label>
                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="acao" class="form-label">Ação:</label>
                <select class="form-select" id="acao" name="acao">
                    <option value="">Todas</option>
                    <?php foreach ($acoes as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $acao == $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="col-md-3 mb-3">
                <label for="status" class="form-label">Status:</label>
                <select class="form-select" id="status" name="status">
                    <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Sucesso</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Falha</option>
                </select>
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="logs.php" class="btn btn-secondary ms-2">Limpar Filtros</a>
            </div>
        </div>

    </form>


    <div class="table-responsive">
       <table class="table table-striped table-hover">
           <thead>
                <tr>
                   <th>ID</th>
                   <th>Usuário</th>
                    <th>E-mail</th>
                    <th>IP</th>
                   <th>Data/Hora</th>
                    <th>Ação</th>
                    <th>Sucesso</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['nome_usuario']) ?></td>
                            <td><?= htmlspecialchars($log['email_usuario']) ?></td>
                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                            <td><?= htmlspecialchars((new DateTime($log['data_hora']))->format('d/m/Y H:i:s')) ?></td>
                            <td><?= htmlspecialchars($log['acao']) ?></td>
                            <td><?= $log['sucesso'] ? 'Sim' : 'Não' ?></td>
                            <td><?= htmlspecialchars($log['detalhes']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Nenhum log encontrado.</td>
                    </tr>
                <?php endif; ?>
           </tbody>
        </table>
    </div>

    <nav aria-label="Paginação">
        <ul class="pagination">
            <?php if ($paginacao['pagina_atual'] > 1): ?>
                <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] - 1 ?>&data_inicio=<?= htmlspecialchars($data_inicio) ?>&data_fim=<?= htmlspecialchars($data_fim) ?>&usuario_id=<?= htmlspecialchars($usuario_id) ?>&acao=<?= htmlspecialchars($acao) ?>&status=<?= htmlspecialchars($status) ?>">Anterior</a></li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $paginacao['total_paginas']; $i++): ?>
                <li class="page-item <?= ($i == $paginacao['pagina_atual']) ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>&data_inicio=<?= htmlspecialchars($data_inicio) ?>&data_fim=<?= htmlspecialchars($data_fim) ?>&usuario_id=<?= htmlspecialchars($usuario_id) ?>&acao=<?= htmlspecialchars($acao)?>&status=<?= htmlspecialchars($status) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($paginacao['pagina_atual'] < $paginacao['total_paginas']): ?>
                <li class="page-item"><a class="page-link" href="?pagina=<?= $paginacao['pagina_atual'] + 1 ?>&data_inicio=<?= htmlspecialchars($data_inicio) ?>&data_fim=<?= htmlspecialchars($data_fim) ?>&usuario_id=<?= htmlspecialchars($usuario_id) ?>&acao=<?= htmlspecialchars($acao) ?>&status=<?= htmlspecialchars($status) ?>">Próxima</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php echo getFooterAdmin(); ?>