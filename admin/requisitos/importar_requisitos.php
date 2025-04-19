<?php
// admin/requisitos/importar_requisitos.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php'; // Funções CRUD requisitos

// Proteção e Verificação de Perfil
protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') {
    http_response_code(403); exit("Acesso Negado.");
}

// Define limite de tempo e memória (importante para arquivos grandes)
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '256M');

// Variáveis para resumo da importação
$linhas_processadas = 0;
$requisitos_criados = 0;
$requisitos_atualizados = 0; // Implementar atualização opcional depois
$erros_validacao = 0;
$erros_db = 0;
$linhas_ignoradas = 0; // Ex: Cabeçalho, linhas vazias
$mensagens_erro_detalhadas = [];
$max_erros_detalhados = 20; // Limita o número de erros detalhados na sessão

// --- Processar apenas se for POST e tiver o arquivo ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['erro'] = "Erro de validação da sessão. Importação cancelada.";
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_csrf_falha', 0, 'Token CSRF inválido.', $conexao);
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenera

    // 2. Validar Arquivo de Upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $erro_upload = "Nenhum arquivo enviado ou erro no upload. Código: " . ($_FILES['csv_file']['error'] ?? 'N/A');
        $_SESSION['erro'] = "Falha no upload do arquivo CSV: " . $erro_upload;
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_upload_falha', 0, $erro_upload, $conexao);
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    }

    $caminho_arquivo = $_FILES['csv_file']['tmp_name'];
    $nome_original = $_FILES['csv_file']['name'];
    $tamanho_arquivo = $_FILES['csv_file']['size'];

    // Validação básica de tamanho e extensão
    if ($tamanho_arquivo === 0 || $tamanho_arquivo > 10 * 1024 * 1024) { // Limite de 10MB
         $_SESSION['erro'] = "Arquivo CSV vazio ou excede o limite de 10MB.";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_tamanho_invalido', 0, "Tamanho: $tamanho_arquivo", $conexao);
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    }
    $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    if ($extensao !== 'csv') {
         $_SESSION['erro'] = "Formato de arquivo inválido. Apenas arquivos .csv são permitidos.";
          dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_ext_invalida', 0, "Extensão: $extensao", $conexao);
         header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    }

    // Validação de tipo MIME (mais segura)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $caminho_arquivo);
        finfo_close($finfo);
        // Permite text/csv e application/csv, e outros que possam ocorrer
        if (strpos($mime_type, 'text') === false && strpos($mime_type, 'csv') === false) {
             $_SESSION['erro'] = "Tipo de arquivo inválido (detectado: $mime_type). Apenas CSV é permitido.";
              dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_mime_invalido', 0, "MIME: $mime_type", $conexao);
             header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
        }
    }

    // 3. Processar o Arquivo CSV
    $arquivo_handle = fopen($caminho_arquivo, 'r');
    if ($arquivo_handle === false) {
         $_SESSION['erro'] = "Não foi possível abrir o arquivo CSV enviado.";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_fopen_falha', 0, "Não abriu tmp file", $conexao);
         header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    }

    // Log de início
     dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_inicio', 1, "Iniciando importação de: $nome_original", $conexao);


    // --- Mapeamento de Colunas (Flexível) ---
    $cabecalho = fgetcsv($arquivo_handle, 0, ';'); // Assume delimitador ;
    if ($cabecalho === false) {
        fclose($arquivo_handle);
        $_SESSION['erro'] = "Arquivo CSV vazio ou não foi possível ler o cabeçalho.";
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    }

    $mapa_colunas = [];
    $colunas_esperadas = ['codigo', 'nome', 'descricao', 'categoria', 'norma_referencia', 'ativo'];
    foreach ($cabecalho as $index => $coluna) {
        $coluna_limpa = strtolower(trim(preg_replace('/[^a-z0-9_]/i', '', $coluna))); // Limpa nome da coluna
        if (in_array($coluna_limpa, $colunas_esperadas)) {
            $mapa_colunas[$coluna_limpa] = $index;
        }
    }

    // Verificar se colunas obrigatórias (nome, descricao) existem no cabeçalho
    if (!isset($mapa_colunas['nome']) || !isset($mapa_colunas['descricao'])) {
        fclose($arquivo_handle);
        $_SESSION['erro'] = "Cabeçalho do CSV inválido. As colunas 'nome' e 'descricao' são obrigatórias.";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_cabecalho_invalido', 0, "Faltando nome/descricao", $conexao);
        header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
    }

    // --- Ler e Processar Linhas ---
    $linha_num = 1; // Começa em 1 (cabeçalho já lido)
    $conexao->beginTransaction(); // Inicia transação

    try {
        while (($linha_dados = fgetcsv($arquivo_handle, 0, ';')) !== false) {
            $linha_num++;
            $linhas_processadas++;

            // Ignora linhas vazias
            if (count(array_filter($linha_dados)) == 0) {
                $linhas_ignoradas++;
                continue;
            }
            // Verifica se tem o número esperado de colunas
            if (count($linha_dados) !== count($cabecalho)) {
                 $erros_validacao++;
                 if (count($mensagens_erro_detalhadas) < $max_erros_detalhados) {
                     $mensagens_erro_detalhadas[] = "Linha $linha_num: Número incorreto de colunas.";
                 }
                 continue; // Pula para próxima linha
            }

            // Montar array associativo com dados da linha
            $dados_requisito = [];
            foreach ($mapa_colunas as $campo => $index) {
                $dados_requisito[$campo] = trim($linha_dados[$index] ?? '');
            }

            // --- Validação dos Dados da Linha ---
            $erros_linha = [];
            if (empty($dados_requisito['nome'])) $erros_linha[] = "'nome' obrigatório";
            if (empty($dados_requisito['descricao'])) $erros_linha[] = "'descricao' obrigatória";

            // Validar 'ativo' (1, 0, Sim, Nao, S, N - case-insensitive)
            if (isset($dados_requisito['ativo'])) {
                 $ativo_lower = strtolower($dados_requisito['ativo']);
                 if (in_array($ativo_lower, ['1', 'sim', 's', 'true', 'ativo'])) {
                     $dados_requisito['ativo'] = 1;
                 } elseif (in_array($ativo_lower, ['0', 'nao', 'n', 'não', 'false', 'inativo'])) {
                    $dados_requisito['ativo'] = 0;
                 } else {
                     $erros_linha[] = "'ativo' inválido (usar 1/0, Sim/Nao)";
                 }
            } else {
                 $dados_requisito['ativo'] = 1; // Default para ativo se coluna não existir ou vazia
            }

            // Validar código (se presente)
            if (!empty($dados_requisito['codigo']) && strlen($dados_requisito['codigo']) > 50) {
                 $erros_linha[] = "'codigo' excede 50 caracteres";
            }
            // Outras validações de tamanho, formato, etc.

            if (!empty($erros_linha)) {
                $erros_validacao++;
                 if (count($mensagens_erro_detalhadas) < $max_erros_detalhados) {
                    $mensagens_erro_detalhadas[] = "Linha $linha_num: " . implode(', ', $erros_linha);
                 }
                continue; // Pula para próxima linha
            }

            // --- Inserir ou Atualizar (Estratégia: Atualizar se código existir, senão Inserir) ---
            $requisito_existente = null;
            if (!empty($dados_requisito['codigo'])) {
                $requisito_existente = getRequisitoAuditoriaPorCodigo($conexao, $dados_requisito['codigo']);
            }

            if ($requisito_existente) {
                 // ** ATUALIZAR ** (Opcional - por enquanto vamos só inserir)
                 // $resultado_db = atualizarRequisitoAuditoria($conexao, $requisito_existente['id'], $dados_requisito, $_SESSION['usuario_id']);
                 // if($resultado_db === true) $requisitos_atualizados++; else { ... }
                 $linhas_ignoradas++; // Por enquanto, ignora se código já existe
                 if (count($mensagens_erro_detalhadas) < $max_erros_detalhados) {
                     $mensagens_erro_detalhadas[] = "Linha $linha_num: Código '{$dados_requisito['codigo']}' já existe, atualização não implementada (ignorado).";
                 }

            } else {
                // ** INSERIR **
                $resultado_db = criarRequisitoAuditoria($conexao, $dados_requisito, $_SESSION['usuario_id']);
                if ($resultado_db === true) {
                    $requisitos_criados++;
                } else {
                    $erros_db++;
                    if (count($mensagens_erro_detalhadas) < $max_erros_detalhados) {
                         $mensagens_erro_detalhadas[] = "Linha $linha_num: Erro DB ao criar - $resultado_db";
                    }
                     // Considerar parar a importação em caso de erro DB? Ou apenas logar?
                     // Por enquanto, continua, mas loga o erro.
                }
            }

        } // Fim while fgetcsv

        // Se chegou aqui sem lançar exceção, commita a transação
        $conexao->commit();
        $_SESSION['sucesso'] = "Importação concluída! Linhas processadas: $linhas_processadas. Criados: $requisitos_criados. Erros Validação: $erros_validacao. Erros DB: $erros_db. Ignorados/Atualizar: $linhas_ignoradas.";
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_sucesso', 1, "Importado: $nome_original. Resumo: $requisitos_criados criados, $erros_validacao valid, $erros_db db.", $conexao);


    } catch (Exception $e) { // Pega exceções gerais ou do DB
        $conexao->rollBack(); // Desfaz a transação
        $_SESSION['erro'] = "Erro crítico durante a importação na linha $linha_num: " . $e->getMessage();
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'importar_req_erro_critico', 0, "Erro: " . $e->getMessage(), $conexao);

    } finally {
        fclose($arquivo_handle); // Garante que o arquivo seja fechado
    }

    // Armazena erros detalhados na sessão (se houver)
    if(!empty($mensagens_erro_detalhadas)) {
        $_SESSION['importar_req_erros_detalhes'] = $mensagens_erro_detalhadas;
        if ( ($erros_validacao + $erros_db) > $max_erros_detalhados) {
             $_SESSION['importar_req_erros_detalhes'][] = "... (mais erros ocorreram, mas não foram listados)";
        }
    }


} else {
    // Se não for POST, redireciona
    $_SESSION['erro'] = "Método inválido para importação.";
     header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;
}

// Redireciona de volta para a página de listagem
 header('Location: ' . BASE_URL . 'admin/requisitos/requisitos_index.php'); exit;

?>