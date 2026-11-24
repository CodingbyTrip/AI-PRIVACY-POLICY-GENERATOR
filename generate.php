<?php
ini_set('display_errors', 0);
set_time_limit(120);
header('Content-Type: application/json');

set_exception_handler(function ($e) {
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
    exit;
});

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$url = filter_var(trim($input['url'] ?? ''), FILTER_VALIDATE_URL);
$companyName = htmlspecialchars(trim($input['company_name'] ?? ''));
$contactEmail = filter_var(trim($input['contact_email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$url) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid URL (including https://).']);
    exit;
}

// 1. Scrape Website Content & Technical Signals
function scrapeWebsiteSignals(string $targetUrl): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $targetUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);
    $html = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $httpCode === 0) {
        return ['error' => 'Website connection failed: ' . ($curlError ?: 'Unknown cURL error')];
    }

    if ($httpCode >= 400) {
        return ['error' => "Website returned HTTP Status: $httpCode."];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $titleNode = $xpath->query('//title')->item(0);
    $title = $titleNode ? trim($titleNode->textContent) : '';

    $descNode = $xpath->query('//meta[@name="description"]/@content')->item(0);
    $description = $descNode ? trim($descNode->nodeValue) : '';

    $inputs = [];
    foreach ($xpath->query('//input') as $input) {
        $type = $input->getAttribute('type') ?: 'text';
        $name = $input->getAttribute('name') ?: $input->getAttribute('placeholder');
        if ($name) {
            $inputs[] = "$type ($name)";
        }
    }

    $scripts = [];
    foreach ($xpath->query('//script[@src]') as $script) {
        $scripts[] = $script->getAttribute('src');
    }

    foreach ($xpath->query('//script|//style|//noscript') as $remove) {
        $remove->parentNode->removeChild($remove);
    }
    $bodyText = preg_replace('/\s+/', ' ', trim($dom->textContent ?? ''));
    $bodySnippet = mb_substr($bodyText, 0, 3500);

    return [
        'title'       => $title,
        'description' => $description,
        'inputs'      => array_slice(array_unique($inputs), 0, 20),
        'scripts'     => array_slice(array_unique($scripts), 0, 20),
        'snippet'     => $bodySnippet
    ];
}

$scraped = scrapeWebsiteSignals($url);
if (isset($scraped['error'])) {
    echo json_encode(['status' => 'error', 'message' => $scraped['error']]);
    exit;
}

// 2. Build Prompt for Gemini API
$detectedSummary = json_encode([
    'url'              => $url,
    'company_name'     => $companyName ?: $scraped['title'],
    'contact_email'    => $contactEmail ?: 'privacy@' . parse_url($url, PHP_URL_HOST),
    'site_title'       => $scraped['title'],
    'meta_description' => $scraped['description'],
    'form_inputs'      => $scraped['inputs'],
    'external_scripts' => $scraped['scripts'],
    'page_excerpt'     => $scraped['snippet']
], JSON_UNESCAPED_SLASHES);

$systemPrompt = "You are a legal compliance specialist. Generate a comprehensive, well-structured, ready-to-publish Privacy Policy in clean Markdown tailored specifically to the analyzed website data provided. Include standard applicable clauses (Data Collection, Cookies & Tracking, Third-Party Services, Data Security, User Rights under GDPR/CCPA/DPDP, Children's Privacy, Policy Updates, and Contact Information) based on the detected inputs and third-party scripts.";

$userPrompt = "Analyze the following website metadata and scraped technical signals, then generate an accurate Privacy Policy:\n\n" . $detectedSummary;

// 3. Call Gemini API with Automatic ListModels Fallback
function callGeminiModel(string $modelName, array $payload): array {
    $cleanModel = strpos($modelName, 'models/') === 0 ? $modelName : 'models/' . trim($modelName);
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/" . $cleanModel . ":generateContent";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . trim(GEMINI_API_KEY)
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text) {
            return ['success' => true, 'text' => $text];
        }
    }

    $decoded = json_decode($response, true);
    return [
        'success' => false,
        'code'    => $httpCode,
        'error'   => $decoded['error']['message'] ?? $curlErr ?: "HTTP Status $httpCode"
    ];
}

function getAvailableGeminiModels(): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://generativelanguage.googleapis.com/v1beta/models",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => ['x-goog-api-key: ' . trim(GEMINI_API_KEY)]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res, true);
    $valid = [];
    foreach ($data['models'] ?? [] as $m) {
        $name = $m['name'] ?? '';
        $methods = $m['supportedGenerationMethods'] ?? [];
        if (stripos($name, 'gemini') !== false && in_array('generateContent', $methods)) {
            $valid[] = $name;
        }
    }
    return $valid;
}

$payload = [
    'systemInstruction' => [
        'parts' => [['text' => $systemPrompt]]
    ],
    'contents' => [
        [
            'role'  => 'user',
            'parts' => [['text' => $userPrompt]]
        ]
    ],
    'generationConfig' => [
        'temperature'     => 0.3,
        'maxOutputTokens' => 4096
    ]
];

$result = callGeminiModel(GEMINI_MODEL, $payload);
$generatedPolicy = '';
$lastError = $result['error'] ?? 'Unknown error';

if ($result['success']) {
    $generatedPolicy = $result['text'];
} else {
    $availableModels = getAvailableGeminiModels();
    foreach ($availableModels as $fallbackModel) {
        if (str_ends_with($fallbackModel, trim(GEMINI_MODEL))) {
            continue;
        }
        sleep(1);
        $fallbackResult = callGeminiModel($fallbackModel, $payload);
        if ($fallbackResult['success']) {
            $generatedPolicy = $fallbackResult['text'];
            break;
        }
        $lastError = $fallbackResult['error'];
    }
}

if (!$generatedPolicy) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gemini API Error: ' . $lastError
    ]);
    exit;
}

// 4. Save to MySQL Database & Return New ID
$stmt = $pdo->prepare("INSERT INTO policies (website_url, company_name, detected_features, policy_content) VALUES (?, ?, ?, ?)");
$stmt->execute([
    $url,
    $companyName ?: $scraped['title'],
    json_encode(['inputs' => $scraped['inputs'], 'scripts' => $scraped['scripts']]),
    $generatedPolicy
]);

echo json_encode([
    'status'    => 'success',
    'policy_id' => $pdo->lastInsertId()
]);
?>