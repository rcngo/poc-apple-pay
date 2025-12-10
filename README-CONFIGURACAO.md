# Configuração Apple Pay - Validação Local

## 📋 Arquivos Criados

- `validate-merchant.php` - Endpoint de validação do merchant
- `index.php` - Front-end para testes (já existente)

## 🔧 Passo a Passo para Configurar

### 1. Criar pasta para certificados

```bash
mkdir certs
chmod 700 certs
```

### 2. Adicionar seus certificados Apple Pay

Você precisa dos certificados no formato `.pem`. Se você tem arquivos `.p12` ou `.cer`, converta-os:

#### Converter .p12 para .pem:

```bash
# Extrair certificado
openssl pkcs12 -in apple_pay_cert.p12 -out certs/apple_pay_cert.pem -clcerts -nokeys

# Extrair chave privada
openssl pkcs12 -in apple_pay_cert.p12 -out certs/apple_pay_key.pem -nocerts -nodes
```

### 3. Configurar o validate-merchant.php

Edite o arquivo `validate-merchant.php` e altere as seguintes linhas (por volta da linha 68-70):

```php
$merchantIdentifier = 'merchant.com.seudominio.exemplo'; // ← SEU MERCHANT ID
$displayName = 'Minha Loja';                             // ← NOME DA SUA LOJA
```

**Onde encontrar seu Merchant ID:**
- Apple Developer Console → Certificates, Identifiers & Profiles → Identifiers
- Procure por "Merchant IDs"
- Exemplo: `merchant.com.minhaempresa.applepay`

### 4. Atualizar o index.php

Altere a linha 69 do `index.php` para apontar para o arquivo local:

**ANTES:**
```javascript
const VALIDATE_ENDPOINT = 'https://be0844367de6.ngrok-free.app/charge/apple-pay/validate-merchant';
```

**DEPOIS:**
```javascript
const VALIDATE_ENDPOINT = './validate-merchant.php';  // ou caminho completo
```

### 5. Servir via HTTPS

Apple Pay **EXIGE HTTPS**. Use uma das opções:

#### Opção A - PHP Built-in Server com ngrok:

```bash
# Terminal 1: Iniciar servidor PHP
php -S localhost:8000

# Terminal 2: Expor via ngrok
ngrok http 8000
```

Depois acesse a URL HTTPS fornecida pelo ngrok.

#### Opção B - Servidor local com certificado SSL:

Se você tem Apache/Nginx configurado com SSL, aponte para a pasta do projeto.

### 6. Testar

1. Abra o `index.php` no Safari (em HTTPS)
2. Clique no botão "Pagar com Apple Pay"
3. Verifique o console do navegador para debug

## 🔍 Estrutura de Pastas

```
Integração Apple Pay/
├── index.php                    # Front-end
├── validate-merchant.php        # Endpoint de validação ✨
├── teste.php                    # (arquivo existente)
├── certs/                       # 🔒 Certificados (criar)
│   ├── apple_pay_cert.pem      # Certificado público
│   └── apple_pay_key.pem       # Chave privada
└── README-CONFIGURACAO.md       # Este arquivo
```

## ⚠️ Troubleshooting

### Erro: "Certificado não encontrado"
- Verifique se os arquivos `.pem` estão na pasta `certs/`
- Verifique permissões: `chmod 600 certs/*.pem`

### Erro: "URL de validação inválida"
- A `validationURL` deve vir da Apple (começa com `https://apple-pay-gateway`)
- Não altere esta URL, ela é fornecida automaticamente pelo navegador

### Erro: "Merchant validation failed"
- Verifique se o Merchant ID está correto
- Verifique se o domínio está registrado no Apple Developer
- Confirme que os certificados são válidos e não expiraram

### Erro CORS
- Se testar de domínio diferente, ajuste o header `Access-Control-Allow-Origin`
- Na linha 15 do `validate-merchant.php`, troque `*` pelo seu domínio

## 📚 Referências

- [Apple Pay JS API](https://developer.apple.com/documentation/apple_pay_on_the_web)
- [Payment Request Validation](https://developer.apple.com/documentation/apple_pay_on_the_web/apple_pay_js_api/requesting_an_apple_pay_payment_session)

## 💡 Próximos Passos

Após validar o merchant com sucesso, você precisará:

1. Processar o payment token recebido
2. Enviar para seu gateway de pagamento (PagBank, etc)
3. Capturar o pagamento

O token é retornado no evento `onpaymentauthorized` do JavaScript (linha 154-195 do `index.php`).

