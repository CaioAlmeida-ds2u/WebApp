<?php
// includes/funcoes_upload.php

/**
 * Valida uma imagem enviada por upload, verificando erros, tamanho e tipo MIME real.
 */
function processarUploadFoto(array $imagem, int $usuarioId, PDO $conexao, string $uploadDir = 'uploads/fotos/'): array {

    // --- 1. Validação Inicial ---
    $erroValidacao = validarImagem($imagem); // Usa a função de validação aprimorada
    if ($erroValidacao !== null) {
        // Se for UPLOAD_ERR_NO_FILE, não é um erro real para esta função, apenas não faz nada.
        if ($imagem['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'message' => 'Nenhuma nova foto enviada.', 'caminho' => null];
        }
        // Se for outro erro de validação, retorna falha.
        return ['success' => false, 'message' => $erroValidacao, 'caminho' => null];
    }

    // --- 2. Preparar Diretório e Nome do Arquivo ---
    // Caminho absoluto para o diretório de upload
    $caminhoAbsolutoDir = __DIR__ . '/../' . trim($uploadDir, '/\\') . '/'; // Garante barra no final

    // Criar o diretório se não existir
    if (!is_dir($caminhoAbsolutoDir)) {
        // Tenta criar recursivamente com permissões adequadas
        if (!mkdir($caminhoAbsolutoDir, 0755, true)) {
            error_log("Falha ao criar diretório de upload: " . $caminhoAbsolutoDir);
            return ['success' => false, 'message' => 'Erro no servidor: não foi possível preparar o local para salvar a foto.', 'caminho' => null];
        }
    }
     // Verificar se o diretório tem permissão de escrita
     if (!is_writable($caminhoAbsolutoDir)) {
        error_log("Diretório de upload sem permissão de escrita: " . $caminhoAbsolutoDir);
        return ['success' => false, 'message' => 'Erro no servidor: permissão negada para salvar a foto.', 'caminho' => null];
     }


    // Gerar um nome de arquivo seguro e único
    $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
    // Limpar a extensão para evitar manipulação (ex: .php.jpg) - Pega apenas a última extensão
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($extensao, $allowedExts)) {
         // Isso não deveria acontecer se validarImagem funcionou, mas é uma dupla checagem.
         return ['success' => false, 'message' => 'Extensão de arquivo inválida após validação.', 'caminho' => null];
    }
    // Nome único: user_ID_timestamp_aleatorio.extensao
    $nomeArquivoUnico = 'user_' . $usuarioId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
    $caminhoCompletoDestino = $caminhoAbsolutoDir . $nomeArquivoUnico;

    // --- 3. Obter Foto Antiga (Antes de Mover/Salvar) ---
    $fotoAntiga = null;
    try {
        $stmtFotoAntiga = $conexao->prepare("SELECT foto FROM usuarios WHERE id = :id");
        $stmtFotoAntiga->bindParam(':id', $usuarioId, PDO::PARAM_INT);
        $stmtFotoAntiga->execute();
        $fotoAntiga = $stmtFotoAntiga->fetchColumn(); // Retorna nome do arquivo ou false/null
    } catch (PDOException $e) {
        error_log("Erro ao buscar foto antiga para usuário ID $usuarioId: " . $e->getMessage());
        // Decide se continua ou não. Continuar pode deixar fotos antigas órfãs.
        // Vamos retornar erro aqui para ser mais seguro.
         return ['success' => false, 'message' => 'Erro ao verificar foto existente.', 'caminho' => null];
    }


    // --- 4. Mover o Arquivo Enviado ---
    if (move_uploaded_file($imagem['tmp_name'], $caminhoCompletoDestino)) {

        // --- 5. Atualizar Banco de Dados ---
        try {
            $stmtUpdate = $conexao->prepare("UPDATE usuarios SET foto = :foto WHERE id = :id");
            $stmtUpdate->bindParam(':foto', $nomeArquivoUnico); // Salva APENAS o nome do arquivo
            $stmtUpdate->bindParam(':id', $usuarioId, PDO::PARAM_INT);
            $stmtUpdate->execute();

            if ($stmtUpdate->rowCount() >= 0) { // Sucesso mesmo se não alterou (já era essa foto?)
                // --- 6. Excluir Foto Antiga (Se existir e for diferente) ---
                if ($fotoAntiga && $fotoAntiga != $nomeArquivoUnico) {
                    $caminhoFotoAntigaAbs = $caminhoAbsolutoDir . $fotoAntiga;
                    if (file_exists($caminhoFotoAntigaAbs)) {
                        if (!unlink($caminhoFotoAntigaAbs)) {
                            // Loga o erro, mas não impede o sucesso geral da operação
                            error_log("AVISO: Não foi possível excluir a foto antiga: " . $caminhoFotoAntigaAbs);
                        }
                    }
                }

                // --- 7. Atualizar Sessão ---
                 // Salva o caminho RELATIVO à raiz do projeto na sessão
                $_SESSION['foto'] = trim($uploadDir, '/\\') . '/' . $nomeArquivoUnico;

                // --- Sucesso Total ---
                return ['success' => true, 'message' => 'Foto de perfil atualizada com sucesso!', 'caminho' => $nomeArquivoUnico];

            } else {
                 // Erro específico do rowCount (raro no update sem where complexo)
                 throw new PDOException("Nenhuma linha afetada ao atualizar a foto no banco.");
            }

        } catch (PDOException $e) {
             error_log("Erro ao atualizar DB com nova foto para usuário ID $usuarioId: " . $e->getMessage());
             // Se falhou ao salvar no DB, TENTA excluir a foto que acabou de ser movida para não deixar lixo
             if (file_exists($caminhoCompletoDestino)) {
                 @unlink($caminhoCompletoDestino);
             }
             return ['success' => false, 'message' => 'Erro ao salvar a referência da foto.', 'caminho' => null];
        }

    } else {
        // Erro no move_uploaded_file
        error_log("Falha em move_uploaded_file para '$caminhoCompletoDestino'");
        return ['success' => false, 'message' => 'Erro ao mover o arquivo da foto para o destino final.', 'caminho' => null];
    }
}

// includes/funcoes_upload.php - COM MAIS LOGS

/**
 * Valida uma imagem enviada por upload...
 */
function validarImagem(array $imagem, int $maxSize = 5 * 1024 * 1024, array $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']): ?string { // Adicionado SVG
    error_log("--- Iniciando validarImagem ---"); // Log início

    if (!isset($imagem['error']) || is_array($imagem['error'])) {
        error_log("validarImagem: Erro - Parâmetros inválidos.");
        return "Parâmetros de upload inválidos.";
    }

    // ... (switch case para $imagem['error'] - sem mudanças nos logs aqui) ...
     switch ($imagem['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: error_log("validarImagem: Info - Nenhum arquivo enviado."); return null; // Não é erro aqui
        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: error_log("validarImagem: Erro - Tamanho excedido (UPLOAD_ERR)"); return "O arquivo excede o limite de tamanho.";
        // ... outros cases ...
        default: error_log("validarImagem: Erro - Código upload desconhecido: " . $imagem['error']); return "Erro desconhecido no upload.";
    }


    error_log("validarImagem: Verificando tamanho: {$imagem['size']} bytes (Max: $maxSize)");
    if ($imagem['size'] > $maxSize) {
        error_log("validarImagem: Erro - Tamanho excedido.");
        return "O arquivo é muito grande (Máx: " . ($maxSize / 1024 / 1024) . "MB).";
    }
     if ($imagem['size'] === 0) {
         error_log("validarImagem: Erro - Arquivo vazio.");
         return "O arquivo enviado está vazio.";
     }

    error_log("validarImagem: Verificando com getimagesize: {$imagem['tmp_name']}");
    $imageInfo = @getimagesize($imagem['tmp_name']);
    if ($imageInfo === false) {
        error_log("validarImagem: Erro - getimagesize falhou ou não é imagem.");
        // Tentar finfo mesmo assim, pode ser SVG
        // return "O arquivo enviado não parece ser uma imagem válida ou está corrompido.";
    }

    error_log("validarImagem: Verificando tipo MIME real.");
    $mimeTypeReal = null; // Inicializa
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeReal = finfo_file($finfo, $imagem['tmp_name']);
        finfo_close($finfo);
        error_log("validarImagem: Tipo MIME detectado (finfo): $mimeTypeReal");
    } elseif ($imageInfo && isset($imageInfo['mime'])) {
        $mimeTypeReal = $imageInfo['mime'];
        error_log("validarImagem: Tipo MIME detectado (getimagesize): $mimeTypeReal");
    } else {
         error_log("validarImagem: Erro - Não foi possível determinar o tipo MIME real.");
         return "Não foi possível verificar o tipo do arquivo.";
    }


    if ($mimeTypeReal === null || !in_array(strtolower($mimeTypeReal), array_map('strtolower', $tiposPermitidos))) {
        error_log("validarImagem: Erro - Tipo MIME '$mimeTypeReal' não está na lista de permitidos.");
        return "Tipo de arquivo inválido. Permitidos: JPG, PNG, GIF, SVG. (Detectado: " . htmlspecialchars($mimeTypeReal ?? 'N/A') .")";
    }

    error_log("validarImagem: Validação concluída com sucesso.");
    return null; // Válido
}

/**
 * Processa o upload de um logo de empresa...
 */
function processarUploadLogoEmpresa(
    array $logoFile,
    int $empresaId,
    PDO $conexao,
    ?string $logoAntigo = null,
    string $uploadDir = 'uploads/logos/'
): array {
    error_log("--- Iniciando processarUploadLogoEmpresa para Empresa ID: $empresaId ---");

    // 1. Validação Inicial
    $erroValidacao = validarImagem($logoFile, 2 * 1024 * 1024, ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']);
    if ($erroValidacao !== null) {
        // UPLOAD_ERR_NO_FILE é tratado aqui para retornar sucesso sem fazer nada
        if (isset($logoFile['error']) && $logoFile['error'] === UPLOAD_ERR_NO_FILE) {
            error_log("processarUploadLogoEmpresa: Info - Nenhum arquivo novo enviado.");
            return ['success' => true, 'message' => 'Nenhum novo logo enviado.', 'nome_arquivo' => null];
        }
        error_log("processarUploadLogoEmpresa: Falha na validação interna: $erroValidacao");
         dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'upload_logo_falha_valid', 0, "Empresa ID: $empresaId, Erro: $erroValidacao", $conexao);
        return ['success' => false, 'message' => $erroValidacao, 'nome_arquivo' => null];
    }

    // 2. Preparar Diretório e Nome
    $caminhoAbsolutoDir = __DIR__ . '/../' . trim($uploadDir, '/\\') . '/';
    error_log("processarUploadLogoEmpresa: Diretório de destino: $caminhoAbsolutoDir");
    if (!is_dir($caminhoAbsolutoDir)) {
        error_log("processarUploadLogoEmpresa: Criando diretório...");
        if (!mkdir($caminhoAbsolutoDir, 0755, true)) { /* ... erro criar dir ... */ return ['success' => false, 'message' => 'Erro servidor (dir).', 'nome_arquivo' => null];}
    }
    if (!is_writable($caminhoAbsolutoDir)) { error_log("processarUploadLogoEmpresa: Erro - Diretório sem permissão de escrita."); return ['success' => false, 'message' => 'Erro servidor (perm).', 'nome_arquivo' => null]; }

    $extensao = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    if (!in_array($extensao, $allowedExts)) { error_log("processarUploadLogoEmpresa: Erro - Extensão '$extensao' inválida (pós-validação)."); return ['success' => false, 'message' => 'Extensão inválida.', 'nome_arquivo' => null]; }
    $nomeArquivoUnico = 'logo_empresa_' . $empresaId . '_' . time() . '.' . $extensao;
    $caminhoCompletoDestino = $caminhoAbsolutoDir . $nomeArquivoUnico;
    error_log("processarUploadLogoEmpresa: Nome do arquivo final: $nomeArquivoUnico");
    error_log("processarUploadLogoEmpresa: Caminho completo final: $caminhoCompletoDestino");

    // 3. Mover o Arquivo
    error_log("processarUploadLogoEmpresa: Tentando mover '{$logoFile['tmp_name']}' para '$caminhoCompletoDestino'");
    if (move_uploaded_file($logoFile['tmp_name'], $caminhoCompletoDestino)) {
        error_log("processarUploadLogoEmpresa: Arquivo movido com sucesso.");
        // 4. Excluir Logo Antigo
        if ($logoAntigo) {
            $caminhoLogoAntigoAbs = $caminhoAbsolutoDir . $logoAntigo;
            error_log("processarUploadLogoEmpresa: Verificando logo antigo: $caminhoLogoAntigoAbs");
            if (file_exists($caminhoLogoAntigoAbs)) {
                error_log("processarUploadLogoEmpresa: Tentando excluir logo antigo...");
                if (!@unlink($caminhoLogoAntigoAbs)) { // @ para suprimir warning se falhar
                    error_log("processarUploadLogoEmpresa: AVISO - Falha ao excluir logo antigo: $caminhoLogoAntigoAbs");
                } else {
                     error_log("processarUploadLogoEmpresa: Logo antigo excluído.");
                }
            } else {
                 error_log("processarUploadLogoEmpresa: Logo antigo não encontrado para excluir.");
            }
        } else {
             error_log("processarUploadLogoEmpresa: Nenhum logo antigo para excluir.");
        }
         dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'upload_logo_sucesso', 1, "Empresa ID: $empresaId, Arquivo: $nomeArquivoUnico", $conexao);
        return ['success' => true, 'message' => 'Logo enviado com sucesso!', 'nome_arquivo' => $nomeArquivoUnico];
    } else {
        // Verifica o erro específico do move_uploaded_file
        $move_error_code = $logoFile['error']; // Pega o código de erro original
        $php_error = error_get_last(); // Pega o último erro PHP
        error_log("processarUploadLogoEmpresa: Erro - Falha em move_uploaded_file. Código de erro: $move_error_code. Último erro PHP: " . print_r($php_error, true));
        dbRegistrarLogAcesso(null, $_SERVER['REMOTE_ADDR'], 'upload_logo_falha_move', 0, "Empresa ID: $empresaId, Erro Code: $move_error_code", $conexao);
        return ['success' => false, 'message' => 'Erro ao salvar o arquivo de logo (verifique permissões e limites).', 'nome_arquivo' => null];
    }
}