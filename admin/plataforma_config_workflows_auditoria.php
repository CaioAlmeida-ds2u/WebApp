<?php
// admin/plataforma_config_workflows_auditoria.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções para salvar/carregar configs de workflow

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Carregar Configurações de Workflow Atuais ---
// Isso viria de uma tabela de configurações globais ou uma tabela específica para workflows.
// $configWorkflowAtual = getWorkflowConfigGlobal($conexao); // Função a ser criada
// Simulação dos dados que seriam carregados:
$configWorkflowAtual = [
    'status_disponiveis' => ['Planejada', 'Em Andamento', 'Pausada', 'Concluída (Auditor)', 'Em Revisão', 'Aprovada', 'Rejeitada', 'Cancelada', 'Aguardando Correção Auditor'], // Poderia vir do ENUM do DB
    'regras_transicao' => [ // Exemplo de como poderíamos armazenar (JSON no DB)
        'Concluída (Auditor)' => [
            'proximo_status_gestor_aprova' => 'Aprovada',
            'proximo_status_gestor_rejeita' => 'Rejeitada',
            'proximo_status_gestor_pede_correcao' => 'Aguardando Correção Auditor', // Novo status
            'notificar_gestor_para_revisao' => true,
            'prazo_dias_revisao_gestor' => 7, // Opcional
        ],
        'Aguardando Correção Auditor' => [
            'proximo_status_auditor_corrige' => 'Concluída (Auditor)', // Volta para revisão do gestor
            'notificar_auditor_para_correcao' => true
        ],
        'Aprovada' => [
            'requer_plano_acao_para_nc' => true, // Se aprovada com NC, obriga plano de ação
            'notificar_responsavel_plano_acao' => true
        ]
    ],
    'templates_notificacao' => [ // Exemplo (TEXT no DB)
        'auditoria_atribuida_auditor' => "Olá {NOME_AUDITOR},\n\Uma nova auditoria '{TITULO_AUDITORIA}' foi atribuída a você.\nPrazo: {PRAZO_FIM}\nLink: {LINK_AUDITORIA}",
        'auditoria_pronta_revisao_gestor' => "Prezado(a) {NOME_GESTOR},\n\A auditoria '{TITULO_AUDITORIA}' (ID: {ID_AUDITORIA}) foi concluída pelo auditor e está pronta para sua revisão.\nLink: {LINK_REVISAO}",
        'plano_acao_atrasado_responsavel' => "Atenção {NOME_RESPONSAVEL_PA},\n\O plano de ação '{DESCRICAO_PA}' da auditoria '{TITULO_AUDITORIA_PA}' está atrasado.\nPrazo: {PRAZO_PA}\nLink: {LINK_PLANO_ACAO}"
    ]
];


// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera

        // Coletar os dados do formulário
        // A estrutura exata dependerá de como você montar o form,
        // mas a ideia é salvar as `regras_transicao` e `templates_notificacao`
        // provavelmente como JSONs no banco de dados.

        $novasConfigWorkflow = [
            'regras_transicao' => $_POST['regras_transicao'] ?? [], // Espera um array estruturado
            'templates_notificacao' => $_POST['templates_notificacao'] ?? [] // Array de templates
        ];

        // Validações podem ser complexas aqui, dependendo da estrutura
        // Por exemplo, verificar se os status referenciados em 'proximo_status' existem.

        // *** Chamar função de backend: salvarWorkflowConfigGlobal($conexao, $novasConfigWorkflow, $_SESSION['usuario_id']) ***
        if (salvarWorkflowConfigGlobal($conexao, $novasConfigWorkflow, $_SESSION['usuario_id'])) { // Função a ser criada
            definir_flash_message('sucesso', 'Configurações de workflow da plataforma atualizadas.');
            $configWorkflowAtual = $novasConfigWorkflow; // Atualiza para exibição
        } else {
            definir_flash_message('erro', 'Erro ao salvar as configurações de workflow.');
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$csrf_token_page = $_SESSION['csrf_token'];

$title = "ACodITools - Configurar Workflows de Auditoria da Plataforma";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-project-diagram me-2"></i>Configurar Workflows Padrão de Auditoria</h1>
        <small class="text-muted">Defina regras de transição de status e modelos de notificação para as auditorias na plataforma.</small>
    </div>

    <?php if ($sucesso_msg): /* ... Mensagens ... */ endif; ?>
    <?php if ($erro_msg): /* ... Mensagens ... */ endif; ?>

    <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="configWorkflowForm" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">

        <div class="alert alert-info small mb-4">
            <i class="fas fa-info-circle me-1"></i>
            Esta seção permite configurar o comportamento padrão do fluxo de auditoria para todas as empresas clientes.
            Modificações aqui podem impactar como as auditorias progridem e como os usuários são notificados.
        </div>

        <!-- Seção de Regras de Transição de Status -->
        <div class="card shadow-sm mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-exchange-alt me-2 text-primary opacity-75"></i>Regras de Transição entre Status de Auditoria</h6>
            </div>
            <div class="card-body p-3">
                <p class="small text-muted mb-3">Para cada status principal, defina os próximos status possíveis e outras regras associadas.</p>
                <?php
                // Os status viriam do ENUM da tabela 'auditorias' ou de uma configuração
                $todosStatusSistema = $configWorkflowAtual['status_disponiveis'] ?? [];
                $regrasSalvas = $configWorkflowAtual['regras_transicao'] ?? [];

                // Exemplo para o status 'Concluída (Auditor)'
                $statusAtualConfig = 'Concluída (Auditor)';
                $regraAtual = $regrasSalvas[$statusAtualConfig] ?? [];
                ?>
                <fieldset class="mb-3 p-3 border rounded">
                    <legend class="h6 small fw-semibold">Quando uma Auditoria está "<?= htmlspecialchars($statusAtualConfig) ?>":</legend>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label for="regra_ca_gestor_aprova" class="form-label form-label-sm">Se Gestor Aprova, próximo status é:</label>
                            <select class="form-select form-select-sm" name="regras_transicao[<?= htmlspecialchars($statusAtualConfig) ?>][proximo_status_gestor_aprova]" id="regra_ca_gestor_aprova">
                                <?php foreach($todosStatusSistema as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= ($regraAtual['proximo_status_gestor_aprova'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="regra_ca_gestor_rejeita" class="form-label form-label-sm">Se Gestor Rejeita, próximo status é:</label>
                            <select class="form-select form-select-sm" name="regras_transicao[<?= htmlspecialchars($statusAtualConfig) ?>][proximo_status_gestor_rejeita]" id="regra_ca_gestor_rejeita">
                                <?php foreach($todosStatusSistema as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= ($regraAtual['proximo_status_gestor_rejeita'] ?? 'Rejeitada') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                             <label for="regra_ca_gestor_pede_correcao" class="form-label form-label-sm">Se Gestor Solicita Correção, status é:</label>
                             <select class="form-select form-select-sm" name="regras_transicao[<?= htmlspecialchars($statusAtualConfig) ?>][proximo_status_gestor_pede_correcao]" id="regra_ca_gestor_pede_correcao">
                                 <option value="">-- Não Aplicar --</option>
                                <?php foreach($todosStatusSistema as $s): ?>
                                 <option value="<?= htmlspecialchars($s) ?>" <?= ($regraAtual['proximo_status_gestor_pede_correcao'] ?? 'Aguardando Correção Auditor') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                        <div class="col-md-6 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="regra_ca_notificar_gestor" name="regras_transicao[<?= htmlspecialchars($statusAtualConfig) ?>][notificar_gestor_para_revisao]" value="1" <?= !empty($regraAtual['notificar_gestor_para_revisao']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="regra_ca_notificar_gestor">Notificar Gestor para revisão?</label>
                            </div>
                        </div>
                         <div class="col-md-6">
                            <label for="regra_ca_prazo_revisao" class="form-label form-label-sm">Prazo (dias) para revisão do Gestor (Opcional):</label>
                            <input type="number" class="form-control form-control-sm" name="regras_transicao[<?= htmlspecialchars($statusAtualConfig) ?>][prazo_dias_revisao_gestor]" id="regra_ca_prazo_revisao" value="<?= htmlspecialchars($regraAtual['prazo_dias_revisao_gestor'] ?? '') ?>" min="0" placeholder="Ex: 7">
                        </div>
                    </div>
                </fieldset>

                <?php
                // Exemplo para o status 'Aprovada'
                $statusAtualConfig2 = 'Aprovada';
                $regraAtual2 = $regrasSalvas[$statusAtualConfig2] ?? [];
                ?>
                 <fieldset class="mt-3 p-3 border rounded">
                    <legend class="h6 small fw-semibold">Quando uma Auditoria é "<?= htmlspecialchars($statusAtualConfig2) ?>":</legend>
                     <div class="row g-3 align-items-center">
                         <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="regra_ap_requer_pa" name="regras_transicao[<?= htmlspecialchars($statusAtualConfig2) ?>][requer_plano_acao_para_nc]" value="1" <?= !empty($regraAtual2['requer_plano_acao_para_nc']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="regra_ap_requer_pa">Obrigar criação de Plano de Ação se houver NCs/Parciais?</label>
                            </div>
                        </div>
                         <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="regra_ap_notificar_resp_pa" name="regras_transicao[<?= htmlspecialchars($statusAtualConfig2) ?>][notificar_responsavel_plano_acao]" value="1" <?= !empty($regraAtual2['notificar_responsavel_plano_acao']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="regra_ap_notificar_resp_pa">Notificar responsável ao criar Plano de Ação?</label>
                            </div>
                        </div>
                    </div>
                </fieldset>
                
                <?php /* Adicionar fieldsets para OUTROS status relevantes (ex: 'Aguardando Correção Auditor') */ ?>
            </div>
        </div>

        <!-- Seção de Templates de Notificação -->
        <div class="card shadow-sm mb-4 rounded-3 border-0">
            <div class="card-header bg-light border-bottom pt-3 pb-2">
                <h6 class="mb-0 fw-bold"><i class="fas fa-envelope-open-text me-2 text-primary opacity-75"></i>Modelos de Notificação por E-mail</h6>
            </div>
            <div class="card-body p-3">
                <p class="small text-muted mb-3">Edite os textos padrão para os e-mails automáticos enviados pela plataforma. Use placeholders como <code class="small">{NOME_USUARIO}</code>, <code class="small">{TITULO_AUDITORIA}</code>, <code class="small">{LINK_SISTEMA}</code>, etc. (A lista completa de placeholders deve ser documentada).</p>

                <?php
                $templatesSalvos = $configWorkflowAtual['templates_notificacao'] ?? [];
                $templateExemploChave = 'auditoria_atribuida_auditor';
                $templateExemploConteudo = $templatesSalvos[$templateExemploChave] ?? "Olá {NOME_AUDITOR},\n\nUma nova auditoria '{TITULO_AUDITORIA}' foi atribuída a você.\nPrazo de Conclusão: {PRAZO_FIM_PLANEJADO}\n\nAcesse a plataforma para mais detalhes: {LINK_AUDITORIA}\n\nAtenciosamente,\nEquipe AcodITools";
                ?>
                <div class="mb-3">
                    <label for="template_<?= $templateExemploChave ?>" class="form-label form-label-sm fw-semibold">Auditoria Atribuída ao Auditor:</label>
                    <textarea class="form-control form-control-sm" name="templates_notificacao[<?= $templateExemploChave ?>]" id="template_<?= $templateExemploChave ?>" rows="5"><?= htmlspecialchars($templateExemploConteudo) ?></textarea>
                </div>

                <?php
                $templateExemploChave2 = 'auditoria_pronta_revisao_gestor';
                $templateExemploConteudo2 = $templatesSalvos[$templateExemploChave2] ?? "Prezado(a) {NOME_GESTOR},\n\nA auditoria '{TITULO_AUDITORIA}' (ID: {ID_AUDITORIA}) foi concluída pelo auditor {NOME_AUDITOR_RESPONSAVEL} e está pronta para sua revisão.\n\nPor favor, acesse o sistema para analisar os resultados e tomar as ações necessárias: {LINK_REVISAO_GESTOR}\n\nObrigado(a),\nPlataforma AcodITools";
                ?>
                <div class="mb-3">
                    <label for="template_<?= $templateExemploChave2 ?>" class="form-label form-label-sm fw-semibold">Auditoria Pronta para Revisão do Gestor:</label>
                    <textarea class="form-control form-control-sm" name="templates_notificacao[<?= $templateExemploChave2 ?>]" id="template_<?= $templateExemploChave2 ?>" rows="5"><?= htmlspecialchars($templateExemploConteudo2) ?></textarea>
                </div>

                <?php /* Adicionar mais textareas para outros templates importantes */ ?>
            </div>
        </div>


        <div class="mt-4 mb-5 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary rounded-pill px-4 action-button-main">
                <i class="fas fa-save me-1"></i> Salvar Configurações de Workflow
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação Bootstrap
    const form = document.getElementById('configWorkflowForm');
    if (form) {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php
echo getFooterAdmin();
?>