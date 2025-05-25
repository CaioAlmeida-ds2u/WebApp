<?php
// admin/requisitos/importar_requisitos.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/layout_admin.php';
require_once __DIR__ . '/../../includes/admin_functions.php';

// Proteção de perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    header('Location: '.BASE_URL.'acesso_negado.php'); exit;
}

// Funções CSRF (já devem estar em config.php ou admin_functions.php, mas para garantir)
if (!function_exists('gerar_csrf_token')) { function gerar_csrf_token() { if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } return $_SESSION['csrf_token']; } }
if (!function_exists('validar_csrf_token')) { function validar_csrf_token($token) { return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); } }

set_time_limit(600);
ini_set('memory_limit', '512M');

$etapa = 1;
// Variáveis que serão preenchidas na Etapa 1 e usadas na Etapa 2
$dados_para_preview = [];
$cabecalho_original = [];
$mapa_colunas = []; // Mapeamento normalizado de [chave_interna => indice_csv]
$nome_arquivo_original = '';

// =========================================================================
// ETAPA 3: PROCESSAR A SELEÇÃO DO USUÁRIO
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_selection') {
    $etapa = 3; // Marcar que estamos na etapa 3
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) {
        definir_flash_message('erro', "Erro de validação da sessão ao processar. Tente novamente.");
        header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
    }
    $_SESSION['csrf_token'] = gerar_csrf_token(); // Regenerar para a próxima requisição

    if (!isset($_SESSION['import_preview_data'])) {
        definir_flash_message('erro', "Dados da pré-visualização expiraram ou não encontrados. Por favor, reenvie o arquivo.");
        header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
    }
    
    $dp = $_SESSION['import_preview_data'];
    $ls = $_POST['selecionar'] ?? []; // Linhas selecionadas para importar/atualizar
    $la = $_POST['atualizar'] ?? [];  // Linhas existentes marcadas para atualizar

    // Limpar dados da sessão após usá-los
    unset($_SESSION['import_preview_data'], $_SESSION['import_preview_header'], $_SESSION['import_preview_mapa'], $_SESSION['import_preview_filename']);
    
    if (empty($ls)) {
        definir_flash_message('info', "Nenhum requisito foi selecionado para ser processado.");
        header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
    }
    
    if (!function_exists('criarRequisitoAuditoria') || !function_exists('atualizarRequisitoAuditoria')) {
        definir_flash_message('erro', "Erro crítico: Funções essenciais do sistema não estão disponíveis.");
        header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
    }
    
    $rc = 0; $ra = 0; $edb = 0; $iproc = 0; $med = []; $merr_display_limit = 20; // Limite de erros detalhados para exibir
    $uid = $_SESSION['usuario_id'];
    $conexao->beginTransaction();
    
    try {
        foreach ($ls as $idx_s) { // $idx_s é o índice do array $dp (dados de preview)
            $idx = filter_var($idx_s, FILTER_VALIDATE_INT);
            if ($idx === false || !isset($dp[$idx])) {
                $edb++; if (count($med) < $merr_display_limit) $med[] = "Linha selecionada inválida (índice: '$idx_s').";
                continue;
            }
            
            $item = $dp[$idx];
            if (!empty($item['errors']) || !in_array($item['status'], ['novo', 'existente'])) {
                continue; // Pula itens com erro de análise ou status inválido
            }
            
            $iproc++;
            $dados_req_para_bd = $item['data'];
            $dados_req_para_bd['global_ou_empresa_id'] = null; // Requisitos globais

            if ($item['status'] === 'novo') {
                $res_op_bd = criarRequisitoAuditoria($conexao, $dados_req_para_bd, $uid);
                if ($res_op_bd === true) $rc++;
                else { $edb++; $msg = is_string($res_op_bd) ? $res_op_bd : 'Erro ao criar.'; if (count($med) < $merr_display_limit) $med[] = "L.CSV {$item['linha_num']} (Novo): $msg"; }
            } elseif ($item['status'] === 'existente' && isset($item['id_existente'])) {
                if (in_array((string)$idx, $la)) { // Somente se explicitamente marcado para atualizar
                    $res_op_bd = atualizarRequisitoAuditoria($conexao, $item['id_existente'], $dados_req_para_bd, $uid);
                    if ($res_op_bd === true) $ra++;
                    else { $edb++; $msg = is_string($res_op_bd) ? $res_op_bd : 'Erro ao atualizar.'; if (count($med) < $merr_display_limit) $med[] = "L.CSV {$item['linha_num']} (Atualizar ID {$item['id_existente']}): $msg"; }
                }
            }
        }
        
        if ($edb == 0) {
            $conexao->commit();
            $msg_sucesso_final = "Importação concluída com sucesso!";
            if ($rc > 0) $msg_sucesso_final .= " $rc requisito(s) novo(s) criado(s).";
            if ($ra > 0) $msg_sucesso_final .= " $ra requisito(s) existente(s) atualizado(s).";
            if ($iproc == 0 && !empty($ls)) $msg_sucesso_final = "Nenhum item válido foi selecionado ou processado.";
            
            definir_flash_message('sucesso', $msg_sucesso_final);
            dbRegistrarLogAcesso($uid, $_SERVER['REMOTE_ADDR'], 'importar_requisitos_sucesso', 1, "Criados: $rc, Atualizados: $ra, Processados: $iproc", $conexao);
        } else {
            $conexao->rollBack();
            $msg_erro_final = "Importação falhou. $edb erro(s) ocorreram durante o processamento no banco de dados. Nenhuma alteração foi salva permanentemente.";
            definir_flash_message('erro', $msg_erro_final);
            if (!empty($med)) {
                $_SESSION['importar_req_erros_detalhes'] = $med; // Para exibir na página de requisitos
                if ($edb > $merr_display_limit) $_SESSION['importar_req_erros_detalhes'][] = "...e mais " . ($edb - $merr_display_limit) . " outros erros.";
            }
            dbRegistrarLogAcesso($uid, $_SERVER['REMOTE_ADDR'], 'importar_requisitos_falha_db', 0, "Erros BD: $edb, Processados: $iproc, Detalhes: " . implode('; ', $med), $conexao);
        }
    } catch (Exception $e) {
        if ($conexao->inTransaction()) $conexao->rollBack();
        definir_flash_message('erro', "Erro crítico durante o processamento da importação: " . $e->getMessage());
        error_log("Erro crítico importação requisitos: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
        dbRegistrarLogAcesso($uid, $_SERVER['REMOTE_ADDR'], 'importar_requisitos_excecao', 0, "Exceção: " . $e->getMessage(), $conexao);
    }
    header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
}
// =========================================================================
// ETAPA 1: RECEBER UPLOAD E ANALISAR CSV
// =========================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $etapa = 1; // Marca que estamos processando o upload
    // ... (SUA LÓGICA DA ETAPA 1, COMO VOCÊ AJUSTOU PARA OS NOVOS CAMPOS - MANTENHA) ...
    // No final desta lógica, se tudo correr bem, você deve ter:
    // $_SESSION['import_preview_data'] = $dados_para_preview;
    // $_SESSION['import_preview_header'] = $cabecalho_csv;
    // $_SESSION['import_preview_mapa'] = $mapa_colunas_csv;
    // $_SESSION['import_preview_filename'] = $nome_arquivo_original;
    // $etapa = 2; // <--- ESSENCIAL: MUDAR A VARIÁVEL $etapa PARA 2
    // Se houver erro na análise, use definir_flash_message e redirecione de volta para requisitos_index.php

    // COLE SUA LÓGICA COMPLETA DA ETAPA 1 AQUI
    // Exemplo resumido:
    if (!validar_csrf_token($_POST['csrf_token'] ?? null)) { /* ... redirect com erro ... */ }
    // ... (validação do arquivo CSV) ...
    // ... (abrir arquivo, detectar delimitador, ler cabeçalho) ...
    // ... (definir $colunas_esperadas_csv e $colunas_obrigatorias_csv como fizemos) ...
    // ... (mapear colunas e verificar obrigatórias) ...
    // ... (loop while para ler linhas e validar dados, populando $dados_para_preview) ...
    // Exemplo final da Etapa 1:
    // if (empty($dados_para_preview)) {
    //     definir_flash_message('info', "Nenhuma linha de dados válida encontrada no arquivo CSV.");
    //     header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    // }
    // $_SESSION['import_preview_data'] = $dados_para_preview;
    // $_SESSION['import_preview_header'] = $cabecalho_original; // $cabecalho_csv que você leu
    // $_SESSION['import_preview_mapa'] = $mapa_colunas;      // $mapa_colunas_csv que você montou
    // $_SESSION['import_preview_filename'] = $nome_arquivo_original;
    // $etapa = 2; // SINALIZA QUE A ANÁLISE FOI FEITA E PODEMOS MOSTRAR O PREVIEW


    // >>>>>>>>>> INÍCIO DA SUA LÓGICA DA ETAPA 1 QUE JÁ FUNCIONAVA PARA OS CAMPOS <<<<<<<<<<
    if (!validar_csrf_token($_POST['csrf_token']??null)) { $_SESSION['erro']="Erro sessão."; header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; }
    if (!isset($_FILES['csv_file'])||!is_uploaded_file($_FILES['csv_file']['tmp_name'])||$_FILES['csv_file']['error']!==UPLOAD_ERR_OK) { definir_flash_message('erro', "Erro no upload do arquivo."); header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;}
    
    $caminho = $_FILES['csv_file']['tmp_name']; $nome_arq = $_FILES['csv_file']['name']; $tam = $_FILES['csv_file']['size'];
    $nome_arquivo_original = $nome_arq; // Definindo a variável para uso posterior

    if($tam===0||$tam>10*1024*1024) { definir_flash_message('erro', "Arquivo CSV vazio ou muito grande."); header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; } 
    $ext=strtolower(pathinfo($nome_arq,PATHINFO_EXTENSION)); 
    if($ext!=='csv'){ definir_flash_message('erro', "Apenas arquivos CSV são permitidos."); header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;}
    
    $fh=@fopen($caminho,'r'); 
    if($fh===false){ definir_flash_message('erro', "Não foi possível abrir o arquivo CSV."); header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit; } 
    
    $delim=';';$l1=fgets($fh);rewind($fh);if($l1&&strpos($l1,',')!==false&&strpos($l1,';')===false)$delim=',';
    
    $ch=fgetcsv($fh,0,$delim);
    $cabecalho_original = $ch; // Salva o cabeçalho original
    if($ch===false||count(array_filter($ch))===0){definir_flash_message('erro', "Cabeçalho do CSV inválido ou vazio."); fclose($fh); header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;}
    
    $nc=count($ch);
    $mc=[]; // $mapa_colunas
    // Nomes de colunas que o seu backend espera (depois da normalização)
    $ce=['codigo','nome','descricao','categoria','norma_referencia','ativo', 'peso', 'guia_evidencia', 'objetivo_controle', 'tecnicas_sugeridas', 'versao_norma_aplicavel', 'data_ultima_revisao_norma', 'disponibilidade_planos_ids'];
    $colunas_obrigatorias_no_csv = ['nome', 'descricao', 'peso']; // O que DEVE estar no CSV

    foreach ($ch as $i => $col_raw) {
        $col = trim($col_raw);
        $cn = strtolower($col);
        $cn = preg_replace('/[\s-]+/', '_', $cn);
        
        // Remover BOM e caracteres de controle
        $cn = preg_replace('/^[\x{FEFF}\x{200B}]+/u', '', $cn);
        $cn = preg_replace('/[\x00-\x1F\x7F]/u', '', $cn);
        
        // Log para depuração
        error_log("Valor de cn antes de iconv: " . $cn . " (hex: " . bin2hex($cn) . ")");
        
        // Tentar conversão com iconv
        if (mb_check_encoding($cn, 'UTF-8')) {
            $cn = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cn) ?: $cn;
        } else {
            error_log("Codificação inválida detectada em: " . bin2hex($cn));
            $cn = preg_replace('/[^a-z0-9_]/', '', $cn);
        }
        
        $cn = preg_replace('/[^a-z0-9_]/', '', $cn);
    
        // Mapeamento direto
        if (in_array($cn, $ce) && !isset($mc[$cn])) {
            $mc[$cn] = $i;
        } else {
            // Mapeamento flexível
            $mapa_nomes_flexiveis = [
                'versao_da_norma' => 'versao_norma_aplicavel',
                'data_revisao_norma' => 'data_ultima_revisao_norma',
                'planos_ids' => 'disponibilidade_planos_ids',
                'planos' => 'disponibilidade_planos_ids',
            ];
            if (isset($mapa_nomes_flexiveis[$cn]) && in_array($mapa_nomes_flexiveis[$cn], $ce) && !isset($mc[$mapa_nomes_flexiveis[$cn]])) {
                $mc[$mapa_nomes_flexiveis[$cn]] = $i;
            }
        }
    }
    $mapa_colunas = $mc; // Salva o mapa final

    // Validar colunas obrigatórias
    $obrigatorias_faltando_arr = [];
    foreach ($colunas_obrigatorias_no_csv as $col_obrig) {
        if (!isset($mc[$col_obrig])) {
            $obrigatorias_faltando_arr[] = "'$col_obrig'";
        }
    }
    if(!empty($obrigatorias_faltando_arr)){
        definir_flash_message('erro', "Colunas obrigatórias não encontradas ou mal mapeadas no CSV: " . implode(', ', $obrigatorias_faltando_arr) . ". Verifique o cabeçalho.");
        fclose($fh);
        header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
    }
    
    $dpv=[]; // $dados_para_preview
    $ln=1; // Linha atual do arquivo (cabeçalho é 1)
    $ml=1000; // Max linhas para preview
    $ll=0;    // Linhas de dados lidas
    
    while(($ld_raw=fgetcsv($fh,0,$delim))!==false && $ll<$ml){
        $ln++; $ll++;
        if(count(array_filter($ld_raw))==0) continue; // Pula linha vazia
        
        $ip=['linha_num'=>$ln,'data'=>[],'raw_data'=>$ld_raw,'status'=>'novo','id_existente'=>null,'errors'=>[]];
        
        if(count($ld_raw)!=$nc) $ip['errors'][]="Número de colunas na linha difere do cabeçalho ({$nc} esperado).";
        
        $dr=[]; // dados do requisito processados
        // Preenche $dr com base no mapeamento $mc
        foreach($mc as $chave_interna => $indice_csv){
            $dr[$chave_interna] = isset($ld_raw[$indice_csv]) ? trim($ld_raw[$indice_csv]) : '';
        }

        // Validações e transformações específicas (já implementadas anteriormente)
        if(empty($dr['nome'])) $ip['errors'][] = "'nome' é obrigatório.";
        if(empty($dr['descricao'])) $ip['errors'][] = "'descricao' é obrigatória.";
        
        // Ativo
        if (isset($mc['ativo'])) {
            $val_ativo = strtolower($dr['ativo'] ?? '');
            if (in_array($val_ativo, ['1','sim','s','true','ativo','active'])) $dr['ativo']=1;
            elseif (in_array($val_ativo, ['0','nao','n','não','false','inativo','inactive'])) $dr['ativo']=0;
            elseif ($val_ativo === '') $dr['ativo'] = 1; // Default ativo se vazio
            else { $ip['errors'][] = "Valor para 'ativo' ('".htmlspecialchars($dr['ativo'])."') inválido."; $dr['ativo'] = 1; }
        } else {
            $dr['ativo']=1; // Default se coluna não existe
        }

        // Peso
        if (isset($mc['peso'])) {
            if ($dr['peso'] === '' || !is_numeric($dr['peso']) || (int)$dr['peso'] < 0) {
                $ip['errors'][] = "'peso' inválido ('".htmlspecialchars($dr['peso'])."'). Usando 1."; $dr['peso'] = 1;
            } else {
                $dr['peso'] = (int)$dr['peso'];
            }
        } else {
            $ip['errors'][] = "Coluna 'peso' obrigatória não encontrada."; // Erro se peso não está no CSV
        }

        // Data Última Revisão Norma
        if (isset($mc['data_ultima_revisao_norma'])) { // Checa se a coluna foi mapeada
            $data_norma_raw = $dr['data_ultima_revisao_norma'] ?? null;
            if (!empty($data_norma_raw)) {
                $data_obj = DateTime::createFromFormat('d/m/Y', $data_norma_raw) ?: DateTime::createFromFormat('Y-m-d', $data_norma_raw);
                if ($data_obj) {
                    $dr['data_ultima_revisao_norma'] = $data_obj->format('Y-m-d');
                } else {
                    $ip['errors'][] = "Formato de 'data_ultima_revisao_norma' ('".htmlspecialchars($data_norma_raw)."') inválido.";
                    $dr['data_ultima_revisao_norma'] = null;
                }
            } else {
                 $dr['data_ultima_revisao_norma'] = null;
            }
        } else {
            $dr['data_ultima_revisao_norma'] = null; // Campo opcional, se não mapeado, é null
        }

        // Disponibilidade Planos IDs
        if (isset($mc['disponibilidade_planos_ids'])) {
            $planos_str = $dr['disponibilidade_planos_ids'] ?? '';
            if (!empty($planos_str)) {
                $ids_planos_arr = preg_split('/[|,;]+/', $planos_str);
                $ids_planos_int_arr = [];
                foreach($ids_planos_arr as $pid_str_raw) {
                    $pid_str = trim($pid_str_raw);
                    if ($pid_str === '') continue; // Pula vazios
                    $pid = filter_var($pid_str, FILTER_VALIDATE_INT);
                    if ($pid !== false && $pid > 0) {
                        $ids_planos_int_arr[] = $pid;
                    } else {
                         $ip['errors'][] = "ID de plano inválido ('".htmlspecialchars($pid_str)."') em 'disponibilidade_planos_ids'.";
                    }
                }
                $dr['disponibilidade_planos_ids'] = array_unique($ids_planos_int_arr);
            } else {
                $dr['disponibilidade_planos_ids'] = [];
            }
        } else {
            $dr['disponibilidade_planos_ids'] = []; // Default se coluna não mapeada
        }

        // Código (verificação de duplicidade)
        if(empty($ip['errors']) && !empty($dr['codigo'])){
            if(function_exists('getRequisitoAuditoriaPorCodigo')){ // Verifica se a função existe
                try {
                    $re_existente = getRequisitoAuditoriaPorCodigo($conexao, $dr['codigo'], null); // Passa null para global_ou_empresa_id
                    if($re_existente){
                        $ip['status']='existente'; $ip['id_existente']=$re_existente['id'];
                    }
                } catch (Exception $e){
                    $ip['errors'][]="Erro DB ao verificar código: ".$e->getMessage();
                }
            } else {
                 // $ip['errors'][]="Função getRequisitoAuditoriaPorCodigo não disponível."; // Opcional: erro ou aviso
            }
        }
        
        if(!empty($ip['errors'])) $ip['status']='erro';
        $ip['data']=$dr; // Armazena dados processados
        $dpv[]=$ip;
    }
    fclose($fh);
    
    if(empty($dpv)){ 
        definir_flash_message('info',"Nenhuma linha de dados válida encontrada no CSV para processar."); 
        header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php'); exit;
    }
    
    $_SESSION['import_preview_data']=$dpv;
    $_SESSION['import_preview_header']=$cabecalho_original; // Usar o cabeçalho original lido
    $_SESSION['import_preview_mapa']=$mapa_colunas; // Usar o mapa final
    $_SESSION['import_preview_filename']=$nome_arquivo_original;
    $etapa=2;
    // NÃO há redirecionamento aqui, o script continua para a ETAPA 2
// >>>>>>>>>> FIM DA SUA LÓGICA DA ETAPA 1 <<<<<<<<<<

} // Fim do elseif para ETAPA 1


// =========================================================================
// ETAPA 2: EXIBIR PÁGINA DE CONFIRMAÇÃO (HTML COM VISUAL MELHORADO)
// =========================================================================
// Esta condição agora é apenas 'if ($etapa === 2)'
if ($etapa === 2):
    // ... (SEU CÓDIGO HTML COMPLETO DA ETAPA 2 QUE FORNECI NA RESPOSTA ANTERIOR, COM CSS E JS) ...
    // Cole o HTML e CSS aqui. Ele termina com o `echo getFooterAdmin(); exit();`

    $dados_para_preview_sess = $_SESSION['import_preview_data'] ?? [];
    $cabecalho_original_sess = $_SESSION['import_preview_header'] ?? [];
    $nome_arquivo_original_sess = $_SESSION['import_preview_filename'] ?? 'Desconhecido';
    $csrf_token_confirmacao_sess = gerar_csrf_token();

    $planos_disponiveis_map = [];
    if (function_exists('listarPlanosAssinatura')) {
        $todos_planos_lista = listarPlanosAssinatura($conexao, false); // Pega todos, ativos ou não
        foreach ($todos_planos_lista as $pl) {
            $planos_disponiveis_map[$pl['id']] = $pl['nome_plano'];
        }
    }

    $title = "Confirmar Importação de Requisitos - AcodITools";
    echo getHeaderAdmin($title);
?>

<style>
    /* Seu CSS customizado para a Etapa 2 ... (COLE AQUI O CSS DA RESPOSTA ANTERIOR) */
    body { /* background-color: #f8f9fa; */ } /* Light gray background */
    .import-preview-page .page-header { margin-bottom: 1.5rem; }
    .import-preview-page .alert.file-info-alert {
        background-color: #e9ecef; /* Bootstrap .bg-light */
        color: #004085; /* Bootstrap .text-primary (escuro) */
        border-left: 5px solid #0d6efd; /* Bootstrap $primary */
        border-radius: .375rem;
    }
    .import-preview-page .alert.file-info-alert .alert-heading { color: #004085; }
    .import-preview-page .preview-table-container {
        max-height: 60vh;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: .375rem;
        background-color: #fff;
    }
    .import-preview-page .table thead.sticky-table-header th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f1f3f5; /* Um pouco mais escuro que o .bg-light */
        box-shadow: inset 0 -2px 0 #ced4da; /* Linha mais sutil */
        font-weight: 600;
        white-space: nowrap;
        padding: .6rem .75rem;
    }
    .import-preview-page .table td, .import-preview-page .table th {
        vertical-align: middle;
        padding: .5rem .75rem; /* Mais espaço */
        font-size: 0.875rem;
    }
    .import-preview-page .table-hover tbody tr:hover { background-color: rgba(0,0,0,.04) !important; }
    .import-preview-page .badge { font-size: 0.8em; padding: .4em .65em; }

    .status-novo { background-color: var(--bs-success-bg-subtle) !important; color: var(--bs-success-text-emphasis) !important; border: 1px solid var(--bs-success-border-subtle) !important; }
    .status-existente { background-color: var(--bs-warning-bg-subtle) !important; color: var(--bs-warning-text-emphasis) !important; border: 1px solid var(--bs-warning-border-subtle) !important; }
    .status-erro { background-color: var(--bs-danger-bg-subtle) !important; color: var(--bs-danger-text-emphasis) !important; border: 1px solid var(--bs-danger-border-subtle) !important; }

    .actions-footer {
        background-color: #f8f9fa; /* .bg-light */
        border-top: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }
    .import-preview-page .form-check-input { margin-top: 0.1em; } /* Alinhar melhor os checkboxes */
</style>

<div class="container-fluid import-preview-page py-3">
    <div class="page-header">
        <h1 class="h2 fw-bold"><i class="fas fa-file-import me-2 text-primary"></i>Revisar Importação de Requisitos</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small bg-transparent p-0 m-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/dashboard_admin.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php">Requisitos Globais</a></li>
                <li class="breadcrumb-item active" aria-current="page">Confirmação da Importação</li>
            </ol>
        </nav>
    </div>

    <div class="alert file-info-alert shadow-sm mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-file-csv fa-3x me-3 opacity-75"></i>
            <div>
                <h4 class="alert-heading mb-1">Arquivo: <strong><?= htmlspecialchars($nome_arquivo_original_sess) ?></strong></h4>
                <p class="mb-1">Analise os dados extraídos do seu arquivo CSV. Selecione os itens válidos que deseja importar ou atualizar.
                <br><small><i class="fas fa-info-circle fa-xs"></i> Itens marcados com <span class="badge status-erro px-1 py-0">Erro Análise</span> serão ignorados no processamento.</small></p>
            </div>
        </div>
    </div>

    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="form-confirmar-importacao-req" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token_confirmacao_sess) ?>">
        <input type="hidden" name="action" value="process_selection">

        <div class="card shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-body-tertiary py-2 border-bottom d-flex justify-content-between align-items-center flex-wrap">
                <div class="form-check form-check-inline form-switch my-1">
                    <input class="form-check-input" type="checkbox" role="switch" id="selecionar-todos-req" title="Selecionar/Deselecionar Todos os Itens Válidos">
                    <label class="form-check-label fw-semibold text-dark-emphasis small" for="selecionar-todos-req">Selecionar/Deselecionar Válidos</label>
                </div>
                <span class="badge bg-dark-subtle text-dark-emphasis rounded-pill small py-1 px-2 my-1">
                    <?= count($dados_para_preview_sess) ?> linhas analisadas
                </span>
            </div>

            <div class="preview-table-container">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="sticky-table-header">
                        <tr>
                            <th class="text-center">Sel.</th>
                            <th class="text-center"># Linha</th>
                            <th>Status Análise</th>
                            <th class="text-center">Atualizar? <i class="fas fa-question-circle text-primary fa-xs" title="Marque para atualizar um item existente (identificado pelo Código do Requisito)."></i></th>
                            <?php foreach ($cabecalho_original_sess as $col_h_original_val): ?>
                                <th><?= htmlspecialchars($col_h_original_val) ?></th>
                            <?php endforeach; ?>
                            <th style="min-width: 200px;">Observações / Erros</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dados_para_preview_sess)): ?>
                            <tr><td colspan="<?= count($cabecalho_original_sess) + 5 ?>" class="text-center text-muted p-5"><i class="fas fa-folder-open fa-3x mb-3 d-block text-light-emphasis"></i>Nenhum dado para pré-visualizar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dados_para_preview_sess as $index_item_val => $item_data_val):
                                $temErrosItemAnalisado = !empty($item_data_val['errors']);
                                $statusDoItemAnalisado = $item_data_val['status'] ?? 'erro';
                                $idDoItemExistente = $item_data_val['id_existente'] ?? null;

                                $statusItemBadgeClass = 'status-erro';
                                $statusItemTexto = 'Erro Análise';
                                $linhaItemClassesHover = 'table-danger-hover-custom';

                                if (!$temErrosItemAnalisado) {
                                    if ($statusDoItemAnalisado === 'novo') { $statusItemBadgeClass = 'status-novo'; $statusItemTexto = 'Novo Requisito'; $linhaItemClassesHover = 'table-success-hover-custom'; }
                                    elseif ($statusDoItemAnalisado === 'existente') { $statusItemBadgeClass = 'status-existente'; $statusItemTexto = 'Existente no BD'; $linhaItemClassesHover = 'table-warning-hover-custom';}
                                }
                            ?>
                                <tr class="<?= $temErrosItemAnalisado ? 'opacity-75' : '' ?> <?= $linhaItemClassesHover ?>">
                                    <td class="text-center align-middle">
                                        <?php if (!$temErrosItemAnalisado && $statusDoItemAnalisado !== 'erro'): ?>
                                            <input type="checkbox" name="selecionar[]" value="<?= $index_item_val ?>" class="selecionar-item-req form-check-input">
                                        <?php else: echo '<i class="fas fa-ban text-danger" title="Item com erro, não pode ser selecionado"></i>'; endif; ?>
                                    </td>
                                    <td class="text-center align-middle fw-medium text-secondary"><?= htmlspecialchars($item_data_val['linha_num']) ?></td>
                                    <td class="align-middle">
                                        <span class="badge rounded-pill <?= $statusItemBadgeClass ?> status-col-badge"><?= $statusItemTexto ?></span>
                                        <?php if ($statusDoItemAnalisado === 'existente' && $idDoItemExistente && !$temErrosItemAnalisado): ?>
                                            <small class="text-muted d-block fst-italic">(ID no BD: <?= htmlspecialchars($idDoItemExistente) ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php if ($statusDoItemAnalisado === 'existente' && !$temErrosItemAnalisado): ?>
                                            <div class="form-check form-switch d-flex justify-content-center">
                                              <input type="checkbox" name="atualizar[]" value="<?= $index_item_val ?>" class="atualizar-item-req form-check-input" role="switch" id="update-req-<?= $index_item_val ?>">
                                              <label class="visually-hidden" for="update-req-<?= $index_item_val ?>">Marcar para Atualizar</label>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php
                                    $rawDataDaLinha = $item_data_val['raw_data'] ?? [];
                                    foreach ($cabecalho_original_sess as $indice_col_cab => $nome_col_cab_original):
                                        $valorColunaRaw = $rawDataDaLinha[$indice_col_cab] ?? '';
                                    ?>
                                        <td class="align-middle" title="<?= htmlspecialchars($valorColunaRaw) ?>">
                                            <?= htmlspecialchars(mb_strimwidth($valorColunaRaw, 0, 30, "...")) ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="align-middle" style="font-size: 0.8em;">
                                        <?php if ($temErrosItemAnalisado): ?>
                                            <ul class="list-unstyled text-danger-emphasis mb-0">
                                                <?php foreach ($item_data_val['errors'] as $erro_msg_item): ?>
                                                    <li title="<?= htmlspecialchars($erro_msg_item) ?>"><i class="fas fa-times-circle fa-xs me-1"></i><?= htmlspecialchars(mb_strimwidth($erro_msg_item, 0, 60, "...")) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif ($statusDoItemAnalisado === 'existente') : ?>
                                            <span class="text-body-secondary fst-italic"><i class="fas fa-info-circle fa-xs me-1"></i>Código já existe. Marque "Atualizar?" para sobrescrever.</span>
                                        <?php else: ?>
                                            <span class="text-success-emphasis fst-italic"><i class="fas fa-check-circle fa-xs me-1"></i>Pronto para ser importado.</span>
                                        <?php endif; ?>
                                        <?php
                                            if (!$temErrosItemAnalisado && isset($item_data_val['data']['disponibilidade_planos_ids']) && is_array($item_data_val['data']['disponibilidade_planos_ids']) && !empty($item_data_val['data']['disponibilidade_planos_ids'])) {
                                                $nomesPlanosItem = array_map(function($pid) use ($planos_disponiveis_map) {
                                                    return htmlspecialchars($planos_disponiveis_map[$pid] ?? "ID Plano:{$pid}");
                                                }, $item_data_val['data']['disponibilidade_planos_ids']);
                                                echo '<div class="mt-1 text-primary" style="font-size:0.95em;"><i class="fas fa-layer-group fa-xs me-1"></i><strong class="me-1 small">Planos:</strong> ' . implode(', ', $nomesPlanosItem) . '</div>';
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end py-3 actions-footer">
                <a href="<?= BASE_URL ?>admin/requisitos/requisitos_index.php" class="btn btn-outline-secondary rounded-pill px-4 me-2 btn-sm" onclick="return confirm('Tem certeza que deseja cancelar a importação? Todos os dados analisados serão perdidos.');"><i class="fas fa-times me-1"></i>Cancelar Importação</a>
                <button type="submit" class="btn btn-primary rounded-pill px-4 btn-sm" id="btn-processar-selecao" onclick="return confirm('Confirmar o processamento dos itens selecionados? Esta ação pode criar e/ou atualizar múltiplos registros no banco de dados.');">
                    <i class="fas fa-cogs me-1"></i>Confirmar e Processar Selecionados
                </button>
            </div>
        </div>
    </form>
</div>

<?php /* JavaScript (MESMO DE ANTES, MAS AJUSTADO) */ ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectAllCheckbox = document.getElementById('selecionar-todos-req');
    const itemCheckboxes = document.querySelectorAll('.selecionar-item-req');
    const formConfirm = document.getElementById('form-confirmar-importacao-req');

    if (selectAllCheckbox && itemCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', (e) => {
            itemCheckboxes.forEach(cb => {
                if (!cb.disabled) { 
                    cb.checked = e.target.checked;
                }
            });
        });
        itemCheckboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                let allValidEnabledChecked = true;
                itemCheckboxes.forEach(otherCb => {
                    if (!otherCb.disabled && !otherCb.checked) {
                        allValidEnabledChecked = false;
                    }
                });
                selectAllCheckbox.checked = allValidEnabledChecked;
            });
        });
    }

    if(formConfirm){ 
        formConfirm.addEventListener('submit', function(e){
            const algumSelecionado = Array.from(itemCheckboxes).some(cb => cb.checked && !cb.disabled);
            if(!algumSelecionado && itemCheckboxes.length > 0 && document.querySelectorAll('.selecionar-item-req:not(:disabled)').length > 0){ 
                 // Apenas previne se há itens válidos para selecionar e nenhum foi selecionado
                alert("Nenhum requisito foi selecionado para importação ou atualização. Por favor, marque os itens válidos desejados na coluna 'Sel.'.");
                e.preventDefault();
            }
        });
    }
});
</script>
<?php
    echo getFooterAdmin();
    exit(); // Termina o script APÓS exibir o footer da Etapa 2
endif; // <<<<<<< FECHAMENTO DO if ($etapa === 2)

// Fallback: Se $etapa não for 2 e não for um POST para Etapa 1 ou 3,
// redireciona para a página principal de requisitos.
// Isso cobre o caso de acesso GET direto a importar_requisitos.php
if ($etapa === 1 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    definir_flash_message('info', "Para importar requisitos, por favor, selecione um arquivo CSV na página de listagem.");
    header('Location: '.BASE_URL.'admin/requisitos/requisitos_index.php');
    exit;
}
// Se o script chegar aqui, pode ser um estado inesperado.
// Opcional: Adicionar um redirecionamento final ou mensagem de erro.
// echo "Estado inesperado do script de importação.";
?>