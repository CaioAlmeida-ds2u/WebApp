<?php
// admin/plataforma_catalogos_globais.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php';

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// Contagem de itens em cada catálogo para exibir nos cards (opcional)
// $totalTiposNC = contarTiposNaoConformidadeGlobal($conexao); // Função a ser criada
// $totalNiveisCrit = contarNiveisCriticidadeGlobal($conexao);   // Função a ser criada
// Simulação:
$totalTiposNC = rand(5, 20);
$totalNiveisCrit = rand(3, 7);


$title = "ACodITools - Catálogos Globais da Plataforma";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tags me-2"></i>Catálogos Globais da Plataforma</h1>
        <small class="text-muted">Gerencie os elementos padronizados utilizados nas auditorias.</small>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <div class="col">
            <div class="card h-100 shadow-sm hover-lift">
                <div class="card-body text-center p-4">
                    <div class="display-4 text-primary mb-3">
                        <i class="fas fa-ban"></i>
                    </div>
                    <h5 class="card-title fw-semibold">Tipos de Não Conformidade</h5>
                    <p class="card-text small text-muted">
                        Defina e gerencie os tipos padrão para classificar não conformidades encontradas nas auditorias.
                    </p>
                    <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill mb-3"><?= $totalTiposNC ?> tipos cadastrados</span>
                    <a href="<?= BASE_URL ?>admin/plataforma_catalogo_tipos_nao_conformidade.php" class="btn btn-primary btn-sm stretched-link mt-auto">
                        Gerenciar Tipos de NC <i class="fas fa-arrow-right fa-xs ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 shadow-sm hover-lift">
                <div class="card-body text-center p-4">
                     <div class="display-4 text-warning mb-3">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5 class="card-title fw-semibold">Níveis de Criticidade dos Achados</h5>
                    <p class="card-text small text-muted">
                        Estabeleça a escala de severidade (Baixa, Média, Alta, Crítica) para os achados da auditoria.
                    </p>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill mb-3"><?= $totalNiveisCrit ?> níveis definidos</span>
                    <a href="<?= BASE_URL ?>admin/plataforma_catalogo_niveis_criticidade_achado.php" class="btn btn-warning btn-sm stretched-link mt-auto">
                        Gerenciar Níveis de Criticidade <i class="fas fa-arrow-right fa-xs ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Placeholder para futuros catálogos -->
        <div class="col">
            <div class="card h-100 shadow-sm border-dashed bg-light o-75">
                <div class="card-body text-center p-4 d-flex flex-column justify-content-center">
                     <div class="display-4 text-muted mb-3">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h5 class="card-title fw-semibold text-muted">Novo Catálogo (Futuro)</h5>
                    <p class="card-text small text-muted">
                        Espaço reservado para futuros catálogos globais da plataforma.
                    </p>
                    {/* <a href="#" class="btn btn-outline-secondary btn-sm stretched-link mt-auto disabled">
                        Em Breve <i class="fas fa-arrow-right fa-xs ms-1"></i>
                    </a> */}
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .hover-lift {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .hover-lift:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
    .card-body {
        display: flex;
        flex-direction: column;
    }
    .card-body .btn {
        margin-top: auto; /* Empurra o botão para baixo se o card tiver altura fixa via h-100 e row g-4 */
    }
</style>

<?php
echo getFooterAdmin();
?>