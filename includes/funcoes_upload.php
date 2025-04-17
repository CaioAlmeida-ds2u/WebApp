<?php
// includes/funcoes_upload.php

/**
 * Valida uma imagem enviada por upload, verificando erros, tamanho e tipo MIME real.
 *
 * @param array $imagem Array $_FILES['nome_do_campo'].
 * @param int $maxSize Tamanho máximo em bytes (padrão 5MB).
 * @param array $tiposPermitidos Array de tipos MIME permitidos (ex: ['image/jpeg', 'image/png', 'image/gif']).
 * @return string|null Retorna string com erro ou null se válido.
 */
function validarImagem(array $imagem, int $maxSize = 5 * 1024 * 1024, array $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif']): ?string {

    // 1. Verificar erros básicos de upload
    if (!isset($imagem['error']) || is_array($imagem['error'])) {
        return "Parâmetros de upload inválidos."; // Estrutura inesperada
    }
    switch ($imagem['error']) {
        case UPLOAD_ERR_OK:
            break; // Tudo certo, continuar validação
        case UPLOAD_ERR_NO_FILE:
            return "Nenhum arquivo foi enviado."; // Não é um erro fatal, mas informa
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "O arquivo enviado excede o limite de tamanho permitido.";
        case UPLOAD_ERR_PARTIAL:
            return "O upload do arquivo foi feito parcialmente.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Erro no servidor: diretório temporário ausente.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Erro no servidor: falha ao escrever arquivo em disco.";
        case UPLOAD_ERR_EXTENSION:
            return "Erro no servidor: uma extensão PHP impediu o upload.";
        default:
            return "Erro desconhecido durante o upload.";
    }

    // 2. Verificar tamanho do arquivo (se passou do UPLOAD_ERR_OK)
    if ($imagem['size'] > $maxSize) {
        return "O arquivo é muito grande. O tamanho máximo permitido é " . ($maxSize / 1024 / 1024) . "MB.";
    }
    if ($imagem['size'] === 0) {
         return "O arquivo enviado está vazio.";
    }

    // 3. Verificar se é uma imagem válida (usando getimagesize)
    // getimagesize retorna false se não for imagem ou se não conseguir ler
    $imageInfo = @getimagesize($imagem['tmp_name']); // Usar @ para suprimir warnings se não for imagem
    if ($imageInfo === false) {
        return "O arquivo enviado não parece ser uma imagem válida ou está corrompido.";
    }

    // 4. **VALIDAÇÃO CRÍTICA: Verificar o tipo MIME REAL no servidor**
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeTypeReal = finfo_file($finfo, $imagem['tmp_name']);
        finfo_close($finfo);
    } else {
        // Fallback (menos seguro, mas melhor que nada) - usar o getimagesize que já pegamos
        $mimeTypeReal = $imageInfo['mime'] ?? null;
    }

    if ($mimeTypeReal === null || !in_array($mimeTypeReal, $tiposPermitidos)) {
        // Logar o tipo MIME detectado pode ser útil para depuração
        // error_log("Tentativa de upload de tipo MIME inválido: " . $mimeTypeReal);
        return "Tipo de arquivo inválido. Permitidos: JPG, PNG, GIF. (Detectado: " . htmlspecialchars($mimeTypeReal ?? 'N/A') .")";
    }

    // 5. Verificação adicional (opcional): Comparar MIME detectado com extensão?
    // Isso pode ser útil, mas também pode bloquear arquivos válidos se a extensão
    // não corresponder exatamente ao tipo MIME (ex: .jpg com MIME image/jpeg).
    // $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
    // $extensaoEsperada = match($mimeTypeReal) {
    //     'image/jpeg' => ['jpg', 'jpeg'],
    //     'image/png' => ['png'],
    //     'image/gif' => ['gif'],
    //     default => []
    // };
    // if (!in_array($extensao, $extensaoEsperada)) {
    //     return "Extensão do arquivo não corresponde ao tipo de imagem detectado.";
    // }


    // Se chegou até aqui, a imagem é considerada válida
    return null; // Sem erros
}

/**
 * Processa o upload de uma foto de perfil, valida, move e atualiza o banco.
 * IMPORTANTE: A função validarImagem() DEVE ser chamada antes ou no início desta.
 *
 * @param array $imagem Array $_FILES['nome_do_campo'].
 * @param int $usuarioId ID do usuário para vincular a foto.
 * @param PDO $conexao Conexão PDO.
 * @param string $uploadDir Diretório base para uploads (relativo à raiz do projeto, com barra no final).
 * @return array Retorna ['success' => bool, 'message' => string, 'caminho' => ?string (nome do arquivo gerado)].
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
?>