<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';
require_editor();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo nao permitido.']);
    exit;
}

require_csrf();

$url = trim((string) ($_POST['url'] ?? ''));

if ($url === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Preencha a URL antes de extrair metadados.']);
    exit;
}

if (!is_supported_article_url($url)) {
    http_response_code(422);
    echo json_encode(['error' => 'Por favor, informe uma URL válida (HTTP/HTTPS).']);
    exit;
}

try {
    $html = fetch_remote_html($url);
    
    // Instantiate ParserManager
    $manager = new \App\Parsers\ParserManager();
    $article = $manager->parse($html, $url);

    if (empty(array_filter($article, fn($value) => trim((string) $value) !== ''))) {
        http_response_code(422);
        echo json_encode(['error' => 'Nao encontrei metadados reconheciveis nessa pagina.']);
        exit;
    }

    echo json_encode(['article' => $article], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(502);
    echo json_encode(['error' => $error->getMessage()]);
}

function is_supported_article_url(string $url): bool
{
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return false;
    }

    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return false;
    }

    if (is_private_or_reserved_host($host)) {
        return false;
    }

    return true;
}

function is_private_or_reserved_host(string $host): bool
{
    $host = trim($host, '[]');

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return !is_public_ip($host);
    }

    $ips = gethostbynamel($host);
    if ($ips === false || $ips === []) {
        return false;
    }

    foreach ($ips as $ip) {
        if (!is_public_ip($ip)) {
            return true;
        }
    }

    return false;
}

function is_public_ip(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function fetch_remote_html(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensao curl do PHP nao esta disponivel.');
    }

    $handle = curl_init($url);
    $html = '';
    $maxBytes = 5 * 1024 * 1024;

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 FicharioAcademico/0.1',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.7',
        ],
        CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$html, $maxBytes): int {
            $html .= $chunk;
            if (strlen($html) > $maxBytes) {
                return 0;
            }

            return strlen($chunk);
        },
    ]);

    $result = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if (strlen($html) > $maxBytes) {
        throw new RuntimeException('A pagina informada excede o limite de tamanho para extracao.');
    }

    if ($result === false) {
        throw new RuntimeException('Nao consegui acessar a URL informada. ' . $error);
    }

    if ($html === '') {
        throw new RuntimeException('Nao consegui acessar a URL informada. ' . $error);
    }

    if ($status >= 400) {
        throw new RuntimeException('A pagina respondeu com erro HTTP ' . $status . '.');
    }

    return (string) $html;
}
