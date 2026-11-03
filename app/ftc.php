<?php
require_once __DIR__ . '/db.php';

const FTC_BASE   = 'https://ftc-api.firstinspires.org/v2.0/';
const FTC_SEASON = 2026;
const FTC_HISTORY_SEASONS = [2026, 2025, 2024, 2023];

function ci_get($a, string $key, $default = null) {
  if (!is_array($a)) return $default;
  foreach ($a as $k => $v) {
    if (strcasecmp((string)$k, $key) === 0) return $v;
  }
  return $default;
}

function d10($v): ?string {
  return (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) ? substr($v, 0, 10) : null;
}

function http_get(string $url, array $headers = [], bool $follow = true): array {
  if (!function_exists('curl_init')) return ['status' => 0, 'body' => '', 'error' => 'PHP cURL eklentisi kapalı', 'redirect' => ''];
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
    CURLOPT_USERAGENT => 'OdtumusPrimeScouting/1.0 (+https://odtumusprime.com)',
    CURLOPT_HTTPHEADER => $headers,
  ]);
  $body = curl_exec($ch);
  $res = ['status' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => is_string($body) ? $body : '',
          'error' => curl_error($ch), 'redirect' => (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL)];
  curl_close($ch);
  return $res;
}

function ftc_get(string $path, array $q = [], int $ttl = 0): array {
  $path = ltrim($path, '/');
  $key  = substr($path . '?' . http_build_query($q), 0, 190);
  if ($ttl > 0) {
    $st = db()->prepare('SELECT body FROM api_cache WHERE cache_key=? AND fetched_at > NOW() - INTERVAL ' . (int)$ttl . ' SECOND');
    $st->execute([$key]);
    $b = $st->fetchColumn();
    if ($b !== false) {
      return ['ok' => true, 'status' => 200, 'error' => '', 'data' => json_decode($b, true) ?: [], 'raw' => $b];
    }
  }
  $c = cfg();
  $auth = base64_encode(trim((string)$c['ftc_user']) . ':' . trim((string)$c['ftc_token']));
  $r = http_get(FTC_BASE . $path . ($q ? '?' . http_build_query($q) : ''),
    ['Accept: application/json', 'Authorization: Basic ' . $auth], false);
  usleep(150000);
  $ok = $r['status'] === 200;
  if ($ok && $ttl > 0) {
    db()->prepare('REPLACE INTO api_cache (cache_key, body, status) VALUES (?,?,200)')->execute([$key, $r['body']]);
  }
  $err = $r['error'] ?: ($r['redirect'] ? 'Yönlendirme: ' . $r['redirect'] : '');
  return ['ok' => $ok, 'status' => $r['status'], 'error' => $err,
          'data' => json_decode($r['body'], true) ?: [], 'raw' => $r['body']];
}

function ftc_paged(string $path, array $q, string $listKey, int $ttl = 0): array {
  $items = []; $page = 1;
  do {
    $r = ftc_get($path, $q + ['page' => $page], $ttl);
    if (!$r['ok']) return ['ok' => false, 'status' => $r['status'], 'error' => $r['error'], 'items' => $items];
    $items = array_merge($items, ci_get($r['data'], $listKey, []));
    $total = (int)ci_get($r['data'], 'pageTotal', 1);
    $page++;
  } while ($page <= $total && $page < 20);
  return ['ok' => true, 'status' => 200, 'error' => '', 'items' => $items];
}

function sync_events(): array {
  $r = ftc_get(FTC_SEASON . '/events');
  if (!$r['ok']) return ['ok' => false, 'msg' => "Etkinlikler alınamadı (HTTP {$r['status']}) {$r['error']}"];
  $up = db()->prepare('INSERT INTO events (code,season,name,event_date,date_end,city,venue,type_name,region_code)
    VALUES (?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE name=VALUES(name), event_date=VALUES(event_date), date_end=VALUES(date_end),
      city=VALUES(city), venue=VALUES(venue), type_name=VALUES(type_name), region_code=VALUES(region_code)');
  $n = 0;
  foreach (ci_get($r['data'], 'events', []) as $e) {
    $code = (string)ci_get($e, 'code', '');
        if (strcasecmp((string)ci_get($e, 'typeName', ''), 'Kickoff') === 0) continue;
    $region = (string)ci_get($e, 'regionCode', '');
    if (!($region === 'TR' || preg_match('/^TR[A-Z0-9]/', $code))) continue;
    $up->execute([$code, FTC_SEASON, (string)ci_get($e, 'name', ''), d10(ci_get($e, 'dateStart')),
      d10(ci_get($e, 'dateEnd')), (string)ci_get($e, 'city', ''), (string)ci_get($e, 'venue', ''),
      (string)ci_get($e, 'typeName', ''), $region]);
    $n++;
  }
  return ['ok' => $n > 0, 'msg' => $n . ' TR etkinliği güncellendi' . ($n ? '' : ' (hiç etkinlik bulunamadı)')];
}

function region_team_numbers(): array {
  $r = http_get('https://ftc-events.firstinspires.org/' . FTC_SEASON . '/region/TR');
  if ($r['status'] !== 200) return [];
  preg_match_all('#/' . FTC_SEASON . '/team/(\d{1,6})#', $r['body'], $m);
  return array_values(array_unique(array_map('intval', $m[1])));
}

function upsert_team(array $t): int {
  $no = (int)ci_get($t, 'teamNumber', 0);
  if (!$no) return 0;
  $name   = (string)(ci_get($t, 'nameShort') ?: ci_get($t, 'nameFull') ?: '');
  $city   = trim((string)ci_get($t, 'city', ''));
  $prov   = trim((string)ci_get($t, 'stateProv', ''));
  $cityFull = $city . ($prov !== '' ? ($city !== '' ? ', ' : '') . $prov : '');
  $rookie = ci_get($t, 'rookieYear');
  $school = (string)ci_get($t, 'schoolName', '');
  $web    = (string)ci_get($t, 'website', '');
  $robot  = (string)ci_get($t, 'robotName', '');
  db()->prepare('INSERT INTO teams (team_no,name,city,rookie_year,state_prov,country,school,website,robot_name)
    VALUES (?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE name=VALUES(name), city=VALUES(city), rookie_year=VALUES(rookie_year),
      state_prov=VALUES(state_prov), country=VALUES(country), school=VALUES(school),
      website=VALUES(website), robot_name=VALUES(robot_name)')
    ->execute([$no, $name, $city, $rookie ?: null, $prov, (string)ci_get($t, 'country', ''), $school, $web, $robot]);
  // Elle veya eski veriden gelen alanlara dokunma; sadece "auto" olanları güncelle
  $ins = db()->prepare("INSERT INTO team_fields (team_no,field_key,value,source) VALUES (?,?,?,'auto')
    ON DUPLICATE KEY UPDATE value=IF(source='auto', VALUES(value), value)");
  foreach (['name' => $name, 'city' => $cityFull, 'rookie' => (string)$rookie,
            'school' => $school, 'web' => $web, 'robot' => $robot] as $k => $v) {
    if ($v !== '') $ins->execute([$no, $k, $v]);
  }
  return $no;
}

function sync_teams(): array {
  $seen = []; $warn = [];
  $codes = db()->query("SELECT code FROM events WHERE season=" . FTC_SEASON . " AND (region_code='TR' OR code LIKE 'TR%')")
                ->fetchAll(PDO::FETCH_COLUMN);
  $te = db()->prepare('INSERT IGNORE INTO team_events (season,event_code,team_no) VALUES (?,?,?)');
  foreach ($codes as $code) {
    $r = ftc_paged(FTC_SEASON . '/teams', ['eventCode' => $code], 'teams');
    if (!$r['ok']) { $warn[] = "$code takımları alınamadı (HTTP {$r['status']})"; continue; }
    foreach ($r['items'] as $t) {
      $no = upsert_team($t);
      if ($no) { $seen[$no] = true; $te->execute([FTC_SEASON, $code, $no]); }
    }
  }
  $region = region_team_numbers();
  if (!$region) $warn[] = 'Bölge sayfasından takım listesi okunamadı; yalnızca turnuvalara kayıtlı takımlar eklendi.';
  $missing = array_values(array_filter($region, fn($n) => !isset($seen[$n])));
  return ['ok' => true, 'teams' => count($seen), 'region_count' => count($region), 'missing' => $missing, 'warn' => $warn];
}

function sync_one_team(int $no): array {
  $r = ftc_get(FTC_SEASON . '/teams', ['teamNumber' => $no]);
  $list = $r['ok'] ? ci_get($r['data'], 'teams', []) : [];
  if ($list) {
    upsert_team($list[0]);
    return ['ok' => true, 'msg' => 'eklendi'];
  }
  if ($no > 0) {
    db()->prepare('INSERT IGNORE INTO teams (team_no) VALUES (?)')->execute([$no]);
    return ['ok' => true, 'msg' => "yalnızca numara eklendi (API: HTTP {$r['status']})"];
  }
  return ['ok' => false, 'error' => 'Geçersiz takım numarası'];
}

function sync_history(int $no): array {
  $rows = 0; $warn = [];
  $q = db()->prepare('SELECT rookie_year FROM teams WHERE team_no=?');
  $q->execute([$no]);
  $rookie = (int)$q->fetchColumn();

  $up = db()->prepare("INSERT INTO team_history (team_no,season,event_code,event_name,event_date,rank_no,record_wlt,qual_avg,awards,source)
    VALUES (?,?,?,?,?,?,?,?,?,'auto')
    ON DUPLICATE KEY UPDATE
      event_name = IF(source='auto', VALUES(event_name), event_name),
      event_date = IF(source='auto', VALUES(event_date), event_date),
      rank_no    = IF(source='auto', VALUES(rank_no), rank_no),
      record_wlt = IF(source='auto', VALUES(record_wlt), record_wlt),
      qual_avg   = IF(source='auto', VALUES(qual_avg), qual_avg),
      awards     = IF(source='auto', VALUES(awards), awards)");

  foreach (FTC_HISTORY_SEASONS as $s) {
    if ($rookie && $s < $rookie) continue;               // takım o sezonda yoktu
    $ttl = $s < FTC_SEASON ? 2592000 : 3600;
    $ev = ftc_get("$s/events", ['teamNumber' => $no], $ttl);
    if (!$ev['ok']) {
      if ($ev['status'] !== 400) $warn[] = "$s etkinlikleri: HTTP {$ev['status']} {$ev['error']}";
      continue;                                          // 400 = takım o sezonda kayıtlı değil
    }
    $events = ci_get($ev['data'], 'events', []);
    if (!$events) continue;

    $byEvent = [];
    $aw = ftc_get("$s/awards/$no", [], $ttl);
    if ($aw['ok']) {
      foreach (ci_get($aw['data'], 'awards', []) as $a) {
        $nm = trim((string)ci_get($a, 'name', ''));
        if ($nm !== '') $byEvent[(string)ci_get($a, 'eventCode', '')][] = $nm;
      }
    } elseif ($aw['status'] !== 400) {
      $warn[] = "$s ödülleri: HTTP {$aw['status']}";
    }

    foreach ($events as $e) {
      $code = (string)ci_get($e, 'code', '');
      if ($code === '') continue;
            if (strcasecmp((string)ci_get($e, 'typeName', ''), 'Kickoff') === 0) continue;
      $rank = $rec = $avg = null;
      $rk = ftc_get("$s/rankings/$code", ['teamNumber' => $no], $ttl);
      if ($rk['ok']) {
        $list = ci_get($rk['data'], 'rankings', []);
        if ($list) {
          $r0 = $list[0];
          $rank = ci_get($r0, 'rank');
          $rec  = (int)ci_get($r0, 'wins', 0) . '-' . (int)ci_get($r0, 'losses', 0) . '-' . (int)ci_get($r0, 'ties', 0);
          $qa   = ci_get($r0, 'qualAverage');
          $avg  = $qa !== null ? (string)$qa : null;
        }
      }
      $up->execute([$no, $s, $code, (string)ci_get($e, 'name', ''), d10(ci_get($e, 'dateStart')),
        $rank, $rec, $avg, implode("\n", $byEvent[$code] ?? []) ?: null]);
      $rows++;
    }
  }
  return ['ok' => true, 'rows' => $rows, 'warn' => $warn];
}