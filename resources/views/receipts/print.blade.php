<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>وصل تحصيل {{ $receipt['number'] }}</title>
    <style>
        @page { size: {{ $receipt['size'] }} portrait; margin: {{ $receipt['size'] === 'A5' ? '8mm' : '12mm' }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #fff; color: #102a43; font-family: Tahoma, Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .receipt { max-width: 190mm; margin: 0 auto; font-variant-numeric: tabular-nums; }
        .head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding-bottom:14px; border-bottom:2px solid #163a63; }
        .brand { display:flex; align-items:center; gap:10px; } .mark { width:38px; height:38px; display:grid; place-items:center; border-radius:8px; background:#163a63; color:#fff; font-weight:800; font-size:18px; }
        .brand strong, .reference strong { display:block; } .brand strong { font-size:19px; } .brand small, .reference small, .reference span { display:block; margin-top:3px; color:#60758c; font-size:10px; }
        .reference { text-align:left; } .reference strong { color:#163a63; font-size:13px; }
        .status { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin:15px 0; padding:11px 14px; border-right:4px solid #16845d; background:#eef9f4; } .status span { color:#247759; font-size:12px; font-weight:700; } .status b { color:#11865b; font-size:25px; }
        .details { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-top:1px solid #d7e0ea; border-right:1px solid #d7e0ea; } .details > div { padding:9px 11px; border-bottom:1px solid #d7e0ea; border-left:1px solid #d7e0ea; } .details span { display:block; margin-bottom:4px; color:#60758c; font-size:10px; } .details strong { display:block; overflow-wrap:anywhere; font-size:13px; }
        .total { margin-top:14px; padding:12px; text-align:center; border:1px solid #c3d3e3; border-radius:7px; background:#f7fbff; } .total span, .total small { display:block; color:#60758c; font-size:10px; } .total strong { display:block; margin:5px 0; color:#163a63; font-size:22px; }
        .foot { display:grid; grid-template-columns:repeat(2,1fr); gap:22px; margin-top:18px; } .foot div { color:#60758c; font-size:10px; } .foot b { display:block; height:21px; margin-top:6px; border-bottom:1px solid #98aac0; } .foot p { grid-column:1 / -1; margin:0; color:#60758c; font-size:10px; text-align:center; }.receipt-actions { margin:18px 0 0; text-align:center; }.receipt-actions a { display:inline-block; padding:8px 12px; border-radius:6px; background:#e7f8f0; color:#047857; font-size:11px; font-weight:700; text-decoration:none; }
        @media print { .receipt-actions { display:none; } }
        @media screen { body { padding:24px; background:#edf2f7; } .receipt { padding:22px; background:#fff; box-shadow:0 8px 28px rgb(16 42 67 / .15); } }
    </style>
</head>
<body>
    @php
        $phoneDigits = preg_replace('/\D/', '', $receipt['phone']);
        $localPhone = str_starts_with($phoneDigits, '20') ? substr($phoneDigits, 2) : $phoneDigits;
        $whatsAppPhone = '20'.ltrim($localPhone, '0');
        $whatsAppMessage = rawurlencode("تم استلام {$receipt['amount']} من سنتر دريم عن مادة {$receipt['subject']}.");
    @endphp
    <article class="receipt" aria-label="وصل تحصيل">
        <header class="head"><div class="brand"><span class="mark">د</span><div><strong>سنتر دريم</strong><small>للإدارة التعليمية والتحصيل</small></div></div><div class="reference"><small>وصل تحصيل</small><strong>{{ $receipt['number'] }}</strong><span>{{ $receipt['date'] }}</span></div></header>
        <section class="status"><span>تم التحصيل</span><b>{{ $receipt['amount'] }}</b></section>
        <section class="details"><div><span>استلمنا من</span><strong>{{ $receipt['student'] }}</strong></div><div><span>رقم الهاتف</span><strong dir="ltr">{{ $receipt['phone'] }}</strong></div><div><span>عن مادة</span><strong>{{ $receipt['subject'] }}</strong></div><div><span>طريقة الدفع</span><strong>{{ $receipt['method'] }}</strong></div><div><span>تم التحصيل بواسطة</span><strong>{{ $receipt['receiver'] ?? 'مستخدم النظام' }}</strong></div><div><span>وقت العملية</span><strong>{{ $receipt['time'] }}</strong></div></section>
        <section class="total"><span>إجمالي المبلغ المستلم</span><strong>{{ $receipt['amount'] }}</strong><small>هذا الوصل يثبت استلام المبلغ المذكور أعلاه عن المادة المحددة.</small></section>
        <footer class="foot"><div>توقيع المستلم<b></b></div><div>توقيع ولي الأمر<b></b></div><p>شكرًا لثقتكم في سنتر دريم.</p></footer>
        <p class="receipt-actions"><a href="https://wa.me/{{ $whatsAppPhone }}?text={{ $whatsAppMessage }}" target="_blank" rel="noopener noreferrer">مشاركة الوصل عبر واتساب</a></p>
    </article>
</body>
</html>
