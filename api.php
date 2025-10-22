<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$DATA_FILE = __DIR__ . '/data/coins.json';

// ensure data folder exists
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}
if(!file_exists($DATA_FILE)) file_put_contents($DATA_FILE, json_encode([]));

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'load') {
    $coins = json_decode(file_get_contents($DATA_FILE), true);
    if (!is_array($coins)) $coins = [];
    echo json_encode(['success'=>true,'coins'=>$coins]);
    exit;
}

if ($action === 'save') {
    $coins = $input['coins'] ?? [];
    // basic validation - ensure array of objects with cg_id
    if(!is_array($coins)) $coins = [];
    file_put_contents($DATA_FILE, json_encode(array_values($coins), JSON_PRETTY_PRINT));
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'add') {
    $tv_link = trim($input['tv_link'] ?? '');
    if (!$tv_link) { echo json_encode(['success'=>false,'message'=>'No link']); exit;}
    // extract symbol from TradingView link
    // look for /symbols/<SYMBOL>/
    if (preg_match('#/symbols/([^/]+)/?#i', $tv_link, $m)) {
        $tvSymbol = $m[1]; // e.g. BTCUSDT or BINANCE:BTCUSDT
    } else {
        echo json_encode(['success'=>false,'message'=>'Could not extract symbol from link']); exit;
    }
    // If it is prefixed like EXCHANGE:SYMBOL, take part after colon
    if (strpos($tvSymbol, ':') !== false) {
        $parts = explode(':', $tvSymbol);
        $tvSymbol = end($parts);
    }
    $tvSymbol = trim($tvSymbol);

    // Query CoinGecko /coins/list once and attempt to match
    $list = cg_get('/coins/list');
    if (!$list || !is_array($list)) { echo json_encode(['success'=>false,'message'=>'CoinGecko list error']); exit;}
    $tvLower = strtolower($tvSymbol);

    // Choose best match: coin whose symbol is a prefix of tvLower and with the longest symbol
    $candidates = [];
    foreach($list as $c) {
        if (!isset($c['symbol']) || !isset($c['id'])) continue;
        $sym = strtolower($c['symbol']);
        if (strpos($tvLower, $sym) === 0) {
            $candidates[] = $c;
        }
    }
    // If no candidate, also try exact symbol match
    if (empty($candidates)) {
        foreach($list as $c) {
            if (strtolower($c['symbol']) === $tvLower) $candidates[] = $c;
        }
    }
    // choose candidate with longest symbol length to prefer BTC over B etc.
    usort($candidates, function($a,$b){
        return strlen($b['symbol']) - strlen($a['symbol']);
    });

    if (empty($candidates)) {
        echo json_encode(['success'=>false,'message'=>'No matching coin found on CoinGecko for symbol: ' . $tvSymbol]);
        exit;
    }
    $coinMeta = $candidates[0]; // id, symbol, name

    // Now fetch coin details (logo & price)
    $id = $coinMeta['id'];
    $details = cg_get("/coins/{$id}?localization=false&tickers=false&market_data=true&community_data=false&developer_data=false&sparkline=false");
    if (!$details || !isset($details['id'])) {
        echo json_encode(['success'=>false,'message'=>'Failed to fetch coin details']); exit;
    }
    $logo = $details['image']['thumb'] ?? ($details['image']['small'] ?? ($details['image']['large'] ?? ''));
    $price = $details['market_data']['current_price']['usd'] ?? null;

    // Build coin object to store
    $coinObj = [
        'cg_id' => $details['id'],
        'symbol' => strtoupper($coinMeta['symbol']),
        'name' => $details['name'],
        'logo' => $logo,
        'price' => $price,
        'tv_link' => $tv_link,
        'fav' => false,
        'added_at' => time()
    ];

    echo json_encode(['success'=>true,'coin'=>$coinObj]);
    exit;
}

if ($action === 'refresh') {
    // expects { ids: "bitcoin,ethereum" }
    $ids = $input['ids'] ?? '';
    $ids = preg_replace('/[^a-z0-9,\\-]/','',strtolower($ids));
    if (!$ids) { echo json_encode(['success'=>false,'message'=>'No ids']); exit; }
    $res = cg_get("/simple/price?ids={$ids}&vs_currencies=usd");
    if (!$res) { echo json_encode(['success'=>false,'message'=>'price error']); exit; }
    echo json_encode(['success'=>true,'prices'=>$res]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'invalid action']);
exit;

/**
 * Simple wrapper for CoinGecko public API GET
 */
function cg_get($path) {
    $base = 'https://api.coingecko.com/api/v3';
    $url = $base . $path;
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: CryptoListManager/1.0\r\nAccept: application/json\r\n"
        ]
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) return null;
    $data = json_decode($res, true);
    return $data;
}
<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$DATA_FILE = __DIR__ . '/data/coins.json';

// ensure data folder exists
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}
if(!file_exists($DATA_FILE)) file_put_contents($DATA_FILE, json_encode([]));

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'load') {
    $coins = json_decode(file_get_contents($DATA_FILE), true);
    if (!is_array($coins)) $coins = [];
    echo json_encode(['success'=>true,'coins'=>$coins]);
    exit;
}

if ($action === 'save') {
    $coins = $input['coins'] ?? [];
    // basic validation - ensure array of objects with cg_id
    if(!is_array($coins)) $coins = [];
    file_put_contents($DATA_FILE, json_encode(array_values($coins), JSON_PRETTY_PRINT));
    echo json_encode(['success'=>true]);
    exit;
}

if ($action === 'add') {
    $tv_link = trim($input['tv_link'] ?? '');
    if (!$tv_link) { echo json_encode(['success'=>false,'message'=>'No link']); exit;}
    // extract symbol from TradingView link
    // look for /symbols/<SYMBOL>/
    if (preg_match('#/symbols/([^/]+)/?#i', $tv_link, $m)) {
        $tvSymbol = $m[1]; // e.g. BTCUSDT or BINANCE:BTCUSDT
    } else {
        echo json_encode(['success'=>false,'message'=>'Could not extract symbol from link']); exit;
    }
    // If it is prefixed like EXCHANGE:SYMBOL, take part after colon
    if (strpos($tvSymbol, ':') !== false) {
        $parts = explode(':', $tvSymbol);
        $tvSymbol = end($parts);
    }
    $tvSymbol = trim($tvSymbol);

    // Query CoinGecko /coins/list once and attempt to match
    $list = cg_get('/coins/list');
    if (!$list || !is_array($list)) { echo json_encode(['success'=>false,'message'=>'CoinGecko list error']); exit;}
    $tvLower = strtolower($tvSymbol);

    // Choose best match: coin whose symbol is a prefix of tvLower and with the longest symbol
    $candidates = [];
    foreach($list as $c) {
        if (!isset($c['symbol']) || !isset($c['id'])) continue;
        $sym = strtolower($c['symbol']);
        if (strpos($tvLower, $sym) === 0) {
            $candidates[] = $c;
        }
    }
    // If no candidate, also try exact symbol match
    if (empty($candidates)) {
        foreach($list as $c) {
            if (strtolower($c['symbol']) === $tvLower) $candidates[] = $c;
        }
    }
    // choose candidate with longest symbol length to prefer BTC over B etc.
    usort($candidates, function($a,$b){
        return strlen($b['symbol']) - strlen($a['symbol']);
    });

    if (empty($candidates)) {
        echo json_encode(['success'=>false,'message'=>'No matching coin found on CoinGecko for symbol: ' . $tvSymbol]);
        exit;
    }
    $coinMeta = $candidates[0]; // id, symbol, name

    // Now fetch coin details (logo & price)
    $id = $coinMeta['id'];
    $details = cg_get("/coins/{$id}?localization=false&tickers=false&market_data=true&community_data=false&developer_data=false&sparkline=false");
    if (!$details || !isset($details['id'])) {
        echo json_encode(['success'=>false,'message'=>'Failed to fetch coin details']); exit;
    }
    $logo = $details['image']['thumb'] ?? ($details['image']['small'] ?? ($details['image']['large'] ?? ''));
    $price = $details['market_data']['current_price']['usd'] ?? null;

    // Build coin object to store
    $coinObj = [
        'cg_id' => $details['id'],
        'symbol' => strtoupper($coinMeta['symbol']),
        'name' => $details['name'],
        'logo' => $logo,
        'price' => $price,
        'tv_link' => $tv_link,
        'fav' => false,
        'added_at' => time()
    ];

    echo json_encode(['success'=>true,'coin'=>$coinObj]);
    exit;
}

if ($action === 'refresh') {
    // expects { ids: "bitcoin,ethereum" }
    $ids = $input['ids'] ?? '';
    $ids = preg_replace('/[^a-z0-9,\\-]/','',strtolower($ids));
    if (!$ids) { echo json_encode(['success'=>false,'message'=>'No ids']); exit; }
    $res = cg_get("/simple/price?ids={$ids}&vs_currencies=usd");
    if (!$res) { echo json_encode(['success'=>false,'message'=>'price error']); exit; }
    echo json_encode(['success'=>true,'prices'=>$res]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'invalid action']);
exit;

/**
 * Simple wrapper for CoinGecko public API GET
 */
function cg_get($path) {
    $base = 'https://api.coingecko.com/api/v3';
    $url = $base . $path;
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: CryptoListManager/1.0\r\nAccept: application/json\r\n"
        ]
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) return null;
    $data = json_decode($res, true);
    return $data;
}
