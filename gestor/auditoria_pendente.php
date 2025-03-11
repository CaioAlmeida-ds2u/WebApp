<?php
// gestor/auditorias_pendentes.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/auditorias_functions.php';

echo getHeaderAdmin("Auditorias Pendentes");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['id'])) {
    $id = intval($_POST['id']);
    if ($_POST['acao'] === 'aprovar') {
        aprovarAuditoria($conexao, $id);
    } elseif ($_POST['acao'] === 'rejeitar') {
        rejeitarAuditoria($conexao, $id);
    }
    header("Location: auditorias_pendentes.php");
    exit;
}

$auditorias = getAuditoriasPendentes($conexao);
?>

<div class="container mt-4">
    <h2>Auditorias Pendentes</h2>
    <p>Lista de auditorias aguardando aprovação.</p>
    
    <table class="table table-striped">
        <thead>
            <?php echo $auditorias ?> 
            <tr>
                <th>ID</th>
                <th>Auditor</th>
                <th>Descrição</th>
                <th>Data</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($auditorias as $auditoria): ?>
                
                <tr>
                    <td><?= $auditoria['id'] ?></td>
                    <td><?= htmlspecialchars($auditoria['auditor']) ?></td>
                    <td><?= htmlspecialchars($auditoria['descricao']) ?></td>
                    <td><?= $auditoria['data_criacao'] ?></td>
                    <td><?= $auditoria['status'] ?></td>
                    <td>
                        <?php if ($auditoria['status'] === 'pendente') { ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $auditoria['id'] ?>">
                                <button type="submit" name="acao" value="aprovar" class="btn btn-success btn-sm">Aprovar</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $auditoria['id'] ?>">
                                <button type="submit" name="acao" value="rejeitar" class="btn btn-danger btn-sm">Rejeitar</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
echo getFooterAdmin();
?>
