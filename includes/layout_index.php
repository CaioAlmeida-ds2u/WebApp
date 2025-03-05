<?php
// includes/layout_index.php

function getHeaderIndex($title) {
    $header = '<!DOCTYPE html>
                <html lang="pt-BR">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>' . htmlspecialchars($title) . '</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
                    <link rel="stylesheet" href="assets/css/style_index.css">
                </head>
                <body>
                    <div class="main-content">';
    return $header;
}

function getFooterIndex() {
    ob_start(); // Inicia o buffer de saída
    ?>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="bg-light text-center py-3 mt-4">
                    <p>&copy; <?= date("Y") ?> ACodITools. Todos os direitos reservados.</p>
                </footer>
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            </body>
            </html>
    <?php
    return ob_get_clean(); // Retorna o conteúdo e limpa o buffer
}
?>