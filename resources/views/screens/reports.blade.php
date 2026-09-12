@extends('layouts.app')

@section('content')
<section class="screen reports-screen" id="reports" aria-labelledby="reports-title">
    <x-page-header title="التقارير" subtitle="متابعة التحصيل والصرف خلال {{ $periodLabel }}" title-id="reports-title" />
    <form class="panel report-filters" method="GET" action="{{ route('reports.index') }}" data-date-range-picker>
        <div class="field-group"><label for="report-academic-year">السنة المالية</label><select id="report-academic-year" name="academic_year_id"><option value="">كل السنوات</option>@foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>{{ $year->name }}{{ $year->financial_closed_at ? ' — مقفلة' : '' }}</option>@endforeach</select></div>
        <input name="from" type="hidden" value="{{ $filters['from'] }}" data-date-range-from>
        <input name="to" type="hidden" value="{{ $filters['to'] }}" data-date-range-to>
        <div class="field-group report-date-range"><span class="field-label" id="report-date-range-label">فترة التقرير</span><button class="date-range-trigger" type="button" data-date-range-open aria-haspopup="dialog" aria-labelledby="report-date-range-label" aria-describedby="report-date-range-value"><span data-date-range-value id="report-date-range-value"></span><x-icon name="calendar" aria-hidden="true" /></button></div>
        <div class="field-group"><label for="report-secretary">المنفذ</label><select id="report-secretary" name="secretary_id"><option value="">كل المستخدمين</option>@foreach ($secretaries as $secretary)<option value="{{ $secretary->id }}" @selected(($filters['secretary_id'] ?? null) == $secretary->id)>{{ $secretary->name }}</option>@endforeach</select></div>
        <div class="field-group"><label for="report-teacher">المدرس</label><select id="report-teacher" name="teacher_id"><option value="">كل المدرسين</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(($filters['teacher_id'] ?? null) == $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>
        <div class="field-group"><label for="report-type">نوع الحركة</label><select id="report-type" name="type"><option value="all" @selected($filters['type'] === 'all')>كل الحركات</option><option value="collection" @selected($filters['type'] === 'collection')>تحصيل الطلاب</option><option value="payout" @selected($filters['type'] === 'payout')>صرف المدرسين</option><option value="refund" @selected($filters['type'] === 'refund')>رد مبالغ الطلاب</option><option value="daily_income" @selected($filters['type'] === 'daily_income')>إيراد السنتر اليومي</option><option value="daily_expense" @selected($filters['type'] === 'daily_expense')>مصروفات السنتر</option></select></div>
        <button class="primary-button" type="submit">تحديث التقرير</button><a class="outline-button" href="{{ route('reports.index') }}">إعادة ضبط</a>
    </form>
    <dialog class="date-range-dialog" data-date-range-dialog aria-labelledby="report-range-dialog-title">
        <div class="date-range-dialog-head"><div><p class="eyebrow">تصفية التقرير</p><h2 id="report-range-dialog-title">اختر نطاق التاريخ</h2></div><button class="icon-button date-range-close" type="button" data-date-range-close aria-label="إغلاق تقويم الفترة"><x-icon name="close" /></button></div>
        <div class="date-range-calendar-head"><button class="icon-button date-range-nav" type="button" data-date-range-prev aria-label="الشهر السابق">‹</button><strong data-date-range-month></strong><button class="icon-button date-range-nav" type="button" data-date-range-next aria-label="الشهر التالي">›</button></div>
        <div class="date-range-weekdays" aria-hidden="true"><span>أحد</span><span>اثن</span><span>ثلا</span><span>أرب</span><span>خمي</span><span>جمع</span><span>سبت</span></div>
        <div class="date-range-calendar" data-date-range-calendar role="grid" aria-label="تقويم اختيار الفترة"></div>
        <p class="date-range-selection" data-date-range-selection aria-live="polite"></p>
        <div class="dialog-actions"><button class="outline-button" type="button" data-date-range-clear>مسح الفترة</button><button class="primary-button" type="button" data-date-range-apply>عرض الفترة</button></div>
    </dialog>
    <div class="reports-grid">
        <article class="panel report-card"><span>إجمالي التحصيل</span><strong class="amount-ok">{{ number_format($collectionTotal, 2) }} ج.م</strong><small>خلال الفترة المختارة</small></article>
        <article class="panel report-card"><span>صرف المدرسين</span><strong class="amount-due">{{ number_format($teacherPayoutTotal, 2) }} ج.م</strong><small>خلال الفترة المختارة</small></article>
        <article class="panel report-card"><span>مبالغ مردودة للطلاب</span><strong class="amount-due">{{ number_format($refundTotal, 2) }} ج.م</strong><small>خلال الفترة المختارة</small></article>
        <article class="panel report-card"><span>صافي التحصيل</span><strong class="amount-ok">{{ number_format($netCollections, 2) }} ج.م</strong><small>بعد تسجيل صرف المدرسين</small></article>
        <article class="panel report-card"><span>إيرادات السنتر اليومية</span><strong class="amount-ok">{{ number_format($dailyIncomeTotal, 2) }} ج.م</strong><small>من دفتر الإيراد اليومي</small></article>
        <article class="panel report-card"><span>مصروفات السنتر اليومية</span><strong class="amount-due">{{ number_format($dailyExpenseTotal, 2) }} ج.م</strong><small>من دفتر المصروفات</small></article>
        <article class="panel report-card"><span>اشتراكات مكتملة</span><strong>{{ $completedEnrollments }}</strong><small>من إجمالي {{ $enrollmentCount }} اشتراكًا</small></article>
        <article class="panel report-card"><span>أرصدة معلقة</span><strong class="amount-due">{{ number_format($dueTotal, 2) }} ج.م</strong><small>{{ $studentsWithDue }} طلاب لهم رصيد</small></article>
    </div>
    <div class="reports-layout">
        <article class="panel collection-report">
            <div class="panel-heading"><h2>التحصيل حسب المادة</h2><span class="muted">وفق الفلاتر المختارة</span></div>
            @forelse ($reportRows as $report)
                <div class="report-bar"><span>{{ $report[0] }}</span><div><b class="bar {{ $report[2] }}" style="width: {{ $report[1] }}%"></b></div><strong>{{ $report[3] }}</strong></div>
            @empty
                <p class="muted">لا توجد عمليات تحصيل مطابقة للفلاتر.</p>
            @endforelse
        </article>
        <article class="panel balance-report"><h2>تنبيه الأرصدة</h2><p>{{ $studentsWithDue }} طلاب يحتاجون متابعة تحصيل.</p><strong class="amount-due">{{ number_format($dueTotal, 2) }} ج.م</strong><a class="outline-button" href="{{ route('students.index') }}">عرض الطلاب</a></article>
    </div>
    <section class="panel structured-list" id="movements">
        <div class="panel-heading"><div><p class="eyebrow">تدقيق مالي</p><h2>سجل الحركات</h2></div><span class="muted">تحصيل الطالب وصرف المدرس في ترتيب زمني واحد.</span></div>
        <div class="table-wrap"><table><thead><tr><th>التاريخ</th><th>النوع</th><th>صاحب العملية</th><th>التفاصيل</th><th>المنفذ</th><th>القيمة</th></tr></thead><tbody>@forelse ($auditRows as $row)<tr><td dir="ltr">{{ \App\Support\DatePresenter::date($row['occurred_at']) }}، {{ $row['occurred_at']->translatedFormat('h:i A') }}</td><td>{{ $row['type'] }}</td><td class="strong">{{ $row['person'] }}</td><td>{{ $row['detail'] }}</td><td>{{ $row['executor'] }}</td><td class="{{ $row['direction'] === 'in' ? 'amount-ok' : 'amount-due' }}">{{ $row['direction'] === 'in' ? '+' : '−' }}{{ number_format($row['amount'], 2) }} ج.م</td></tr>@empty<tr><td colspan="6" class="muted">لا توجد حركات مطابقة للفلاتر.</td></tr>@endforelse</tbody></table></div>
    </section>
</section>
@endsection
