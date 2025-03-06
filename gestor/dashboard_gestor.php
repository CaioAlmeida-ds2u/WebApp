<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../includes/layout_admin.php';

echo getHeaderAdmin("Painel do Gestor de Auditoria");
?>

<div class="container mt-4">
    <h2>Bem-vindo, Gestor de Auditoria</h2>
    <p>Aqui você pode gerenciar auditorias, aprovar relatórios e acompanhar estatísticas do sistema.</p>

    <div class="row">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Auditorias Pendentes</h5>
                    <p class="card-text">Visualize e aprove auditorias em andamento.</p>
                    <a href="auditorias_gestor.php" class="btn btn-light">Gerenciar</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Relatórios de Conformidade</h5>
                    <p class="card-text">Gere e exporte relatórios das auditorias.</p>
                    <a href="relatorios.php" class="btn btn-light">Acessar</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Estatísticas Gerais</h5>
                    <p class="card-text">Acompanhe métricas sobre auditorias e conformidade.</p>
                    <a href="estatisticas.php" class="btn btn-light">Visualizar</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title">Gestão de Auditores</h5>
                    <p class="card-text">Gerencie os auditores cadastrados e suas permissões.</p>
                    <a href="gestao_auditores.php" class="btn btn-light">Gerenciar</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <h5 class="card-title">Plano de Ação</h5>
                    <p class="card-text">Defina planos de ação para conformidade e melhorias.</p>
                    <a href="plano_acao.php" class="btn btn-light">Definir Ações</a>
                </div>
            </div>
        </div>
        

        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Riscos e Não Conformidades</h5>
                    <p class="card-text">Analise riscos e gerencie não conformidades identificadas.</p>
                    <a href="riscos_nao_conformidades.php" class="btn btn-light">Visualizar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
echo getFooterAdmin();
?>
