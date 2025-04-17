<?php
// admin/dashboard_admin.php

require_once __DIR__ . '/../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../includes/layout_admin.php';   // Layout unificado (Header/Footer)
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções admin (getDashboardCounts, dbGetDadosUsuario)

// Proteção da página: Verifica se está logado
protegerPagina($conexao); // Passa a conexão para a função

// Verificação de Perfil: Apenas Admin pode acessar
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens (Sucesso/Erro de outras páginas) ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']); // Limpa após ler

// --- Verificar Primeiro Acesso ---
$usuario_id = $_SESSION['usuario_id'];
$usuario = dbGetDadosUsuario($conexao, $usuario_id); // Função de db.php ou admin_functions.php

// Se não encontrar dados do usuário, algo está errado, deslogar por segurança
if (!$usuario) {
    header('Location: ' . BASE_URL . 'logout.php?erro=usuario_invalido');
    exit;
}

$primeiro_acesso = (isset($usuario['primeiro_acesso']) && $usuario['primeiro_acesso'] == 1);

// --- Buscar Dados para o Dashboard (Apenas se não for primeiro acesso) ---
$counts = [];
if (!$primeiro_acesso) {
    $counts = getDashboardCounts($conexao); // Busca as contagens
}

// --- Geração do HTML ---
$title = "ACodITools - Dashboard Admin";
echo getHeaderAdmin($title); // Inclui Header, Navbar, Sidebar e abre <main>
?>

<?php /* ----- Modal de Primeiro Acesso ----- */ ?>
<?php if ($primeiro_acesso): ?>
    <?php /* O modal será exibido via JS (scripts_admin.js) */ ?>
    <div id="bloqueio-conteudo" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center;">
         <?php /* Adicionado um container para centralizar melhor */ ?>
        <div class="modal fade show" id="primeiroAcessoModal" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-modal="true" role="dialog" style="display: block;">
            <div class="modal-dialog modal-dialog-centered"> <?php /* Centraliza verticalmente */ ?>
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="primeiroAcessoModalLabel">Primeiro Acesso - Redefinir Senha</h5>
                        <?php /* Sem botão de fechar, pois é obrigatório */ ?>
                    </div>
                    <div class="modal-body">
                        <p>Por segurança, você precisa definir uma nova senha para continuar.</p>
                        <?php /* Formulário para redefinir senha (será processado por JS/AJAX) */ ?>
                        <form id="formRedefinirSenha" novalidate> <?php /* novalidate para deixar JS cuidar */ ?>
                             <?php /* Adicionar CSRF aqui por consistência, embora menos crítico */ ?>
                             <input type="hidden" name="csrf_token_modal" value="<?= htmlspecialchars($csrf_token) ?>"> <?php /* Nome diferente para evitar conflito se houver outro form */ ?>
                            <div class="mb-3">
                                <label for="nova_senha_modal" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="nova_senha_modal" name="nova_senha" required minlength="8"> <?php /* Adicionado minlength */ ?>
                                <div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div> <?php /* Feedback do Bootstrap */ ?>
                                <div class="form-text">Use no mínimo 8 caracteres, combinando letras e números.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_senha_modal" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="confirmar_senha_modal" name="confirmar_senha" required>
                                <div class="invalid-feedback">As senhas não coincidem.</div>
                            </div>
                            <?php /* Divs para mensagens de erro/sucesso do JS */ ?>
                            <div id="senha_error" class="alert alert-danger mt-3" style="display: none;"></div>
                            <div id="senha_sucesso" class="alert alert-success mt-3" style="display: none;"></div>
                            <button type="submit" class="btn btn-primary w-100">Redefinir Senha</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php /* ----- Conteúdo Normal do Dashboard (Se NÃO for Primeiro Acesso) ----- */ ?>
<?php else: ?>
    <div class="container-fluid"> <?php /* Usar container-fluid para ocupar espaço */ ?>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Dashboard</h1>
            <?php /* Poderia adicionar botões de ação rápida aqui */ ?>
             <div class="btn-toolbar mb-2 mb-md-0">
                <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-list-alt me-1"></i> Gerenciar Requisitos
                </a>
                <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fas fa-users me-1"></i> Gerenciar Usuários
                </a>
                <a href="<?= BASE_URL ?>admin/empresa/empresa_index.php" class="btn btn-sm btn-outline-secondary">
                     <i class="fas fa-building me-1"></i> Gerenciar Empresas
                </a>
             </div>
        </div>

        <?php /* Exibir mensagens de sucesso/erro vindas de outras páginas */ ?>
        <?php if ($sucesso_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($sucesso_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($erro_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($erro_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <?php /* ----- Cards de Informação ----- */ ?>
        <div class="row g-4 mb-4"> <?php /* g-4 adiciona espaçamento entre colunas/linhas */ ?>
            <div class="col-md-6 col-xl-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="card-title mb-2"><i class="fas fa-user-plus me-2"></i>Solicitações de Acesso</h5>
                            <p class="card-text fs-1 fw-bold"><?= $counts['solicitacoes_acesso'] ?? '?' ?></p>
                        </div>
                        <a href="<?= BASE_URL ?>admin/usuarios.php#solicitacoes-acesso-tab" class="btn btn-light btn-sm stretched-link">Ver Pendentes</a> <?php /* Link direto para a aba */ ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                 <div class="card bg-warning text-dark h-100"> <?php /* Mudança de cor */ ?>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="card-title mb-2"><i class="fas fa-key me-2"></i>Solicitações de Senha</h5>
                            <p class="card-text fs-1 fw-bold"><?= $counts['solicitacoes_reset'] ?? '?' ?></p>
                        </div>
                         <a href="<?= BASE_URL ?>admin/usuarios.php#solicitacoes-reset-tab" class="btn btn-dark btn-sm stretched-link">Ver Pendentes</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                         <div>
                            <h5 class="card-title mb-2"><i class="fas fa-user-check me-2"></i>Usuários Ativos</h5>
                            <p class="card-text fs-1 fw-bold"><?= $counts['usuarios_ativos'] ?? '?' ?></p>
                        </div>
                         <a href="<?= BASE_URL ?>admin/usuarios.php" class="btn btn-light btn-sm stretched-link">Gerenciar Usuários</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                         <div>
                            <h5 class="card-title mb-2"><i class="fas fa-building me-2"></i>Empresas Cadastradas</h5>
                            <p class="card-text fs-1 fw-bold"><?= $counts['total_empresas'] ?? '?' ?></p>
                         </div>
                        <a href="<?= BASE_URL ?>admin/empresa/empresa_index.php" class="btn btn-light btn-sm stretched-link">Gerenciar Empresas</a>
                    </div>
                </div>
            </div>
        </div>

        <?php /* ----- Área para Gráficos ou Atividades Recentes (Exemplo) ----- */ ?>
        <div class="row">
            <div class="col-12">
                 <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line me-2"></i>Atividade Recente
                    </div>
                    <div class="card-body">
                        <p>Atividades recentes registradas em log.</p>
                        <?php
                        
                        // Exemplo: Buscar últimos 5 logs
                        require_once __DIR__ . '/../includes/admin_functions.php'; // logs function
                        $logsRecentesData = getLogsAcesso($conexao, 1, 5); // Pag 1, 5 itens
                        $logsRecentes = $logsRecentesData['logs'];

                        if (!empty($logsRecentes)) {
                            echo '<ul class="list-group list-group-flush">';
                            foreach ($logsRecentes as $log) {
                                $log_classe = $log['sucesso'] ? 'text-success' : 'text-danger';
                                $log_icone = $log['sucesso'] ? 'fas fa-check-circle' : 'fas fa-times-circle';
                                $log_data = new DateTime($log['data_hora']);
                                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                echo '<div><i class="' . $log_icone . ' ' . $log_classe . ' me-2"></i> ' . htmlspecialchars($log['acao']) . ' <small class="text-muted"> por ' . htmlspecialchars($log['nome_usuario'] ?? 'Sistema') . ' (' . htmlspecialchars($log['ip_address']) . ')</small></div>';
                                echo '<span class="badge bg-light text-dark">' . $log_data->format('d/m/Y H:i') . '</span>';
                                echo '</li>';
                            }
                            echo '</ul>';
                            echo '<div class="text-center mt-3"><a href="'.BASE_URL.'admin/logs.php" class="btn btn-outline-primary btn-sm">Ver Todos os Logs</a></div>';
                        } else {
                            echo '<p class="text-center text-muted">Nenhuma atividade recente registrada.</p>';
                        }
                        
                        // Exemplo de gráfico (pode ser substituído por qualquer outro conteúdo)
                        ?>
                         <p class="text-center text-muted mt-3"><i>(Conteúdo de atividade recente a ser implementado)</i></p>
                    </div>
                </div>
            </div>
        </div>

    </div> <?php /* Fim container-fluid */ ?>
<?php endif; ?>

<?php
// Inclui o Footer e fecha o HTML
echo getFooterAdmin();
?>