<?php
require_once __DIR__ . '/includes/layout.php';
echo getHeader("Acesso Negado");
?>
<div class="container mt-5">
    <div class="alert alert-danger">
        <h1>Acesso Negado</h1>
        <p>Você não tem permissão para acessar esta página.</p>
    </div>
</div>
<?php echo getFooter(); ?>