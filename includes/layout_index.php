<?php
// includes/layout_index.php

/**
 * Gera o cabeçalho HTML para páginas públicas (não logadas).
 * @param string $title O título da página.
 * @return string O HTML do cabeçalho.
 */
function getHeaderIndex(string $title): string {
    // Garante que BASE_URL foi definida em config.php
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/'); // Fallback seguro, mas idealmente config.php já definiu
    }
    ob_start(); // Usa output buffering para construir a string
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <!-- Link para o CSS usando BASE_URL -->
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style_index.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
        <!-- Link para o CSS base/tema (se você criar depois) -->
        <!-- <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css"> -->
    </head>
    <body>
        <div class="main-content"> <!-- Abre o container principal -->
    <?php
    return ob_get_clean(); // Retorna o conteúdo do buffer
}

/**
 * Gera o rodapé HTML para páginas públicas.
 * @return string O HTML do rodapé.
 */
function getFooterIndex(): string {
    // Garante que BASE_URL foi definida em config.php
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/'); // Fallback
    }
    ob_start();
    ?>
        </div> <!-- Fecha .main-content -->

        <footer class="bg-light text-center py-3 mt-auto"> <!-- mt-auto para sticky footer com flexbox -->
            <p class="mb-0">© <?= date("Y") ?> ACodITools. Todos os direitos reservados.</p>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
        <!-- Se houver JS específico para o index, incluir aqui -->
        <!-- <script src="<?= BASE_URL ?>assets/js/script_index.js"></script> -->
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>