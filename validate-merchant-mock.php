<?php
/**
 * Mock do Endpoint de Validação Apple Pay
 * 
 * Use este arquivo para TESTES LOCAIS quando você ainda NÃO tem os certificados.
 * Ele retorna uma resposta SIMULADA (fake) do merchantSession.
 * 
 * ⚠️ ATENÇÃO: Este mock NÃO funcionará com Apple Pay real!
 * É apenas para testar a integração do front-end.
 * 
 * Para usar:
 * 1. No index.php, altere VALIDATE_ENDPOINT para './validate-merchant-mock.php'
 * 2. Teste a UI e o fluxo (mas o pagamento real não funcionará)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Recebe dados
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log para debug
error_log("Mock Apple Pay - Validação recebida: " . json_encode($data));

// Simula delay da Apple
usleep(300000); // 300ms

// Validação básica
if (!isset($data['validationURL'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'validationURL é obrigatório'
    ]);
    exit;
}

/**
 * Resposta MOCK do merchantSession
 * 
 * Esta é uma estrutura SIMULADA baseada no formato real da Apple.
 * Os valores são fictícios e NÃO funcionarão para pagamentos reais.
 */
$mockMerchantSession = [
    'epochTimestamp' => time() * 1000,
    'expiresAt' => (time() + 300) * 1000, // Expira em 5 minutos
    'merchantSessionIdentifier' => 'MOCK_' . bin2hex(random_bytes(16)),
    'nonce' => bin2hex(random_bytes(32)),
    'merchantIdentifier' => 'merchant.com.exemplo.mock',
    'domainName' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'displayName' => 'Loja Mock (Teste)',
    'signature' => base64_encode(random_bytes(128)),
    
    // Metadados adicionais
    'operationalAnalyticsIdentifier' => 'Merchant:' . bin2hex(random_bytes(8)),
    'retries' => 0,
    'pspId' => bin2hex(random_bytes(16)),
    
    // Avisos para o desenvolvedor
    '_mock' => true,
    '_warning' => 'Esta é uma resposta SIMULADA. Não funcionará com Apple Pay real.',
    '_instructions' => [
        'Para usar Apple Pay de verdade:',
        '1. Obtenha certificados reais no Apple Developer',
        '2. Configure validate-merchant.php (não o -mock.php)',
        '3. Use um domínio HTTPS registrado na Apple'
    ]
];

// Retorna o mock
http_response_code(200);
echo json_encode($mockMerchantSession);

// Log de sucesso
error_log("Mock Apple Pay - Validação simulada com sucesso");

