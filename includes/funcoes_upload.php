<?php
// includes/funcoes_upload.php

/**
 * Valida uma imagem enviada por upload.
 */
function validarImagem($imagem) {
    $maxSize = 5 * 1024 * 1024; // 5MB
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];

    if ($imagem['error'] !== UPLOAD_ERR_OK) {
        return "Erro no upload: " . $imagem['error'];
    }

    if ($imagem['size'] > $maxSize) {
        return "A imagem é muito grande. O tamanho máximo é 5MB.";
    }

    if (!in_array($imagem['type'], $tiposPermitidos)) {
        return "Tipo de imagem inválido. Use JPG, JPEG, PNG ou GIF.";
    }

     if (getimagesize($imagem['tmp_name']) === false){
        return "O arquivo não é uma imagem.";
     }

    return null; // Sem erros
}

/**
 * Processa o upload de uma foto de perfil e atualiza o banco de dados.
 */
function processarUploadFoto($imagem, $usuarioId, $conexao) {
    $uploadDir = __DIR__ . '/../uploads/fotos/'; // Pasta de uploads (relativo a config.php)

    // Criar o diretório se não existir
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'Erro ao criar o diretório de uploads.'];
        }
    }

    // Validar a imagem
    $erro = validarImagem($imagem);
    if ($erro) {
        return ['success' => false, 'message' => $erro];
    }

    // Gerar um nome único para o arquivo
    $nomeArquivo = uniqid('user_' . $usuarioId . '_') . '_' . time() . '.' . pathinfo($imagem['name'], PATHINFO_EXTENSION);
    $caminhoCompleto = $uploadDir . $nomeArquivo;

    // Obter o nome da foto antiga ANTES de mover a nova
    $stmt = $conexao->prepare("SELECT foto FROM usuarios WHERE id = ?");
    $stmt->execute([$usuarioId]);
    $fotoAntiga = $stmt->fetchColumn(); // Obtém o nome da foto antiga (pode ser NULL)


    // Mover o arquivo para o diretório de uploads
    if (move_uploaded_file($imagem['tmp_name'], $caminhoCompleto)) {
        // Atualizar o banco de dados com o nome do arquivo
        $stmt = $conexao->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
        $stmt->execute([$nomeArquivo, $usuarioId]);

        if ($stmt->rowCount() > 0) {
            // Atualizar a variável de sessão $_SESSION['foto']
            $_SESSION['foto'] = 'uploads/fotos/' . $nomeArquivo; // Caminho relativo

            // Excluir a foto antiga (se existir e for diferente da nova)
            if ($fotoAntiga && $fotoAntiga != $nomeArquivo) { // Verifica se fotoAntiga existe e é diferente
                $caminhoFotoAntiga = __DIR__ . '/../uploads/fotos/' . $fotoAntiga; //Caminho absoluto.

                //Verifica antes de apagar, se o arquivo realmente existe.
                if (file_exists($caminhoFotoAntiga)) {
                    if (!unlink($caminhoFotoAntiga)) { // Tenta apagar
                        error_log("Erro ao excluir foto antiga: " . $caminhoFotoAntiga); // Loga o erro
                    }
                }
            }

            return ['success' => true, 'message' => 'Foto de perfil atualizada com sucesso!', 'caminho' => $nomeArquivo];
        } else {
             //Se ocorrer erro, deleta o arquivo do upload.
            unlink($caminhoCompleto);
            return ['success' => false, 'message' => 'Erro ao atualizar o banco de dados.'];
        }
    } else {
        return ['success' => false, 'message' => 'Erro ao fazer upload do arquivo.'];
    }
}