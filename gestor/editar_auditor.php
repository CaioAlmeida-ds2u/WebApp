<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Buscar os dados do auditor
    $query = "SELECT nome, email FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($query);
    $stmt->execute([$id]);
    $auditor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auditor) {
        die("Auditor não encontrado!");
    }
} else {
    die("ID do auditor não fornecido!");
}

// Atualizar os dados do auditor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'update') {
        $nome = $_POST['nome'];
        $email = $_POST['email'];

        $query = "UPDATE usuarios SET nome = ?, email = ? WHERE id = ?";
        $stmt = $conexao->prepare($query);

        if ($stmt->execute([$nome, $email, $id])) {
            echo "<script>alert('Auditor atualizado com sucesso!'); window.location.href='http://localhost/WebApp/gestor/dashboard_gestor.php';</script>";
        } else {
            echo "<script>alert('Erro ao atualizar auditor.');</script>";
        }
    } elseif ($_POST['action'] == 'delete') {
        // Excluir o auditor
        $query = "DELETE FROM usuarios WHERE id = ?";
        $stmt = $conexao->prepare($query);
        
        if ($stmt->execute([$id])) {
            echo "<script>alert('Auditor removido com sucesso!'); window.location.href='http://localhost/WebApp/gestor/dashboard_gestor.php';</script>";
        } else {
            echo "<script>alert('Erro ao remover auditor.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Auditor</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <script>
        function confirmarCancelamento() {
            if (confirm("Tem certeza de que deseja cancelar a edição? As alterações não serão salvas.")) {
                window.location.href = 'http://localhost/WebApp/gestor/dashboard_gestor.php';
            }
        }

        function confirmarRemocao() {
            if (confirm("Tem certeza de que deseja remover este auditor? Esta ação não pode ser desfeita.")) {
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</head>
<body>
    <div class="container mt-4">
        <h2>Editar Auditor</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome:</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($auditor['nome']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email:</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($auditor['email']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            <a href="javascript:void(0);" class="btn btn-secondary" onclick="confirmarCancelamento()">Cancelar</a>
        </form>

        <!-- Formulário para remoção do auditor -->
        <form id="delete-form" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete">
        </form>
        <button class="btn btn-danger mt-3" onclick="confirmarRemocao()">Remover Auditor</button>

        <!-- Botão para voltar ao dashboard -->
        <a href="http://localhost/WebApp/gestor/dashboard_gestor.php" class="btn btn-warning mt-3">Voltar ao Dashboard</a>
    </div>
</body>
</html>
