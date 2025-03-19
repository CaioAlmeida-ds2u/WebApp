<?php
// includes/atualizar_senha_primeiro_acesso.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_functions.php'; // Ou funções de usuário

session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['erro' => 'Acesso não autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $usuario_id = $_SESSION['usuario_id'];

    if ($nova_senha !== $confirmar_senha) {
        echo json_encode(['erro' => 'As senhas não coincidem.']);
        exit;
    } else {
        // Atualizar a senha e o status de primeiro acesso
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET senha = ?, primeiro_acesso = 0 WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([$senha_hash, $usuario_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['sucesso' => true]);
        } else {
            echo json_encode(['erro' => 'Erro ao atualizar a senha. Tente novamente.']);
        }
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['erro' => 'Método não permitido.']);
}
?>