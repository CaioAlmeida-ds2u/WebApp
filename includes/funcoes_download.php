<?php
// includes/funcoes_download.php

// Esta função assume que $conexao e as sessões já estão disponíveis (incluído via config.php)

/**
 * Lida com o download seguro de arquivos de auditoria (planejamento ou evidências).
 *
 * @param PDO $conexao Conexão com o banco de dados.
 * @param string $tipo 'plan' para documentos de planejamento, 'evid' para evidências de itens.
 * @param int $id ID do registro do documento no banco de dados.
 * @param int $empresa_id_usuario ID da empresa do usuário logado (para verificação de permissão).
 * @param int $usuario_id_logado ID do usuário logado.
 */
function processarDownloadSeguro(PDO $conexao, string $tipo, int $id, int $empresa_id_usuario, int $usuario_id_logado): void {
    $tabela_doc = '';
    $coluna_auditoria_id = ''; // Coluna que referencia auditorias.id na tabela de documentos
    $diretorio_base_fisico = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/'); // Definido em config.php
    $subpasta_arquivos = '';

    if ($tipo === 'plan') {
        $tabela_doc = 'auditoria_documentos_planejamento';
        $coluna_auditoria_id = 'auditoria_id';
        $subpasta_arquivos = '/auditorias_planejamento/'; // Subpasta relativa dentro de UPLOADS_BASE_PATH_ABSOLUTE
    } elseif ($tipo === 'evid') {
        $tabela_doc = 'auditoria_evidencias';
        // Na tabela 'auditoria_evidencias', precisamos fazer um JOIN com 'auditoria_itens' para pegar 'auditoria_id'
        // para verificar se o usuário da empresa tem acesso à auditoria-mãe do item.
        $coluna_auditoria_id = 'ai.auditoria_id'; // Precisará de JOIN
        $subpasta_arquivos = '/auditorias_evidencias/'; // Ajuste conforme sua estrutura de pastas de evidências
    } else {
        http_response_code(400);
        echo "Tipo de arquivo inválido para download.";
        exit;
    }

    $sql_doc = "";
    if ($tipo === 'plan') {
        $sql_doc = "SELECT doc.nome_arquivo_original, doc.nome_arquivo_armazenado, doc.caminho_arquivo, doc.tipo_mime, aud.empresa_id
                    FROM {$tabela_doc} doc
                    JOIN auditorias aud ON doc.{$coluna_auditoria_id} = aud.id
                    WHERE doc.id = :doc_id";
    } elseif ($tipo === 'evid') {
        // Este JOIN é mais complexo para garantir que a evidência pertença a um item de uma auditoria da empresa do usuário
        $sql_doc = "SELECT evid.nome_arquivo_original, evid.nome_arquivo_armazenado, evid.caminho_arquivo, evid.tipo_mime, aud.empresa_id
                    FROM auditoria_evidencias evid
                    JOIN auditoria_itens ai ON evid.auditoria_item_id = ai.id
                    JOIN auditorias aud ON ai.auditoria_id = aud.id
                    WHERE evid.id = :doc_id";
    }

    if (empty($sql_doc)) {  http_response_code(500); echo "Erro de configuração do download."; exit; }

    $stmt_doc = $conexao->prepare($sql_doc);
    $stmt_doc->execute([':doc_id' => $id]);
    $documento = $stmt_doc->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        http_response_code(404);
        echo "Documento não encontrado.";
        if (function_exists('dbRegistrarLogAcesso')) {dbRegistrarLogAcesso($usuario_id_logado, $_SERVER['REMOTE_ADDR'], 'download_doc_notfound', 0, "Tipo: $tipo, ID: $id", $conexao);}
        exit;
    }

    // Verificação de Permissão: O documento pertence a uma auditoria da empresa do usuário?
    if ((int)$documento['empresa_id'] !== $empresa_id_usuario) {
        http_response_code(403);
        echo "Acesso negado a este documento.";
        if (function_exists('dbRegistrarLogAcesso')) {dbRegistrarLogAcesso($usuario_id_logado, $_SERVER['REMOTE_ADDR'], 'download_doc_forbidden', 0, "Tipo: $tipo, ID: $id, EmpDoc: ".$documento['empresa_id'].", EmpUser: ".$empresa_id_usuario, $conexao);}
        exit;
    }

    // $documento['caminho_arquivo'] do DB é o caminho RELATIVO usado na URL (ex: 'auditorias_planejamento/123/arquivo.pdf')
    // Precisamos do caminho FÍSICO completo para ler o arquivo do servidor
    // Usamos o nome_arquivo_armazenado se o caminho_arquivo for apenas o relativo com ID.
    // Se caminho_arquivo no DB já é `auditorias_planejamento/ID_AUDITORIA/NOME_ARQUIVO_ARMAZENADO`, então está ok.
    // Senão, construa o caminho físico corretamente.
    // Se `caminho_arquivo` no DB já é algo como 'auditorias_planejamento/AUDIT_ID/nome_seguro.pdf',
    // e `UPLOADS_BASE_PATH_ABSOLUTE` é a raiz da pasta 'uploads', então o caminho físico é:
    // $caminho_fisico_completo = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/' . $documento['caminho_arquivo'];

    // Assumindo que 'caminho_arquivo' no DB é o caminho relativo A PARTIR da pasta UPLOADS_BASE_PATH_ABSOLUTE.
    // Ex: $documento['caminho_arquivo'] = "auditorias_planejamento/AUDITORIA_ID/NOME_ARMAZENADO.pdf"
    // $caminho_fisico_completo = UPLOADS_BASE_PATH_ABSOLUTE . $documento['caminho_arquivo'];

    // OU se caminho_arquivo é só 'nome_seguro.pdf' e você precisa do ID da auditoria para construir a pasta:
    // Para isso, a query SQL precisaria retornar o auditoria_id do documento.
    // Simplificação: vou assumir que $documento['caminho_arquivo'] é o caminho relativo *a partir da raiz da pasta `uploads`*
    // e $documento['nome_arquivo_armazenado'] é apenas o nome do arquivo final.
    // **VOCÊ PRECISA AJUSTAR A LÓGICA DE CONSTRUÇÃO DO CAMINHO FÍSICO CONFORME SUA ESTRUTURA!**

    $caminho_fisico_completo = rtrim(UPLOADS_BASE_PATH_ABSOLUTE, '/') . '/' . $documento['caminho_arquivo'];

    if (!file_exists($caminho_fisico_completo) || !is_readable($caminho_fisico_completo)) {
        http_response_code(404);
        error_log("Download: Arquivo físico não encontrado ou ilegível: " . $caminho_fisico_completo);
        echo "Arquivo não disponível no servidor.";
        exit;
    }

    // Limpar buffer de saída para evitar corromper o arquivo
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Definir headers para forçar o download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($documento['tipo_mime'] ?: 'application/octet-stream')); // Fallback para binário
    header('Content-Disposition: attachment; filename="' . basename($documento['nome_arquivo_original']) . '"'); // Usa nome original para o usuário
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($caminho_fisico_completo));

    // Ler e enviar o arquivo
    readfile($caminho_fisico_completo);
    if (function_exists('dbRegistrarLogAcesso')) {dbRegistrarLogAcesso($usuario_id_logado, $_SERVER['REMOTE_ADDR'], 'download_doc_ok', 1, "Tipo: $tipo, ID: $id, Nome: ".$documento['nome_arquivo_original'], $conexao);}
    exit;
}

// Adicione ao seu config.php a constante:
// define('UPLOADS_BASE_PATH_ABSOLUTE', $_SERVER['DOCUMENT_ROOT'] . rtrim(BASE_URL, '/') . '/uploads/'); // Ajuste se necessário
// Ou defina diretamente se não quiser depender de BASE_URL para o caminho físico.
// Ex: define('UPLOADS_BASE_PATH_ABSOLUTE', '/var/www/html/meuapp/uploads/');
?>