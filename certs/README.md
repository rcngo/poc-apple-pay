# Pasta de Certificados Apple Pay

Esta pasta deve conter seus certificados Apple Pay no formato `.pem`.

## ⚠️ IMPORTANTE

**NUNCA faça commit desses arquivos no Git!** Eles são sensíveis e específicos da sua conta Apple Developer.

## 📁 Arquivos Necessários

Coloque os seguintes arquivos nesta pasta:

1. **apple_pay_cert.pem** - Certificado público do Apple Pay
2. **apple_pay_key.pem** - Chave privada do Apple Pay

## 🔐 Como Obter os Certificados

### 1. Acessar Apple Developer Console

Vá para: https://developer.apple.com/account/

### 2. Criar/Baixar Certificado

1. Acesse **Certificates, Identifiers & Profiles**
2. Clique em **Identifiers**
3. Selecione seu **Merchant ID** (ou crie um novo)
4. Crie um **Payment Processing Certificate** (para processar pagamentos)
5. Faça o download do certificado (arquivo `.cer`)

### 3. Converter Certificados para PEM

Se você tem arquivos `.p12` ou `.cer`, converta-os:

#### De .p12 para .pem:

```bash
# Extrair certificado público
openssl pkcs12 -in apple_pay_cert.p12 -out apple_pay_cert.pem -clcerts -nokeys

# Extrair chave privada
openssl pkcs12 -in apple_pay_cert.p12 -out apple_pay_key.pem -nocerts -nodes
```

#### De .cer para .pem:

```bash
openssl x509 -inform der -in apple_pay_cert.cer -out apple_pay_cert.pem
```

### 4. Definir Permissões Corretas

```bash
chmod 600 *.pem
```

## ✅ Verificar Configuração

Execute o script de teste:

```bash
php ../test-config.php
```

## 📚 Mais Informações

- [Apple Pay Certificate Guide](https://developer.apple.com/documentation/apple_pay_on_the_web/configuring_your_environment)
- [Merchant ID Setup](https://developer.apple.com/help/account/configure-app-capabilities/configure-apple-pay)

## 🆘 Problemas Comuns

### Erro: "No such file or directory"
- Verifique se os arquivos estão nesta pasta
- Confirme os nomes: `apple_pay_cert.pem` e `apple_pay_key.pem`

### Erro: "Permission denied"
- Execute: `chmod 600 *.pem`

### Erro: "Invalid certificate format"
- Confirme que converteu corretamente para `.pem`
- Abra o arquivo em um editor e verifique se começa com `-----BEGIN CERTIFICATE-----`

