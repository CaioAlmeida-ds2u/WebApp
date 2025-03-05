<?php
// logout.php

session_start(); // Inicia a sessão (se já não estiver iniciada)

// Destrói todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir a sessão completamente, apague também o cookie de sessão.
// Nota: Isto destruirá a sessão, e não apenas os dados da sessão!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão
session_destroy();

// Redireciona para a página de login
header('Location: index.php');
exit;
?>