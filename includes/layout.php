<?php
// includes/layout.php

function getHeader($title) {
    $header = '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <link rel="icon" href="' . BASE_URL . 'assets/img/favicon.ico" type="image/x-icon">
        <link rel="stylesheet" href="' . BASE_URL . 'assets/css/style.css">
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="' . BASE_URL . 'assets/img/ACodITools_logo.png" alt="ACodITools Logo" style="max-height: 40px;" class="d-inline-block align-text-top">
                    ACodITools
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        ';

                        if (isset($_SESSION['usuario_id'])) {
                            // Usuário logado: Mostrar nome e botão de logout
                            $header .= '<li class="nav-item">
                                            <span class="nav-link text-light">Olá, ' . htmlspecialchars($_SESSION['nome']) . '</span>
                                        </li>';

                            //Item Dashboard
                            $header .= '<li class="nav-item"><a class="nav-link" href="';

                            if($_SESSION['perfil'] == 'admin'){
                                $header .= "dashboard_admin.php";
                            }else{
                                $header .= "dashboard_auditor.php";
                            }

                            $header .= '">Dashboard</a></li>';


                            $header .= '<li class="nav-item">
                                            <a class="nav-link" href="logout.php">Sair</a>
                                        </li>';
                        } else {
                            // Usuário não logado: Mostrar link de login
                            $header .= '<li class="nav-item">
                                            <a class="nav-link" href="index.php">Login</a>
                                        </li>';
                        }

                        $header .= '
                    </ul>
                </div>
            </div>
        </nav>
        <main class="container mt-5"> ';
    return $header;
}

function getFooter() {
    $footer = '
        </main> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="' . BASE_URL . 'assets/js/scripts.js"></script>
    </body>
    </html>';
    return $footer;
}
?>