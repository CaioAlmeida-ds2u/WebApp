<?php
// admin/requisitos/importar_requisitos.php
// VERSÃO FINAL - Com Layout Admin e usando apenas CSS externo + Bootstrap

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php'; // Para getHeaderAdmin/getFooterAdmin
require_once __DIR__ . '/../../includes/admin_functions.php'; // Funções CRUD, segurança, etc.

// --- Proteção e Verificação de Perfil ---
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: '.BASE_URL.'acesso_negado.php'); exit;
}

// --- Funções CSRF ---
if (!function_exists('gerar_csrf_token')) { function gerar_csrf_token() { if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } return $_SESSION['csrf_token']; } }
if (!function_exists('validar_csrf_token')) { function validar_csrf_token($token) { return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); } }

// --- Define limites ---
set_time_limit(600);
ini_set('memory_limit', '512M');

// --- Variáveis Globais do Script ---
$etapa = 1; $dados_para_preview = []; $cabecalho_original = []; $mapa_colunas = []; $nome_arquivo_original = ''; $erro_analise = null;

// =========================================================================
// ETAPA 3: PROCESSAR A SELEÇÃO DO USUÁRIO
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_selection') {
    $etapa = 3;
    // --- Lógica PHP da Etapa 3 (Processamento - Inalterada) ---
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) { /* Erro CSRF */ $_SESSION['erro']="Erro Sessão"; header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; }
    $_SESSION['csrf_token'] = gerar_csrf_token();
    if (!isset($_SESSION['import_preview_data'])) { /* Erro dados */ $_SESSION['erro']="Dados expirados"; header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; }
    $dp = $_SESSION['import_preview_data']; $ls = $_POST['selecionar']??[]; $la = $_POST['atualizar']??[];
    unset($_SESSION['import_preview_data'], $_SESSION['import_preview_header'], $_SESSION['import_preview_mapa'], $_SESSION['import_preview_filename']);
    if (empty($ls)) { /* Info nada sel */ $_SESSION['info']="Nada selecionado"; header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; }
    if (!function_exists('criarRequisitoAuditoria')||!function_exists('atualizarRequisitoAuditoria')) { $_SESSION['erro']="Erro Func DB"; header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; }
    $rc=0; $ra=0; $edb=0; $iproc=0; $med=[]; $merr=50; $uid=$_SESSION['usuario_id']; $conexao->beginTransaction();
    try { foreach($ls as $idx_s){$idx=filter_var($idx_s, FILTER_VALIDATE_INT); if($idx===false || !isset($dp[$idx])){ $edb++; if(count($med)<$merr)$med[]="Índice '$idx_s' inválido."; continue; } $item=$dp[$idx]; if(!empty($item['errors'])||!in_array($item['status'],['novo','existente'])) continue; $iproc++; $dr=$item['data']; if($item['status']==='novo'){$rdb=criarRequisitoAuditoria($conexao,$dr,$uid); if($rdb===true)$rc++;else{$edb++;$msg=is_string($rdb)?$rdb:'Erro criar.'; if(count($med)<$merr)$med[]="L{$item['linha_num']} N: $msg";}} elseif($item['status']==='existente'&&isset($item['id_existente'])){if(in_array((string)$idx,$la)){$rdb=atualizarRequisitoAuditoria($conexao,$item['id_existente'],$dr,$uid); if($rdb===true)$ra++; else {$edb++;$msg=is_string($rdb)?$rdb:'Erro att.'; if(count($med)<$merr)$med[]="L{$item['linha_num']} A: $msg";}}}} if($edb==0){$conexao->commit(); $mSuc="OK! $rc criados, $ra atualizados.";$_SESSION['sucesso']=$mSuc;/*log*/} else{$conexao->rollBack();$mErr="Falhou ($edb erros). Nada salvo.";$_SESSION['erro']=$mErr;if(!empty($med)){$_SESSION['importar_req_erros_detalhes']=$med; if($edb>$merr)$_SESSION['importar_req_erros_detalhes'][]="...";}/*log*/} } catch(Exception $e){$conexao->rollBack(); $_SESSION['erro']="Erro crítico: ".$e->getMessage();/*log*/}
    header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
}
// =========================================================================
// ETAPA 1: RECEBER UPLOAD E ANALISAR CSV
// =========================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $etapa = 1;
    // --- Lógica PHP da Etapa 1 (Análise - Inalterada) ---
    if (!validar_csrf_token($_POST['csrf_token']??null)) { $_SESSION['erro']="Erro sessão."; header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; }
    if (!isset($_FILES['csv_file'])||!is_uploaded_file($_FILES['csv_file']['tmp_name'])||$_FILES['csv_file']['error']!==UPLOAD_ERR_OK) { /*Erro upload*/ header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;}
    $caminho = $_FILES['csv_file']['tmp_name']; $nome_arq = $_FILES['csv_file']['name']; $tam = $_FILES['csv_file']['size'];
    if($tam===0||$tam>10*1024*1024) { /*Erro tam*/ header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; } $ext=strtolower(pathinfo($nome_arq,PATHINFO_EXTENSION)); if($ext!=='csv'){ /*Erro ext*/ header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;} //MIME...
    $fh=@fopen($caminho,'r'); if($fh===false){ /*Erro fopen*/ header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; } $delim=';';$l1=fgets($fh);rewind($fh);if($l1&&strpos($l1,',')!==false&&strpos($l1,';')===false)$delim=',';$ch=fgetcsv($fh,0,$delim);if($ch===false||count(array_filter($ch))===0){/*Erro cab*/header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;}$nc=count($ch);$mc=[];$ce=['codigo','nome','descricao','categoria','norma_referencia','ativo'];foreach($ch as $i=>$col){$cn=trim($col);$cn=strtolower($cn);$cn=preg_replace('/[\s-]+/','_',$cn);$cn=iconv('UTF-8','ASCII//TRANSLIT',$cn);$cn=preg_replace('/[^a-z0-9_]/','',$cn);if(in_array($cn,$ce)&&!isset($mc[$cn])){$mc[$cn]=$i;}}if(!isset($mc['nome'])||!isset($mc['descricao'])){/*Erro cols*/header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;}
    $dpv=[];$ln=1;$ml=1000;$ll=0;while(($ld=fgetcsv($fh,0,$delim))!==false && $ll<$ml){$ln++;$ll++;if(count(array_filter($ld))==0)continue;$ip=['linha_num'=>$ln,'data'=>[],'raw_data'=>$ld,'status'=>'novo','id_existente'=>null,'errors'=>[]];if(count($ld)!=$nc)$ip['errors'][]="Num colunas difere.";$dr=[];foreach($mc as $c=>$i)$dr[$c]=isset($ld[$i])?trim($ld[$i]):'';if(empty($dr['nome']))$ip['errors'][]="'nome' vazio.";if(empty($dr['descricao']))$ip['errors'][]="'descricao' vazia.";if(isset($mc['ativo'])){if(isset($dr['ativo'])&&$dr['ativo']!==''){$al=strtolower($dr['ativo']);if(in_array($al,['1','sim','s','true','ativo']))$dr['ativo']=1;elseif(in_array($al,['0','nao','n','não','false','inativo']))$dr['ativo']=0;else $ip['errors'][]="'ativo' inválido.";}else $dr['ativo']=1;}else $dr['ativo']=1;if(!empty($dr['codigo'])&&mb_strlen($dr['codigo'])>50)$ip['errors'][]="'codigo'>50.";$ip['data']=$dr;if(empty($ip['errors'])&&!empty($dr['codigo'])){if(function_exists('getRequisitoAuditoriaPorCodigo')){try{$re=getRequisitoAuditoriaPorCodigo($conexao,$dr['codigo']);if($re){$ip['status']='existente';$ip['id_existente']=$re['id'];}}catch(Exception $e){$ip['errors'][]="Erro DB check.";}}else{$ip['errors'][]="Func check n/a.";}}if(!empty($ip['errors']))$ip['status']='erro';$dpv[]=$ip;}fclose($fh);
    if(empty($dpv)){ $_SESSION['info']="Nenhuma linha válida."; header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;}
    $_SESSION['import_preview_data']=$dpv;$_SESSION['import_preview_header']=$ch;$_SESSION['import_preview_mapa']=$mc;$_SESSION['import_preview_filename']=$nome_arq;$etapa=2;
}
// =========================================================================
// CASO PADRÃO: ACESSO GET OU POST INVÁLIDO
// =========================================================================
elseif ($etapa === 1) {
    $_SESSION['erro'] = "Acesso inválido à página de importação.";
    header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
}


// =========================================================================
// ETAPA 2: EXIBIR PÁGINA DE CONFIRMAÇÃO
// =========================================================================
if ($etapa === 2):
    $dados_para_preview = $_SESSION['import_preview_data'] ?? [];
    $cabecalho_original = $_SESSION['import_preview_header'] ?? [];
    $nome_arquivo_original = $_SESSION['import_preview_filename'] ?? 'Desconhecido';
    $csrf_token_confirmacao = gerar_csrf_token();
    $_SESSION['csrf_token'] = $csrf_token_confirmacao;

    $title = "Confirmar Importação - Requisitos";
    echo getHeaderAdmin($title);
?>

<div class="container-fluid main-content-fluid">
    <!-- Cabeçalho da página -->
    <div class="page-header">
        <h1><i class="fas fa-check-double me-2 text-primary"></i>Confirmar Importação</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard_admin.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php">Requisitos</a></li>
                <li class="breadcrumb-item active" aria-current="page">Confirmar Importação</li>
            </ol>
        </nav>
    </div>

    <!-- Bloco informativo -->
    <div class="alert alert-dark-blue" role="alert" style="background-color:rgb(101, 156, 239); color: #ffffff;">
        <div class="d-flex align-items-center">
            <i class="fas fa-file-alt fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading">Arquivo: <strong><?= htmlspecialchars($nome_arquivo_original) ?></strong></h5>
                <p class="mb-1">Verifique os dados abaixo. Selecione os itens a importar (<span class="badge bg-success-subtle text-success-emphasis">Novo</span>) ou marque <strong>"Atualizar"</strong> para os existentes (<span class="badge bg-warning-subtle text-warning-emphasis">Existente</span>).</p>
                <p class="mb-0 text-muted">Itens com <span class="badge bg-danger-subtle text-danger-emphasis">Erro</span> serão ignorados.</p>
            </div>
        </div>
    </div>

    <!-- Formulário -->
    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="form-confirmar-importacao" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_confirmacao) ?>">
        <input type="hidden" name="action" value="process_selection">

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selecionar-todos" title="Selecionar/Deselecionar Todos Válidos">
                    <label class="form-check-label" for="selecionar-todos">Selecionar Todos</label>
                </div>
                <span class="badge bg-secondary rounded-pill"><?= count($dados_para_preview) ?> linhas</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;" class="text-center">Sel.</th>
                                <th style="width: 8%;" class="text-center">Linha</th>
                                <th style="width: 12%;">Status</th>
                                <th style="width: 10%;" class="text-center">Atualizar</th>
                                <?php foreach ($cabecalho_original as $col_header): ?>
                                    <th><?= htmlspecialchars($col_header) ?></th>
                                <?php endforeach; ?>
                                <th style="width: 25%;">Detalhes/Erros</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dados_para_preview)): ?>
                                <tr><td colspan="<?= count($cabecalho_original) + 5 ?>" class="text-center text-muted py-4">Nenhum dado para pré-visualizar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($dados_para_preview as $index => $item):
                                    $hasErrors = !empty($item['errors']);
                                    $rowClass = $hasErrors ? 'table-danger' : '';
                                    $statusBadge = $statusText = '';
                                    if ($hasErrors) {
                                        $statusText = 'Erro';
                                        $statusBadge = 'bg-danger-subtle text-danger-emphasis';
                                    } elseif ($item['status'] === 'novo') {
                                        $statusText = 'Novo';
                                        $statusBadge = 'bg-success-subtle text-success-emphasis';
                                    } else {
                                        $statusText = 'Existente';
                                        $statusBadge = 'bg-warning-subtle text-warning-emphasis';
                                    }
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="text-center">
                                            <?php if (!$hasErrors): ?>
                                                <input type="checkbox" name="selecionar[]" value="<?= $index ?>" class="selecionar-item form-check-input">
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= $item['linha_num'] ?></td>
                                        <td>
                                            <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                            <?php if ($item['status'] === 'existente' && !$hasErrors): ?>
                                                <small class="text-muted d-block">(ID: <?= $item['id_existente'] ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($item['status'] === 'existente' && !$hasErrors): ?>
                                                <input type="checkbox" name="atualizar[]" value="<?= $index ?>" class="atualizar-item form-check-input" id="update-<?= $index ?>">
                                                <label class="visually-hidden" for="update-<?= $index ?>">Atualizar</label>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php for ($i = 0; $i < count($cabecalho_original); $i++): ?>
                                            <td><?= isset($item['raw_data'][$i]) ? htmlspecialchars($item['raw_data'][$i]) : '' ?></td>
                                        <?php endfor; ?>
                                        <td>
                                            <?php if ($hasErrors): ?>
                                                <ul class="list-unstyled text-danger mb-0">
                                                    <?php foreach ($item['errors'] as $err): ?>
                                                        <li><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars(mb_strimwidth($err, 0, 50, "...")) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-outline-secondary me-2" onclick="return confirm('Tem certeza que deseja cancelar?');">Cancelar</a>
                <button type="submit" class="btn btn-primary">Confirmar e Processar</button>
            </div>
        </div>
    </form>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const selectAll = document.getElementById('selecionar-todos');
            const items = document.querySelectorAll('.selecionar-item');
            selectAll.addEventListener('change', () => items.forEach(cb => cb.checked = selectAll.checked));
            items.forEach(cb => cb.addEventListener('change', () => { if (!cb.checked) selectAll.checked = false; }));
        });
    </script>
</div>

<?php
echo getFooterAdmin();
exit();
endif;