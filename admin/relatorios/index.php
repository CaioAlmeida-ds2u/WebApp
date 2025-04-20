<?php
// admin/relatorios/index.php - Página principal de seleção de relatórios (Visual Melhorado)

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens ---
$sucesso_msg = $_SESSION['report_sucesso'] ?? null;
$erro_msg = $_SESSION['report_erro'] ?? null;
unset($_SESSION['report_sucesso'], $_SESSION['report_erro']);

// --- Obter dados para filtros ---
$lista_usuarios = getTodosUsuarios($conexao);

// --- Geração do HTML ---
$title = "ACodITools - Central de Relatórios";
echo getHeaderAdmin($title); // Layout unificado já abre <main class="container ...">
?>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h2"><i class="fas fa-file-download me-2"></i>Central de Relatórios</h1>
        <span class="text-muted small">Selecione, configure e gere os relatórios em formato CSV.</span>
    </div>

    <?php /* Mensagens */ ?>
    <?php if ($sucesso_msg): ?><div class="alert alert-success d-flex align-items-center alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert"><i class="fas fa-check-circle flex-shrink-0 me-2"></i><div><?= htmlspecialchars($sucesso_msg) ?></div><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger d-flex align-items-center alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert"><i class="fas fa-exclamation-triangle flex-shrink-0 me-2"></i><div><?= htmlspecialchars($erro_msg) ?></div><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4"> <?php /* Layout responsivo dos cards */ ?>

        <?php /* ----- Card Relatório de Usuários ----- */ ?>
        <div class="col">
            <div class="card h-100 shadow-sm report-card border-start border-5 border-primary"> <?php /* Borda colorida e classe customizada */ ?>
                <div class="card-body d-flex flex-column">
                     <div class="d-flex align-items-center mb-3">
                         <div class="flex-shrink-0 text-primary bg-primary-subtle rounded p-2 me-3">
                            <i class="fas fa-users fa-lg fa-fw"></i>
                         </div>
                         <h5 class="card-title fw-bold mb-0">Relatório de Usuários</h5>
                    </div>
                    <p class="card-text small text-muted mb-3 flex-grow-1">Exporte a lista de usuários com filtros opcionais.</p>
                    <form action="<?= BASE_URL ?>admin/relatorios/exportar_usuarios.php" method="GET" target="_blank" class="mt-auto"> <?php /* mt-auto empurra form para baixo */ ?>
                         <div class="row g-2 mb-3"> <?php /* Filtros em linha */ ?>
                             <div class="col-6">
                                <label for="filtro_user_status" class="form-label form-label-sm visually-hidden">Status</label>
                                <select name="status" id="filtro_user_status" class="form-select form-select-sm" title="Filtrar por Status">
                                    <option value="todos" selected>Todos Status</option>
                                    <option value="ativos">Apenas Ativos</option>
                                    <option value="inativos">Apenas Inativos</option>
                                </select>
                             </div>
                             <div class="col-6">
                                <label for="filtro_user_perfil" class="form-label form-label-sm visually-hidden">Perfil</label>
                                <select name="perfil" id="filtro_user_perfil" class="form-select form-select-sm" title="Filtrar por Perfil">
                                    <option value="" selected>Todos Perfis</option>
                                    <option value="admin">Admin</option>
                                    <option value="auditor">Auditor</option>
                                    <option value="gestor">Gestor</option>
                                </select>
                             </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-download me-2"></i>Gerar Relatório (CSV)</button>
                    </form>
                </div>
            </div>
        </div>

        <?php /* ----- Card Relatório de Empresas ----- */ ?>
        <div class="col">
             <div class="card h-100 shadow-sm report-card border-start border-5 border-info">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                         <div class="flex-shrink-0 text-info bg-info-subtle rounded p-2 me-3">
                            <i class="fas fa-building fa-lg fa-fw"></i>
                         </div>
                        <h5 class="card-title fw-bold mb-0">Relatório de Empresas</h5>
                    </div>
                    <p class="card-text small text-muted mb-3 flex-grow-1">Exporte a lista completa de empresas cadastradas.</p>
                    <form action="<?= BASE_URL ?>admin/relatorios/exportar_empresas.php" method="GET" target="_blank" class="mt-auto">
                        <?php /*Pode adicionar filtro de status aqui se implementar no backend */ ?>
                        <div class="mb-3">
                           <label for="filtro_emp_status" class="form-label form-label-sm visually-hidden">Status</label>
                           <select name="status" id="filtro_emp_status" class="form-select form-select-sm" title="Filtrar por Status">
                               <option value="todos" selected>Todos Status</option>
                               <option value="ativos">Apenas Ativas</option>
                               <option value="inativos">Apenas Inativas</option>
                           </select>
                       </div>
                       <button type="submit" class="btn btn-sm btn-outline-info w-100"><i class="fas fa-download me-2"></i>Gerar Relatório (CSV)</button>
                    </form>
                </div>
            </div>
        </div>

         <?php /* ----- Card Relatório de Logs ----- */ ?>
        <div class="col">
            <div class="card h-100 shadow-sm report-card border-start border-5 border-secondary">
                 <div class="card-body d-flex flex-column">
                     <div class="d-flex align-items-center mb-3">
                         <div class="flex-shrink-0 text-secondary bg-secondary-subtle rounded p-2 me-3">
                             <i class="fas fa-history fa-lg fa-fw"></i>
                         </div>
                         <h5 class="card-title fw-bold mb-0">Relatório de Logs</h5>
                    </div>
                    <p class="card-text small text-muted mb-3 flex-grow-1">Exporte logs com filtros de data, usuário e status.</p>
                    <form action="<?= BASE_URL ?>admin/relatorios/exportar_logs.php" method="GET" target="_blank" class="mt-auto">
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label for="log_data_inicio" class="form-label form-label-sm">De:</label><input type="date" name="data_inicio" id="log_data_inicio" class="form-control form-control-sm"></div>
                            <div class="col-6"><label for="log_data_fim" class="form-label form-label-sm">Até:</label><input type="date" name="data_fim" id="log_data_fim" class="form-control form-control-sm"></div>
                        </div>
                        <div class="mb-2">
                            <label for="log_usuario" class="form-label form-label-sm">Usuário:</label>
                            <select name="usuario_id" id="log_usuario" class="form-select form-select-sm">
                                <option value="">-- Todos Usuários --</option>
                                <?php foreach($lista_usuarios as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nome']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                         <div class="mb-3">
                            <label for="log_status" class="form-label form-label-sm">Status do Log:</label>
                             <select name="status" id="log_status" class="form-select form-select-sm">
                                 <option value="" selected>Todos Status</option>
                                 <option value="1">Apenas Sucesso</option>
                                 <option value="0">Apenas Falha</option>
                             </select>
                        </div>
                         <button type="submit" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-download me-2"></i>Gerar Relatório (CSV)</button>
                    </form>
                 </div>
            </div>
        </div>

        <?php /* ----- Card Relatório de Requisitos ----- */ ?>
        <div class="col">
            <div class="card h-100 shadow-sm report-card border-start border-5 border-success">
                 <div class="card-body d-flex flex-column">
                     <div class="d-flex align-items-center mb-3">
                         <div class="flex-shrink-0 text-success bg-success-subtle rounded p-2 me-3">
                            <i class="fas fa-tasks fa-lg fa-fw"></i>
                         </div>
                        <h5 class="card-title fw-bold mb-0">Relatório de Requisitos</h5>
                    </div>
                    <p class="card-text small text-muted mb-3 flex-grow-1">Exporte a lista mestra de requisitos de auditoria.</p>
                    <form action="<?= BASE_URL ?>admin/requisitos/exportar_requisitos.php" method="GET" target="_blank" class="mt-auto">
                         <div class="mb-3"> <?php /* Filtro de status para requisitos */ ?>
                            <label for="filtro_req_status" class="form-label form-label-sm visually-hidden">Status Requisito</label>
                            <select name="status" id="filtro_req_status" class="form-select form-select-sm" title="Filtrar por Status">
                                <option value="todos" selected>Todos Status</option>
                                <option value="ativos">Apenas Ativos</option>
                                <option value="inativos">Apenas Inativos</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-success w-100"><i class="fas fa-download me-2"></i>Gerar Relatório (CSV)</button>
                    </form>
                </div>
            </div>
        </div>


        <?php /* ----- Card Futuro: Relatório de Auditorias ----- */ ?>
        <div class="col">
            <div class="card h-100 shadow-sm bg-light border-dashed">
                 <div class="card-body d-flex flex-column text-center text-muted">
                     <div class="mb-3"><i class="fas fa-clipboard-check fa-3x text-black-50 opacity-25"></i></div>
                    <h5 class="card-title fw-bold">Relatório de Auditorias</h5>
                    <p class="card-text small flex-grow-1">Relatórios sobre auditorias concluídas, pendentes, conformidade por empresa, etc.</p>
                     <button type="button" class="btn btn-sm btn-outline-secondary w-100 disabled mt-auto" aria-disabled="true"><i class="fas fa-lock me-1"></i>Indisponível</button>
                 </div>
            </div>
        </div>

    </div> <?php /* Fim da row */ ?>

<?php /* Não precisa fechar main ou container-fluid se o layout_admin já faz isso */ ?>

<?php
echo getFooterAdmin();
?>