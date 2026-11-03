<?php
require_once __DIR__ . '/db.php';

// En küçük kareler yöntemiyle OPR: her ittifak skoru = iki takımın katkısı toplamı
function opr_solve(array $obs, array $teams): array {
  $n = count($teams);
  if ($n === 0 || !$obs) return [];
  $idx = array_flip($teams);
  $A = array_fill(0, $n, array_fill(0, $n, 0.0));
  $b = array_fill(0, $n, 0.0);
  foreach ($obs as [$ts, $val]) {
    foreach ($ts as $i) {
      if (!isset($idx[$i])) continue;
      foreach ($ts as $j) if (isset($idx[$j])) $A[$idx[$i]][$idx[$j]] += 1;
      $b[$idx[$i]] += $val;
    }
  }
  for ($i = 0; $i < $n; $i++) $A[$i][$i] += 0.001;
  for ($c = 0; $c < $n; $c++) {
    $p = $c;
    for ($r = $c + 1; $r < $n; $r++) if (abs($A[$r][$c]) > abs($A[$p][$c])) $p = $r;
    if (abs($A[$p][$c]) < 1e-12) continue;
    if ($p !== $c) { [$A[$p], $A[$c]] = [$A[$c], $A[$p]]; [$b[$p], $b[$c]] = [$b[$c], $b[$p]]; }
    for ($r = $c + 1; $r < $n; $r++) {
      $f = $A[$r][$c] / $A[$c][$c];
      if ($f == 0) continue;
      for ($k = $c; $k < $n; $k++) $A[$r][$k] -= $f * $A[$c][$k];
      $b[$r] -= $f * $b[$c];
    }
  }
  $x = array_fill(0, $n, 0.0);
  for ($i = $n - 1; $i >= 0; $i--) {
    $s = $b[$i];
    for ($k = $i + 1; $k < $n; $k++) $s -= $A[$i][$k] * $x[$k];
    $x[$i] = abs($A[$i][$i]) < 1e-12 ? 0 : $s / $A[$i][$i];
  }
  $out = [];
  foreach ($teams as $k => $t) $out[$t] = $x[$k];
  return $out;
}

// Turnuva istatistikleri: yalnızca oynanmış eleme dışı (qual) maçlar
function event_stats(int $season, string $code): array {
  $st = db()->prepare("SELECT * FROM event_matches WHERE season=? AND event_code=? AND played=1
    AND mkey NOT LIKE 'P:%' AND red_total IS NOT NULL AND blue_total IS NOT NULL");
  $st->execute([$season, $code]);
  $S = []; $oT = $oN = $oA = $oE = [];
  foreach ($st->fetchAll() as $r) {
    foreach (['red' => 'blue', 'blue' => 'red'] as $us => $op) {
      $ts = array_values(array_filter(array_map('intval', explode(',', (string)$r[$us . '_teams']))));
      if (!$ts) continue;
      $tot = (int)$r[$us . '_total']; $otot = (int)$r[$op . '_total'];
      $fg = (int)$r[$us . '_foul']; $fv = (int)$r[$op . '_foul'];
      $oT[] = [$ts, $tot];
      $oN[] = [$ts, $tot - $fg];
      if ($r[$us . '_auto'] !== null) $oA[] = [$ts, (int)$r[$us . '_auto']];
      if ($r[$us . '_teleop'] !== null) $oE[] = [$ts, (int)$r[$us . '_teleop']];
      foreach ($ts as $t) {
        $S[$t] ??= ['m' => 0, 'w' => 0, 'l' => 0, 't' => 0, 'tot' => 0, 'auto' => 0, 'tele' => 0, 'fg' => 0, 'fv' => 0];
        $S[$t]['m']++;
        $S[$t]['tot'] += $tot; $S[$t]['auto'] += (int)$r[$us . '_auto']; $S[$t]['tele'] += (int)$r[$us . '_teleop'];
        $S[$t]['fg'] += $fg; $S[$t]['fv'] += $fv;
        if ($tot > $otot) $S[$t]['w']++; elseif ($tot < $otot) $S[$t]['l']++; else $S[$t]['t']++;
      }
    }
  }
  $teams = array_keys($S);
  sort($teams);
  $opr = opr_solve($oT, $teams); $np = opr_solve($oN, $teams);
  $ao = opr_solve($oA, $teams); $to = opr_solve($oE, $teams);
  $out = [];
  foreach ($teams as $t) {
    $s = $S[$t]; $m = max(1, $s['m']);
    $out[$t] = ['m' => $s['m'], 'w' => $s['w'], 'l' => $s['l'], 't' => $s['t'],
      'avg' => $s['tot'] / $m, 'auto' => $s['auto'] / $m, 'tele' => $s['tele'] / $m,
      'fg' => $s['fg'] / $m, 'fv' => $s['fv'] / $m,
      'opr' => $opr[$t] ?? 0, 'np' => $np[$t] ?? 0, 'ao' => $ao[$t] ?? 0, 'to' => $to[$t] ?? 0];
  }
  return $out;
}