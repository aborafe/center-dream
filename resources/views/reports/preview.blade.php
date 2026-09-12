<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>معاينة تقرير {{ $centerSettings->center_name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf3f9; color: #122d49; font-family: Tahoma, Arial, sans-serif; font-variant-numeric: tabular-nums; }
        .report { max-width: 190mm; margin: 24px auto; padding: 18mm; background: #fff; box-shadow: 0 10px 30px rgb(15 42 71 / .14); }
        .head { display:flex; align-items:start; justify-content:space-between; gap:18px; padding-bottom:14px; border-bottom:2px solid #173d65; }.brand { display:flex; gap:10px; align-items:center; }.mark { display:grid; place-items:center; width:38px; height:38px; border-radius:9px; background:#173d65; color:#fff; font-weight:800; }.brand strong, .head h1 { display:block; margin:0; }.brand small, .head p { display:block; margin:4px 0 0; color:#60758c; font-size:11px; }.head h1 { font-size:20px; }.head p { text-align:left; }
        .summary { display:grid; grid-template-columns:repeat(3, 1fr); margin:18px 0; border:1px solid #dbe5ef; border-radius:8px; overflow:hidden; }.summary div { padding:11px; border-inline-start:1px solid #dbe5ef; }.summary div:first-child { border-inline-start:0; }.summary span { display:block; color:#60758c; font-size:10px; }.summary strong { display:block; margin-top:5px; font-size:16px; }.positive { color:#087e58; }.negative { color:#bb5a06; }
        table { width:100%; border-collapse:collapse; font-size:10px; } th { background:#eff5fa; color:#355775; } th, td { padding:8px 7px; border:1px solid #dbe5ef; text-align:right; vertical-align:top; } td.amount { font-weight:700; }.empty { padding:20px; color:#60758c; text-align:center; border:1px solid #dbe5ef; }.actions { display:flex; justify-content:center; gap:8px; margin-top:18px; }.button { border:1px solid #1f62d8; border-radius:7px; padding:9px 14px; background:#2469e8; color:#fff; cursor:pointer; font:inherit; font-weight:700; }.button.secondary { background:#fff; color:#1f62d8; text-decoration:none; }
        @media print { body { background:#fff; }.report { max-width:none; margin:0; padding:0; box-shadow:none; }.actions { display:none; } }
    </style>
</head>
<body>
    <main class="report" aria-label="معاينة التقرير المالي">
        <header class="head"><div class="brand"><span class="mark">د</span><div><strong>{{ $centerSettings->center_name }}</strong><small>تقرير مالي وإداري</small></div></div><div><h1>تقرير التحصيل والصرف</h1><p>{{ $periodLabel }}</p></div></header>
        <section class="summary" aria-label="ملخص التقرير"><div><span>إجمالي التحصيل</span><strong class="positive">{{ number_format($collectionTotal, 2) }} ج.م</strong></div><div><span>صرف المدرسين</span><strong class="negative">{{ number_format($teacherPayoutTotal, 2) }} ج.م</strong></div><div><span>صافي التحصيل</span><strong class="positive">{{ number_format($netCollections, 2) }} ج.م</strong></div><div><span>إيرادات السنتر</span><strong class="positive">{{ number_format($dailyIncomeTotal, 2) }} ج.م</strong></div><div><span>مصروفات السنتر</span><strong class="negative">{{ number_format($dailyExpenseTotal, 2) }} ج.م</strong></div><div><span>الأرصدة المعلقة</span><strong class="negative">{{ number_format($dueTotal, 2) }} ج.م</strong></div></section>
        <table><thead><tr><th>التاريخ</th><th>النوع</th><th>صاحب العملية</th><th>التفاصيل</th><th>المنفذ</th><th>القيمة</th></tr></thead><tbody>@forelse ($auditRows as $row)<tr><td dir="ltr">{{ \App\Support\DatePresenter::date($row['occurred_at']) }}</td><td>{{ $row['type'] }}</td><td>{{ $row['person'] }}</td><td>{{ $row['detail'] }}</td><td>{{ $row['executor'] }}</td><td class="amount {{ $row['direction'] === 'in' ? 'positive' : 'negative' }}">{{ $row['direction'] === 'in' ? '+' : '−' }}{{ number_format($row['amount'], 2) }} ج.م</td></tr>@empty<tr><td class="empty" colspan="6">لا توجد حركات مطابقة للفلاتر المختارة.</td></tr>@endforelse</tbody></table>
        <div class="actions"><a class="button secondary" href="{{ route('reports.index', $filters) }}">العودة للتقرير</a><button class="button" type="button" data-print-report>طباعة أو حفظ PDF</button></div>
    </main>
    <script>document.querySelector('[data-print-report]')?.addEventListener('click', () => window.print());</script>
</body>
</html>
