<?php
/**
 * Endpoint de Validação do Merchant Apple Pay
 * 
 * Este arquivo processa a validação do merchant Apple Pay server-to-server.
 * É chamado pelo JavaScript front-end no evento onvalidatemerchant.
 * 
 * Requisitos:
 * - Certificados Apple Pay (.pem) na pasta certs/
 * - PHP com extensão cURL habilitada
 * - HTTPS (Apple Pay só funciona em ambiente seguro)
 */

// Headers CORS para permitir requisições do front-end
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responde OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit;
}

try {
    // Recebe o JSON do corpo da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validação básica
    if (!isset($data['validationURL']) || empty($data['validationURL'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'validationURL é obrigatório'
        ]);
        exit;
    }

    $validationURL = $data['validationURL'];

    // Valida se é uma URL da Apple
    if (!filter_var($validationURL, FILTER_VALIDATE_URL) || 
        !preg_match('/apple(-pay)?\.com/', $validationURL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'URL de validação inválida. Deve ser um domínio Apple.'
        ]);
        exit;
    }

    /**
     * ========================================
     * CONFIGURAÇÃO DO MERCHANT
     * ========================================
     * Configure esses valores conforme seu Merchant ID Apple Pay
     */
    $merchantIdentifier = 'merchant.vncsapplepay'; // Seu Merchant ID
    $displayName = 'Loja Teste';                          // Nome exibido no Apple Pay

    // Domínio atual (precisa estar registrado no Apple Pay Merchant ID)
    // Preferimos APPLE_PAY_DOMAIN, mas por padrão usamos o host da requisição.
    $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null;
    $requestHost = $forwardedHost ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $domainName = getenv('APPLE_PAY_DOMAIN') ?: preg_replace('/:\\d+$/', '', strtolower($requestHost));
    $domainName = "lojateste.checkoout.dev.br";
    /**
     * Caminhos dos certificados
     * Você precisa dos arquivos .pem gerados a partir do certificado Apple Pay
     */
    $certPath = __DIR__ . '/certs/merchant_cert.pem';
    $keyPath = __DIR__ . '/certs/merchant_key.pem';

    // Verifica se os certificados existem e são legíveis
    if (!file_exists($certPath) || !is_readable($certPath)) {
        throw new Exception("Certificado não encontrado ou ilegível: {$certPath}");
    }
    if (!file_exists($keyPath) || !is_readable($keyPath)) {
        throw new Exception("Chave privada não encontrada ou ilegível: {$keyPath}");
    }

    // Valida se o certificado foi emitido para o Merchant ID configurado
    $certContent = file_get_contents($certPath);
    $certData = openssl_x509_parse($certContent);

    $certificateCN  = $certData['subject']['CN']  ?? null;
    $certificateUID = $certData['subject']['UID'] ?? null;
    $validFrom      = $certData['validFrom_time_t'] ?? null;
    $validTo        = $certData['validTo_time_t']   ?? null;

    if ($certificateUID === null) {
        throw new Exception('Não foi possível ler o UID (Merchant ID) do certificado Apple Pay');
    }

    if ($certificateUID !== $merchantIdentifier) {
        throw new Exception("O UID do certificado ({$certificateUID}) difere do Merchant ID configurado ({$merchantIdentifier})");
    }

    // (Opcional) Log só informativo sobre o CN
    // Ex: Apple Pay Merchant Identity:merchant.vncsapplepay
    if ($certificateCN !== "Apple Pay Merchant Identity:{$merchantIdentifier}") {
        error_log("Aviso: CN do certificado é '{$certificateCN}', esperado 'Apple Pay Merchant Identity:{$merchantIdentifier}'");
    }

    $now = time();
    if ($validFrom !== null && $now < $validFrom) {
        throw new Exception('Certificado Apple Pay ainda não está válido (verifique data/hora do servidor)');
    }

    if ($validTo !== null && $now > $validTo) {
        throw new Exception('Certificado Apple Pay expirado — gere um novo certificado de pagamento');
    }

    // Valida se o certificado foi emitido para o Merchant ID configurado
    $certContent = file_get_contents($certPath);
    $certData = openssl_x509_parse($certContent);

    $certificateCN  = $certData['subject']['CN']  ?? null;
    $certificateUID = $certData['subject']['UID'] ?? null;
    $validFrom      = $certData['validFrom_time_t'] ?? null;
    $validTo        = $certData['validTo_time_t']   ?? null;

    if ($certificateUID === null) {
        throw new Exception('Não foi possível ler o UID (Merchant ID) do certificado Apple Pay');
    }

    if ($certificateUID !== $merchantIdentifier) {
        throw new Exception("O UID do certificado ({$certificateUID}) difere do Merchant ID configurado ({$merchantIdentifier})");
    }

    // valida datas do certificado
    $now = time();
    if ($validFrom !== null && $now < $validFrom) {
        throw new Exception('Certificado Apple Pay ainda não está válido (verifique data/hora do servidor)');
    }

    if ($validTo !== null && $now > $validTo) {
        throw new Exception('Certificado Apple Pay expirado — gere um novo certificado');
    }

    // (Opcional) só para log/diagnóstico
    // error_log("Apple Pay CN: {$certificateCN}, UID: {$certificateUID}");


    /**
     * ========================================
     * REQUISIÇÃO SERVER-TO-SERVER PARA APPLE
     * ========================================
     */
    $payload = [
        'merchantIdentifier' => $merchantIdentifier,
        'displayName' => $displayName,
        'initiative' => 'web',
        'initiativeContext' => $domainName
    ];

    $sslPassphrase = getenv('APPLE_PAY_KEY_PASSPHRASE') ?: null;

    $ch = curl_init($validationURL);
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSLCERT => $certPath,
        CURLOPT_SSLKEY => $keyPath,
        CURLOPT_KEYPASSWD => $sslPassphrase,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $curlInfo = curl_getinfo($ch);
    $errno    = curl_errno($ch);
    $err      = curl_error($ch);

    // Se der erro de cURL, retorna 500
    if ($errno) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro no cURL ao chamar Apple Pay: ' . $err,
            'curl_errno' => $errno,
            'curl_info'  => $curlInfo,
        ]);
        exit;
    }

    $httpCode = $curlInfo['http_code'] ?? 0;

    // Se a Apple não respondeu 200, retorna 500 com o corpo que ela mandou
    if ($httpCode !== 200) {
        http_response_code(500);
        echo json_encode([
            'success'        => false,
            'message'        => 'Erro ao validar merchant Apple Pay (HTTP ' . $httpCode . ')',
            'http_code'      => $httpCode,
            'apple_response' => $response,
            'curl_info'      => $curlInfo,
            'payload'        => $payload,
            'validation_url' => $validationURL,
        ]);
        exit;
    }
        // Então devolvemos exatamente isso para o frontend.
        header('Content-Type: application/json');
        http_response_code(200);
        echo $response;
        exit;

    /**
     * ========================================
     * RETORNA O MERCHANT SESSION
     * ========================================
     * Retorna o JSON recebido da Apple diretamente para o front-end
     */
    $merchantSession = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Resposta da Apple não é um JSON válido: ' . json_last_error_msg());
    }

    // Sucesso! Retorna o merchantSession
    http_response_code(200);
    echo json_encode($merchantSession);

} catch (Exception $e) {
    // Captura qualquer erro e retorna JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}