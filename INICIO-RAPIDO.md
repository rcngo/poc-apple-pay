# 🚀 Guia de Início Rápido - Apple Pay

## Arquivos Criados

✅ **validate-merchant.php** - Endpoint de validação  
✅ **test-config.php** - Script de verificação  
✅ **README-CONFIGURACAO.md** - Documentação completa  
✅ **certs/** - Pasta para certificados  
✅ **.gitignore** - Proteção dos certificados  
✅ **index.php** - Já atualizado para usar o endpoint local

---

## ⚡ Configuração em 5 Passos

### 1️⃣ Adicionar Certificados

Coloque seus certificados Apple Pay na pasta `certs/`:

```bash
cd "Integração Apple Pay"
ls certs/
# Deve mostrar:
# apple_pay_cert.pem
# apple_pay_key.pem
```

**Não tem os certificados ainda?**
📚 **[GUIA COMPLETO: Gerar Certificados Apple Pay](GUIA-CERTIFICADOS-APPLE-PAY.md)**

Este guia explica passo a passo:
- Como criar Merchant ID
- Como gerar CSR e certificados
- Como converter para .pem
- Troubleshooting completo

---

### 2️⃣ Configurar Merchant ID

Edite o arquivo `validate-merchant.php` (linha ~68):

```php
$merchantIdentifier = 'merchant.com.SEU_DOMINIO.exemplo'; // ← Altere aqui!
$displayName = 'Nome da Sua Loja';                        // ← E aqui!
```

**Onde encontrar seu Merchant ID?**
- Apple Developer → Certificates, Identifiers & Profiles → Identifiers
- Procure por "Merchant IDs"

---

### 3️⃣ Verificar Configuração

Execute o **novo script de verificação**:

```bash
php verificar-certificados.php
```

Este script verifica:
- ✅ Se os certificados existem
- ✅ Permissões corretas
- ✅ Formato PEM válido
- ✅ Validade do certificado
- ✅ Compatibilidade certificado + chave
- ✅ Configuração do validate-merchant.php

Se aparecer "🎉 Configuração válida!", continue para o próximo passo.

---

### 4️⃣ Iniciar Servidor Local

```bash
# Terminal 1: Servidor PHP
php -S localhost:8000
```

Em outro terminal:

```bash
# Terminal 2: Expor via HTTPS com ngrok
ngrok http 8000
```

**Importante:** Apple Pay só funciona em HTTPS! O ngrok fornece uma URL HTTPS automaticamente.

---

### 5️⃣ Testar no Safari

1. Copie a URL HTTPS do ngrok (ex: `https://abc123.ngrok-free.app`)
2. Abra no **Safari** (iPhone, iPad ou Mac)
3. Acesse: `https://abc123.ngrok-free.app/index.php`
4. Clique em "Pagar com Apple Pay"

---

## 🔍 Troubleshooting Rápido

### ❌ "Apple Pay não suportado"
- Use **Safari** (Chrome/Firefox não suportam)
- Precisa ter um cartão configurado no Apple Pay
- URL deve ser HTTPS

### ❌ "Erro ao validar merchant"
- Verifique se o Merchant ID está correto
- Confirme que os certificados não expiraram
- Veja logs no console do browser (F12 → Console)

### ❌ "Certificado não encontrado"
- Os arquivos estão em `certs/apple_pay_cert.pem` e `certs/apple_pay_key.pem`?
- Execute: `chmod 600 certs/*.pem`

### ❌ "CORS error"
- Certifique-se de acessar via ngrok
- Não use `file://` (deve ser `http://` ou `https://`)

---

## 📊 Fluxo de Funcionamento

```
┌─────────────┐
│   Safari    │
│ (index.php) │
└──────┬──────┘
       │ 1. Usuário clica "Pagar"
       │
       │ 2. onvalidatemerchant
       ▼
┌─────────────────────┐
│ validate-merchant   │  ◄── Você criou este arquivo!
│      .php           │
└──────┬──────────────┘
       │ 3. POST com certificados
       ▼
┌─────────────┐
│   Apple     │
│  Servers    │
└──────┬──────┘
       │ 4. Retorna merchantSession
       │
       ▼
┌─────────────┐
│   Safari    │
│ (Apple Pay  │
│    UI)      │
└──────┬──────┘
       │ 5. Usuário autoriza
       │
       │ 6. Retorna payment token
       ▼
┌─────────────┐
│ Seu Backend │  ◄── Próximo passo: processar pagamento
│ (PagBank)   │
└─────────────┘
```

---

## 📝 Checklist

- [ ] Certificados gerados (veja `GUIA-CERTIFICADOS-APPLE-PAY.md`)
- [ ] Certificados na pasta `certs/` (apple_pay_cert.pem + apple_pay_key.pem)
- [ ] Merchant ID configurado em `validate-merchant.php`
- [ ] Script `verificar-certificados.php` passou sem erros ✅
- [ ] Domínio registrado no Apple Developer Console
- [ ] Servidor PHP rodando (`php -S localhost:8000`)
- [ ] ngrok expondo via HTTPS
- [ ] Testado no Safari com Apple Pay configurado

---

## 🎯 Próximos Passos

Depois que a validação funcionar:

1. ✅ Validação do Merchant (você está aqui!)
2. ⏳ Processar Payment Token
3. ⏳ Integrar com Gateway (PagBank)
4. ⏳ Capturar Pagamento

---

## 📞 Recursos Úteis

- **Documentação Apple Pay:** https://developer.apple.com/apple-pay/
- **Console do Desenvolvedor:** https://developer.apple.com/account/
- **ngrok (HTTPS local):** https://ngrok.com/download

---

## 💡 Dica Pro

Durante desenvolvimento, mantenha o Console do Safari aberto (F12) para ver os logs:

```javascript
// Logs úteis aparecem aqui:
console.log('Validação iniciada...');
console.log('merchantSession:', merchantSession);
```

---

**🎉 Pronto para testar!**

Se tiver problemas, consulte o arquivo `README-CONFIGURACAO.md` para informações detalhadas.

