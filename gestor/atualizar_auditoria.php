<?php
require_once 'auditoria_functions.php';
require_once 'config.php'; // Certifique-se de que carrega a conexão corretamente

header('Content-Type: application/json'); // Define o tipo de resposta como JSON

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $status = isset($_POST['status']) ? $_POST['status'] : null;

    if (!$id || !$status) {
        echo json_encode(["success" => false, "message" => "Parâmetros inválidos."]);
        exit;
    }

    $resultado = atualizarStatusAuditoria($conexao, $id, $status, '');

}
?>
