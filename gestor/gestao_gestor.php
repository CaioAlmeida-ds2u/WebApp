<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ .'/../includes/db.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/gestao_func.php'; // Caminho corrigido

echo getHeaderAdmin("Gestão de Auditores");
?>

<div class="container mt-4">
    <h2>Gestão de Auditores</h2>
    <p>Aqui você pode visualizar, adicionar, editar e remover auditores.</p>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $auditores = getAuditores($conexao);

            if (empty($auditores)) {
                echo "<tr><td colspan='4' class='text-center'>Nenhum auditor encontrado.</td></tr>";
            } else {
                foreach ($auditores as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td>
                            <a href='editar_auditor.php?id=" . urlencode($row['id']) . "' class='btn btn-warning btn-sm'>Editar</a>
                            <a href='remover_auditor.php?id=" . urlencode($row['id']) . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Tem certeza que deseja remover este auditor?\");'>Remover</a>
                          </td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>

<?php
echo getFooterAdmin();
?>
