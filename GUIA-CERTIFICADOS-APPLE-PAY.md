# 🔐 Guia Completo: Como Gerar Certificados Apple Pay (.pem)

Este guia explica **passo a passo** como criar e configurar os certificados necessários para usar Apple Pay no seu backend.

---

## 📚 Índice

1. [Tipos de Certificados](#tipos-de-certificados)
2. [Pré-requisitos](#pré-requisitos)
3. [Passo 1: Criar Merchant ID](#passo-1-criar-merchant-id)
4. [Passo 2: Registrar Domínio](#passo-2-registrar-domínio)
5. [Passo 3: Gerar Certificado de Merchant Identity](#passo-3-gerar-certificado-de-merchant-identity)
6. [Passo 4: Converter para .pem](#passo-4-converter-para-pem)
7. [Passo 5: Verificar Certificados](#passo-5-verificar-certificados)
8. [Troubleshooting](#troubleshooting)

---

## 🎯 Tipos de Certificados

O Apple Pay usa **2 tipos de certificados**:

### 1. **Merchant Identity Certificate** (Obrigatório) ✅
- **Para que serve**: Validar seu merchant com a Apple
- **Quando é usado**: No `validate-merchant.php` para autenticar no servidor Apple
- **Arquivo gerado**: `apple_pay_cert.pem` + `apple_pay_key.pem`
- **👉 ESTE É O QUE VOCÊ PRECISA AGORA!**

### 2. **Payment Processing Certificate** (Opcional)
- **Para que serve**: Descriptografar tokens de pagamento no SEU backend
- **Quando é usado**: Apenas se você descriptografar os tokens antes de enviar ao PagBank
- **⚠️ Na maioria dos casos NÃO é necessário**: O PagBank descriptografa para você!

---

## 📋 Pré-requisitos

- [x] Conta Apple Developer ativa ($99/ano)
- [x] Acesso ao terminal (Mac, Linux ou Windows com Git Bash)
- [x] OpenSSL instalado (vem por padrão no Mac/Linux)
- [x] Domínio configurado com HTTPS

---

## 🚀 Passo 1: Criar Merchant ID

### 1.1 Acessar Apple Developer Console

Vá para: https://developer.apple.com/account/

### 1.2 Criar o Merchant ID

1. No menu lateral, clique em **"Certificates, Identifiers & Profiles"**
2. Clique em **"Identifiers"**
3. Clique no botão **"+"** (azul, no canto superior)
4. Selecione **"Merchant IDs"** → Clique em **"Continue"**

### 1.3 Preencher os dados

```
Description: Minha Loja Apple Pay
Identifier: merchant.com.suaempresa.applepay
```

**Exemplo real:**
```
Description: Adoorei Store Apple Pay
Identifier: merchant.adoorei
```

**⚠️ IMPORTANTE:**
- O Identifier deve ser único e não pode ser alterado depois
- Use o formato: `merchant.com.suaempresa.nomedoprojeto`
- Anote esse Merchant ID, você vai precisar dele!

5. Clique em **"Register"**

---

## 🌐 Passo 2: Registrar Domínio

Agora você precisa registrar o domínio onde o Apple Pay vai funcionar.

### 2.1 Acessar o Merchant ID criado

1. Na lista de Identifiers, clique no **Merchant ID** que você acabou de criar
2. Role até a seção **"Merchant Domains"**
3. Clique em **"Add Domain"**

### 2.2 Verificar o domínio

```
Digite seu domínio: lojateste.checkoout.dev.br
```

**⚠️ IMPORTANTE:**
- Use apenas o domínio, SEM `https://` ou `www`
- Se usar ngrok, registre: `seu-subdominio.ngrok-free.app`
- Você vai precisar fazer upload de um arquivo de verificação

### 2.3 Fazer o download do arquivo de verificação

1. Clique em **"Download"** para baixar o arquivo `apple-developer-merchantid-domain-association`
2. Coloque este arquivo no seu servidor no caminho:
   ```
   https://seudominio.com/.well-known/apple-developer-merchantid-domain-association
   ```

**Exemplo de estrutura:**
```
seu-projeto/
├── .well-known/
│   └── apple-developer-merchantid-domain-association
├── index.php
└── validate-merchant.php
```

3. Certifique-se de que o arquivo está acessível via HTTPS
4. Volte ao Apple Developer Console e clique em **"Verify"**

✅ Se aparecer "Verified", está tudo certo!

---

## 🔑 Passo 3: Gerar Certificado de Merchant Identity

Agora vamos criar o certificado que seu backend usa para se autenticar com a Apple.

### 3.1 Gerar CSR (Certificate Signing Request)

Abra o terminal e execute:

```bash
# Entre na pasta certs do seu projeto
cd "/Users/marcelo/Documents/Integração Apple Pay/certs"

# Gere a chave privada e o CSR
openssl req -new -newkey rsa:2048 -nodes \
  -keyout apple_pay_key.pem \
  -out apple_pay.csr \
  -subj "/C=BR/ST=SP/L=SaoPaulo/O=MinhaEmpresa/CN=merchant.adoorei"
```

**Ajuste os valores:**
- `C=BR` → Código do país (BR = Brasil)
- `ST=SP` → Estado (SP = São Paulo)
- `L=SaoPaulo` → Cidade
- `O=MinhaEmpresa` → Nome da sua empresa
- `CN=merchant.adoorei` → Seu Merchant ID

**✅ Isso vai criar 2 arquivos:**
- `apple_pay_key.pem` → Chave privada (GUARDAR COM SEGURANÇA!)
- `apple_pay.csr` → Requisição de certificado (vai enviar para Apple)

### 3.2 Criar Merchant Identity Certificate no Apple Developer

1. Volte ao Apple Developer Console
2. Clique no seu **Merchant ID**
3. Role até a seção **"Apple Pay Merchant Identity Certificate"**
4. Clique em **"Create Certificate"**

### 3.3 Fazer upload do CSR

1. Clique em **"Choose File"**
2. Selecione o arquivo `apple_pay.csr` que você gerou
3. Clique em **"Continue"**
4. Clique em **"Download"**

**✅ Você vai baixar um arquivo:**
- `merchant_id.cer` (ou nome similar)

---

## 🔄 Passo 4: Converter para .pem

Agora vamos converter o certificado `.cer` para o formato `.pem` que o PHP usa.

### 4.1 Converter o certificado

No terminal, execute:

```bash
# Entre na pasta onde baixou o certificado
cd ~/Downloads

# Converta de .cer para .pem
openssl x509 -inform der -in merchant_id.cer -out apple_pay_cert.pem

# Mova para a pasta certs do projeto
mv apple_pay_cert.pem "/Users/marcelo/Documents/Integração Apple Pay/certs/"
```

### 4.2 Verificar os arquivos finais

```bash
cd "/Users/marcelo/Documents/Integração Apple Pay/certs"
ls -la *.pem
```

**✅ Você deve ter 2 arquivos:**
```
-rw------- 1 marcelo staff 1679 Dec  9 10:00 apple_pay_key.pem
-rw------- 1 marcelo staff 1234 Dec  9 10:00 apple_pay_cert.pem
```

### 4.3 Definir permissões corretas

```bash
chmod 600 apple_pay_cert.pem
chmod 600 apple_pay_key.pem
```

**⚠️ Importante:** Permissões 600 significam que apenas o dono pode ler/escrever.

---

## ✅ Passo 5: Verificar Certificados

### 5.1 Verificar o conteúdo dos arquivos

```bash
# Ver o certificado
openssl x509 -in apple_pay_cert.pem -text -noout

# Ver a chave privada
openssl rsa -in apple_pay_key.pem -check
```

### 5.2 Verificar se certificado e chave combinam

```bash
# Extrair o módulo do certificado
openssl x509 -noout -modulus -in apple_pay_cert.pem | openssl md5

# Extrair o módulo da chave
openssl rsa -noout -modulus -in apple_pay_key.pem | openssl md5
```

**✅ Os dois comandos devem retornar o MESMO hash!**

Exemplo:
```
(stdin)= 1234567890abcdef1234567890abcdef  # certificado
(stdin)= 1234567890abcdef1234567890abcdef  # chave
```

### 5.3 Testar com seu backend

Execute o script de teste:

```bash
php test-config.php
```

**✅ Se aparecer:** "Certificados encontrados e válidos!" → Sucesso!

---

## 🔧 Configurar o validate-merchant.php

Edite o arquivo `validate-merchant.php` e atualize (linhas 70-72):

```php
$merchantIdentifier = 'merchant.adoorei';        // ← Seu Merchant ID
$displayName = 'Loja Teste';                     // ← Nome da loja
$domainName = 'lojateste.checkoout.dev.br';      // ← Seu domínio
```

**✅ Pronto! Agora seu backend pode validar o merchant com a Apple.**

---

## 🆘 Troubleshooting

### ❌ Erro: "Certificado não encontrado"

**Causa:** Arquivos não estão na pasta correta ou com nomes errados.

**Solução:**
```bash
cd "/Users/marcelo/Documents/Integração Apple Pay/certs"
ls -la
```

Certifique-se de ter:
- `apple_pay_cert.pem`
- `apple_pay_key.pem`

---

### ❌ Erro: "Permission denied"

**Causa:** Permissões incorretas nos arquivos.

**Solução:**
```bash
chmod 600 certs/*.pem
```

---

### ❌ Erro: "SSL certificate problem: unable to get local issuer certificate"

**Causa:** Certificado intermediário da Apple não encontrado.

**Solução:**

1. Baixe o certificado raiz da Apple:
```bash
curl -o certs/apple_root.pem https://www.apple.com/certificateauthority/AppleRootCA-G3.cer
openssl x509 -inform der -in certs/apple_root.pem -out certs/apple_root.pem
```

2. Adicione ao `validate-merchant.php` (linha 115):
```php
CURLOPT_CAINFO => __DIR__ . '/certs/apple_root.pem',
```

---

### ❌ Erro: "Merchant validation failed"

**Possíveis causas:**

1. **Merchant ID incorreto:**
   - Verifique se o `$merchantIdentifier` no `validate-merchant.php` é exatamente igual ao registrado no Apple Developer

2. **Domínio não verificado:**
   - Confirme que o domínio está verificado no Apple Developer Console
   - O arquivo `.well-known/apple-developer-merchantid-domain-association` deve estar acessível

3. **Certificado expirado:**
   - Verifique a validade:
   ```bash
   openssl x509 -in certs/apple_pay_cert.pem -noout -dates
   ```

4. **HTTPS não configurado:**
   - Apple Pay só funciona em HTTPS
   - Use ngrok se estiver testando localmente

---

### ❌ Erro: "unable to load certificate"

**Causa:** Formato do certificado incorreto.

**Solução:**

Verifique se o arquivo começa com:
```
-----BEGIN CERTIFICATE-----
```

E termina com:
```
-----END CERTIFICATE-----
```

Se não, reconverta:
```bash
openssl x509 -inform der -in merchant_id.cer -out apple_pay_cert.pem
```

---

## 📝 Resumo dos Arquivos

Após seguir este guia, você terá:

```
Integração Apple Pay/
├── certs/
│   ├── apple_pay_cert.pem      # ✅ Certificado público (baixado da Apple)
│   ├── apple_pay_key.pem       # ✅ Chave privada (gerada por você)
│   ├── apple_pay.csr           # 📄 CSR (pode deletar após gerar certificado)
│   └── README.md
├── .well-known/
│   └── apple-developer-merchantid-domain-association  # ✅ Verificação de domínio
├── validate-merchant.php        # ✅ Backend configurado
└── index.php                    # ✅ Frontend pronto
```

---

## 🎓 Conceitos Importantes

### O que é CSR?
**Certificate Signing Request** - É uma requisição que você envia para a Apple pedindo um certificado. Contém sua chave pública e informações da sua empresa.

### O que é .pem?
**Privacy Enhanced Mail** - É um formato de texto para armazenar certificados e chaves. Usado pelo OpenSSL e PHP.

### O que é .cer?
Formato de certificado binário usado pela Apple. Precisa ser convertido para .pem.

### O que é .p12?
Arquivo que contém tanto o certificado quanto a chave privada em um único arquivo (geralmente protegido por senha). Se você tiver um .p12, pode extrair ambos:

```bash
# Extrair certificado
openssl pkcs12 -in apple_pay.p12 -out apple_pay_cert.pem -clcerts -nokeys

# Extrair chave
openssl pkcs12 -in apple_pay.p12 -out apple_pay_key.pem -nocerts -nodes
```

---

## 🔗 Links Úteis

- [Apple Developer Account](https://developer.apple.com/account/)
- [Apple Pay Documentation](https://developer.apple.com/documentation/apple_pay_on_the_web)
- [Configuring Your Environment](https://developer.apple.com/documentation/apple_pay_on_the_web/configuring_your_environment)
- [Apple Pay JS API](https://developer.apple.com/documentation/apple_pay_on_the_web/apple_pay_js_api)

---

## 🎉 Próximos Passos

Após configurar os certificados:

1. ✅ Testar validação do merchant: `php test-config.php`
2. ✅ Testar em ambiente local com ngrok
3. ✅ Implementar processamento de pagamento com PagBank
4. ✅ Deploy em produção com HTTPS

---

## 💡 Dicas de Segurança

- ❌ **NUNCA** faça commit dos arquivos `.pem` no Git
- ✅ Adicione `*.pem` no `.gitignore`
- ✅ Use permissões 600 nos arquivos `.pem`
- ✅ Faça backup dos certificados em local seguro
- ✅ Renove os certificados antes de expirarem (válidos por ~1-2 anos)

---

**Dúvidas?** Consulte o [Troubleshooting](#troubleshooting) ou a [documentação oficial da Apple](https://developer.apple.com/documentation/apple_pay_on_the_web).
