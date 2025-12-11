<?php
/**
 * Teste Rápido - Validação via ngrok
 * 
 * Acesse este arquivo via ngrok para verificar se o ambiente HTTPS está OK.
 * 
 * Exemplo: https://sua-url.ngrok-free.app/test-ngrok.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste ngrok + Apple Pay</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            line-height: 1.6;
        }
        .check { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 15px 0;
        }
        .error-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 15px 0;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🧪 Teste de Ambiente - Apple Pay + ngrok</h1>

    <?php
    $checks = [];
    $errors = 0;
    $warnings = 0;

    // 1. Verificar HTTPS
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        $_SERVER['SERVER_PORT'] == 443
    );

    if ($isHttps) {
        echo '<div class="success-box">';
        echo '<span class="check">✅ HTTPS Detectado!</span><br>';
        echo 'URL atual: <strong>' . htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . htmlspecialchars($_SERVER['HTTP_HOST']) . htmlspecialchars($_SERVER['REQUEST_URI']) . '</strong>';
        echo '</div>';
    } else {
        echo '<div class="error-box">';
        echo '<span class="error">❌ HTTPS NÃO detectado</span><br>';
        echo 'URL atual: <strong>' . htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . htmlspecialchars($_SERVER['HTTP_HOST']) . htmlspecialchars($_SERVER['REQUEST_URI']) . '</strong><br>';
        echo '<small>Apple Pay requer HTTPS. Certifique-se de acessar via ngrok.</small>';
        echo '</div>';
        $errors++;
    }

    // 2. Verificar se é ngrok
    $isNgrok = strpos($_SERVER['HTTP_HOST'] ?? '', 'ngrok') !== false;
    
    echo '<h2>📋 Informações do Servidor</h2>';
    echo '<div class="info-box">';
    echo '<strong>Host:</strong> ' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A') . '<br>';
    echo '<strong>É ngrok?</strong> ' . ($isNgrok ? '<span class="check">✅ Sim</span>' : '<span class="warning">⚠️ Não</span>') . '<br>';
    echo '<strong>User Agent:</strong> ' . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . '<br>';
    
    // Detectar Safari
    $isSafari = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Safari') !== false && 
                strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Chrome') === false;
    echo '<strong>Navegador:</strong> ' . ($isSafari ? '<span class="check">✅ Safari</span>' : '<span class="warning">⚠️ Não é Safari</span>') . '<br>';
    
    echo '<strong>IP do Cliente:</strong> ' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'N/A');
    echo '</div>';

    // 3. Verificar certificados
    echo '<h2>🔐 Certificados Apple Pay</h2>';
    
    $certPath = __DIR__ . '/certs/merchant_cert.pem';
    $keyPath = __DIR__ . '/certs/merchant_key.pem';
    
    $certExists = file_exists($certPath);
    $keyExists = file_exists($keyPath);
    
    if ($certExists && $keyExists) {
        echo '<div class="success-box">';
        echo '<span class="check">✅ Certificados encontrados</span><br>';
        echo 'Certificado: <code>certs/merchant_cert.pem</code><br>';
        echo 'Chave: <code>certs/merchant_key.pem</code>';
        echo '</div>';
    } else {
        echo '<div class="error-box">';
        echo '<span class="error">❌ Certificados não encontrados</span><br>';
        if (!$certExists) echo '- Faltando: <code>certs/merchant_cert.pem</code><br>';
        if (!$keyExists) echo '- Faltando: <code>certs/merchant_key.pem</code><br>';
        echo '</div>';
        $errors++;
    }

    // 4. Verificar validate-merchant.php
    echo '<h2>⚙️ Configuração do Merchant</h2>';
    
    $validateFile = __DIR__ . '/validate-merchant.php';
    if (file_exists($validateFile)) {
        $content = file_get_contents($validateFile);
        
        // Extrair Merchant ID
        if (preg_match('/\$merchantIdentifier\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $merchantId = $matches[1];
            
            echo '<div class="info-box">';
            echo '<strong>Merchant ID configurado:</strong> <code>' . htmlspecialchars($merchantId) . '</code><br>';
            
            if ($merchantId === 'merchant.com.seudominio.exemplo') {
                echo '<span class="warning">⚠️ Ainda é o valor padrão! Configure seu Merchant ID real.</span>';
                $warnings++;
            } else {
                echo '<span class="check">✅ Configurado</span>';
            }
            echo '</div>';
        }
        
        // Extrair Display Name
        if (preg_match('/\$displayName\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            echo '<div class="info-box">';
            echo '<strong>Nome da Loja:</strong> ' . htmlspecialchars($matches[1]);
            echo '</div>';
        }
    }

    // 5. Verificar suporte Apple Pay (JavaScript)
    ?>

    <h2>🍎 Suporte Apple Pay</h2>
    <div id="applePay" class="info-box">
        <span id="applePayStatus">Verificando...</span>
    </div>

    <script>
        const statusEl = document.getElementById('applePayStatus');
        
        if (window.ApplePaySession) {
            if (ApplePaySession.canMakePayments()) {
                statusEl.innerHTML = '<span class="check">✅ Apple Pay está disponível!</span><br>' +
                                   '<small>Versão Apple Pay JS: ' + ApplePaySession.supportsVersion(3) + '</small>';
            } else {
                statusEl.innerHTML = '<span class="warning">⚠️ Apple Pay disponível mas sem cartões configurados</span>';
            }
        } else {
            statusEl.innerHTML = '<span class="error">❌ Apple Pay não está disponível</span><br>' +
                               '<small>Use Safari em um dispositivo Apple com Apple Pay configurado</small>';
        }
    </script>

    <?php
    // Resumo final
    echo '<h2>📊 Resumo</h2>';
    
    if ($errors === 0 && $warnings === 0 && $isHttps && $isSafari) {
        echo '<div class="success-box">';
        echo '<h3 style="margin-top:0">🎉 Tudo pronto para testar!</h3>';
        echo '<p>Seu ambiente está configurado corretamente. Clique no botão abaixo para testar o Apple Pay:</p>';
        echo '<a href="index.php" class="btn">🚀 Testar Apple Pay</a>';
        echo '</div>';
    } else {
        echo '<div class="info-box">';
        echo '<strong>Problemas encontrados:</strong><br>';
        if ($errors > 0) echo "❌ {$errors} erro(s)<br>";
        if ($warnings > 0) echo "⚠️ {$warnings} aviso(s)<br>";
        if (!$isHttps) echo "- Acesse via HTTPS (ngrok)<br>";
        if (!$isSafari) echo "- Use o navegador Safari<br>";
        echo '</div>';
        
        echo '<a href="index.php" class="btn">Testar Mesmo Assim</a>';
    }
    ?>

    <h2>🔗 Links Úteis</h2>
    <div class="info-box">
        <a href="validate-merchant.php" target="_blank">📄 validate-merchant.php</a> (não deve abrir no browser)<br>
        <a href="test-config.php" target="_blank">🔍 test-config.php</a> (verificação completa)<br>
        <a href="index.php">🎯 index.php</a> (página de teste)
    </div>

    <hr>
    <small style="color: #666;">
        💡 Dica: Mantenha o Console do Safari aberto (Cmd+Opt+C) para ver logs de debug
    </small>
</body>
</html>

