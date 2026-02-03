<?php
/**
 * API v1 - Deep Links API
 * Create, resolve, and track deep links
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/api-functions.php';
require_once __DIR__ . '/../../includes/jwt-functions.php';
require_once __DIR__ . '/../../includes/rate-limit-functions.php';

// Handle CORS
handleCors();

// Database connection
$db = new Database();
$pdo = $db->connect();

// Get client IP for rate limiting
$client_ip = getClientIp();

// Get request method and path
$request_method = getRequestMethod();
$request_uri = $_SERVER['REQUEST_URI'];

// Route handling
if ($request_method === 'POST' && strpos($request_uri, '/deep-links/create') !== false) {
    handleCreateDeepLink($pdo, $client_ip);
} elseif ($request_method === 'GET' && preg_match('/\/deep-links\/resolve\/([a-zA-Z0-9]+)/', $request_uri, $matches)) {
    handleResolveDeepLink($pdo, $matches[1], $client_ip);
} elseif ($request_method === 'GET' && preg_match('/\/deep-links\/analytics\/(\d+)/', $request_uri, $matches)) {
    handleGetAnalytics($pdo, $matches[1]);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Create deep link
 */
function handleCreateDeepLink($pdo, $client_ip) {
    // Rate limiting
    $rate_limit = checkRateLimit($pdo, $client_ip, 'ip', 'deep_link_create', 10, 60);
    if (!$rate_limit['allowed']) {
        sendErrorResponse('Too many requests. Please try again later.', 429);
    }
    
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    $data = getRequestBody();
    $required = ['destination_url'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $destination_url = sanitizeInput($data['destination_url']);
    $title = sanitizeInput($data['title'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $metadata = json_encode($data['metadata'] ?? []);
    
    // Validate URL
    if (!filter_var($destination_url, FILTER_VALIDATE_URL)) {
        sendErrorResponse('Invalid destination URL', 400);
    }
    
    try {
        // Generate unique short code
        $short_code = generateUniqueShortCode($pdo);
        
        $stmt = $pdo->prepare("
            INSERT INTO deep_links (user_id, destination_url, short_code, title, description, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user['id'],
            $destination_url,
            $short_code,
            $title,
            $description,
            $metadata
        ]);
        
        $link_id = $pdo->lastInsertId();
        $short_url = BASE_URL . '/l/' . $short_code;
        
        sendSuccessResponse([
            'id' => $link_id,
            'short_code' => $short_code,
            'short_url' => $short_url,
            'destination_url' => $destination_url
        ], 'Deep link created successfully');
        
    } catch (PDOException $e) {
        error_log('Deep link creation error: ' . $e->getMessage());
        sendErrorResponse('Failed to create deep link', 500);
    }
}

/**
 * Resolve deep link by short code
 */
function handleResolveDeepLink($pdo, $short_code, $client_ip) {
    // Rate limiting
    $rate_limit = checkRateLimit($pdo, $client_ip, 'ip', 'deep_link_resolve', 100, 60);
    if (!$rate_limit['allowed']) {
        sendErrorResponse('Too many requests. Please try again later.', 429);
    }
    
    $short_code = sanitizeInput($short_code);
    
    try {
        // Get deep link
        $stmt = $pdo->prepare("
            SELECT id, destination_url, title, description, metadata, status, click_count
            FROM deep_links
            WHERE short_code = ? AND status = 'active'
        ");
        $stmt->execute([$short_code]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$link) {
            sendErrorResponse('Deep link not found', 404);
        }
        
        // Track click
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO deep_link_clicks (link_id, ip_address, user_agent, referer, clicked_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$link['id'], $client_ip, $user_agent, $referer]);
        
        // Update click count
        $stmt = $pdo->prepare("
            UPDATE deep_links SET click_count = click_count + 1, last_clicked_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$link['id']]);
        
        sendSuccessResponse([
            'destination_url' => $link['destination_url'],
            'title' => $link['title'],
            'description' => $link['description'],
            'metadata' => json_decode($link['metadata'], true)
        ], 'Deep link resolved successfully');
        
    } catch (PDOException $e) {
        error_log('Deep link resolution error: ' . $e->getMessage());
        sendErrorResponse('Failed to resolve deep link', 500);
    }
}

/**
 * Get link analytics
 */
function handleGetAnalytics($pdo, $link_id) {
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    $link_id = (int)$link_id;
    
    try {
        // Get link details
        $stmt = $pdo->prepare("
            SELECT *
            FROM deep_links
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$link_id, $user['id']]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$link) {
            sendErrorResponse('Deep link not found', 404);
        }
        
        // Get click statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_clicks,
                COUNT(DISTINCT ip_address) as unique_clicks,
                COUNT(DISTINCT DATE(clicked_at)) as active_days,
                MAX(clicked_at) as last_click
            FROM deep_link_clicks
            WHERE link_id = ?
        ");
        $stmt->execute([$link_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get clicks by date (last 30 days)
        $stmt = $pdo->prepare("
            SELECT 
                DATE(clicked_at) as date,
                COUNT(*) as clicks,
                COUNT(DISTINCT ip_address) as unique_clicks
            FROM deep_link_clicks
            WHERE link_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(clicked_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$link_id]);
        $clicks_by_date = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get top referrers
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(NULLIF(referer, ''), 'Direct') as source,
                COUNT(*) as clicks
            FROM deep_link_clicks
            WHERE link_id = ?
            GROUP BY referer
            ORDER BY clicks DESC
            LIMIT 10
        ");
        $stmt->execute([$link_id]);
        $top_referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccessResponse([
            'link' => [
                'id' => $link['id'],
                'short_code' => $link['short_code'],
                'short_url' => BASE_URL . '/l/' . $link['short_code'],
                'destination_url' => $link['destination_url'],
                'title' => $link['title'],
                'created_at' => $link['created_at']
            ],
            'statistics' => $stats,
            'clicks_by_date' => $clicks_by_date,
            'top_referrers' => $top_referrers
        ], 'Analytics retrieved successfully');
        
    } catch (PDOException $e) {
        error_log('Analytics fetch error: ' . $e->getMessage());
        sendErrorResponse('Failed to fetch analytics', 500);
    }
}

/**
 * Generate unique short code
 */
function generateUniqueShortCode($pdo, $length = 8) {
    $max_attempts = 10;
    
    for ($i = 0; $i < $max_attempts; $i++) {
        $short_code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
        
        $stmt = $pdo->prepare("SELECT id FROM deep_links WHERE short_code = ?");
        $stmt->execute([$short_code]);
        
        if (!$stmt->fetch()) {
            return $short_code;
        }
    }
    
    throw new Exception('Failed to generate unique short code');
}

/**
 * Authenticate request using JWT
 */
function authenticateRequest($pdo) {
    $token = getBearerToken();
    
    if (!$token) {
        return false;
    }
    
    try {
        $decoded = verifyJWT($token);
        
        if (!$decoded || !isset($decoded->user_id)) {
            return false;
        }
        
        $stmt = $pdo->prepare("SELECT id, username, email, user_type FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$decoded->user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: false;
        
    } catch (Exception $e) {
        return false;
    }
}
