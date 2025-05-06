<?php
// includes/funcoes_upload.php

/**
 * Valida uma imagem enviada por upload, verificando erros, tamanho e tipo MIME real.
 *
 * @param array $imagem Array $_FILES['nome_do_campo_file'] para UM arquivo.
 * @param int $maxSize Tamanho máximo permitido em bytes.
 * @param array $tiposPermitidos Array de strings, tipos MIME permitidos (ex: ['image/jpeg', 'image/png']).
 * @return string|null Retorna uma string de erro ou null se o arquivo é válido (ignora UPLOAD_ERR_NO_FILE).
 */
function validarImagem(array $imagem, int $maxSize = 5 * 1024 * 1024, array $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']): ?string {
    // Removidos logs excessivos em prod

    if (!isset($imagem['error']) || is_array($imagem['error'])) {
        return "Parâmetros de upload inválidos.";
    }

    switch ($imagem['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: return null; // Nenhum arquivo enviado não é um erro de validação aqui
        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: error_log("validarImagem: Erro - Tamanho excedido (UPLOAD_ERR) p/ {$imagem['name']}"); return "O arquivo excede o limite de tamanho permitido pelo servidor.";
        case UPLOAD_ERR_PARTIAL: error_log("validarImagem: Erro - Upload parcial p/ {$imagem['name']}"); return "O upload do arquivo foi incompleto.";
        case UPLOAD_ERR_NO_TMP_DIR: error_log("validarImagem: Erro - Diretório temp ausente p/ {$imagem['name']}"); return "Erro servidor: Falta um diretório temporário para upload.";
        case UPLOAD_ERR_CANT_WRITE: error_log("validarImagem: Erro - Falha ao escrever disco p/ {$imagem['name']}"); return "Erro servidor: Falha ao salvar o arquivo em disco.";
        case UPLOAD_ERR_EXTENSION: error_log("validarImagem: Erro - Upload interrompido por extensão p/ {$imagem['name']}"); return "O upload do arquivo foi bloqueado.";
        default: error_log("validarImagem: Erro - Código upload desconhecido: {$imagem['error']} p/ {$imagem['name']}"); return "Erro desconhecido no upload.";
    }

    if ($imagem['size'] > $maxSize) {
        return "O arquivo é muito grande (Máx: " . ($maxSize / 1024 / 1024) . "MB).";
    }
     if ($imagem['size'] === 0) {
          // Considerar arquivo vazio como inválido para imagens de perfil/logos.
          return "O arquivo enviado está vazio.";
     }


    // Validar tipo MIME real para segurança
    $mimeTypeReal = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeReal = @finfo_file($finfo, $imagem['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('getimagesize') && ($imageInfo = @getimagesize($imagem['tmp_name'])) !== false) {
        $mimeTypeReal = $imageInfo['mime'];
    }


    $allowedTypesLower = array_map('strtolower', $tiposPermitidos);
    $mimeTypeRealLower = strtolower($mimeTypeReal ?? '');

    if ($mimeTypeRealLower === '' && $imagem['size'] > 0 && $imagem['error'] === UPLOAD_ERR_OK) {
         // Se não detectou MIME mas o arquivo não é vazio e upload ok, tentar verificar por extensão popular
          $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
         $commonImageExts = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
          if (in_array($extensao, $commonImageExts)) {
              // Pode ser um MIME genérico, mas a extensão é de imagem. Permite.
              // log "validarImagem: Aprovado - MIME desconhecido/genérico, mas extensão comum permitida '$extensao'."
              return null;
          }
     }

    if ($mimeTypeRealLower === null || !in_array($mimeTypeRealLower, $allowedTypesLower)) {
        // Verifica se é application/octet-stream E a extensão é uma das permitidas (workaround)
        if ($mimeTypeRealLower === 'application/octet-stream') {
            $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
            $isAllowedByExt = false;
             foreach ($allowedTypesLower as $allowedMime) {
                  $commonExts = []; // Mapa simples mime -> extensoes comuns
                  if (str_contains($allowedMime, 'jpeg')) $commonExts[] = 'jpg'; $commonExts[] = 'jpeg';
                  if (str_contains($allowedMime, 'png')) $commonExts[] = 'png';
                  if (str_contains($allowedMime, 'gif')) $commonExts[] = 'gif';
                  if (str_contains($allowedMime, 'svg+xml')) $commonExts[] = 'svg';

                 if (in_array($extensao, $commonExts)) { $isAllowedByExt = true; break; }
             }
            if ($isAllowedByExt) { /* log "validarImagem: Aprovado - MIME octet-stream, extensão permitida '$extensao'."; */ return null; }
        }

        // Se não passou nos checks acima, é inválido.
        return "Tipo de arquivo não permitido. Permitidos: " . implode(', ', array_unique(array_map(function($t){
             $ext = strtoupper(pathinfo($t, PATHINFO_EXTENSION));
             if(empty($ext) && str_contains($t, '/')) $ext = strtoupper(substr($t, strpos($t, '/') + 1)); // Ex: PDF
             return $ext ?: $t;
         }, $tiposPermitidos)));
    }


    // Validado
    return null; // Válido
}


/**
 * Processa o upload de uma foto de perfil.
 * Move o arquivo para o destino final, atualiza o DB e remove a foto antiga.
 * @return array { success: bool, message: string, caminho: string|null } caminho é o nome do arquivo no uploadDir
 */
function processarUploadFoto(array $imagem, int $usuarioId, PDO $conexao, string $uploadDir = 'uploads/fotos/'): array {
    // Reutiliza a validação, específica para imagens de perfil (tamanho e tipos)
     $maxSizeFoto = 5 * 1024 * 1024; // 5MB
     $allowedTypesFoto = ['image/jpeg', 'image/png', 'image/gif'];

    $erroValidacao = validarImagem($imagem, $maxSizeFoto, $allowedTypesFoto);
    if ($erroValidacao !== null) {
        if (isset($imagem['error']) && $imagem['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'message' => 'Nenhuma nova foto enviada.', 'caminho' => null];
        }
        return ['success' => false, 'message' => $erroValidacao, 'caminho' => null];
    }
     if ($imagem['error'] !== UPLOAD_ERR_OK) { // Checar UPLOAD_ERR_OK aqui de novo após validarImagem
          return ['success' => false, 'message' => 'Erro interno no upload (código: ' . $imagem['error'] . ')', 'caminho' => null];
     }


    // Preparar Diretório
    $caminhoAbsolutoDir = __DIR__ . '/../' . trim($uploadDir, '/\\') . '/';
    if (!is_dir($caminhoAbsolutoDir)) {
         if (!mkdir($caminhoAbsolutoDir, 0755, true)) { error_log("processarUploadFoto: Falha ao criar dir: " . $caminhoAbsolutoDir); return ['success' => false, 'message' => 'Erro servidor (criação dir).', 'caminho' => null]; }
    }
    if (!is_writable($caminhoAbsolutoDir)) { error_log("processarUploadFoto: Falha: Diretório sem permissão escrita: " . $caminhoAbsolutoDir); return ['success' => false, 'message' => 'Erro servidor (permissão dir).', 'caminho' => null]; }

    // Gerar nome seguro e único (baseado no ID do usuário e timestamp)
    $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
     if (!in_array($extensao, $allowedExts)) { // Checagem extra de extensão após validarImagem
         error_log("processarUploadFoto: Erro - Extensão final '$extensao' não permitida.");
         return ['success' => false, 'message' => 'Extensão de arquivo inválida após validação interna.', 'caminho' => null];
     }
    $nomeArquivoUnico = 'user_' . $usuarioId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
    $caminhoCompletoDestino = $caminhoAbsolutoDir . $nomeArquivoUnico;

    // Obter Foto Antiga ANTES de Mover/Salvar DB
     $fotoAntiga = null;
     try {
         $stmtFotoAntiga = $conexao->prepare("SELECT foto FROM usuarios WHERE id = :id");
         $stmtFotoAntiga->bindParam(':id', $usuarioId, PDO::PARAM_INT);
         $stmtFotoAntiga->execute();
         $fotoAntiga = $stmtFotoAntiga->fetchColumn(); // Retorna nome do arquivo ou false/null
     } catch (PDOException $e) {
         error_log("processarUploadFoto: Erro DB buscando foto antiga para usuário ID $usuarioId: " . $e->getMessage());
         return ['success' => false, 'message' => 'Erro ao verificar foto existente do usuário.', 'caminho' => null];
     }


    // Mover o Arquivo
    if (move_uploaded_file($imagem['tmp_name'], $caminhoCompletoDestino)) {
        // Atualizar Banco de Dados
        try {
            $stmtUpdate = $conexao->prepare("UPDATE usuarios SET foto = :foto WHERE id = :id");
            $stmtUpdate->bindParam(':foto', $nomeArquivoUnico); // Salva APENAS o nome do arquivo
            $stmtUpdate->bindParam(':id', $usuarioId, PDO::PARAM_INT);
            $stmtUpdate->execute();

            if ($stmtUpdate->rowCount() >= 0) { // Sucesso mesmo se não alterou (já era essa foto?)
                // Excluir Foto Antiga (Se existir e for diferente)
                 if ($fotoAntiga && $fotoAntiga !== $nomeArquivoUnico) { // Use !== para garantir tipos
                    $caminhoFotoAntigaAbs = $caminhoAbsolutoDir . $fotoAntiga;
                    if (file_exists($caminhoFotoAntigaAbs)) {
                         if (!@unlink($caminhoFotoAntigaAbs)) { error_log("processarUploadFoto: AVISO: Falha ao excluir a foto antiga (permissão?): " . $caminhoFotoAntigaAbs); }
                    }
                }

                // Atualizar Sessão
                $_SESSION['foto'] = trim($uploadDir, '/\\') . '/' . $nomeArquivoUnico;

                // Sucesso Total
                return ['success' => true, 'message' => 'Foto de perfil atualizada com sucesso!', 'caminho' => $nomeArquivoUnico];

            } else {
                 throw new Exception("Erro interno ao verificar atualização no banco de dados.");
            }

        } catch (PDOException $e) {
             error_log("processarUploadFoto: Erro PDO ao atualizar foto no DB para usuário ID $usuarioId: " . $e->getMessage());
             if (file_exists($caminhoCompletoDestino)) { @unlink($caminhoCompletoDestino); } // Limpar o arquivo salvo
             return ['success' => false, 'message' => 'Erro ao salvar a referência da foto no banco de dados.', 'caminho' => null];
        } catch (Exception $e) {
             error_log("processarUploadFoto: Erro Exceção durante DB update ou cleanup: " . $e->getMessage());
             if (file_exists($caminhoCompletoDestino)) { @unlink($caminhoCompletoDestino); } // Limpar o arquivo salvo
             return ['success' => false, 'message' => 'Erro interno após upload bem-sucedido: ' . $e->getMessage(), 'caminho' => null];
        }


    } else {
        // Erro no move_uploaded_file
         $move_error_code = $imagem['error']; // Código de erro PHP UPLOAD
         $last_php_error = error_get_last(); // Último erro PHP
         error_log("processarUploadFoto: Erro move_uploaded_file. PHP UPLOAD code: $move_error_code. Último PHP Error: " . json_encode($last_php_error));
        return ['success' => false, 'message' => 'Erro ao mover o arquivo da foto. Verifique permissões.', 'caminho' => null];
    }
}

/**
 * Processa o upload de um logo de empresa.
 * Move o arquivo para o destino final, atualiza o DB e remove a logo antiga.
 * @return array { success: bool, message: string, nome_arquivo: string|null } nome_arquivo é o nome do arquivo no uploadDir
 */
function processarUploadLogoEmpresa(
    array $logoFile,
    int $empresaId,
    PDO $conexao, // Passar a conexão
    ?string $logoAntigo = null, // O nome do arquivo antigo, passado explicitamente (vem do DB ou input hidden)
    string $uploadDir = 'uploads/logos/' // Diretório de upload específico
): array {
    // Reutiliza a validação de imagem (tamanho e tipos um pouco mais flexíveis para logo talvez?)
     $maxSizeLogo = 2 * 1024 * 1024; // 2MB para logos
     $allowedTypesLogo = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']; // SVG adicionado

     // Suprimir o warning de move_uploaded_file ao final da função se ele ocorrer antes do @unlink
     $old_error_reporting = error_reporting(E_ALL & ~E_WARNING);


    $erroValidacao = validarImagem($logoFile, $maxSizeLogo, $allowedTypesLogo);
    if ($erroValidacao !== null) {
        if (isset($logoFile['error']) && $logoFile['error'] === UPLOAD_ERR_NO_FILE) {
            error_reporting($old_error_reporting); // Restaurar antes de sair
            return ['success' => true, 'message' => 'Nenhum novo logo enviado.', 'nome_arquivo' => $logoAntigo]; // Manter logo antigo no sucesso nulo
        }
         // Log específico para upload de logo
         $usuario_id_log = $_SESSION['usuario_id'] ?? null; // Pega da sessão se houver
         // Só registra log se for erro REAL (não UPLOAD_ERR_NO_FILE)
         if (isset($logoFile['error']) && $logoFile['error'] !== UPLOAD_ERR_NO_FILE) {
             dbRegistrarLogAcesso($usuario_id_log, $_SERVER['REMOTE_ADDR'], 'upload_logo_falha_valid', 0, "Empresa ID: $empresaId, File: {$logoFile['name']}, Erro: $erroValidacao", $conexao);
         }
        error_reporting($old_error_reporting); // Restaurar antes de sair
        return ['success' => false, 'message' => $erroValidacao, 'nome_arquivo' => $logoAntigo]; // Manter logo antigo em caso de falha
    }

     if ($logoFile['error'] !== UPLOAD_ERR_OK) { // Checar UPLOAD_ERR_OK aqui de novo após validarImagem
           $usuario_id_log = $_SESSION['usuario_id'] ?? null;
           dbRegistrarLogAcesso($usuario_id_log, $_SERVER['REMOTE_ADDR'], 'upload_logo_falha_php', 0, "Empresa ID: $empresaId, File: {$logoFile['name']}, Erro PHP: " . $logoFile['error'], $conexao);
           error_reporting($old_error_reporting); // Restaurar antes de sair
          return ['success' => false, 'message' => 'Erro interno no upload do arquivo (código PHP).', 'nome_arquivo' => $logoAntigo];
     }


    // Preparar Diretório
    $caminhoAbsolutoDir = __DIR__ . '/../' . trim($uploadDir, '/\\') . '/';
    if (!is_dir($caminhoAbsolutoDir)) {
         if (!mkdir($caminhoAbsolutoDir, 0755, true)) { error_log("processarUploadLogoEmpresa: Falha ao criar dir: " . $caminhoAbsolutoDir); error_reporting($old_error_reporting); return ['success' => false, 'message' => 'Erro servidor (criação dir).', 'nome_arquivo' => $logoAntigo]; }
    }
    if (!is_writable($caminhoAbsolutoDir)) { error_log("processarUploadLogoEmpresa: Falha: Diretório sem permissão escrita: " . $caminhoAbsolutoDir); error_reporting($old_error_reporting); return ['success' => false, 'message' => 'Erro servidor (permissão dir).', 'nome_arquivo' => $logoAntigo]; }


    // Gerar nome seguro e único (baseado no ID da empresa e timestamp)
    $extensao = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
     if (!in_array($extensao, $allowedExts)) { // Checagem extra
         error_log("processarUploadLogoEmpresa: Erro - Extensão final '$extensao' não permitida.");
         error_reporting($old_error_reporting); return ['success' => false, 'message' => 'Extensão inválida após validação interna.', 'nome_arquivo' => $logoAntigo];
     }

    // Nome único: logo_empresa_ID_timestamp.extensao
    $nomeArquivoUnico = 'logo_empresa_' . $empresaId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extensao; // Adicionado aleatório para mais unicidade
    $caminhoCompletoDestino = $caminhoAbsolutoDir . $nomeArquivoUnico;


    // Mover o Arquivo
     // Registrar o log de *tentativa* de move_uploaded_file pode ser útil aqui antes de tentar
    if (move_uploaded_file($logoFile['tmp_name'], $caminhoCompletoDestino)) {
        // Excluir Logo Antigo (se foi enviado um novo arquivo e move_uploaded_file foi bem sucedido)
         if ($logoAntigo) {
            $caminhoLogoAntigoAbs = $caminhoAbsolutoDir . $logoAntigo;
            if (file_exists($caminhoLogoAntigoAbs)) {
                 if (!@unlink($caminhoLogoAntigoAbs)) { error_log("processarUploadLogoEmpresa: AVISO: Falha ao excluir o logo antigo: " . $caminhoLogoAntigoAbs); }
            }
        }

        // Log de sucesso de UPLOAD
        $usuario_id_log = $_SESSION['usuario_id'] ?? null;
        dbRegistrarLogAcesso($usuario_id_log, $_SERVER['REMOTE_ADDR'], 'upload_logo_sucesso', 1, "Empresa ID: $empresaId, Arquivo: $nomeArquivoUnico", $conexao);

        error_reporting($old_error_reporting); // Restaurar antes de sair
        return ['success' => true, 'message' => 'Logo enviado com sucesso!', 'nome_arquivo' => $nomeArquivoUnico]; // Retorna nome para atualizar no DB


    } else {
        // Erro no move_uploaded_file - já logado na função validarImagem (UPLOAD_ERR_XX), mas pode ser erro de move_uploaded_file mesmo
         $move_error_code = $logoFile['error']; // Código de erro PHP UPLOAD
         $last_php_error = error_get_last(); // Último erro PHP, útil para move_uploaded_file falhas
        error_log("processarUploadLogoEmpresa: Erro move_uploaded_file. PHP UPLOAD code: $move_error_code. Último PHP Error: " . json_encode($last_php_error));
         $usuario_id_log = $_SESSION['usuario_id'] ?? null;
         dbRegistrarLogAcesso($usuario_id_log, $_SERVER['REMOTE_ADDR'], 'upload_logo_falha_move', 0, "Empresa ID: $empresaId, File: {$logoFile['name']}, Code: $move_error_code", $conexao);
        error_reporting($old_error_reporting); // Restaurar antes de sair
        return ['success' => false, 'message' => 'Erro ao mover o arquivo de logo. Verifique permissões.', 'nome_arquivo' => $logoAntigo];
    }
}


/**
 * Valida um arquivo para upload, verificando erros, tamanho e tipo MIME real.
 * Versão mais genérica que validarImagem.
 */
function validarArquivo(array $file, int $maxSize, array $allowedTypes): ?string {
    // error_log("--- Iniciando validarArquivo: {$file['name']} ---"); // Log início

    // 1. Validar estrutura básica do $_FILES
    if (!isset($file['error']) || is_array($file['error'])) {
        // error_log("validarArquivo: Erro - Parâmetros de upload inválidos.");
        return "Parâmetros de upload inválidos.";
    }

    // 2. Verificar erros de upload PHP
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE:
             return null; // Ignorar este "arquivo", não é um erro de validação aqui.
        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: error_log("validarArquivo: Erro - Tamanho excedido (UPLOAD_ERR) p/ {$file['name']}"); return "O arquivo '{$file['name']}' excede o limite de tamanho permitido pelo servidor.";
        case UPLOAD_ERR_PARTIAL: error_log("validarArquivo: Erro - Upload parcial p/ {$file['name']}"); return "O upload do arquivo '{$file['name']}' foi incompleto.";
        case UPLOAD_ERR_NO_TMP_DIR: error_log("validarArquivo: Erro - Diretório temp ausente p/ {$file['name']}"); return "Erro servidor: Falta um diretório temporário para upload.";
        case UPLOAD_ERR_CANT_WRITE: error_log("validarArquivo: Erro - Falha ao escrever disco p/ {$file['name']}"); return "Erro servidor: Falha ao salvar o arquivo '{$file['name']}' em disco.";
        case UPLOAD_ERR_EXTENSION: error_log("validarArquivo: Erro - Upload interrompido por extensão p/ {$file['name']}"); return "O upload do arquivo '{$file['name']}' foi bloqueado.";
        default: error_log("validarArquivo: Erro - Código upload desconhecido: {$file['error']} p/ {$file['name']}"); return "Erro desconhecido no upload do arquivo '{$file['name']}'.";
    }

    // 3. Validar tamanho real após verificar erros de upload PHP
     if ($file['size'] > $maxSize) {
        return "O arquivo '{$file['name']}' é muito grande (Máx: " . ($maxSize / 1024 / 1024) . "MB).";
    }
     if ($file['size'] === 0) {
          // Para documentos, arquivo vazio *pode* ser um erro.
           return "O arquivo '{$file['name']}' enviado está vazio.";
     }


    // 4. Validar tipo MIME real (finfo recomendado)
    $mimeTypeReal = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeReal = @finfo_file($finfo, $file['tmp_name']); // Suprimir warnings se finfo falhar (ex: arquivo não existe ou permissão)
        finfo_close($finfo);
    }
    // Não usar getimagesize aqui, pois não é apenas para imagens

     // error_log("validarArquivo: Tipo MIME real detectado para {$file['name']}: " . ($mimeTypeReal ?? 'N/A'));

    // Normalizar e verificar se o tipo detectado está na lista permitida
    $allowedTypesLower = array_map('strtolower', $allowedTypes);
    $mimeTypeRealLower = strtolower($mimeTypeReal ?? '');

     // Fallback/Workaround para 'application/octet-stream' ou MIME vazio + extensão comum
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
     // Mapa comum extensão -> mime (simples, não cobre tudo)
     $commonMimesFromExt = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed',
     ];
    $expectedMimeFromExt = $commonMimesFromExt[$extensao] ?? null;

     if (
         !in_array($mimeTypeRealLower, $allowedTypesLower) // MIME real não está na lista principal
     ) {
          // Se o MIME real não é permitido, checar se é um MIME genérico e se a extensão aponta para um MIME permitido
         if ( ($mimeTypeRealLower === '' || $mimeTypeRealLower === 'application/octet-stream') &&
              $expectedMimeFromExt && in_array($expectedMimeFromExt, $allowedTypesLower) )
         {
             // Aprovado por extensão: MIME genérico/vazio mas extensão válida mapeia para tipo permitido
              // error_log("validarArquivo: Aprovado por extensão para {$file['name']} - MIME: {$mimeTypeRealLower}, Ext: {$extensao}");
         } else {
              // Tipo/Extensão final inválido(s).
              // error_log("validarArquivo: Erro - Tipo MIME real '$mimeTypeReal' ou extensão '$extensao' não permitidos para {$file['name']}.");
               return "Tipo de arquivo não permitido para '{$file['name']}'.";
         }
     }

    // Validado
    // error_log("validarArquivo: Validação concluída com sucesso para {$file['name']}.");
    return null; // Válido
}

/**
 * Processa o(s) arquivo(s) de upload de documentos.
 * Valida cada arquivo individualmente e move os arquivos VÁLIDOS
 * para um diretório temporário ÚNICO por requisição.
 * Retorna metadados para posterior processamento (mover para destino final e salvar no DB).
 *
 * @param array $files Array $_FILES['nome_do_campo_file'] processado para inputs múltiplos.
 * @param string $baseTempDir O caminho base do diretório temporário (ex: 'uploads/temp/'). Deve estar abaixo da raiz do projeto.
 * @return array Retorna um array associativo:
 *               {
 *                 'success': bool (true se *nenhum* arquivo teve erro *crítico* - mas pode haver erros *individuais* reportados na message),
 *                 'message': string (mensagem sumarizando o resultado e erros individuais),
 *                 'files': array (lista de arquivos válidos salvos temp, cada um { nome_original, nome_armazenado, caminho_temp, tipo_mime, tamanho_bytes }),
 *                 'error_count': int (quantos arquivos tiveram erro *impeditivo para processar*),
 *                 'temp_dir_path': string|null (caminho COMPLETO para o diretório temporário único criado para esta req, null se nenhum arquivo ou erro na criação do dir)
 *               }
 */
function processarDocumentUploads(array $files, string $baseTempDir = 'uploads/temp/'): array {
    // error_log("--- Iniciando processarDocumentUploads ---");

    $resultados = [
        'success' => true, // Assume sucesso a menos que haja erro em *todos* os arquivos OU falha crítica no diretório
        'message' => '',
        'files' => [], // Lista de arquivos que passaram na validação e foram movidos para TEMP
        'error_count' => 0, // Contador de arquivos que falharam
        'temp_dir_path' => null, // Caminho completo do diretório temporário único para esta requisição
        'individual_errors' => [], // Detalhes dos erros individuais
    ];

    // Definir limites e tipos para DOCUMENTOS
    $allowed_types = [
        'application/pdf', // .pdf
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel', // .xls
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/msword', // .doc
        'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', // Permitir imagens e SVG
        'text/csv', 'text/plain', // CSV e TXT simples
        'application/zip', 'application/x-rar-compressed', 'application/vnd.rar', // Zip, Rar
    ];
    $max_size_per_file = 10 * 1024 * 1024; // 10MB por arquivo

    // Lidar com uploads múltiplos: a estrutura padrão é flat, ex: $_FILES['documentos']['name'][0,1,2...], ['type'][0,1,2...]
    // Verifica se o campo existe e tem a estrutura esperada para inputs 'multiple'
    if (!isset($files['name']) || !is_array($files['name'])) {
        // O campo não foi enviado, ou não é multiple como esperado. Não é necessariamente um erro se o campo era opcional.
        // Consideramos isso como 'nenhum arquivo selecionado'.
         $resultados['message'] = "Nenhum arquivo de documento selecionado para upload.";
        // error_log("processarDocumentUploads: Info: $_FILES com estrutura inesperada ou vazio.");
        return $resultados; // success: true, files: [], error_count: 0
    }

    $total_files_sent = count($files['name']);
    // error_log("processarDocumentUploads: Total de slots de arquivo recebidos: $total_files_sent");

    // Gerar um diretório temporário único para esta requisição, AGORA que sabemos que há slots de arquivos
     // Caminho absoluto para o diretório temporário raiz
    $baseTempDirAbs = __DIR__ . '/../' . trim($baseTempDir, '/\\') . '/';
    $uniqueTempDirName = session_id() . '_' . time() . '_' . bin2hex(random_bytes(4)); // Nome único
    $requestTempDirAbs = $baseTempDirAbs . $uniqueTempDirName . '/'; // Caminho completo para o subdiretório desta req

    // Verificar e criar o diretório base temp
    if (!is_dir($baseTempDirAbs)) {
         if (!mkdir($baseTempDirAbs, 0755, true) && !is_dir($baseTempDirAbs)) { // Verifica se não existia E a criação falhou
              $resultados['success'] = false; $resultados['message'] = "Erro interno: Não foi possível criar o diretório temporário base para uploads."; error_log("processarDocumentUploads: Falha ao criar dir temp base: " . $baseTempDirAbs); return $resultados;
         }
          // Permissões (verifique seu umask e necessidade de chmod)
         // if (!is_writable($baseTempDirAbs)) { chmod($baseTempDirAbs, 0755); } // Exemplo
    }
    if (!is_writable($baseTempDirAbs)) { error_log("processarDocumentUploads: Falha: Diretório temp base sem permissão escrita: " . $baseTempDirAbs); $resultados['success'] = false; $resultados['message'] = "Erro interno: Permissão negada no diretório temporário base."; return $resultados; }

     // Criar o diretório temporário ÚNICO para esta requisição (somente se o base for writable)
    if (!mkdir($requestTempDirAbs, 0755, true)) { // Permissões 0755 geralmente suficientes para o dono e grupo/outros ler/exec
        error_log("processarDocumentUploads: Falha ao criar dir temp req: " . $requestTempDirAbs);
         $resultados['success'] = false; $resultados['message'] = "Erro interno: Não foi possível criar o diretório temporário para o upload da requisição."; return $resultados;
    }
    if (!is_writable($requestTempDirAbs)) { // Verificar permissão de escrita
        error_log("processarDocumentUploads: Falha: Diretório temp req sem permissão escrita: " . $requestTempDirAbs);
         // Tentar remover o diretório recém-criado, se possível e se estiver vazio
         @rmdir($requestTempDirAbs);
         $resultados['success'] = false; $resultados['message'] = "Erro interno: Permissão negada no diretório temporário para o upload da requisição."; return $resultados;
     }

     // Se chegamos aqui, o diretório temporário para esta requisição foi criado e é writable.
     $resultados['temp_dir_path'] = $requestTempDirAbs; // Armazena o caminho completo

    // Iterar sobre os arquivos enviados e validar individualmente
    for ($i = 0; $i < $total_files_sent; $i++) {
         // Extrair dados do arquivo individual para facilitar o passo a passo
        $file_info = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '', // Caminho temporário ORIGINAL do PHP
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0
        ];

        // Pular slots de upload vazios explicitamente marcados pelo PHP
        if ($file_info['error'] === UPLOAD_ERR_NO_FILE) {
             // error_log("processarDocumentUploads: Info - Slot vazio ou nenhum arquivo selecionado.");
            continue;
        }

        // Validar o arquivo individual usando a função validarArquivo
        $erroValidacao = validarArquivo($file_info, $max_size_per_file, $allowed_types);

        if ($erroValidacao !== null) {
            // Se validarArquivo retorna uma string de erro, o arquivo é inválido.
            $resultados['error_count']++;
            $resultados['individual_errors'][] = "Arquivo '{$file_info['name']}': {$erroValidacao}";
            error_log("processarDocumentUploads: Arquivo falhou validação/PHP move: '{$file_info['name']}' - Erro: {$erroValidacao}. Code PHP: {$file_info['error']}");
            continue; // Pula para o próximo arquivo
        }

        // Se chegou aqui, o arquivo individual passou na validação e UPLOAD_ERR_OK
        // Mover o arquivo do temporário ORIGINAL do PHP para o nosso diretório temporário SEGURO (dentro de baseTempDir)
        $extensao = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
         // Gerar nome único para o arquivo *no diretório temporário da requisição*
        $nome_seguro_temp = uniqid(rand(), true) . '.' . $extensao;
        $caminho_completo_destino_temp_requisição = $requestTempDirAbs . $nome_seguro_temp; // Caminho completo no disco

        // Tentar mover
         // errors de move_uploaded_file podem não setar $file_info['error']. Precisamos de log detalhado se falhar.
        if (move_uploaded_file($file_info['tmp_name'], $caminho_completo_destino_temp_requisição)) {
             // error_log("processarDocumentUploads: Arquivo '{$file_info['name']}' movido com sucesso para temp req dir.");
            // Adiciona os metadados do arquivo válido salvo temporariamente à lista de resultados
            $resultados['files'][] = [
                'nome_original' => $file_info['name'],
                'nome_armazenado' => $nome_seguro_temp, // Este é o nome seguro que usaremos SEMPRE (em temp e no destino final)
                'caminho_temp' => $caminho_completo_destino_temp_requisição, // Caminho COMPLETO no disco onde está AGORA
                'tipo_mime' => $file_info['type'], // MIME reportado inicialmente (get_uploaded_file_info), validarArquivo checou o real
                'tamanho_bytes' => $file_info['size'],
                // Sem descrição individual nesta etapa do HTML
            ];
        } else {
            // Falha *inexperada* ao mover do temporário ORIGINAL do PHP para nosso temporário seguro
            $resultados['error_count']++;
             $last_php_error = error_get_last(); // Capturar último erro PHP (move_uploaded_file)
             $error_message = "Falha ao mover arquivo '{$file_info['name']}' do diretório temporário do PHP. (PHP Error: " . json_encode($last_php_error) . ")";
            $resultados['individual_errors'][] = $error_message;
             error_log("processarDocumentUploads: " . $error_message); // Log detalhado da falha
        }
    } // Fim do loop sobre slots de arquivo

    // --- Compilação Final de Resultados e Mensagem ---

     $sucesso_count = count($resultados['files']);

     if ($resultados['error_count'] > 0) {
         $resultados['success'] = ($sucesso_count > 0); // Success = true APENAS se pelo menos 1 arquivo processou E NENHUM teve erro crítico no setup/move inicial
         $resultados['message'] = "Processamento de uploads concluído. ";
         if ($sucesso_count > 0) { $resultados['message'] .= "{$sucesso_count} arquivo(s) processado(s) com sucesso para a etapa temporária. "; }
         if ($resultados['error_count'] > 0) { $resultados['message'] .= "{$resultados['error_count']} arquivo(s) falharam: <br>- " . implode("<br>- ", $resultados['individual_errors']); }

     } else {
         $resultados['success'] = true; // Nenhum erro individual ou de setup
         if ($sucesso_count > 0) {
             $resultados['message'] = "{$sucesso_count} arquivo(s) processado(s) com sucesso para upload temporário.";
         } else {
              // Nenhum arquivo recebido E nenhum erro (UPload_ERR_NO_FILE, loop count = 0 etc)
              $resultados['message'] = "Nenhum arquivo de documento válido foi encontrado ou processado.";
         }
     }


    // --- Cleanup do diretório temporário se nenhum arquivo válido foi salvo NELE ---
     // Se o diretório foi criado ($resultados['temp_dir_path'] não é null)
     // MAS nenhum arquivo válido foi salvo nele ($sucesso_count == 0)
     // Remove o diretório temporário único desta requisição (se existir e estiver vazio AGORA)
     if ($resultados['temp_dir_path'] !== null && $sucesso_count === 0) {
          if (is_dir($resultados['temp_dir_path'])) {
              // Verificar se está realmente vazio (apenas '.' e '..')
              $items_in_dir = scandir($resultados['temp_dir_path']);
              if ($items_in_dir !== false && count($items_in_dir) === 2) {
                   if (!@rmdir($resultados['temp_dir_path'])) {
                       error_log("processarDocumentUploads: AVISO: Falha ao remover diretório temp vazio: " . $resultados['temp_dir_path']);
                   } else {
                       // Diretório removido com sucesso. Nulifica o path no resultado.
                       $resultados['temp_dir_path'] = null;
                   }
               } else {
                   // Diretório existe mas não está vazio. Logar que sobrou coisa.
                   // Isso pode acontecer se move_uploaded_file falhou, mas arquivos temporários originais
                   // ficaram no nosso diretório temp em vez de no temp do PHP. Improvável.
                   error_log("processarDocumentUploads: AVISO: Diretório temp '$requestTempDirAbs' não vazio após tentativa de limpeza, {$sucesso_count} sucesso(s), {$resultados['error_count']} falha(s). Itens: " . json_encode($items_in_dir));
               }
          }
     }
     // Se há arquivos válidos ($sucesso_count > 0), o diretório TEMPORÁRIO *não* é removido aqui.
     // Ele será manipulado (movido ou deletado) por criarAuditoria na transação.


    // error_log("--- Fim processarDocumentUploads ---");

    return $resultados;
}