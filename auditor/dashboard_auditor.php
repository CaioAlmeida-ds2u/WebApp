<?php
// auditor/dashboard_auditor.php - COM VERIFICAÇÃO DE PRIMEIRO ACESSO

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_auditor.php'; // Usa o layout do auditor
require_once __DIR__ . '/../includes/auditor_functions.php';
require_once __DIR__ . '/../includes/db.php'; // Para dbGetDadosUsuario e dbRegistrarLogAcesso

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'auditor') {
    header('Location: ' . BASE_URL . 'acesso_negado.php'); exit;
}

$auditor_id = (int)$_SESSION['usuario_id'];
$empresa_id = (int)$_SESSION['usuario_empresa_id'];
$nome_auditor = $_SESSION['nome'];

// --- Lógica de Mensagens Flash (para depois da redefinição) ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro'); // Captura erros de login/sessão se houver

// --- Verificar Primeiro Acesso ---
$usuario_auditor = dbGetDadosUsuario($conexao, $auditor_id);
if (!$usuario_auditor) {
    // Se não encontrar o usuário logado no DB, algo está muito errado. Deslogar.
    error_log("Dashboard Auditor: Não foi possível encontrar dados do usuário ID $auditor_id logado.");
    header('Location: ' . BASE_URL . 'logout.php?erro=sessao_invalida');
    exit;
}
$primeiro_acesso_auditor = ($usuario_auditor['primeiro_acesso'] == 1);

// --- Buscar Dados para o Dashboard (APENAS SE NÃO FOR PRIMEIRO ACESSO) ---
$dashboard_data = [];
if (!$primeiro_acesso_auditor) {
    $dashboard_data = getAuditorDashboardStats($conexao, $auditor_id, $empresa_id);
}
// Extrair dados para variáveis (mesmo que vazias se for primeiro acesso)
$auditorias_para_agir = $dashboard_data['auditorias_para_agir_lista'] ?? [];
$notificacoes = $dashboard_data['notificacoes'] ?? [];
$recentes_concluidas = $dashboard_data['recentes_concluidas_lista'] ?? [];
// Contagens para os cards
$cont_pendentes_iniciar = $dashboard_data['pendentes_iniciar'] ?? 0;
$cont_em_andamento = $dashboard_data['em_andamento'] ?? 0;
$cont_aguardando_revisao = $dashboard_data['aguardando_revisao_gestor'] ?? 0;
$cont_solicitado_correcao = $dashboard_data['solicitado_correcao'] ?? 0;
// $cont_planos_acao = $dashboard_data['planos_acao_pendentes'] ?? 0; // Descomentar se usar


// --- Geração do HTML ---
$page_title = "Dashboard do Auditor";
// Token CSRF para o formulário do modal de primeiro acesso
$csrf_token_modal = gerar_csrf_token(); // Gera um token fresco
echo getHeaderAuditor($page_title);
?>

<?php /* ----- Modal de Primeiro Acesso (Quase Idêntico ao do Admin) ----- */ ?>
<?php if ($primeiro_acesso_auditor): ?>
    <div id="bloqueio-conteudo-auditor" class="modal-backdrop fade show" style="z-index: 1059;"></div> <!-- Fundo escuro -->
    <div class="modal fade show" id="primeiroAcessoModalAuditor" tabindex="-1" aria-labelledby="primeiroAcessoModalLabel" aria-modal="true" role="dialog" style="display: block; z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="primeiroAcessoModalLabel">
                        <i class="fas fa-shield-alt me-2"></i>Bem-vindo(a)! Defina sua Senha
                    </h5>
                    <!-- Sem botão de fechar (força redefinição) -->
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3">Este é seu primeiro acesso. Por segurança, por favor, crie uma senha pessoal.</p>
                    <!-- Formulário de redefinição (ação será tratada por AJAX) -->
                    <form id="formRedefinirSenhaAuditor" novalidate>
                        <input type="hidden" name="csrf_token_modal_senha" value="<?= htmlspecialchars($csrf_token_modal) ?>">
                        <div class="mb-3">
                            <label for="nova_senha_modal_auditor" class="form-label form-label-sm">Nova Senha</label>
                            <input type="password" class="form-control form-control-sm" id="nova_senha_modal_auditor" name="nova_senha" required minlength="8" aria-describedby="novaSenhaHelpAuditor">
                            <div id="novaSenhaHelpAuditor" class="form-text small">Mínimo 8 caracteres, com maiúscula, minúscula e número.</div>
                            <div class="invalid-feedback small" id="invalid-feedback-nova-senha">Senha inválida. Verifique os requisitos.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmar_senha_modal_auditor" class="form-label form-label-sm">Confirmar Nova Senha</label>
                            <input type="password" class="form-control form-control-sm" id="confirmar_senha_modal_auditor" name="confirmar_senha" required>
                            <div class="invalid-feedback small" id="invalid-feedback-confirmar-senha">As senhas não coincidem.</div>
                        </div>
                        <!-- Div para mensagens de erro/sucesso do AJAX -->
                        <div id="senha_error_auditor" class="alert alert-danger alert-sm mt-3 p-2 small" style="display: none;"></div>
                        <div id="senha_sucesso_auditor" class="alert alert-success alert-sm mt-3 p-2 small" style="display: none;"></div>
                        <button type="submit" class="btn btn-primary w-100 mt-3" id="btnRedefinirSenhaAuditor">
                            <i class="fas fa-save me-1"></i> Definir Nova Senha
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php /* Script JS para o modal de primeiro acesso (colocado aqui para carregar apenas quando necessário) */ ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const formSenha = document.getElementById('formRedefinirSenhaAuditor');
        const novaSenhaInput = document.getElementById('nova_senha_modal_auditor');
        const confirmarSenhaInput = document.getElementById('confirmar_senha_modal_auditor');
        const erroDiv = document.getElementById('senha_error_auditor');
        const sucessoDiv = document.getElementById('senha_sucesso_auditor');
        const btnSubmit = document.getElementById('btnRedefinirSenhaAuditor');
        const csrfInput = formSenha.querySelector('input[name="csrf_token_modal_senha"]');

        // Função de Validação de Senha Forte (exemplo)
        function isSenhaForte(senha) {
            if (senha.length < 8) return false;
            if (!/[a-z]/.test(senha)) return false; // Pelo menos uma minúscula
            if (!/[A-Z]/.test(senha)) return false; // Pelo menos uma maiúscula
            if (!/[0-9]/.test(senha)) return false; // Pelo menos um número
            // if (!/[!@#$%^&*(),.?":{}|<>]/.test(senha)) return false; // Opcional: caractere especial
            return true;
        }

        // Validação em tempo real (opcional, mas bom para UX)
        novaSenhaInput.addEventListener('input', function() {
             novaSenhaInput.classList.remove('is-invalid'); // Limpa erro ao digitar
             document.getElementById('invalid-feedback-nova-senha').style.display = 'none';
            if (confirmarSenhaInput.value !== '' && novaSenhaInput.value !== confirmarSenhaInput.value) {
                confirmarSenhaInput.classList.add('is-invalid');
                document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block';
            } else {
                 confirmarSenhaInput.classList.remove('is-invalid');
                  document.getElementById('invalid-feedback-confirmar-senha').style.display = 'none';
            }
        });
        confirmarSenhaInput.addEventListener('input', function() {
             confirmarSenhaInput.classList.remove('is-invalid');
             document.getElementById('invalid-feedback-confirmar-senha').style.display = 'none';
             if (novaSenhaInput.value !== confirmarSenhaInput.value) {
                confirmarSenhaInput.classList.add('is-invalid');
                document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block';
             }
        });


        formSenha.addEventListener('submit', async function(event) {
            event.preventDefault();
            erroDiv.style.display = 'none';
            erroDiv.textContent = '';
            sucessoDiv.style.display = 'none';
            sucessoDiv.textContent = '';
            novaSenhaInput.classList.remove('is-invalid');
            confirmarSenhaInput.classList.remove('is-invalid');
             document.getElementById('invalid-feedback-nova-senha').style.display = 'none';
             document.getElementById('invalid-feedback-confirmar-senha').style.display = 'none';
            let formValido = true;

            // Validação Frontend
            const novaSenha = novaSenhaInput.value;
            const confirmarSenha = confirmarSenhaInput.value;

            if (!isSenhaForte(novaSenha)) {
                novaSenhaInput.classList.add('is-invalid');
                document.getElementById('invalid-feedback-nova-senha').textContent = 'Senha inválida. Verifique os requisitos.';
                document.getElementById('invalid-feedback-nova-senha').style.display = 'block';
                formValido = false;
            }
            if (novaSenha !== confirmarSenha) {
                confirmarSenhaInput.classList.add('is-invalid');
                 document.getElementById('invalid-feedback-confirmar-senha').textContent = 'As senhas não coincidem.';
                document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block';
                formValido = false;
            }
            if (!confirmarSenha) { // Se a confirmação estiver vazia
                 confirmarSenhaInput.classList.add('is-invalid');
                 document.getElementById('invalid-feedback-confirmar-senha').textContent = 'Confirme a nova senha.';
                 document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block';
                formValido = false;
            }

            if (!formValido) { return; } // Para se a validação JS falhar

            // Desabilitar botão e mostrar loading
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Redefinindo...';

            // Enviar dados via AJAX para um handler específico
            try {
                const formData = new FormData(formSenha);
                formData.append('action', 'redefinir_senha_primeiro_acesso'); // Ação específica

                // *** IMPORTANTE: Ajuste a URL do handler AJAX ***
                const response = await fetch('<?= BASE_URL ?>ajax_handler_senha.php', { // Handler geral ou específico
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    sucessoDiv.textContent = 'Senha redefinida com sucesso! A página será recarregada.';
                    sucessoDiv.style.display = 'block';
                    // Atualizar o token da página principal se o ajax handler retornar um novo
                    if (data.novo_csrf && document.getElementById("csrf_token_page_input")) {
                        document.getElementById("csrf_token_page_input").value = data.novo_csrf;
                    }
                    // Remover o backdrop e esconder o modal manualmente, depois recarregar
                    const backdrop = document.getElementById('bloqueio-conteudo-auditor');
                    if(backdrop) backdrop.remove();
                    const modal = document.getElementById('primeiroAcessoModalAuditor');
                    if(modal) modal.style.display = 'none'; // Esconde o modal

                    setTimeout(() => { window.location.reload(); }, 2000); // Recarrega a página após 2 segundos
                } else {
                    throw new Error(data.message || 'Erro desconhecido ao redefinir senha.');
                }

            } catch (error) {
                console.error('Erro ao redefinir senha:', error);
                erroDiv.textContent = `Erro: ${error.message}`;
                erroDiv.style.display = 'block';
                // Reabilitar botão
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-save me-1"></i> Definir Nova Senha';
                 // Atualizar token mesmo com erro, se disponível na resposta
                 try {
                    const errorData = await error.response?.json(); // Tenta pegar dados do erro
                     if (errorData && errorData.novo_csrf && document.getElementById("csrf_token_page_input")) {
                         document.getElementById("csrf_token_page_input").value = errorData.novo_csrf;
                     }
                 } catch (e) {/* Ignora erro ao parsear erro */}
            }
        });
    });
    </script>

<?php /* ----- Conteúdo Normal do Dashboard (Escondido se for primeiro acesso) ----- */ ?>
<?php else: ?>
    <style> /* CSS específico do Dashboard do Auditor */
        .stat-card { transition: transform 0.2s ease-in-out; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .card-body { display: flex; flex-direction: column; justify-content: space-between; }
        .stat-card .stat-icon { font-size: 2rem; opacity: 0.6; }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 600; }
        .notification-list .list-group-item { border-left: 4px solid; padding-left: 1rem; }
        .notification-list .list-group-item-warning { border-left-color: var(--bs-warning); }
        .notification-list .list-group-item-danger { border-left-color: var(--bs-danger); }
        .notification-list .list-group-item-info { border-left-color: var(--bs-info); }
        .table-dashboard th { font-weight: 500; }
    </style>

    <div class="container-fluid px-md-4 py-4">

        <?php /* ==== CABEÇALHO DA PÁGINA ==== */ ?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap pb-3 border-bottom">
            <div class="mb-2 mb-md-0">
                <h1 class="h3 mb-0 fw-bold page-title"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard</h1>
                <p class="text-muted mb-0 small">Bem-vindo(a), <?= htmlspecialchars($nome_auditor) ?>!</p>
            </div>
            <a href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fas fa-list-alt me-1"></i> Ver Todas Auditorias
            </a>
        </div>
        <?php /* ==== FIM DO CABEÇALHO ==== */ ?>

        <?php /* Notificações (Alerts de Sucesso/Erro Pós-Ação) */ ?>
        <?php if ($sucesso_msg): ?><div class="alert alert-success auditor-alert d-flex align-items-center alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><div><?= htmlspecialchars($sucesso_msg) ?></div><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($erro_msg): ?><div class="alert alert-danger auditor-alert d-flex align-items-center alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><div><?= htmlspecialchars($erro_msg) ?></div><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <?php /* Cards de Stats */ ?>
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <a href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php?filtro_status=Planejada" class="card text-decoration-none shadow-sm stat-card h-100 border-start border-primary border-4">
                    <div class="card-body"> <div class="d-flex align-items-center justify-content-between mb-2"> <div> <h6 class="card-subtitle text-muted text-uppercase small mb-1">Para Iniciar</h6> <span class="stat-value text-primary"><?= $cont_pendentes_iniciar ?></span> </div> <i class="fas fa-play-circle stat-icon text-primary"></i> </div> <span class="small text-primary">Ver Lista</span> </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php?filtro_status=Em+Andamento" class="card text-decoration-none shadow-sm stat-card h-100 border-start border-info border-4">
                     <div class="card-body"> <div class="d-flex align-items-center justify-content-between mb-2"> <div> <h6 class="card-subtitle text-muted text-uppercase small mb-1">Em Andamento</h6> <span class="stat-value text-info"><?= $cont_em_andamento ?></span> </div> <i class="fas fa-tasks stat-icon text-info"></i> </div> <span class="small text-info">Ver Lista</span> </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php?filtro_status=<?= urlencode('Concluída (Auditor)') ?>" class="card text-decoration-none shadow-sm stat-card h-100 border-start border-warning border-4">
                    <div class="card-body"> <div class="d-flex align-items-center justify-content-between mb-2"> <div> <h6 class="card-subtitle text-muted text-uppercase small mb-1">Aguard. Revisão</h6> <span class="stat-value text-warning"><?= $cont_aguardando_revisao ?></span> </div> <i class="fas fa-user-clock stat-icon text-warning"></i> </div> <span class="small text-warning">Ver Lista</span> </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php?filtro_status=Rejeitada" class="card text-decoration-none shadow-sm stat-card h-100 border-start border-danger border-4">
                    <div class="card-body"> <div class="d-flex align-items-center justify-content-between mb-2"> <div> <h6 class="card-subtitle text-muted text-uppercase small mb-1">Correção Solicitada</h6> <span class="stat-value text-danger"><?= $cont_solicitado_correcao ?></span> </div> <i class="fas fa-exclamation-triangle stat-icon text-danger"></i> </div> <span class="small text-danger">Ver Lista</span> </div>
                </a>
            </div>
        </div>

        <?php /* Linha com Notificações e Auditorias para Agir */ ?>
        <div class="row g-4">
             <div class="col-lg-5">
                <div class="card shadow-sm mb-4 h-100">
                    <div class="card-header bg-light d-flex align-items-center"> <h6 class="mb-0 fw-bold"><i class="fas fa-bell me-2 text-warning"></i>Alertas e Notificações</h6> <?php if(count($notificacoes) > 0): ?> <span class="badge bg-danger ms-auto rounded-pill"><?= count($notificacoes) ?></span> <?php endif; ?> </div>
                    <div class="card-body p-2 notification-list" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($notificacoes)): ?> <p class="text-muted text-center small p-3 mb-0">Nenhum alerta.</p>
                        <?php else: ?> <ul class="list-group list-group-flush">
                             <?php foreach($notificacoes as $notif): $icon = 'fa-info-circle text-info'; $border_class = 'list-group-item-info'; if($notif['tipo'] === 'prazo') { $icon = 'fa-clock text-warning'; $border_class = 'list-group-item-warning';} elseif($notif['tipo'] === 'vencido') { $icon = 'fa-calendar-times text-danger'; $border_class = 'list-group-item-danger';} elseif($notif['tipo'] === 'correcao') { $icon = 'fa-exclamation-triangle text-danger'; $border_class = 'list-group-item-danger';} $link_auditoria = BASE_URL . 'auditor/' . ($notif['tipo'] === 'correcao' ? 'executar_auditoria.php' : 'minhas_auditorias_auditor.php') . '?id=' . $notif['auditoria_id']; ?>
                            <a href="<?= htmlspecialchars($link_auditoria) ?>" class="list-group-item list-group-item-action d-flex align-items-start py-2 <?= $border_class ?>" title="Ver auditoria: <?= htmlspecialchars($notif['titulo']) ?>">
                                <i class="fas <?= $icon ?> fa-fw me-2 mt-1"></i>
                                <div class="flex-grow-1"> <p class="mb-0 small fw-medium"><?= htmlspecialchars($notif['mensagem']) ?></p> <small class="text-muted d-block text-truncate">Auditoria: <?= htmlspecialchars($notif['titulo']) ?></small> <?php if($notif['tipo'] === 'correcao' && !empty($notif['detalhes'])): ?> <small class="text-danger d-block mt-1 fst-italic text-truncate" title="<?= htmlspecialchars($notif['detalhes']) ?>">Motivo: <?= htmlspecialchars(mb_strimwidth($notif['detalhes'], 0, 100, "...")) ?></small> <?php endif; ?> </div>
                            </a>
                            <?php endforeach; ?> </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
             <div class="col-lg-7">
                <div class="card shadow-sm mb-4 h-100">
                     <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-forward me-2 text-primary"></i>Suas Próximas Ações</h6></div>
                    <div class="card-body p-2">
                        <?php if (empty($auditorias_para_agir)): ?> <p class="text-muted text-center small p-3 mb-0">Nenhuma auditoria pendente.</p>
                        <?php else: ?> <div class="table-responsive"> <table class="table table-sm table-hover small table-dashboard mb-0 align-middle">
                             <thead class="table-light"> <tr><th>Auditoria</th><th class="text-center">Status</th><th class="text-center">Início Plan.</th><th class="text-center">Ação</th></tr> </thead>
                             <tbody> <?php foreach($auditorias_para_agir as $aud_agir): $acao_link = BASE_URL . 'auditor/executar_auditoria.php?id=' . $aud_agir['id']; $acao_texto = 'Responder'; $acao_icon = 'fa-arrow-right'; $acao_class = 'btn-primary'; if($aud_agir['status'] === 'Planejada'){$acao_texto='Iniciar'; $acao_icon='fa-play'; $acao_class='btn-success'; /* Idealmente POST p/ iniciar */} ?>
                                <tr> <td class="text-truncate" title="<?= htmlspecialchars($aud_agir['titulo']) ?>"><?= htmlspecialchars(mb_strimwidth($aud_agir['titulo'], 0, 50, "...")) ?></td> <td class="text-center"><span class="badge rounded-pill <?= $aud_agir['status'] === 'Planejada' ? 'bg-light text-dark border' : 'bg-primary' ?> x-small"><?= htmlspecialchars($aud_agir['status']) ?></span></td> <td class="text-center text-nowrap"><?= formatarDataSimples($aud_agir['data_inicio_planejada'], 'd/m/y') ?></td> <td class="text-center"> <a href="<?= $acao_link ?>" class="btn btn-<?= $acao_class ?> btn-xs rounded-pill px-2 py-0"> <i class="fas <?= $acao_icon ?> fa-xs"></i> <?= $acao_texto ?> </a> </td> </tr>
                             <?php endforeach; ?> </tbody>
                        </table> </div>
                        <?php endif; ?>
                    </div>
                    <?php if(count($auditorias_para_agir) >= 5): ?> <div class="card-footer bg-light text-center py-1 border-top-0"> <a href="<?= BASE_URL ?>auditor/minhas_auditorias_auditor.php?filtro_status=pendentes" class="btn btn-link btn-sm text-decoration-none">Ver todas pendentes <i class="fas fa-angle-right fa-xs"></i></a> </div> <?php endif; ?>
                </div>
            </div>
        </div>

        <?php /* Linha Auditorias Recentes */ ?>
        <div class="row"> <div class="col-12"> <div class="card shadow-sm mb-4">
            <div class="card-header bg-light"><h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-secondary"></i>Concluídas/Decididas Recentemente</h6></div>
            <div class="card-body p-2">
                  <?php if (empty($recentes_concluidas)): ?> <p class="text-muted text-center small p-3 mb-0">Nenhuma recente.</p>
                  <?php else: ?> <div class="table-responsive"> <table class="table table-sm table-hover small table-dashboard mb-0 align-middle">
                       <thead class="table-light"> <tr><th>Auditoria</th><th class="text-center">Status Final</th><th class="text-center">Data Evento</th><th class="text-center">Ação</th></tr></thead>
                       <tbody> <?php foreach($recentes_concluidas as $aud_rec): $status_display = htmlspecialchars($aud_rec['status']); $badge_class = 'bg-secondary'; if($aud_rec['status'] === 'Concluída (Auditor)') { $badge_class='bg-warning text-dark'; } elseif($aud_rec['status'] === 'Aprovada') { $badge_class='bg-success'; } elseif($aud_rec['status'] === 'Rejeitada') { $badge_class='bg-danger'; } ?>
                           <tr> <td class="text-truncate" title="<?= htmlspecialchars($aud_rec['titulo']) ?>"><?= htmlspecialchars(mb_strimwidth($aud_rec['titulo'], 0, 70, "...")) ?></td> <td class="text-center"><span class="badge rounded-pill <?= $badge_class ?> x-small"><?= $status_display ?></span></td> <td class="text-center text-nowrap"><?= formatarDataCompleta($aud_rec['data_evento_recente'], 'd/m/y H:i') ?></td> <td class="text-center"> <a href="<?= BASE_URL ?>auditor/detalhes_auditoria_readonly.php?id=<?= $aud_rec['id'] ?>" class="btn btn-outline-primary btn-xs rounded-pill px-2 py-0"> <i class="fas fa-eye fa-xs"></i> Detalhes </a> </td> </tr>
                       <?php endforeach; ?> </tbody>
                  </table> </div>
                 <?php endif; ?>
             </div>
         </div> </div>
        </div>

    <?php /* Fechamento da div .container-fluid */ ?>
    </div>
<?php endif; /* Fim do else (não é primeiro acesso) */ ?>

<?php
// Chama o footer do auditor
echo getFooterAuditor();
?>

<?php /* ----- Script JS para o Modal de Primeiro Acesso (Só imprime se necessário) ----- */ ?>
<?php if ($primeiro_acesso_auditor): ?>
<script>
// Script JS do modal de primeiro acesso (idêntico ao do admin, mas com IDs específicos _auditor)
document.addEventListener('DOMContentLoaded', function() { /* ... (Código JS completo para o modal #primeiroAcessoModalAuditor) ... */
    const formSenha = document.getElementById('formRedefinirSenhaAuditor');
    const novaSenhaInput = document.getElementById('nova_senha_modal_auditor');
    const confirmarSenhaInput = document.getElementById('confirmar_senha_modal_auditor');
    const erroDiv = document.getElementById('senha_error_auditor');
    const sucessoDiv = document.getElementById('senha_sucesso_auditor');
    const btnSubmit = document.getElementById('btnRedefinirSenhaAuditor');
    const csrfInput = formSenha.querySelector('input[name="csrf_token_modal_senha"]'); // Pegar o token DENTRO do modal

    function isSenhaForte(senha) {
        if (senha.length < 8) return false; if (!/[a-z]/.test(senha)) return false;
        if (!/[A-Z]/.test(senha)) return false; if (!/[0-9]/.test(senha)) return false; return true;
    }
    novaSenhaInput.addEventListener('input', function() { novaSenhaInput.classList.remove('is-invalid'); document.getElementById('invalid-feedback-nova-senha').style.display = 'none'; if (confirmarSenhaInput.value !== '' && novaSenhaInput.value !== confirmarSenhaInput.value) { confirmarSenhaInput.classList.add('is-invalid'); document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block'; } else { confirmarSenhaInput.classList.remove('is-invalid'); document.getElementById('invalid-feedback-confirmar-senha').style.display = 'none'; } });
    confirmarSenhaInput.addEventListener('input', function() { confirmarSenhaInput.classList.remove('is-invalid'); document.getElementById('invalid-feedback-confirmar-senha').style.display = 'none'; if (novaSenhaInput.value !== confirmarSenhaInput.value) { confirmarSenhaInput.classList.add('is-invalid'); document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block'; } });

    formSenha.addEventListener('submit', async function(event) {
        event.preventDefault(); erroDiv.style.display = 'none'; sucessoDiv.style.display = 'none';
        novaSenhaInput.classList.remove('is-invalid'); confirmarSenhaInput.classList.remove('is-invalid');
        document.getElementById('invalid-feedback-nova-senha').style.display = 'none'; document.getElementById('invalid-feedback-confirmar-senha').style.display = 'none';
        let formValido = true; const novaSenha = novaSenhaInput.value; const confirmarSenha = confirmarSenhaInput.value;
        if (!isSenhaForte(novaSenha)) { novaSenhaInput.classList.add('is-invalid'); document.getElementById('invalid-feedback-nova-senha').textContent = 'Inválida (Ver requisitos).'; document.getElementById('invalid-feedback-nova-senha').style.display = 'block'; formValido = false; }
        if (novaSenha !== confirmarSenha) { confirmarSenhaInput.classList.add('is-invalid'); document.getElementById('invalid-feedback-confirmar-senha').textContent = 'Senhas não coincidem.'; document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block'; formValido = false; }
        if (!confirmarSenha) { confirmarSenhaInput.classList.add('is-invalid'); document.getElementById('invalid-feedback-confirmar-senha').textContent = 'Confirme a senha.'; document.getElementById('invalid-feedback-confirmar-senha').style.display = 'block'; formValido = false; }
        if (!formValido) { return; }
        btnSubmit.disabled = true; btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Redefinindo...';
        try {
            const formData = new FormData(formSenha); formData.append('action', 'redefinir_senha_primeiro_acesso');
            const response = await fetch('<?= BASE_URL ?>ajax_handler_senha.php', { method: 'POST', body: formData });
            if (!response.ok) { const errTxt = await response.text(); throw new Error(`Erro ${response.status}: ${errTxt || response.statusText}`); }
            const data = await response.json();
            if (data.success) {
                sucessoDiv.textContent = 'Senha redefinida! A página será recarregada.'; sucessoDiv.style.display = 'block';
                const backdrop = document.getElementById('bloqueio-conteudo-auditor'); if(backdrop) backdrop.remove();
                const modal = document.getElementById('primeiroAcessoModalAuditor'); if(modal) modal.style.display = 'none';
                setTimeout(() => { window.location.reload(); }, 1500);
            } else { throw new Error(data.message || 'Erro desconhecido.'); }
        } catch (error) { console.error('Erro redefinir senha:', error); erroDiv.textContent = `Erro: ${error.message}`; erroDiv.style.display = 'block'; btnSubmit.disabled = false; btnSubmit.innerHTML = '<i class="fas fa-save me-1"></i> Definir Nova Senha';}
    });
});
</script>
<?php endif; ?>