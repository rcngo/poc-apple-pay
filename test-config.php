<?php
/**
 * Script de Teste - Configuração Apple Pay
 * 
 * Execute este arquivo para verificar se tudo está configurado corretamente
 * ANTES de testar a integração completa.
 * 
 * Execute via terminal: php test-config.php
 * Ou acesse via browser: http://localhost:8000/test-config.php
 */

echo "===========================================\n";
echo "  TESTE DE CONFIGURAÇÃO - APPLE PAY\n";
echo "===========================================\n\n";

// Cores para terminal
$verde = "\033[32m";
$vermelho = "\033[31m";
$amarelo = "\033[33m";
$reset = "\033[0m";

$erros = 0;
$avisos = 0;

// 1. Verificar PHP e extensões
echo "1. Verificando PHP...\n";
echo "   Versão: " . PHP_VERSION;
if (version_compare(PHP_VERSION, '7.4.0') >= 0) {
    echo " {$verde}✓{$reset}\n";
} else {
    echo " {$amarelo}⚠ Recomendado PHP 7.4+{$reset}\n";
    $avisos++;
}

// 2. Verificar extensão cURL
echo "\n2. Verificando cURL...\n";
if (extension_loaded('curl')) {
    $curlVersion = curl_version();
    echo "   cURL: " . $curlVersion['version'] . " {$verde}✓{$reset}\n";
    echo "   SSL: " . $curlVersion['ssl_version'] . " {$verde}✓{$reset}\n";
} else {
    echo "   {$vermelho}✗ cURL não está instalada!{$reset}\n";
    echo "   Instale com: apt-get install php-curl (Ubuntu) ou yum install php-curl (CentOS)\n";
    $erros++;
}

// 3. Verificar extensão JSON
echo "\n3. Verificando JSON...\n";
if (extension_loaded('json')) {
    echo "   JSON: habilitado {$verde}✓{$reset}\n";
} else {
    echo "   {$vermelho}✗ JSON não está habilitado!{$reset}\n";
    $erros++;
}

// 4. Verificar pasta certs
echo "\n4. Verificando pasta de certificados...\n";
$certsDir = __DIR__ . '/certs';
if (is_dir($certsDir)) {
    echo "   Pasta certs/: existe {$verde}✓{$reset}\n";
    
    // Verificar permissões
    $perms = substr(sprintf('%o', fileperms($certsDir)), -4);
    echo "   Permissões: {$perms}";
    if ($perms === '0700' || $perms === '0755') {
        echo " {$verde}✓{$reset}\n";
    } else {
        echo " {$amarelo}⚠ Recomendado: 700{$reset}\n";
        $avisos++;
    }
} else {
    echo "   {$vermelho}✗ Pasta certs/ não existe!{$reset}\n";
    echo "   Crie com: mkdir certs && chmod 700 certs\n";
    $erros++;
}

// 5. Verificar certificados
echo "\n5. Verificando certificados Apple Pay...\n";
$certPath = $certsDir . '/merchant_cert.pem';
$keyPath = $certsDir . '/merchant_key.pem';

if (file_exists($certPath)) {
    echo "   merchant_cert.pem: encontrado {$verde}✓{$reset}\n";
    
    // Tentar ler informações do certificado
    $certData = @file_get_contents($certPath);
    if ($certData && strpos($certData, 'BEGIN CERTIFICATE') !== false) {
        echo "   Formato do certificado: válido {$verde}✓{$reset}\n";
        
        // Tentar obter informações com openssl
        if (function_exists('openssl_x509_parse')) {
            $certInfo = @openssl_x509_parse($certData);
            if ($certInfo) {
                echo "   Subject: " . ($certInfo['subject']['CN'] ?? 'N/A') . "\n";
                $validTo = date('Y-m-d', $certInfo['validTo_time_t']);
                $hoje = date('Y-m-d');
                
                echo "   Válido até: {$validTo}";
                if ($validTo > $hoje) {
                    echo " {$verde}✓{$reset}\n";
                } else {
                    echo " {$vermelho}✗ EXPIRADO!{$reset}\n";
                    $erros++;
                }
            }
        }
    } else {
        echo "   {$vermelho}✗ Formato inválido (não é um certificado PEM){$reset}\n";
        $erros++;
    }
} else {
    echo "   {$vermelho}✗ merchant_cert.pem não encontrado!{$reset}\n";
    $erros++;
}

if (file_exists($keyPath)) {
    echo "   merchant_key.pem: encontrado {$verde}✓{$reset}\n";
    
    $keyData = @file_get_contents($keyPath);
    if ($keyData && (strpos($keyData, 'BEGIN PRIVATE KEY') !== false || strpos($keyData, 'BEGIN RSA PRIVATE KEY') !== false)) {
        echo "   Formato da chave: válido {$verde}✓{$reset}\n";
    } else {
        echo "   {$vermelho}✗ Formato inválido (não é uma chave privada PEM){$reset}\n";
        $erros++;
    }
} else {
    echo "   {$vermelho}✗ merchant_key.pem não encontrado!{$reset}\n";
    $erros++;
}

// 6. Verificar arquivo validate-merchant.php
echo "\n6. Verificando validate-merchant.php...\n";
$validateFile = __DIR__ . '/validate-merchant.php';
if (file_exists($validateFile)) {
    echo "   Arquivo: encontrado {$verde}✓{$reset}\n";
    
    // Verificar se o merchant ID foi alterado
    $conteudo = file_get_contents($validateFile);
    if (strpos($conteudo, 'merchant.com.seudominio.exemplo') !== false) {
        echo "   {$amarelo}⚠ Merchant ID precisa ser configurado!{$reset}\n";
        echo "   Edite validate-merchant.php e altere a linha:\n";
        echo "   \$merchantIdentifier = 'merchant.com.seudominio.exemplo';\n";
        $avisos++;
    } else {
        echo "   Merchant ID: configurado {$verde}✓{$reset}\n";
    }
    
    // Verificar permissões de execução
    if (is_readable($validateFile)) {
        echo "   Permissões: legível {$verde}✓{$reset}\n";
    } else {
        echo "   {$vermelho}✗ Arquivo não tem permissão de leitura!{$reset}\n";
        $erros++;
    }
} else {
    echo "   {$vermelho}✗ validate-merchant.php não encontrado!{$reset}\n";
    $erros++;
}

// 7. Verificar HTTPS
echo "\n7. Verificando ambiente HTTPS...\n";
if (is_https_active()) {
    echo "   HTTPS: ativo {$verde}✓{$reset}\n";
} else {
    echo "   {$amarelo}⚠ HTTPS não detectado{$reset}\n";
    echo "   Apple Pay EXIGE HTTPS em produção!\n";
    echo "   Para desenvolvimento local, use ngrok: ngrok http 8000\n";
    $avisos++;
}

// 8. Verificar index.php
echo "\n8. Verificando index.php...\n";
$indexFile = __DIR__ . '/index.php';
if (file_exists($indexFile)) {
    echo "   Arquivo: encontrado {$verde}✓{$reset}\n";
    
    $indexContent = file_get_contents($indexFile);
    if (strpos($indexContent, './validate-merchant.php') !== false) {
        echo "   Endpoint configurado: validate-merchant.php {$verde}✓{$reset}\n";
    } else {
        echo "   {$amarelo}⚠ index.php não aponta para validate-merchant.php{$reset}\n";
        echo "   Verifique a constante VALIDATE_ENDPOINT\n";
        $avisos++;
    }
} else {
    echo "   {$amarelo}⚠ index.php não encontrado{$reset}\n";
    $avisos++;
}

// Resumo
echo "\n===========================================\n";
echo "  RESUMO\n";
echo "===========================================\n";

if ($erros === 0 && $avisos === 0) {
    echo "{$verde}✓ Tudo configurado corretamente!{$reset}\n";
    echo "\nPróximos passos:\n";
    echo "1. Configure seu Merchant ID em validate-merchant.php\n";
    echo "2. Inicie o servidor: php -S localhost:8000\n";
    echo "3. Exponha via HTTPS: ngrok http 8000\n";
    echo "4. Acesse index.php no Safari e teste\n";
} else {
    if ($erros > 0) {
        echo "{$vermelho}✗ {$erros} erro(s) encontrado(s){$reset}\n";
    }
    if ($avisos > 0) {
        echo "{$amarelo}⚠ {$avisos} aviso(s){$reset}\n";
    }
    echo "\nCorrija os problemas acima antes de testar.\n";
}

echo "\n";

function is_https_active() {
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    ) {
        return true;
    }

    return false;
}