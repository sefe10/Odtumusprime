<?php

require_once __DIR__ . '/ftc.php';



function sc_sections(): array {

  return [

    'Kimlik ve İletişim' => [

      'name' => ['Takım adı', 'line'], 'city' => ['Şehir', 'line'], 'rookie' => ['Kuruluş yılı', 'line'],

      'status' => ['Durum (ör. Veteran, 4. sezon)', 'line'], 'school' => ['Okul / kurum (resmi kayıt)', 'line'],

      'org' => ['Kurum türü / not', 'area'], 'web' => ['Web sitesi', 'line'],

      'ig' => ['Instagram (@kullaniciadi)', 'line'], 'yt' => ['YouTube', 'line'], 'tiktok' => ['TikTok', 'line'],

      'x' => ['X (Twitter)', 'line'], 'fb' => ['Facebook', 'line'],

      'email' => ['İletişim', 'line'], 'sponsors' => ['Sponsorlar', 'area'],

    ],

    'Geçmiş ve Başarılar' => [

      'events_prev' => ['Önceki turnuvalar (özet)', 'area'],

      'awards' => ['Ödüller (özet)', 'area'],

      'stats' => ['İstatistik ve OPR notları', 'area'],

    ],

    'Teknik Analiz' => [

      'robot' => ['Robot adı', 'line'], 'role' => ['Rol (Scorer / Defender / Hibrit)', 'line'],

      'cycle' => ['Cycle time (sn)', 'line'], 'capacity' => ['Maks. obje kapasitesi', 'line'],

      'chassis' => ['Şasi', 'area'], 'software' => ['Yazılım ve otonom', 'area'],

      'defense' => ['Savunma / darbe dayanımı notu', 'area'],

    ],

    'Outreach ve İşbirliği' => [

      'outreach' => ['Outreach çalışmaları', 'area'], 'collab' => ['İşbirliği fikri', 'area'],

      'priority' => ['Öncelik', 'line'],

    ],

    'Notlar' => [

      'contactNote' => ['İletişim notu', 'area'], 'notes1' => ['Not 1', 'area'], 'notes2' => ['Not 2', 'area'],

      'jury' => ['Jüri ödül adaylığı tahmini', 'area'], 'pick' => ['Pick list etiketi (1 / 2 / dnp)', 'line'],

    ],

  ];

}



function sc_field_keys(): array {

  $k = [];

  foreach (sc_sections() as $s) foreach ($s as $key => $_) $k[] = $key;

  return $k;

}



function sc_auto_keys(): array { return ['name', 'city', 'rookie', 'school', 'web', 'robot']; }

function sc_social_keys(): array { return ['web', 'ig', 'yt', 'tiktok', 'x', 'fb']; }



// Değerden tıklanabilir bağlantılar üretir: [[görünen metin, url], ...]. Yalnızca http(s) adresleri üretilir.

function sc_links(string $key, string $v): array {

  $social = ['ig' => 'https://www.instagram.com/', 'yt' => 'https://www.youtube.com/@',

             'tiktok' => 'https://www.tiktok.com/@', 'x' => 'https://x.com/', 'fb' => 'https://www.facebook.com/'];

  $toks = array_values(array_filter(preg_split('/[\s,;]+/', trim($v)) ?: [], fn($t) => $t !== ''));

  $out = [];

  foreach ($toks as $t) {

    if ($t === '-') continue;

    $h = ltrim($t, '@');

    $u = null;

    if (preg_match('#^https?://\S+$#i', $t)) $u = $t;

    elseif ($key === 'web' && preg_match('#^[a-z0-9-]+(\.[a-z0-9-]+)+(/\S*)?$#i', $t)) $u = 'https://' . $t;

    elseif (isset($social[$key]) && preg_match('/^[A-Za-z0-9._-]{1,60}$/', $h) && (count($toks) === 1 || $t[0] === '@')) $u = $social[$key] . $h;

    if ($u) $out[] = [$t, $u];

  }

  return $out;

}



function sc_ratings(): array {

  return ['score' => 'Skor gücü', 'auto' => 'Otonom', 'defense' => 'Savunma',

          'reliable' => 'Güvenilirlik', 'driver' => 'Sürücü ekibi'];

}



function sc_checks(): array {

  return ['inspect_robot' => 'Robot Inspection geçti', 'inspect_field' => 'Field Inspection geçti',

          'contacted' => 'İletişim kuruldu', 'pit_visit' => 'Pit ziyareti yapıldı',

          'scrimmage' => 'Ortak antrenman planlandı'];

}



function sc_team_fields(int $no): array {

  $st = db()->prepare('SELECT f.field_key, f.value, f.source, f.updated_at, u.full_name AS by_name

                       FROM team_fields f LEFT JOIN users u ON u.id = f.updated_by WHERE f.team_no=?');

  $st->execute([$no]);

  $o = [];

  foreach ($st as $r) {

    $o[$r['field_key']] = ['value' => $r['value'], 'source' => $r['source'],

                           'updated_at' => $r['updated_at'], 'by' => $r['by_name']];

  }

  return $o;

}



function sc_badge(?array $row): string {

  if (!$row) return '<span class="bd">boş</span>';

  $s = $row['source'];

  $label = $s === 'auto' ? 'Otomatik' : ($s === 'import' ? 'Eski veri (doğrulanmadı)' : 'Elle');

  $title = trim(($row['by'] ? $row['by'] . ' • ' : '') . $row['updated_at']);

  return '<span class="bd bd-' . h($s) . '" title="' . h($title) . '">' . h($label) . '</span>';

}
function sc_contact_keys(): array { return ['web', 'ig', 'yt', 'tiktok', 'x', 'fb', 'email', 'contactNote']; }
