# 🔧 CORREÇÃO DO ERRO logo_path

## ❌ Erro Encontrado

```
Fatal error: Unknown column 'sender.logo_path' in 'field list'
```

## ✅ Problema Identificado

A coluna `logo_path` está na tabela `businesses`, não na tabela `users`. A query estava tentando buscar `sender.logo_path` que não existe.

## 🔧 Solução Aplicada

### **Arquivos Corrigidos:**

1. ✅ `mensagens_whatsapp.php` (linha ~24)
2. ✅ `actions/get_messages.php` (linha ~48)

### **Mudança:**

**ANTES (Errado):**
```sql
SELECT 
    sender.logo_path,  -- ❌ NÃO EXISTE
    ...
FROM notifications n
LEFT JOIN users sender ON n.sender_id = sender.id
```

**DEPOIS (Correto):**
```sql
SELECT 
    b.logo_path,  -- ✅ CORRETO
    ...
FROM notifications n
LEFT JOIN users sender ON n.sender_id = sender.id
LEFT JOIN businesses b ON sender.id = b.user_id  -- ✅ JOIN ADICIONADO
```

## 📦 Arquivos Atualizados

Os arquivos já foram corrigidos automaticamente:
- ✅ `mensagens_whatsapp.php` - Corrigido
- ✅ `actions/get_messages.php` - Corrigido

## 🚀 Como Aplicar a Correção

### **Opção 1: Baixar Arquivos Novos** (Recomendado)

1. Baixe os arquivos corrigidos que estou fornecendo agora
2. Substitua os arquivos antigos pelos novos
3. Teste novamente

### **Opção 2: Editar Manualmente**

Se preferir editar manualmente:

#### **Arquivo 1: mensagens_whatsapp.php**

Encontre a linha ~24 com esta query:
```sql
SELECT 
    n.sender_id,
    sender.nome as sender_name,
    sender.type as sender_type,
    sender.logo_path,  -- ❌ MUDAR ESTA LINHA
```

E mude para:
```sql
SELECT 
    n.sender_id,
    sender.nome as sender_name,
    sender.type as sender_type,
    b.logo_path,  -- ✅ NOVA LINHA
    MAX(n.created_at) as last_message_time,
    (SELECT subject FROM notifications WHERE sender_id = n.sender_id AND receiver_id = $userId ORDER BY created_at DESC LIMIT 1) as last_subject,
    (SELECT message FROM notifications WHERE sender_id = n.sender_id AND receiver_id = $userId ORDER BY created_at DESC LIMIT 1) as last_message,
    COUNT(CASE WHEN n.status = 'unread' THEN 1 END) as unread_count
FROM notifications n
LEFT JOIN users sender ON n.sender_id = sender.id
LEFT JOIN businesses b ON sender.id = b.user_id  -- ✅ ADICIONAR ESTA LINHA
WHERE n.receiver_id = $userId
GROUP BY n.sender_id, sender.nome, sender.type, b.logo_path  -- ✅ MUDAR AQUI TAMBÉM
ORDER BY last_message_time DESC
```

#### **Arquivo 2: actions/get_messages.php**

Encontre a linha ~48:
```php
$stmt = $mysqli->prepare("SELECT nome, logo_path, type FROM users WHERE id = ? LIMIT 1");
```

E mude para:
```php
$stmt = $mysqli->prepare("
    SELECT u.nome, b.logo_path, u.type 
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    WHERE u.id = ? 
    LIMIT 1
");
```

## 🧪 Testar a Correção

1. Acesse o dashboard
2. Clique em "Mensagens"
3. A interface deve carregar sem erros ✅

Se ainda der erro, verifique:
- Os caminhos dos arquivos estão corretos?
- O arquivo `db.php` está sendo carregado?
- As tabelas `notifications`, `users` e `businesses` existem?

## 📊 Adicionar Mensagens de Teste

Se você não tiver mensagens para testar, use o arquivo `insert_mensagens_teste.sql`:

1. Abra o arquivo SQL
2. Substitua `{SEU_USER_ID}` pelo seu ID de usuário
3. Execute no MySQL

Para descobrir seu ID:
```sql
SELECT id, nome, email FROM users WHERE type = 'company' LIMIT 5;
```

## ✅ Checklist de Verificação

Antes de testar, confirme:

- [ ] Arquivo `mensagens_whatsapp.php` corrigido
- [ ] Arquivo `actions/get_messages.php` corrigido
- [ ] Banco de dados tem tabela `businesses`
- [ ] Tabela `businesses` tem coluna `logo_path`
- [ ] Relacionamento entre `users` e `businesses` existe (user_id)
- [ ] Existem mensagens na tabela `notifications` para testar

## 🎉 Resultado Esperado

Após a correção:
- ✅ Interface WhatsApp carrega normalmente
- ✅ Lista de conversas aparece
- ✅ Avatares aparecem (com logo ou iniciais)
- ✅ Pode clicar nas conversas
- ✅ Mensagens são exibidas
- ✅ Todas as funcionalidades funcionam

## ⚠️ Se Ainda Não Funcionar

### Problema: Nenhuma conversa aparece

**Solução:** Você não tem mensagens no banco. Execute o SQL de teste.

### Problema: Avatares não aparecem

**Causa:** Coluna `logo_path` está NULL ou arquivo não existe

**Solução:** 
1. Verifique se o caminho está correto
2. O sistema mostra iniciais se não houver logo (normal)

### Problema: Erro 404 ao abrir conversa

**Causa:** Arquivo `get_messages.php` não encontrado ou caminho errado

**Solução:** Verifique se está em `/actions/get_messages.php`

## 📞 Debug

Para debug detalhado, adicione no início do `mensagens_whatsapp.php`:

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Testar a query
try {
    $test = $mysqli->query("
        SELECT u.nome, b.logo_path, u.type 
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id
        LIMIT 1
    ");
    echo "Query funciona! ✅<br>";
    print_r($test->fetch_assoc());
} catch (Exception $e) {
    echo "Erro na query: " . $e->getMessage();
}
```

---

**Correção aplicada em:** 15 de Janeiro de 2026  
**Status:** Arquivos corrigidos e prontos ✅
