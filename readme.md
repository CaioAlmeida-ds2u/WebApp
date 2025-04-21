
## 🛠️ Tecnologias Utilizadas

*   **Backend:** PHP 8.1+ (Estilo procedural com funções)
*   **Banco de Dados:** MySQL / MariaDB (com PDO para interação)
*   **Frontend:** HTML5, CSS3, JavaScript (ES6+)
*   **Framework CSS:** Bootstrap 5.3
*   **Bibliotecas JS:**
    *   Chart.js (Para gráficos na dashboard)
    *   iMask.js (Para máscaras de input em formulários)
*   **Ícones:** Font Awesome 6

## ⚙️ Configuração e Instalação

1.  **Pré-requisitos:**
    *   Servidor web (Apache, Nginx) com suporte a PHP 8.1 ou superior.
    *   Servidor de banco de dados MySQL ou MariaDB.
    *   Composer (se futuras dependências forem adicionadas).
2.  **Clone o Repositório:**
    ```bash
    git clone https://github.com/CaioAlmeida-ds2u/WebApp.git
    cd WebApp
    ```
3.  **Banco de Dados:**
    *   Crie um banco de dados no seu servidor MySQL/MariaDB (ex: `acoditools`).
    *   Importe a estrutura das tabelas. Utilize os comandos `CREATE TABLE` fornecidos durante o desenvolvimento ou um dump SQL completo (`acoditools.txt`, se disponível e atualizado).
4.  **Configuração (`includes/config.php`):**
    *   **IMPORTANTE:** Edite o arquivo `includes/config.php`.
    *   Defina as **credenciais do banco de dados** (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) corretas para o seu ambiente. **NUNCA use 'root' ou senhas fracas em produção!** Considere usar variáveis de ambiente.
    *   Ajuste a constante `BASE_URL` para corresponder ao caminho base da sua aplicação no servidor web (ex: `/WebApp/` ou `/` se estiver na raiz do domínio).
5.  **Permissões de Diretório:**
    *   Certifique-se de que o servidor web tenha permissão de **escrita** nos diretórios `/uploads/fotos/` e `/uploads/logos/`.
    *   Certifique-se de que o servidor web tenha permissão de **escrita** no diretório `/logs/` (ou no caminho definido para `error_log` no `php.ini`).
6.  **Servidor Web:**
    *   Configure seu servidor web (Apache VirtualHost ou Nginx server block) para apontar a raiz do documento para a pasta onde você clonou o projeto (`/WebApp/`).
    *   (Opcional) Configure regras de reescrita (`mod_rewrite` no Apache ou `try_files` no Nginx) se quiser URLs mais limpas no futuro.
7.  **Usuário Admin Inicial:**
    *   Crie manualmente o primeiro usuário administrador diretamente no banco de dados na tabela `usuarios`, definindo o `perfil` como 'admin', `ativo` como 1, e `primeiro_acesso` como 1 (para forçar a definição de senha). Lembre-se de usar um HASH de senha gerado por `password_hash()` para o campo `senha`.

## 🚀 Uso

1.  Acesse a URL base da sua aplicação (ex: `http://localhost/WebApp/`).
2.  Você será direcionado para a página de login (`index.php`).
3.  Faça login com as credenciais do usuário (admin, gestor ou auditor).
4.  Você será redirecionado para o dashboard correspondente ao seu perfil.
5.  Navegue pelas opções disponíveis no menu (navbar ou sidebar, dependendo do layout).

## 🔮 Próximos Passos e Melhorias Futuras

*   [ ] Implementar a funcionalidade completa do perfil **Auditor** (execução de auditorias).
*   [ ] Finalizar as funcionalidades do perfil **Gestor** (criação/revisão de auditorias, gestão de planos de ação).
*   [ ] Criar a página de **edição detalhada** de Requisitos (se necessário adicionar mais campos).
*   [ ] Implementar a funcionalidade de **exclusão segura** para Empresas e Requisitos, verificando todas as dependências.
*   [ ] Implementar os scripts PHP para **Importar/Exportar Requisitos** via CSV.
*   [ ] Refinar o **Relatório de Logs** (mais filtros, talvez exportação PDF).
*   [ ] Criar **Relatórios específicos da Empresa** para o Gestor.
*   [ ] Desenvolver a lógica de **Planos de Ação** para não conformidades.
*   [ ] Implementar um sistema de **Notificações** (ex: para gestor quando auditoria estiver pronta para revisão).
*   [ ] Reforçar a **segurança do upload** com verificação MIME detalhada no servidor (usando `finfo`).
*   [ ] Considerar o uso de um **autoloader** (Composer) para gerenciar includes de forma mais organizada.
*   [ ] Avaliar a migração para um **micro-framework PHP** ou **MVC** se o projeto crescer muito.
*   [ ] Implementar **testes automatizados**.
