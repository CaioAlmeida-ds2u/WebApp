<?php
// includes/funcoes_upload.php

/**
 * Valida uma imagem enviada por upload.
 *
 * @param array $imagem O array $_FILES['nome_do_campo'] contendo os dados da imagem.
 * @return string|null Retorna uma string com a mensagem de erro, ou null se a imagem for válida.
 */
function validarImagem($imagem) {
    $maxSize = 5 * 1024 * 1024; // 5MB
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];

    if ($imagem['error'] !== UPLOAD_ERR_OK) {
        return "Erro no upload: " . $imagem['error']; // Códigos de erro do PHP
    }

    if ($imagem['size'] > $maxSize) {
        return "A imagem é muito grande. O tamanho máximo é 5MB.";
    }

    if (!in_array($imagem['type'], $tiposPermitidos)) {
        return "Tipo de imagem inválido. Use JPG, JPEG, PNG ou GIF.";
    }
    //Verifica se é realmente uma imagem
     if (getimagesize($imagem['tmp_name']) === false){
        return "O arquivo não é uma imagem.";
     }

    return null; // Sem erros
}

/**
 * Processa o upload de uma foto de perfil.
 *
 * @param array $imagem O array $_FILES['foto'].
 * @param int $usuarioId O ID do usuário.
 * @param PDO $conexao A conexão com o banco de dados.
 * @return array Retorna um array com as chaves 'success' (true/false) e 'message'.
 */
function processarUploadFoto($imagem, $usuarioId, $conexao) {
    $uploadDir = __DIR__ . '/../uploads/fotos/'; // Pasta de uploads (relativo a config.php)

    // Criar o diretório se não existir
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) { // Permissões 0755 (ou ajuste conforme necessário)
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

    // Mover o arquivo para o diretório de uploads
    if (move_uploaded_file($imagem['tmp_name'], $caminhoCompleto)) {
        // Atualizar o banco de dados com o caminho do arquivo

        $stmt = $conexao->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
        $stmt->execute([$nomeArquivo, $usuarioId]);

        if ($stmt->rowCount() > 0) {
          // Atualizar o $_SESSION['foto']
          $_SESSION['foto'] = 'assets/img/' . $nomeArquivo;
          return ['success' => true, 'message' => 'Foto de perfil atualizada com sucesso!', 'caminho' =>'assets/img/' . $nomeArquivo];
        } else {
            //Se ocorrer erro, deleta o arquivo do upload.
            unlink($caminhoCompleto);
            return ['success' => false, 'message' => 'Erro ao atualizar o banco de dados.'];
        }

    } else {
        return ['success' => false, 'message' => 'Erro ao fazer upload do arquivo.'];
    }
}