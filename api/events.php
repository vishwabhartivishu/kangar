<?php
/**
 * Eventbrite -> Kangar events proxy.
 * Fetches past + upcoming events from Eventbrite (filtered by name "kanger"),
 * transforms them into the shape events.html already consumes,
 * and caches to disk so we don't hit Eventbrite on every pageview.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$cfg = require __DIR__ . '/../config/eventbrite.php';

$cacheDir  = __DIR__ . '/../cache';
$cacheFile = $cacheDir . '/events.json';
$ttl       = (int)($cfg['cache_ttl'] ?? 600);

if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }

// Serve cached response if fresh and ?refresh=1 wasn't sent
if ($ttl > 0 && empty($_GET['refresh']) && is_file($cacheFile)
    && (time() - filemtime($cacheFile)) < $ttl) {
    readfile($cacheFile);
    exit;
}

$token  = $cfg['private_token'];
$filter = strtolower(trim($cfg['name_filter'] ?? ''));

/**
 * Call Eventbrite GET helper. Returns decoded JSON or null on failure.
 */
function eb_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) return null;
    return json_decode($body, true);
}

/* 1. Find the organisation id tied to this token */
$orgs = eb_get('https://www.eventbriteapi.com/v3/users/me/organizations/', $token);
if (!$orgs || empty($orgs['organizations'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Unable to load Eventbrite organisations. Check the private token.']);
    exit;
}
$orgId = $orgs['organizations'][0]['id'];

/* 2. Pull events, both past and upcoming, following pagination */
$all = [];
foreach (['past', 'current_future'] as $timeFilter) {
    $page = 1;
    while (true) {
        $url = 'https://www.eventbriteapi.com/v3/organizations/' . $orgId
             . '/events/?status=all&order_by=start_desc&time_filter=' . $timeFilter
             . '&page=' . $page . '&expand=venue,logo';
        $data = eb_get($url, $token);
        if (!$data || empty($data['events'])) break;
        $all = array_merge($all, $data['events']);
        if (empty($data['pagination']) || !$data['pagination']['has_more_items']) break;
        $page++;
        if ($page > 20) break; // safety cap
    }
}

/* 3. Transform to Kangar's event shape */
$out = [];
foreach ($all as $ev) {
    $title = $ev['name']['text'] ?? '';
    if ($filter !== '' && stripos($title, $filter) === false) continue;

    $start = $ev['start']['local'] ?? ($ev['start']['utc'] ?? null);
    $end   = $ev['end']['local']   ?? ($ev['end']['utc']   ?? null);
    if (!$start) continue;

    $city = $country = '';
    if (!empty($ev['venue']['address'])) {
        $addr    = $ev['venue']['address'];
        $city    = $addr['city'] ?? '';
        $country = $addr['country'] ?? '';
    }

    $image = '';
    if (!empty($ev['logo']['original']['url'])) $image = $ev['logo']['original']['url'];
    elseif (!empty($ev['logo']['url']))         $image = $ev['logo']['url'];

    $desc = $ev['description']['text'] ?? '';
    $short = $desc !== '' ? (mb_substr($desc, 0, 180) . (mb_strlen($desc) > 180 ? '...' : '')) : '';

    $out[] = [
        'id'               => $ev['id'],
        'title'            => $title,
        'city'             => $city,
        'country'          => $country,
        'date'             => substr($start, 0, 10),
        'endDate'          => $end ? substr($end, 0, 10) : null,
        'time'             => date('g:i A', strtotime($start)),
        'duration'         => '',
        'format'           => 'Investor briefing',
        'image'            => $image ?: 'assets/images/gallery/1.jpg',
        'shortDescription' => $short,
        'description'      => $desc,
        'seats'            => $ev['capacity'] ?? null,
        'spotsLeft'        => null,
        'tag'              => (strtotime($start) >= time()) ? 'upcoming' : null,
        'featured'         => false,
        'url'              => $ev['url'] ?? '',
    ];
}

/* 4. Mark the most recent past event as the "featured recap" */
foreach ($out as $i => $e) {
    if (strtotime($e['date']) < time()) { $out[$i]['featured'] = true; break; }
}

$json = json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $json);
echo $json;
