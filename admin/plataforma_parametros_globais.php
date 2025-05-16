<?php
// admin/plataforma_parametros_globais.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções para salvar/carregar parâmetros

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { // Admin da Acoditools
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Carregar Parâmetros Globais Atuais ---
// Isso viria de uma tabela `plataforma_configuracoes_globais` onde cada parâmetro é uma linha,
// ou de um arquivo de configuração, ou de um campo JSON grande.
// Vamos supor uma função que retorna um array associativo:
// $parametrosAtuais = getParametrosGlobaisPlataforma($conexao); // Função a ser criada
// Simulação dos dados que seriam carregados (com valores default se não encontrados):
$parametrosAtuais = getConfigGlobalSerializado($conexao, 'parametros_gerais_plataforma', [
    'dias_alerta_prazo_pa' => 7, // Dias antes do vencimento do Plano de Ação para alertar
    'retencao_logs_acesso_meses' => 12, // Meses para manter logs de acesso
    'email_remetente_notificacoes' => 'naoresponda@acoditools.com',
    'nome_remetente_notificacoes' => 'Plataforma AcodITools',
    'max_upload_geral_mb' => defined('MAX_UPLOAD_SIZE_MB') ? MAX_UPLOAD_SIZE_MB : 10, // Pega da constante ou default
    'logo_empresa_obrigatorio_cadastro' => 0, // 0 = não, 1 = sim
    'permitir_multiplas_sessoes_usuario' => 1,
    'texto_rodape_emails_padrao' => "Esta é uma mensagem automática da plataforma AcodITools. Por favor, não responda diretamente a este e-mail.",
    'url_termos_servico_plataforma' => BASE_URL . 'termos.php',
    'url_politica_privacidade_plataforma' => BASE_URL . 'privacidade.php',
    'habilitar_modulo_suporte_tickets' => 0 // Se implementado
]);


// --- Lógica de Mensagens Flash ---
$sucesso_msg = obter_flash_message('sucesso');
$erro_msg = obter_flash_message('erro');

// --- Processamento do Formulário (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', 'Erro de validação da sessão.');
    } else {
        $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenera

        $novosParametros = [
            'dias_alerta_prazo_pa' => filter_input(INPUT_POST, 'dias_alerta_prazo_pa', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 7]]),
            'retencao_logs_acesso_meses' => filter_input(INPUT_POST, 'retencao_logs_acesso_meses', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 12]]),
            'email_remetente_notificacoes' => filter_input(INPUT_POST, 'email_remetente_notificacoes', FILTER_VALIDATE_EMAIL),
            'nome_remetente_notificacoes' => trim(filter_input(INPUT_POST, 'nome_remetente_notificacoes', FILTER_SANITIZE_SPECIAL_CHARS)),
            'max_upload_geral_mb' => filter_input(INPUT_POST, 'max_upload_geral_mb', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 10]]),
            'logo_empresa_obrigatorio_cadastro' => isset($_POST['logo_empresa_obrigatorio_cadastro']) ? 1 : 0,
            'permitir_multiplas_sessoes_usuario' => isset($_POST['permitir_multiplas_sessoes_usuario']) ? 1 : 0,
            'texto_rodape_emails_padrao' => trim($_POST['texto_rodape_emails_padrao'] ?? ''), // Usar trim e não filter_input para permitir HTML básico se necessário (cuidado com XSS)
            'url_termos_servico_plataforma' => filter_input(INPUT_POST, 'url_termos_servico_plataforma', FILTER_VALIDATE_URL),
            'url_politica_privacidade_plataforma' => filter_input(INPUT_POST, 'url_politica_privacidade_plataforma', FILTER_VALIDATE_URL),
            'habilitar_modulo_suporte_tickets' => isset($_POST['habilitar_modulo_suporte_tickets']) ? 1 : 0
        ];

        // Validações específicas
        $validation_errors_params = [];
        if ($novosParametros['email_remetente_notificacoes'] === false) {
            $validation_errors_params[] = "Formato do E-mail Remetente inválido.";
            $novosParametros['email_remetente_notificacoes'] = $parametrosAtuais['email_remetente_notificacoes']; // Mantém o antigo em caso de erro
        }
        if ($novosParametros['url_termos_servico_plataforma'] === false && !empty($_POST['url_termos_servico_plataforma'])) {
             $validation_errors_params[] = "URL dos Termos de Serviço inválida.";
             $novosParametros['url_termos_servico_plataforma'] = $parametrosAtuais['url_termos_servico_plataforma'];
        }
        if ($novosParametros['url_politica_privacidade_plataforma'] === false && !empty($_POST['url_politica_privacidade_plataforma'])) {
             $validation_errors_params[] = "URL da Política de Privacidade inválida.";
              $novosParametros['url_politica_privacidade_plataforma'] = $parametrosAtuais['url_politica_privacidade_plataforma'];
        }


        if (empty($validation_errors_params)) {
            // *** Chamar função de backend: salvarParametrosGlobaisPlataforma($conexao, $novosParametros, $_SESSION['usuario_id']) ***
            if (salvarConfigGlobalSerializado($conexao, 'parametros_gerais_plataforma', $novosParametros, $_SESSION['usuario_id'])) { // Função genérica a ser criada
                definir_flash_message('sucesso', 'Parâmetros gerais da plataforma atualizados com sucesso.');
                $parametrosAtuais = $novosParametros; // Atualiza para exibição
            } else {
                definir_flash_message('erro', 'Erro ao salvar os parâmetros gerais da plataforma.');
            }
        } else {
             definir_flash_message('erro', "<strong>Foram encontrados erros:</strong><ul><li>" . implode("</li><li>", $validation_errors_params) . "</li></ul>");
             // Mantém os dados que passaram na validação e os antigos para os que falharam
             $parametrosAtuais = array_merge($parametrosAtuais, array_filter($novosParametros, function($val) { return $val !== false; }));
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$csrf_token_page = $_SESSION['csrf_token'];

$title = "ACodITools - Parâmetros Gerais da Plataforma";
echo getHeaderAdmin($title);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-cogs me-2"></i>Parâmetros Gerais da Plataforma</h1>
        <small class="text-muted">Configurações globais que afetam o comportamento e as políticas da AcodITools.</small>
    </div>

    <?php if ($sucesso_msg): ?><div class="alert alert-success custom-alert fade show" role="alert"><?= $sucesso_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro_msg): ?><div class="alert alert-danger custom-alert fade show" role="alert"><?= $erro_msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" id="configParametrosForm" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_page) ?>">

        <div class="row">
            <div class="col-lg-8 mx-auto"> <?php /* Centraliza o conteúdo um pouco */ ?>

                <!-- Seção de Notificações e E-mails -->
                <div class="card shadow-sm mb-4 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-envelope me-2 text-primary opacity-75"></i>Configurações de E-mail e Notificações</h6></div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="email_remetente_notificacoes" class="form-label form-label-sm fw-semibold">E-mail Remetente Padrão <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm" id="email_remetente_notificacoes" name="email_remetente_notificacoes" value="<?= htmlspecialchars($parametrosAtuais['email_remetente_notificacoes']) ?>" required>
                                <div class="invalid-feedback">Formato de e-mail inválido.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="nome_remetente_notificacoes" class="form-label form-label-sm fw-semibold">Nome Remetente Padrão <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="nome_remetente_notificacoes" name="nome_remetente_notificacoes" value="<?= htmlspecialchars($parametrosAtuais['nome_remetente_notificacoes']) ?>" required>
                                <div class="invalid-feedback">Nome do remetente obrigatório.</div>
                            </div>
                            <div class="col-12">
                                 <label for="texto_rodape_emails_padrao" class="form-label form-label-sm fw-semibold">Texto Rodapé Padrão dos E-mails</label>
                                 <textarea class="form-control form-control-sm" id="texto_rodape_emails_padrao" name="texto_rodape_emails_padrao" rows="3"><?= htmlspecialchars($parametrosAtuais['texto_rodape_emails_padrao']) ?></textarea>
                                 <small class="form-text text-muted">Este texto será adicionado ao final de todos os e-mails transacionais enviados pela plataforma.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="dias_alerta_prazo_pa" class="form-label form-label-sm fw-semibold">Alerta de Prazo de Plano de Ação (dias antes)</label>
                                <input type="number" class="form-control form-control-sm" id="dias_alerta_prazo_pa" name="dias_alerta_prazo_pa" value="<?= htmlspecialchars($parametrosAtuais['dias_alerta_prazo_pa']) ?>" min="0">
                                <small class="form-text text-muted">0 para desativar alertas de proximidade de prazo.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção de Configurações de Arquivos e Segurança -->
                <div class="card shadow-sm mb-4 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-shield-alt me-2 text-primary opacity-75"></i>Uploads, Retenção e Segurança</h6></div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="max_upload_geral_mb" class="form-label form-label-sm fw-semibold">Tamanho Máx. Upload Geral (MB) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm" id="max_upload_geral_mb" name="max_upload_geral_mb" value="<?= htmlspecialchars($parametrosAtuais['max_upload_geral_mb']) ?>" min="1" required>
                                <div class="invalid-feedback">Defina um limite válido (ex: 10 para 10MB).</div>
                                <small class="form-text text-muted">Limite global para uploads (evidências, logos, etc.). Pode ser sobrescrito por configurações de plano.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="retencao_logs_acesso_meses" class="form-label form-label-sm fw-semibold">Retenção de Logs de Acesso (Meses) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm" id="retencao_logs_acesso_meses" name="retencao_logs_acesso_meses" value="<?= htmlspecialchars($parametrosAtuais['retencao_logs_acesso_meses']) ?>" min="1" required>
                                <small class="form-text text-muted">Período que os logs de acesso da plataforma serão mantidos.</small>
                                <div class="invalid-feedback">Defina um período de retenção.</div>
                            </div>
                             <div class="col-12 mt-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="logo_empresa_obrigatorio_cadastro" name="logo_empresa_obrigatorio_cadastro" value="1" <?= !empty($parametrosAtuais['logo_empresa_obrigatorio_cadastro']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="logo_empresa_obrigatorio_cadastro">Exigir upload de logo no cadastro de novas empresas clientes</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="permitir_multiplas_sessoes_usuario" name="permitir_multiplas_sessoes_usuario" value="1" <?= !empty($parametrosAtuais['permitir_multiplas_sessoes_usuario']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="permitir_multiplas_sessoes_usuario">Permitir que um mesmo usuário tenha múltiplas sessões ativas simultaneamente</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                 <!-- Seção de Módulos e Links Externos -->
                <div class="card shadow-sm mb-4 rounded-3 border-0">
                    <div class="card-header bg-light border-bottom pt-3 pb-2"><h6 class="mb-0 fw-bold"><i class="fas fa-puzzle-piece me-2 text-primary opacity-75"></i>Módulos e Políticas Externas</h6></div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                             <div class="col-md-6">
                                <label for="url_termos_servico_plataforma" class="form-label form-label-sm fw-semibold">URL dos Termos de Serviço</label>
                                <input type="url" class="form-control form-control-sm" id="url_termos_servico_plataforma" name="url_termos_servico_plataforma" value="<?= htmlspecialchars($parametrosAtuais['url_termos_servico_plataforma']) ?>" placeholder="https://seudominio.com/termos">
                                <div class="invalid-feedback">URL inválida.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="url_politica_privacidade_plataforma" class="form-label form-label-sm fw-semibold">URL da Política de Privacidade</label>
                                <input type="url" class="form-control form-control-sm" id="url_politica_privacidade_plataforma" name="url_politica_privacidade_plataforma" value="<?= htmlspecialchars($parametrosAtuais['url_politica_privacidade_plataforma']) ?>" placeholder="https://seudominio.com/privacidade">
                                <div class="invalid-feedback">URL inválida.</div>
                            </div>
                             <div class="col-12 mt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="habilitar_modulo_suporte_tickets" name="habilitar_modulo_suporte_tickets" value="1" <?= !empty($parametrosAtuais['habilitar_modulo_suporte_tickets']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="habilitar_modulo_suporte_tickets">Habilitar Módulo de Suporte via Tickets (se implementado)</label>
                                </div>
                                <?php /* Adicionar mais switches para outros módulos opcionais */ ?>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="mt-4 mb-5 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 action-button-main">
                        <i class="fas fa-save me-1"></i> Salvar Parâmetros Globais
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validação Bootstrap
    const form = document.getElementById('configParametrosForm');
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