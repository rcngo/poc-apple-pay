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
    $merchantIdentifier = 'merchant.adoorei'; // Seu Merchant ID
    $displayName = 'Loja Teste';                          // Nome exibido no Apple Pay

    // Domínio atual (precisa estar registrado no Apple Pay Merchant ID)
    // Preferimos APPLE_PAY_DOMAIN, mas por padrão usamos o host da requisição.
    $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null;
    $requestHost = $forwardedHost ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $domainName = getenv('APPLE_PAY_DOMAIN') ?: preg_replace('/:\\d+$/', '', strtolower($requestHost));
    
    /**
     * Caminhos dos certificados
     * Você precisa dos arquivos .pem gerados a partir do certificado Apple Pay
     */
    $certPath = __DIR__ . '/certs/apple_pay_cert.pem';       // Certificado público
    $keyPath = __DIR__ . '/certs/apple_pay_key.pem';         // Chave privada

    // Verifica se os certificados existem
    if (!file_exists($certPath)) {
        throw new Exception("Certificado não encontrado: {$certPath}");
    }
    if (!file_exists($keyPath)) {
        throw new Exception("Chave privada não encontrada: {$keyPath}");
    }

    // Valida se o CN do certificado bate com o Merchant ID configurado
    $certContent = file_get_contents($certPath);
    $certData = openssl_x509_parse($certContent);
    $certificateCN = $certData['subject']['CN'] ?? null;

    if ($certificateCN === null) {
        throw new Exception('Não foi possível ler o CN do certificado Apple Pay');
    }

    if ($certificateCN !== $merchantIdentifier) {
        throw new Exception("O CN do certificado ({$certificateCN}) difere do Merchant ID configurado ({$merchantIdentifier})");
    }

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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Verifica erros cURL
    if ($response === false || !empty($curlError)) {
        throw new Exception("Erro cURL: {$curlError}");
    }

    // Verifica resposta HTTP
    if ($httpCode !== 200) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao validar merchant Apple Pay',
            'http_code' => $httpCode,
            'apple_response' => $response,
            'payload' => $payload,
            'validation_url' => $validationURL
        ]);
        exit;
    }

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

