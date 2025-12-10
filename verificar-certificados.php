<?php
/**
 * Script de Verificação de Certificados Apple Pay
 * 
 * Execute este script para verificar se os certificados estão configurados corretamente:
 * php verificar-certificados.php
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Verificação de Certificados Apple Pay                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$errors = [];
$warnings = [];
$success = [];

// Caminhos dos certificados
$certPath = __DIR__ . '/certs/apple_pay_cert.pem';
$keyPath = __DIR__ . '/certs/apple_pay_key.pem';

// ==========================================
// 1. Verificar se os arquivos existem
// ==========================================
echo "📁 1. Verificando arquivos...\n";

if (file_exists($certPath)) {
    $success[] = "✅ Certificado encontrado: apple_pay_cert.pem";
} else {
    $errors[] = "❌ Certificado NÃO encontrado: certs/apple_pay_cert.pem";
    $warnings[] = "   → Siga o GUIA-CERTIFICADOS-APPLE-PAY.md para gerar";
}

if (file_exists($keyPath)) {
    $success[] = "✅ Chave privada encontrada: apple_pay_key.pem";
} else {
    $errors[] = "❌ Chave privada NÃO encontrada: certs/apple_pay_key.pem";
    $warnings[] = "   → Siga o GUIA-CERTIFICADOS-APPLE-PAY.md para gerar";
}

echo "\n";

// Se não existem, não continuar
if (!empty($errors)) {
    printResults($success, $warnings, $errors);
    exit(1);
}

// ==========================================
// 2. Verificar permissões
// ==========================================
echo "🔒 2. Verificando permissões...\n";

$certPerms = substr(sprintf('%o', fileperms($certPath)), -3);
$keyPerms = substr(sprintf('%o', fileperms($keyPath)), -3);

if ($certPerms === '600' || $certPerms === '400') {
    $success[] = "✅ Permissões corretas no certificado: $certPerms";
} else {
    $warnings[] = "⚠️  Permissões do certificado: $certPerms (recomendado: 600)";
    $warnings[] = "   → Execute: chmod 600 certs/*.pem";
}

if ($keyPerms === '600' || $keyPerms === '400') {
    $success[] = "✅ Permissões corretas na chave: $keyPerms";
} else {
    $warnings[] = "⚠️  Permissões da chave: $keyPerms (recomendado: 600)";
    $warnings[] = "   → Execute: chmod 600 certs/*.pem";
}

echo "\n";

// ==========================================
// 3. Verificar formato dos arquivos
// ==========================================
echo "📄 3. Verificando formato...\n";

$certContent = file_get_contents($certPath);
$keyContent = file_get_contents($keyPath);

if (strpos($certContent, '-----BEGIN CERTIFICATE-----') !== false) {
    $success[] = "✅ Certificado em formato PEM válido";
} else {
    $errors[] = "❌ Certificado não está em formato PEM correto";
    $warnings[] = "   → Deve começar com: -----BEGIN CERTIFICATE-----";
}

if (strpos($keyContent, '-----BEGIN') !== false && 
    (strpos($keyContent, 'PRIVATE KEY') !== false || strpos($keyContent, 'RSA PRIVATE KEY') !== false)) {
    $success[] = "✅ Chave privada em formato PEM válido";
} else {
    $errors[] = "❌ Chave privada não está em formato PEM correto";
    $warnings[] = "   → Deve começar com: -----BEGIN PRIVATE KEY-----";
}

echo "\n";

// ==========================================
// 4. Verificar validade do certificado
// ==========================================
echo "📅 4. Verificando validade...\n";

$certData = openssl_x509_parse(file_get_contents($certPath));
$certificateCN = $certData['subject']['CN'] ?? null;
if ($certData) {
    $validFrom = date('d/m/Y H:i:s', $certData['validFrom_time_t']);
    $validTo = date('d/m/Y H:i:s', $certData['validTo_time_t']);
    $daysLeft = floor(($certData['validTo_time_t'] - time()) / 86400);
    
    echo "   📅 Válido de: $validFrom\n";
    echo "   📅 Válido até: $validTo\n";
    
    if ($daysLeft > 30) {
        $success[] = "✅ Certificado válido por mais $daysLeft dias";
    } elseif ($daysLeft > 0) {
        $warnings[] = "⚠️  Certificado expira em $daysLeft dias - renove em breve!";
    } else {
        $errors[] = "❌ Certificado EXPIRADO há " . abs($daysLeft) . " dias";
        $warnings[] = "   → Gere um novo certificado no Apple Developer Console";
    }
    
    // Extrair informações do Subject
    if (isset($certData['subject'])) {
        echo "   🏢 Emissor: " . ($certData['subject']['O'] ?? 'N/A') . "\n";
        echo "   🆔 CN: " . ($certData['subject']['CN'] ?? 'N/A') . "\n";
    }
} else {
    $errors[] = "❌ Não foi possível ler o certificado";
}

echo "\n";

// ==========================================
// 5. Verificar se certificado e chave combinam
// ==========================================
echo "🔐 5. Verificando compatibilidade certificado + chave...\n";

// Extrair módulo do certificado
$certModulus = null;
$keyModulus = null;

exec("openssl x509 -noout -modulus -in " . escapeshellarg($certPath) . " 2>&1", $certOutput, $certReturn);
if ($certReturn === 0 && !empty($certOutput[0])) {
    preg_match('/Modulus=([A-F0-9]+)/', $certOutput[0], $matches);
    $certModulus = $matches[1] ?? null;
}

// Extrair módulo da chave
exec("openssl rsa -noout -modulus -in " . escapeshellarg($keyPath) . " 2>&1", $keyOutput, $keyReturn);
if ($keyReturn === 0 && !empty($keyOutput[0])) {
    preg_match('/Modulus=([A-F0-9]+)/', $keyOutput[0], $matches);
    $keyModulus = $matches[1] ?? null;
}

if ($certModulus && $keyModulus) {
    $certHash = md5($certModulus);
    $keyHash = md5($keyModulus);
    
    if ($certHash === $keyHash) {
        $success[] = "✅ Certificado e chave privada SÃO COMPATÍVEIS!";
        echo "   🔑 Hash: $certHash\n";
    } else {
        $errors[] = "❌ Certificado e chave privada NÃO SÃO COMPATÍVEIS!";
        $warnings[] = "   → Cert hash: $certHash";
        $warnings[] = "   → Key hash:  $keyHash";
        $warnings[] = "   → Regenere o certificado usando a mesma chave privada";
    }
} else {
    $warnings[] = "⚠️  Não foi possível verificar compatibilidade (OpenSSL pode não estar disponível)";
}

echo "\n";

// ==========================================
// 6. Verificar configuração do validate-merchant.php
// ==========================================
echo "⚙️  6. Verificando configuração...\n";

$validateFile = __DIR__ . '/validate-merchant.php';
if (file_exists($validateFile)) {
    $validateContent = file_get_contents($validateFile);
    
    // Extrair configurações
    preg_match('/\$merchantIdentifier\s*=\s*[\'"]([^\'"]+)[\'"]/', $validateContent, $merchantMatch);
    preg_match('/\$displayName\s*=\s*[\'"]([^\'"]+)[\'"]/', $validateContent, $nameMatch);
    preg_match('/\$domainName\s*=\s*[\'"]([^\'"]+)[\'"]/', $validateContent, $domainMatch);
    
    $merchantId = $merchantMatch[1] ?? null;
    $displayName = $nameMatch[1] ?? null;
    $domainName = $domainMatch[1] ?? null;
    
    if ($merchantId && $merchantId !== 'merchant.com.seudominio.exemplo') {
        $success[] = "✅ Merchant ID configurado: $merchantId";
    } else {
        $warnings[] = "⚠️  Merchant ID não configurado ou usando valor padrão";
        $warnings[] = "   → Edite validate-merchant.php linha 70";
    }
    
    if ($displayName && $displayName !== 'Minha Loja') {
        echo "   🏪 Nome da loja: $displayName\n";
    } else {
        $warnings[] = "⚠️  Nome da loja usando valor padrão";
    }
    
    if ($domainName && !strpos($domainName, 'exemplo')) {
        echo "   🌐 Domínio: $domainName\n";
    } else {
        $warnings[] = "⚠️  Domínio não configurado corretamente";
        $warnings[] = "   → Edite validate-merchant.php linha 72";
    }

    // Validar se o certificado foi emitido para o mesmo Merchant ID configurado
    if ($merchantId && $certificateCN) {
        if ($merchantId === $certificateCN) {
            $success[] = "✅ CN do certificado bate com o Merchant ID ($merchantId)";
        } else {
            $errors[] = "❌ CN do certificado ($certificateCN) difere do Merchant ID ($merchantId)";
            $warnings[] = "   → Gere o CSR usando o Merchant ID correto no campo CN";
        }
    } elseif (!$certificateCN) {
        $warnings[] = "⚠️  Não foi possível ler o CN do certificado para comparar com o Merchant ID";
    }
} else {
    $errors[] = "❌ validate-merchant.php não encontrado";
}

echo "\n";

// ==========================================
// 7. Verificar extensão PHP cURL
// ==========================================
echo "🌐 7. Verificando extensões PHP...\n";

if (extension_loaded('curl')) {
    $success[] = "✅ Extensão cURL habilitada";
    
    // Verificar versão OpenSSL do cURL
    $curlVersion = curl_version();
    $sslVersion = $curlVersion['ssl_version'] ?? 'Desconhecida';
    echo "   🔒 OpenSSL: $sslVersion\n";
} else {
    $errors[] = "❌ Extensão cURL NÃO está habilitada";
    $warnings[] = "   → Instale: apt-get install php-curl (Linux) ou enable no php.ini";
}

if (extension_loaded('openssl')) {
    $success[] = "✅ Extensão OpenSSL habilitada";
    echo "   📦 Versão: " . OPENSSL_VERSION_TEXT . "\n";
} else {
    $errors[] = "❌ Extensão OpenSSL NÃO está habilitada";
    $warnings[] = "   → Enable extension=openssl no php.ini";
}

echo "\n";

// ==========================================
// RESULTADOS FINAIS
// ==========================================
printResults($success, $warnings, $errors);

// ==========================================
// PRÓXIMOS PASSOS
// ==========================================
if (empty($errors)) {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║  🎉 Próximos Passos                                          ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "1. Configure o domínio no Apple Developer Console\n";
    echo "2. Registre o domínio na sua Merchant ID\n";
    echo "3. Teste a validação com: php test-config.php\n";
    echo "4. Abra index.php em HTTPS no Safari\n";
    echo "\n";
    echo "📚 Consulte: GUIA-CERTIFICADOS-APPLE-PAY.md\n";
    echo "\n";
}

exit(empty($errors) ? 0 : 1);

// ==========================================
// FUNÇÕES AUXILIARES
// ==========================================

function printResults($success, $warnings, $errors) {
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║  📊 Resultados                                                ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    if (!empty($success)) {
        echo "✅ SUCESSO:\n";
        foreach ($success as $msg) {
            echo "   $msg\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "⚠️  AVISOS:\n";
        foreach ($warnings as $msg) {
            echo "   $msg\n";
        }
        echo "\n";
    }
    
    if (!empty($errors)) {
        echo "❌ ERROS:\n";
        foreach ($errors as $msg) {
            echo "   $msg\n";
        }
        echo "\n";
    }
    
    // Resumo
    $total = count($success) + count($warnings) + count($errors);
    $successCount = count($success);
    $warningCount = count($warnings);
    $errorCount = count($errors);
    
    echo "───────────────────────────────────────────────────────────────\n";
    echo "Total: $successCount ✅  |  $warningCount ⚠️  |  $errorCount ❌\n";
    echo "───────────────────────────────────────────────────────────────\n";
    
    if (empty($errors)) {
        echo "\n";
        echo "🎉 Configuração válida! Certificados prontos para uso.\n";
    } else {
        echo "\n";
        echo "⚠️  Corrija os erros acima antes de prosseguir.\n";
        echo "📚 Consulte: GUIA-CERTIFICADOS-APPLE-PAY.md\n";
    }
}
