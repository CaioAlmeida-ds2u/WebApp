<?php
// admin/empresa/criar.php - COM UPLOAD DE LOGO

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
// **** ADICIONAR INCLUDE PARA UPLOAD ****
require_once __DIR__ . '/../../includes/funcoes_upload.php';

protegerPagina($conexao);
if ($_SESSION['perfil'] !== 'admin') { /* ... Acesso negado ... */ exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* ... Redireciona se não for POST ... */ exit; }

// 1. Validar CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    /* ... Erro CSRF ... */
     $_SESSION['erro'] = "Erro de validação da sessão.";
     header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=registrar-empresa'); exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// 2. Obter e Validar Dados do Formulário
$nome = trim($_POST['nome'] ?? '');
$cnpj = preg_replace('/[^0-9]/', '', trim($_POST['cnpj'] ?? ''));
$razao_social = trim($_POST['razao_social'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');
$contato = trim($_POST['contato'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email = trim($_POST['email'] ?? '');
// **** PEGAR DADOS DO ARQUIVO LOGO ****
$logoFile = $_FILES['logo'] ?? null;

$errors = [];
if (empty($nome)) $errors[] = "O campo Nome Fantasia é obrigatório.";
if (empty($cnpj)) $errors[] = "O campo CNPJ é obrigatório.";
elseif (!validarCNPJ($cnpj)) $errors[] = "CNPJ inválido.";
// ... (outras validações de campos texto) ...
if (empty($razao_social)) $errors[] = "Razão Social obrigatória.";
if (empty($email)) $errors[] = "E-mail obrigatório.";
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "E-mail inválido.";
if (empty($contato)) $errors[] = "Contato obrigatório.";
if (empty($telefone)) $errors[] = "Telefone obrigatório.";

// **** VALIDAR LOGO (SE ENVIADO) ****
$nomeArquivoLogo = null; // Inicializa como null
if ($logoFile && $logoFile['error'] !== UPLOAD_ERR_NO_FILE) {
    // Usamos validarImagem pois processarUploadLogoEmpresa faz a validação interna
    $erroLogo = validarImagem($logoFile, 2 * 1024 * 1024, ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml']);
    if ($erroLogo) {
        $errors[] = "Erro no Logo: " . $erroLogo;
    }
}


// 3. Chamar Função de Criação se não houver erros
if (empty($errors)) {
    $dadosEmpresa = [
        'nome' => $nome, 'cnpj' => $cnpj, 'razao_social' => $razao_social,
        'endereco' => $endereco, 'contato' => $contato, 'telefone' => $telefone,
        'email' => $email, 'logo' => null // Será preenchido após criar a empresa e fazer upload
    ];

    // Função criarEmpresa precisa ser modificada para retornar o ID da empresa criada OU false/erro
    // Vamos assumir que existe uma função `criarEmpresaRetornandoId` ou adaptar a existente.
    // ADAPTAÇÃO: Vamos chamar criarEmpresa e se der certo, pegamos o lastInsertId e fazemos upload

    $conexao->beginTransaction(); // Iniciar transação

    try {
        // Chamar função original, que agora inclui 'logo' => null
        $resultadoCriacao = criarEmpresa($conexao, $dadosEmpresa, $_SESSION['usuario_id']); // Passa ID do admin

        if ($resultadoCriacao !== true) {
             throw new Exception(htmlspecialchars($resultadoCriacao)); // Lança exceção com a msg de erro
        }

        $novaEmpresaId = $conexao->lastInsertId(); // Pega o ID da empresa recém-criada

        // Processar Upload do Logo (APENAS se um arquivo foi enviado)
        if ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
            $resultadoUpload = processarUploadLogoEmpresa($logoFile, (int)$novaEmpresaId, $conexao);

            if ($resultadoUpload['success']) {
                $nomeArquivoLogo = $resultadoUpload['nome_arquivo'];
                // Atualizar a coluna 'logo' da empresa recém-criada
                $stmtUpdateLogo = $conexao->prepare("UPDATE empresas SET logo = :logo WHERE id = :id");
                $stmtUpdateLogo->bindParam(':logo', $nomeArquivoLogo);
                $stmtUpdateLogo->bindParam(':id', $novaEmpresaId, PDO::PARAM_INT);
                if (!$stmtUpdateLogo->execute()) {
                     throw new Exception("Erro ao vincular o logo à empresa.");
                }
            } else {
                 // Se upload falhou, lança exceção para dar rollback na criação da empresa
                 throw new Exception("Erro no upload do logo: " . $resultadoUpload['message']);
            }
        }

        // Se chegou aqui, tudo certo!
        $conexao->commit();
        $_SESSION['sucesso'] = "Empresa '".htmlspecialchars($nome)."' criada com sucesso!" . ($nomeArquivoLogo ? " Logo enviado." : "");
         dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_empresa_sucesso', 1, "Empresa ID: $novaEmpresaId, Nome: $nome", $conexao);
         header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=empresas');
         exit;

    } catch (Exception $e) {
        $conexao->rollBack(); // Desfaz criação da empresa se algo falhou
        $_SESSION['erro_registrar'] = "Erro ao criar a empresa: " . $e->getMessage();
        dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_empresa_falha_transacao', 0, 'Falha: ' . $e->getMessage(), $conexao);
        $_SESSION['form_data_empresa'] = $_POST; // Mantém dados para repopular
        // Excluir arquivo de logo movido, se houver (melhoria: fazer upload só depois do commit)
        if (isset($resultadoUpload) && !$resultadoUpload['success'] && isset($resultadoUpload['nome_arquivo'])) {
             $caminhoLixo = __DIR__ . '/../../uploads/logos/' . $resultadoUpload['nome_arquivo'];
             if (file_exists($caminhoLixo)) @unlink($caminhoLixo);
        }
    }

} else {
    // Erros de validação
    $_SESSION['erro_registrar'] = "<strong>Foram encontrados os seguintes erros:</strong><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
    dbRegistrarLogAcesso($_SESSION['usuario_id'], $_SERVER['REMOTE_ADDR'], 'criar_empresa_falha_valid', 0, 'Erro de validação.', $conexao);
    $_SESSION['form_data_empresa'] = $_POST; // Salva dados submetidos na sessão para repopular
}

// Redirecionar de volta para a aba "Registrar Empresa" em caso de erro
header('Location: ' . BASE_URL . 'admin/empresa/empresa_index.php?aba=registrar-empresa');
exit;
?>