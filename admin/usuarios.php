<?php
// admin/usuarios.php

require_once __DIR__ . '/../includes/config.php';        // Config, DB, CSRF Token, Base URL
require_once __DIR__ . '/../includes/layout_admin.php';   // Layout unificado (Header/Footer)
require_once __DIR__ . '/../includes/admin_functions.php'; // Funções admin

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: ' . BASE_URL . 'acesso_negado.php');
    exit;
}

// --- Lógica de Mensagens (Sucesso/Erro de outras páginas ou ações POST) ---
$sucesso_msg = $_SESSION['sucesso'] ?? null;
$erro_msg = $_SESSION['erro'] ?? null;
unset($_SESSION['sucesso'], $_SESSION['erro']); // Limpa após ler

// --- Processamento de Ações POST (Ativar/Desativar/Excluir/Aprovar/Rejeitar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Validar CSRF Token para TODAS as ações POST
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['erro'] = "Erro de validação da sessão. Ação não executada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'acao_usuario_csrf_falha', 0, 'Token CSRF inválido para ação: ' . $_POST['action'], $conexao);
        header('Location: ' . BASE_URL . 'admin/usuarios.php'); // Redireciona para evitar reenvio
        exit;
    }

    // Regenerar token após ação bem-sucedida (boa prática)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $action_id = $_POST['id'] ?? null;

    if (!$action_id || !is_numeric($action_id)) {
         $_SESSION['erro'] = "ID inválido para a ação.";
    } else {
        $action_id = (int)$action_id;
        $log_details = "ID: $action_id";

        switch ($_POST['action']) {
            case 'ativar_usuario':
                if (ativarUsuario($conexao, $action_id)) {
                    $_SESSION['sucesso'] = "Usuário ativado com sucesso!";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_usuario', 1, $log_details, $conexao);
                } else {
                    $_SESSION['erro'] = "Erro ao ativar o usuário.";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'ativar_usuario', 0, $log_details . ' (Falha DB)', $conexao);
                }
                break;

            case 'desativar_usuario':
                 if ($action_id == $_SESSION['usuario_id']) { // Prevenir auto-desativação
                    $_SESSION['erro'] = "Você não pode desativar sua própria conta.";
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_usuario', 0, $log_details . ' (Tentativa auto)', $conexao);
                } elseif (desativarUsuario($conexao, $action_id)) {
                    $_SESSION['sucesso'] = "Usuário desativado com sucesso!";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_usuario', 1, $log_details, $conexao);
                } else {
                    $_SESSION['erro'] = "Erro ao desativar o usuário.";
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'desativar_usuario', 0, $log_details . ' (Falha DB)', $conexao);
                }
                break;

            case 'excluir_usuario':
                 if ($action_id == $_SESSION['usuario_id']) {
                    $_SESSION['erro'] = "Você não pode excluir sua própria conta.";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_usuario', 0, $log_details . ' (Tentativa auto)', $conexao);
                } elseif (excluirUsuario($conexao, $action_id)) {
                     $_SESSION['sucesso'] = "Usuário excluído com sucesso!";
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_usuario', 1, $log_details, $conexao);
                 } else {
                     $_SESSION['erro'] = "Erro ao excluir o usuário. Verifique dependências.";
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'excluir_usuario', 0, $log_details . ' (Falha DB)', $conexao);
                 }
                 break;

            case 'aprovar_acesso':
                // Gerar senha temporária aqui, antes de chamar a função
                $senha_temporaria = bin2hex(random_bytes(4)); // 8 caracteres hex
                if (aprovarSolicitacaoAcesso($conexao, $action_id, $senha_temporaria)) {
                    // A senha temporária precisa ser exibida, talvez na mensagem de sucesso?
                     $_SESSION['sucesso'] = "Solicitação ID $action_id aprovada! Senha temporária: " . htmlspecialchars($senha_temporaria);
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'aprovar_acesso', 1, $log_details, $conexao);
                } else {
                    $_SESSION['erro'] = "Erro ao aprovar a solicitação de acesso ID $action_id.";
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'aprovar_acesso', 0, $log_details . ' (Falha DB)', $conexao);
                }
                break;

            case 'rejeitar_acesso':
                if (rejeitarSolicitacaoAcesso($conexao, $action_id)) {
                    $_SESSION['sucesso'] = "Solicitação de acesso ID $action_id rejeitada.";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_acesso', 1, $log_details, $conexao);
                } else {
                    $_SESSION['erro'] = "Erro ao rejeitar a solicitação de acesso ID $action_id.";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_acesso', 0, $log_details . ' (Falha DB)', $conexao);
                }
                break;

             case 'redefinir_senha': // Ação para aprovar reset (gerar temp senha)
                $senha_temporaria_reset = bin2hex(random_bytes(8)); // 16 caracteres hex
                $senha_hash = password_hash($senha_temporaria_reset, PASSWORD_DEFAULT);

                 // Tenta atualizar a senha do usuário e marcar para primeiro acesso
                 // Note: redefinirSenha agora retorna bool, mas não aprova a solicitação
                 // Precisamos combinar redefinirSenha e aprovarSolicitacaoReset
                 // Vamos modificar a função aprovarSolicitacaoReset para fazer isso
                // --> Necessário modificar includes/admin_functions.php <--
                // Por agora, vamos assumir que existe uma função que faz ambos:
                if (aprovarESetarSenhaTemp($conexao, $action_id, $senha_temporaria_reset, $_SESSION['usuario_id'])) { // Função hipotética
                    $_SESSION['sucesso'] = "Senha redefinida para solicitação ID $action_id! Senha temporária: " . htmlspecialchars($senha_temporaria_reset);
                     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'aprovar_reset_senha', 1, $log_details, $conexao);
                 } else {
                     $_SESSION['erro'] = "Erro ao redefinir senha para solicitação ID $action_id.";
                    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'aprovar_reset_senha', 0, $log_details . ' (Falha DB)', $conexao);
                 }
                 // Alternativamente, manter a página redefinir_senha_admin.php e só redirecionar pra ela?
                 // Por simplicidade, vamos manter a lógica aqui. A função aprovarESetarSenhaTemp precisa ser criada.
                 break;

            case 'rejeitar_reset':
                if (rejeitarSolicitacaoReset($conexao, $action_id)) {
                     $_SESSION['sucesso'] = "Solicitação de reset ID $action_id rejeitada.";
                      dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_reset_senha', 1, $log_details, $conexao);
                 } else {
                     $_SESSION['erro'] = "Erro ao rejeitar a solicitação de reset ID $action_id.";
                      dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'rejeitar_reset_senha', 0, $log_details . ' (Falha DB)', $conexao);
                 }
                 break;

            default:
                $_SESSION['erro'] = "Ação desconhecida.";
                 dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'acao_usuario_desconhecida', 0, 'Ação: ' . $_POST['action'], $conexao);
        }
    }

    // Redireciona para a própria página para mostrar mensagens e evitar reenvio do POST
    header('Location: ' . $_SERVER['REQUEST_URI']); // Mantém a aba ativa
    exit;
}

// --- Paginação e Filtros (Manter como estava, mas podemos melhorar depois) ---
$pagina_atual = $_GET['pagina'] ?? 1;
$pagina_atual = max(1, (int)$pagina_atual); // Garante que seja pelo menos 1
$itens_por_pagina = 10;

// --- Obtenção dos Dados para as Abas ---
// Usuários (excluindo o próprio admin da lista principal)
$usuarios_data = getUsuarios($conexao, $_SESSION['usuario_id'], $pagina_atual, $itens_por_pagina);
$usuarios = $usuarios_data['usuarios'];
$paginacao_usuarios = $usuarios_data['paginacao'];

// Solicitações de Acesso Pendentes
$solicitacoes_acesso = getSolicitacoesAcessoPendentes($conexao);

// Solicitações de Reset Pendentes
$solicitacoes_reset = getSolicitacoesResetPendentes($conexao);

// --- Geração do HTML ---
$title = "ACodITools - Gestão de Usuários e Solicitações";
echo getHeaderAdmin($title); // Layout unificado
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Gestão de Usuários e Solicitações</h1>
    </div>

    <?php /* Exibir mensagens de sucesso/erro */ ?>
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

    <?php /* Sistema de Abas Bootstrap (JS nativo) */ ?>
    <ul class="nav nav-tabs mb-3" id="adminUserTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios-content" type="button" role="tab" aria-controls="usuarios-content" aria-selected="true">
                <i class="fas fa-users me-1"></i>Usuários Cadastrados
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link position-relative" id="solicitacoes-acesso-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-acesso-content" type="button" role="tab" aria-controls="solicitacoes-acesso-content" aria-selected="false">
                <i class="fas fa-user-plus me-1"></i>Solicitações de Acesso
                <?php if (count($solicitacoes_acesso) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= count($solicitacoes_acesso) ?>
                        <span class="visually-hidden">solicitações pendentes</span>
                    </span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link position-relative" id="solicitacoes-reset-tab" data-bs-toggle="tab" data-bs-target="#solicitacoes-reset-content" type="button" role="tab" aria-controls="solicitacoes-reset-content" aria-selected="false">
                 <i class="fas fa-key me-1"></i>Solicitações de Senha
                 <?php if (count($solicitacoes_reset) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                         <?= count($solicitacoes_reset) ?>
                        <span class="visually-hidden">solicitações pendentes</span>
                    </span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminUserTabsContent">
        <?php /* ----- Aba Usuários Cadastrados ----- */ ?>
        <div class="tab-pane fade show active" id="usuarios-content" role="tabpanel" aria-labelledby="usuarios-tab">
            <div class="card">
                 <div class="card-header">
                    Usuários Ativos e Inativos
                 </div>
                 <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm"> <?php /* table-sm para mais compacta */ ?>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                    <th>Cadastro</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">Nenhum usuário encontrado (além de você).</td></tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($usuario['id']) ?></td>
                                            <td><?= htmlspecialchars($usuario['nome']) ?></td>
                                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($usuario['perfil'])) ?></td>
                                            <td>
                                                <span class="badge <?= $usuario['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars((new DateTime($usuario['data_cadastro']))->format('d/m/y')) ?></td>
                                            <td>
                                                <div class="d-flex flex-nowrap"> <?php /* Evita quebra de linha nos botões */ ?>
                                                    <?php if ($usuario['perfil'] == 'auditor'): ?>
                                                        <!-- Código para auditor -->
                                                    <?php elseif ($usuario['perfil'] == 'gestor'): ?>
                                                        <!-- Código para gestor -->
                                                    <?php else: ?>
                                                        <a href="<?= BASE_URL ?>admin/editar_usuario.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Editar"><i class="fas fa-edit"></i></a>
                                                    <?php endif; ?>

                                                    <?php if ($usuario['ativo']): ?>
                                                        <form method="POST" action="<?= BASE_URL ?>admin/usuarios.php" class="d-inline me-1" onsubmit="return confirm('Tem certeza que deseja DESATIVAR este usuário?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="action" value="desativar_usuario">
                                                            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Desativar"><i class="fas fa-user-slash"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                         <form method="POST" action="<?= BASE_URL ?>admin/usuarios.php" class="d-inline me-1" onsubmit="return confirm('Tem certeza que deseja ATIVAR este usuário?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="action" value="ativar_usuario">
                                                            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Ativar"><i class="fas fa-user-check"></i></button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <form method="POST" action="<?= BASE_URL ?>admin/usuarios.php" class="d-inline" onsubmit="return confirm('ATENÇÃO! Tem certeza que deseja EXCLUIR PERMANENTEMENTE este usuário? Esta ação não pode ser desfeita.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="excluir_usuario">
                                                        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php /* Paginação para Usuários */ ?>
                    <?php if ($paginacao_usuarios['total_paginas'] > 1): ?>
                        <nav aria-label="Paginação de Usuários">
                            <ul class="pagination pagination-sm justify-content-center">
                                <?php 
                                // Botão "Anterior"
                                if ($paginacao_usuarios['pagina_atual'] > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?= $paginacao_usuarios['pagina_atual'] - 1 ?>">Anterior</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Anterior</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                // Lógica para exibir páginas com elipses
                                $inicio = max(1, $paginacao_usuarios['pagina_atual'] - 2);
                                $fim = min($paginacao_usuarios['total_paginas'], $paginacao_usuarios['pagina_atual'] + 2);
                                
                                if ($inicio > 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif;
                        
                                for ($i = $inicio; $i <= $fim; $i++): ?>
                                    <li class="page-item <?= ($i == $paginacao_usuarios['pagina_atual']) ? 'active' : '' ?>">
                                        <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor;
                        
                                if ($fim < $paginacao_usuarios['total_paginas']): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                // Botão "Última"
                                if ($paginacao_usuarios['pagina_atual'] < $paginacao_usuarios['total_paginas']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?= $paginacao_usuarios['total_paginas'] ?>">Última</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Última</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                // Botão "Próxima"
                                if ($paginacao_usuarios['pagina_atual'] < $paginacao_usuarios['total_paginas']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?= $paginacao_usuarios['pagina_atual'] + 1 ?>">Próxima</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Próxima</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <?php /* ----- Aba Solicitações de Acesso ----- */ ?>
        <div class="tab-pane fade" id="solicitacoes-acesso-content" role="tabpanel" aria-labelledby="solicitacoes-acesso-tab">
            <div class="card">
                 <div class="card-header">
                    Solicitações de Acesso Pendentes
                 </div>
                 <div class="card-body">
                     <?php if (empty($solicitacoes_acesso)): ?>
                        <p class="text-center text-muted">Nenhuma solicitação de acesso pendente.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Empresa</th>
                                        <th>Motivo</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitacoes_acesso as $solicitacao): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($solicitacao['id']) ?></td>
                                            <td><?= htmlspecialchars($solicitacao['nome_completo']) ?></td>
                                            <td><?= htmlspecialchars($solicitacao['email']) ?></td>
                                            <td><?= htmlspecialchars($solicitacao['empresa_nome']) ?></td>
                                            <td title="<?= htmlspecialchars($solicitacao['motivo']) ?>"><?= htmlspecialchars(mb_strimwidth($solicitacao['motivo'], 0, 50, "...")) ?></td> <?php /* Limita motivo */ ?>
                                            <td><?= htmlspecialchars((new DateTime($solicitacao['data_solicitacao']))->format('d/m/y H:i')) ?></td>
                                            <td>
                                                <div class="d-flex flex-nowrap">
                                                    <form method="POST" action="<?= BASE_URL ?>admin/usuarios.php" class="d-inline me-1" onsubmit="return confirm('Aprovar acesso para <?= htmlspecialchars(addslashes($solicitacao['nome_completo'])) ?>? Uma senha temporária será gerada.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="aprovar_acesso">
                                                        <input type="hidden" name="id" value="<?= $solicitacao['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Aprovar"><i class="fas fa-check"></i></button>
                                                    </form>
                                                     <form method="POST" action="<?= BASE_URL ?>admin/usuarios.php" class="d-inline" onsubmit="return confirm('Rejeitar acesso para <?= htmlspecialchars(addslashes($solicitacao['nome_completo'])) ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="rejeitar_acesso">
                                                        <input type="hidden" name="id" value="<?= $solicitacao['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Rejeitar"><i class="fas fa-times"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php /* ----- Aba Solicitações de Reset ----- */ ?>
        <div class="tab-pane fade" id="solicitacoes-reset-content" role="tabpanel" aria-labelledby="solicitacoes-reset-tab">
            <div class="card">
                 <div class="card-header">
                    Solicitações de Reset de Senha Pendentes
                 </div>
                 <div class="card-body">
                    <?php if (empty($solicitacoes_reset)): ?>
                        <p class="text-center text-muted">Nenhuma solicitação de reset de senha pendente.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID Sol.</th>
                                        <th>Usuário</th>
                                        <th>E-mail</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitacoes_reset as $solicitacao): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($solicitacao['id']) ?></td>
                                            <td><?= htmlspecialchars($solicitacao['nome_usuario']) ?></td>
                                            <td><?= htmlspecialchars($solicitacao['email']) ?></td>
                                            <td><?= htmlspecialchars((new DateTime($solicitacao['data_solicitacao']))->format('d/m/y H:i')) ?></td>
                                            <td>
                                                 <div class="d-flex flex-nowrap">
                                                      <?php /* Aprovar Reset (Gerar Senha Temp) - Ação ainda precisa ser ajustada */ ?>
                                                      <form method="POST" action="<?= BASE_URL ?>admin/usuarios.php" class="d-inline me-1" onsubmit="return confirm('Gerar nova senha temporária para <?= htmlspecialchars(addslashes($solicitacao['nome_usuario'])) ?>?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="hidden" name="action" value="redefinir_senha"> <?php /* Nome da ação precisa ser tratado no POST */ ?>
                                                            <input type="hidden" name="id" value="<?= $solicitacao['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Gerar Senha Temporária"><i class="fas fa-key"></i></button>
                                                      </form>
                                                     <form method="POST" action="<?= BASE_URL ?>admin/usuarios.php" class="d-inline" onsubmit="return confirm('Rejeitar solicitação de reset para <?= htmlspecialchars(addslashes($solicitacao['nome_usuario'])) ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="rejeitar_reset">
                                                        <input type="hidden" name="id" value="<?= $solicitacao['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Rejeitar"><i class="fas fa-times"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div> <?php /* Fim tab-content */ ?>
</div> <?php /* Fim container-fluid */ ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Obtém o hash da URL (ex.: #solicitacoes-reset-tab)
    const hash = window.location.hash;

    if (hash) {
        // Remove o '#' e encontra o botão da aba correspondente
        const tabButton = document.querySelector(hash);
        if (tabButton) {
            // Ativa a aba usando o método do Bootstrap
            const bsTab = new bootstrap.Tab(tabButton);
            bsTab.show();
        }
    }
});
</script>

<?php
// Inclui o Footer
echo getFooterAdmin();
?>