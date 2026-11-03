<?php
require_once __DIR__ . '/ftc.php';

function m_level(string $l): string { return stripos($l, 'qual') === 0 ? 'Q' : 'P'; }
function m_key($level, $series, $num): string { return m_level((string)$level) . ':' . (int)$series . ':' . (int)$num; }

function m_num($a, array $keys): ?int {
  if (!is_array($a)) return null;
  foreach ($keys as $k) {
    $v = ci_get($a, $k);
    if (is_numeric($v)) return (int)$v;
  }
  return null;
}

// Otonom / TeleOp (endgame dahil) / Faul (bu ittifaka eklenen) / Toplam
// Otonom / TeleOp (endgame dahil) / Faul (bu ittifaka eklenen) / Toplam
function m_parts($own, $opp, ?int $finalM, ?int $autoM, ?int $foulM): array {
  $auto  = m_num($own, ['autoPoints', 'autoTotal', 'autoScore']) ?? $autoM;
  $tele  = m_num($own, ['teleopPoints', 'dcPoints', 'driverControlledPoints']);
  $total = m_num($own, ['totalPoints', 'total']) ?? $finalM;
  // Bu ittifaka eklenen faul puanı = rakibin işlediği faul puanı
  $foul  = m_num($opp, ['foulPointsCommitted', 'penaltyPointsCommitted']);
  if ($foul === null) $foul = $foulM;
  $teleop = $tele;
  // Parçalar toplamı tutmuyorsa teleop'u kalan olarak hesapla
  if ($total !== null && $auto !== null && $foul !== null
      && ($tele === null || $auto + $tele + $foul !== $total)) {
    $teleop = $total - $auto - $foul;
  }
  if ($teleop !== null && $teleop < 0) $teleop = null;
  return [$auto, $teleop, $foul, $total];
}

function sync_event_matches(int $season, string $code): array {
  $warn = [];
  $r = ftc_get("$season/matches/$code");
  if (!$r['ok']) return ['ok' => false, 'count' => 0, 'warn' => ["Maçlar alınamadı (HTTP {$r['status']}) {$r['error']}"]];
  $matches = ci_get($r['data'], 'matches', []);

  // Ayrıntılı skorlar (otonom / teleop / faul)
  $sc = []; $gotScores = false;
  foreach ([['qual', 'qualification'], ['playoff', 'playoffs']] as $names) {
    foreach ($names as $lv) {
      $s = ftc_get("$season/scores/$code/$lv");
      if (!$s['ok']) continue;
      $gotScores = true;
      foreach (ci_get($s['data'], 'matchScores', []) as $ms) {
        $k = m_key(ci_get($ms, 'matchLevel', $lv), ci_get($ms, 'matchSeries', 0), ci_get($ms, 'matchNumber', 0));
        foreach (ci_get($ms, 'alliances', []) as $al) {
          $sc[$k][strtolower((string)ci_get($al, 'alliance', ''))] = $al;
        }
      }
      break;
    }
  }
  if (!$gotScores) $warn[] = 'Ayrıntılı skor verisi alınamadı; TeleOp ve Faul eksik veya tahmini olabilir.';

  // Takım adları
  $t = ftc_paged("$season/teams", ['eventCode' => $code], 'teams', 86400);
  if ($t['ok']) {
    $ins = db()->prepare('INSERT INTO team_names (team_no,name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
    foreach ($t['items'] as $tm) {
      $no = (int)ci_get($tm, 'teamNumber', 0);
      $nm = (string)(ci_get($tm, 'nameShort') ?: ci_get($tm, 'nameFull') ?: '');
      if ($no && $nm !== '') $ins->execute([$no, $nm]);
    }
  }

  $cols = ['label', 'sort_no', 'start_time', 'red_teams', 'blue_teams', 'red_auto', 'red_teleop', 'red_foul', 'red_total',
           'blue_auto', 'blue_teleop', 'blue_foul', 'blue_total', 'played'];
  $up = db()->prepare("INSERT INTO event_matches
    (season,event_code,mkey,label,sort_no,start_time,red_teams,blue_teams,red_auto,red_teleop,red_foul,red_total,blue_auto,blue_teleop,blue_foul,blue_total,played,source)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'auto')
    ON DUPLICATE KEY UPDATE " . implode(',', array_map(fn($c) => "$c=IF(source='auto',VALUES($c),$c)", $cols)));

  $n = 0;
  foreach ($matches as $m) {
    $level  = (string)ci_get($m, 'tournamentLevel', '');
    $series = (int)ci_get($m, 'series', 0);
    $num    = (int)ci_get($m, 'matchNumber', 0);
    $key    = m_key($level, $series, $num);
    $isQ    = m_level($level) === 'Q';
    $desc   = trim((string)ci_get($m, 'description', ''));
    $label  = $isQ ? 'Q-' . $num : ($desc !== '' ? $desc : "P-$series-$num");
    $sort   = $isQ ? $num : 1000 + $series * 10 + $num;

    $red = $blue = [];
    foreach (ci_get($m, 'teams', []) as $tm) {
      $st = (string)ci_get($tm, 'station', '');
      $tn = (int)ci_get($tm, 'teamNumber', 0);
      if (!$tn) continue;
      if (stripos($st, 'red') === 0) $red[$st] = $tn;
      elseif (stripos($st, 'blue') === 0) $blue[$st] = $tn;
    }
    ksort($red); ksort($blue);

    $rf = ci_get($m, 'scoreRedFinal'); $bf = ci_get($m, 'scoreBlueFinal');
    $pt = ci_get($m, 'postResultTime');
    $played = (($pt !== null && $pt !== '') || ($rf !== null && ci_get($m, 'actualStartTime') !== null)) ? 1 : 0;

    $rp = $bp = [null, null, null, null];
    if ($played) {
      $rp = m_parts($sc[$key]['red'] ?? null, $sc[$key]['blue'] ?? null, is_numeric($rf) ? (int)$rf : null,
                    m_num($m, ['scoreRedAuto']), m_num($m, ['scoreRedFoul']));
      $bp = m_parts($sc[$key]['blue'] ?? null, $sc[$key]['red'] ?? null, is_numeric($bf) ? (int)$bf : null,
                    m_num($m, ['scoreBlueAuto']), m_num($m, ['scoreBlueFoul']));
    }
    $st = ci_get($m, 'actualStartTime') ?: ci_get($m, 'startTime');
    $start = (is_string($st) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $st)) ? str_replace('T', ' ', substr($st, 0, 19)) : null;

    $up->execute([$season, $code, $key, $label, $sort, $start, implode(',', $red), implode(',', $blue),
      $rp[0], $rp[1], $rp[2], $rp[3], $bp[0], $bp[1], $bp[2], $bp[3], $played]);
    $n++;
  }
  db()->prepare('REPLACE INTO event_fetch (season,event_code) VALUES (?,?)')->execute([$season, $code]);
  return ['ok' => true, 'count' => $n, 'warn' => $warn];
}

function team_name_map(array $nos): array {
  if (!$nos) return [];
  $in = implode(',', array_map('intval', $nos));
  $map = [];
  foreach (db()->query("SELECT team_no,name FROM team_names WHERE team_no IN ($in)") as $r) $map[(int)$r['team_no']] = $r['name'];
  foreach (db()->query("SELECT team_no,name FROM teams WHERE team_no IN ($in) AND name<>''") as $r) $map[(int)$r['team_no']] = $r['name'];
  foreach (db()->query("SELECT team_no,value FROM team_fields WHERE field_key='name' AND value<>'' AND team_no IN ($in)") as $r) $map[(int)$r['team_no']] = $r['value'];
  return $map;
}

function render_team_matches(int $season, string $code, int $no, bool $manage): string {
  $st = db()->prepare('SELECT * FROM event_matches WHERE season=? AND event_code=?
    AND (FIND_IN_SET(?, red_teams) OR FIND_IN_SET(?, blue_teams)) ORDER BY sort_no, id');
  $st->execute([$season, $code, $no, $no]);
  $rows = $st->fetchAll();
  $refresh = $manage ? '<button type="button" class="mini" data-refresh data-season="' . $season . '" data-code="' . h($code) . '">↻ Resmî veriyi yenile</button>' : '';
  if (!$rows) {
    return '<div class="note">Bu turnuvada bu takım için maç bulunamadı (fikstür henüz yayınlanmamış olabilir). ' . $refresh . '</div>';
  }
  $nos = [];
  foreach ($rows as $r) foreach (explode(',', $r['red_teams'] . ',' . $r['blue_teams']) as $x) if ($x !== '') $nos[(int)$x] = 1;
  $names = team_name_map(array_keys($nos));
  $tm = function (string $list) use ($names, $no): string {
    $o = [];
    foreach (explode(',', $list) as $x) {
      if ($x === '') continue;
      $n = (int)$x;
      $o[] = '<div class="tm' . ($n === $no ? ' me' : '') . '"><a href="/scouting/team.php?no=' . $n . '">' . $n . '</a> ' . h($names[$n] ?? '') . '</div>';
    }
    return implode('', $o);
  };

  $w = $l = $t = $sum = $cnt = 0;
  $body = '';
  foreach ($rows as $r) {
    $ourRed = in_array((string)$no, explode(',', $r['red_teams']), true);
    $us = $ourRed ? 'red' : 'blue';
    $op = $ourRed ? 'blue' : 'red';
    $res = ''; $cls = '';
    if (!$r['played']) {
      $res = '—';
    } elseif ($r[$us . '_total'] !== null && $r[$op . '_total'] !== null) {
      $a = (int)$r[$us . '_total']; $b = (int)$r[$op . '_total'];
      if ($a > $b) { $w++; $res = 'G'; $cls = 'mw'; }
      elseif ($a < $b) { $l++; $res = 'M'; $cls = 'ml'; }
      else { $t++; $res = 'B'; $cls = 'mt'; }
      $sum += $a; $cnt++;
    }
    $cell = fn(string $col) => '<td class="num" contenteditable data-mid="' . (int)$r['id'] . '" data-col="' . $col . '">' . h($r[$col]) . '</td>';
    $time = $r['start_time'] ? date('d.m H:i', strtotime($r['start_time'])) : '';
    $body .= '<tr class="' . $cls . '"><td><b>' . h($r['label']) . '</b>' . ($r['source'] === 'manual' ? ' <span class="bd bd-manual">elle</span>' : '') . '</td>'
      . '<td>' . h($time) . '</td>'
      . '<td><span class="dot ' . ($ourRed ? 'r' : 'b') . '"></span>' . $tm($ourRed ? $r['red_teams'] : $r['blue_teams']) . '</td>'
      . '<td><span class="dot ' . ($ourRed ? 'b' : 'r') . '"></span>' . $tm($ourRed ? $r['blue_teams'] : $r['red_teams']) . '</td>'
      . '<td><b>' . h($res) . '</b></td>'
      . $cell($us . '_total') . $cell($op . '_total')
      . $cell($us . '_auto') . $cell($op . '_auto')
      . $cell($us . '_teleop') . $cell($op . '_teleop')
      . $cell($us . '_foul') . $cell($op . '_foul') . '</tr>';
  }
  $head = '<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:8px"><b>' . count($rows) . ' maç</b>'
    . '<span>G-M-B: <b>' . "$w-$l-$t" . '</b></span>'
    . ($cnt ? '<span>Ort. skor: <b>' . round($sum / $cnt, 1) . '</b></span>' : '') . $refresh . '</div>';

  return $head . '<div style="overflow-x:auto"><table class="mt">'
    . '<tr><th rowspan="2">Maç</th><th rowspan="2">Saat</th><th rowspan="2">İttifakımız</th><th rowspan="2">Rakip ittifak</th><th rowspan="2"></th>'
    . '<th colspan="2">Skor</th><th colspan="2">Otonom</th><th colspan="2">TeleOp</th><th colspan="2">Faul</th></tr>'
    . '<tr><th>Biz</th><th>Rkp</th><th>Biz</th><th>Rkp</th><th>Biz</th><th>Rkp</th><th>Biz</th><th>Rkp</th></tr>'
    . $body . '</table></div>'
    . '<p style="font-size:11px;color:#94A3B8;margin:6px 0 0">Faul = bu ittifaka rakibin faullerinden eklenen puan. TeleOp (endgame dahil) = toplam − otonom − faul. '
    . 'Skor hücrelerine tıklayıp düzeltebilirsiniz. Elle düzeltilen maçlar yenilemede ezilmez.</p>';
}