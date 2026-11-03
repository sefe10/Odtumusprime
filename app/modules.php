<?php

require_once __DIR__ . '/db.php';



function doc_types(): array {

  return ['veli' => 'Veli İzin Belgesi (MEB)', 'kvkk' => 'KVKK ve Medya Açık Rıza',

          'consent' => 'FIRST Consent & Release', 'atolye' => 'Atölye Güvenlik Sözleşmesi'];

}



function modules(): array {

  $all = ['mentor', 'youth_mentor', 'student'];

  $mng = ['mentor', 'youth_mentor'];

  $men = ['mentor'];

  return [

    'tasks' => ['title' => 'Görevler (Alt Birimler)', 'table' => 'm_tasks', 'view' => $all, 'edit' => $all, 'del' => $mng,

      'order' => "status='Tamam', due_date IS NULL, due_date, id",

      'cols' => ['unit_id' => ['Birim', 'unit'], 'title' => ['Görev', 'text'], 'assignee_id' => ['Sorumlu', 'user'],

                 'due_date' => ['Son tarih', 'date'], 'priority' => ['Öncelik', 'select', ['Düşük', 'Normal', 'Yüksek', 'Acil']],

                 'status' => ['Durum', 'select', ['Bekliyor', 'Devam ediyor', 'Tamam']]],

      'filter' => ['unit_id', 'status'], 'defaults' => []],

    'notebook' => ['title' => 'Mühendislik Defteri', 'table' => 'm_notebook', 'view' => $all, 'edit' => $all, 'del' => $mng,

      'order' => 'entry_date DESC, id DESC',

      'cols' => ['entry_date' => ['Tarih', 'date'], 'unit_id' => ['Birim', 'unit'], 'author_id' => ['Yazan', 'user'],

                 'title' => ['Başlık', 'text'], 'body' => ['Kayıt', 'area'], 'link' => ['Bağlantı', 'url']],

      'filter' => ['unit_id'], 'defaults' => ['entry_date' => '@today']],

    'cad' => ['title' => 'CAD Sürümleri', 'table' => 'm_cad', 'view' => $all, 'edit' => $all, 'del' => $mng,

      'order' => 'part, id',

      'cols' => ['part' => ['Parça / montaj', 'text'], 'version' => ['Sürüm', 'text'],

                 'status' => ['Durum', 'select', ['Taslak', 'Test', 'Onaylı', 'Üretimde']],

                 'link' => ['Bağlantı', 'url'], 'notes' => ['Not', 'area']],

      'filter' => ['status'], 'defaults' => []],

    'inventory' => ['title' => 'Parça Envanteri', 'table' => 'm_inventory', 'view' => $all, 'edit' => $all, 'del' => $mng,

      'order' => 'category, item',

      'cols' => ['category' => ['Kategori', 'select', ['goBILDA', 'REV', 'Sensör', 'Elektronik', 'Mekanik', 'Sarf', 'Diğer']],

                 'item' => ['Parça', 'text'], 'qty' => ['Adet', 'num'], 'min_qty' => ['Asgari', 'num'],

                 'location' => ['Konum', 'text'], 'notes' => ['Not', 'area']],

      'filter' => ['category'], 'defaults' => ['category' => 'Diğer'],

      'warn' => fn($r) => $r['min_qty'] > 0 && $r['qty'] <= $r['min_qty']],

    'portfolio' => ['title' => 'Mühendislik Portfolyosu (15 Sayfa)', 'table' => 'm_portfolio', 'view' => $all, 'edit' => $all, 'del' => $mng,

      'order' => 'page_no, id',

      'cols' => ['page_no' => ['Sayfa', 'num'], 'title' => ['Başlık', 'text'], 'owner_id' => ['Sorumlu', 'user'],

                 'status' => ['Durum', 'select', ['Başlamadı', 'Devam ediyor', 'Gözden geçirmede', 'Tamam']],

                 'link' => ['Bağlantı', 'url'], 'notes' => ['Not', 'area']],

      'filter' => ['status'], 'defaults' => ['page_no' => '@next']],

    'outreach' => ['title' => 'Outreach Etkinlikleri', 'table' => 'm_outreach', 'view' => $all, 'edit' => $all, 'del' => $mng,

      'order' => 'event_date DESC, id DESC',

      'cols' => ['event_date' => ['Tarih', 'date'], 'title' => ['Etkinlik', 'text'], 'place' => ['Yer', 'text'],

                 'reached' => ['Ulaşılan kişi', 'num'], 'rookie_teams' => ['Mentörlük verilen takımlar', 'text'],

                 'evidence_link' => ['Kanıt bağlantısı', 'url'], 'notes' => ['Not', 'area']],

      'sum' => ['reached'], 'defaults' => ['event_date' => '@today']],

    'pitcheck' => ['title' => 'Turnuva Pit Paketi', 'table' => 'm_pitcheck', 'view' => $all, 'edit' => $all, 'del' => $mng,

      'order' => 'packed, id',

      'cols' => ['item' => ['Malzeme', 'text'], 'qty' => ['Adet', 'num'], 'packed' => ['Paketlendi', 'check'], 'owner_id' => ['Sorumlu', 'user']],

      'defaults' => []],

    'budget' => ['title' => 'Gelir - Gider Takibi', 'table' => 'm_budget', 'order' => 'entry_date DESC, id DESC',
      'cols' => ['entry_date' => ['Tarih', 'date'], 'kind' => ['Tür', 'select', ['Gelir', 'Gider']],
                 'category' => ['Kategori', 'select', fin_categories()], 'description' => ['Açıklama', 'text'],
                 'amount' => ['Tutar (TL)', 'money'], 'pay_method' => ['Ödeme', 'select', fin_methods()],
                 'sponsor_id' => ['Sponsor (gelirse)', 'sponsor'], 'invoice_link' => ['Fatura / belge bağlantısı', 'url'], 'notes' => ['Not', 'area']],
      'filter' => ['kind', 'category'], 'defaults' => ['entry_date' => '@today', 'category' => 'Diğer', 'pay_method' => 'Havale / EFT']],
    'sponsors' => ['title' => 'Sponsor Takibi', 'table' => 'm_sponsors', 'order' => 'status, company',
      'sel' => ", (SELECT COALESCE(SUM(b.amount),0) FROM m_budget b WHERE b.kind='Gelir' AND b.sponsor_id=m_sponsors.id) AS received",
      'cols' => ['company' => ['Firma', 'text'], 'contact' => ['Yetkili', 'text'], 'phone' => ['Telefon', 'text'], 'email' => ['E-posta', 'text'],
                 'kind' => ['Tür', 'select', ['Nakdi', 'Ayni']],
                 'status' => ['Durum', 'select', ['Görüşülüyor', 'Dosya gönderildi', 'Onaylandı', 'Ödendi / teslim edildi', 'Reddedildi']],
                 'amount' => ['Taahhüt (TL)', 'money'], 'received' => ['Gelen (gelir kayıtlarından)', 'ro'],
                 'next_contact' => ['Sonraki görüşme', 'date'], 'notes' => ['Not', 'area']],
      'filter' => ['status'], 'sum' => ['amount'], 'defaults' => []],
    'budget_plan' => ['title' => 'Bütçe Planı', 'table' => 'm_budget_plan', 'order' => 'kind, category',
      'cols' => ['kind' => ['Tür', 'select', ['Gelir', 'Gider']], 'category' => ['Kategori', 'select', fin_categories()],
                 'planned' => ['Planlanan (TL)', 'money'], 'notes' => ['Not', 'area']],
      'filter' => ['kind'], 'sum' => ['planned'], 'defaults' => ['kind' => 'Gider', 'category' => 'Diğer']],

    'travel' => ['title' => 'Seyahat ve Konaklama (hassas veri)', 'table' => 'm_travel', 'view' => $men, 'edit' => $men, 'del' => $men,

      'order' => 'person, id',

      'cols' => ['person' => ['Kişi', 'text'], 'role' => ['Rol', 'select', ['Öğrenci', 'Young Mentor', 'Mentor', 'Veli / refakatçi']],

                 'seat' => ['Koltuk', 'text'], 'room' => ['Oda', 'text'],

                 'emergency_name' => ['Acil durum kişisi', 'text'], 'emergency_phone' => ['Acil durum telefonu', 'text'], 'notes' => ['Not', 'area']],

      'defaults' => ['role' => 'Öğrenci']],

  ];

}
