@extends('layouts.app')

@section('content')
<section class="screen reports-screen" id="reports" aria-labelledby="reports-title">
    <x-page-header title="التقارير" subtitle="متابعة التحصيل والصرف خلال {{ $periodLabel }}" />
    <form class="panel report-filters" method="GET" action="{{ route('reports.index') }}">
        <div class="form-field"><label for="report-from">من تاريخ</label><input id="report-from" type="date" name="from" value="{{ $filters['from'] }}" autocomplete="off"></div>
        <div class="form-field"><label for="report-to">إلى تاريخ</label><input id="report-to" type="date" name="to" value="{{ $filters['to'] }}" autocomplete="off"></div>
        <div class="field-group"><label for="report-secretary">المنفذ</label><select id="report-secretary" name="secretary_id"><option value="">كل المستخدمين</option>@foreach ($secretaries as $secretary)<option value="{{ $secretary->id }}" @selected(($filters['secretary_id'] ?? null) == $secretary->id)>{{ $secretary->name }}</option>@endforeach</select></div>
        <div class="field-group"><label for="report-teacher">المدرس</label><select id="report-teacher" name="teacher_id"><option value="">كل المدرسين</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(($filters['teacher_id'] ?? null) == $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>
        <div class="field-group"><label for="report-type">نوع الحركة</label><select id="report-type" name="type"><option value="all" @selected($filters['type'] === 'all')>كل الحركات</option><option value="collection" @selected($filters['type'] === 'collection')>تحصيل الطلاب</option><option value="payout" @selected($filters['type'] === 'payout')>صرف المدرسين</option><option value="refund" @selected($filters['type'] === 'refund')>رد مبالغ الطلاب</option></select></div>
        <button class="primary-button" type="submit">تحديث التقرير</button><a class="outline-button" href="{{ route('reports.index') }}">إعادة ضبط</a>
    </form>
    <div class="reports-grid">
        <article class="panel report-card"><span>إجمالي التحصيل</span><strong class="amount-ok">{{ number_format($collectionTotal, 2) }} ج.م</strong><small>خلال الفترة المختارة</small></article>
        <article class="panel report-card"><span>صرف المدرسين</span><strong class="amount-due">{{ number_format($teacherPayoutTotal, 2) }} ج.م</strong><small>خلال الفترة المختارة</small></article>
        <article class="panel report-card"><span>مبالغ مردودة للطلاب</span><strong class="amount-due">{{ number_format($refundTotal, 2) }} ج.م</strong><small>خلال الفترة المختارة</small></article>
        <article class="panel report-card"><span>صافي التحصيل</span><strong class="amount-ok">{{ number_format($netCollections, 2) }} ج.م</strong><small>بعد تسجيل صرف المدرسين</small></article>
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
    <section class="panel structured-list">
        <div class="panel-heading"><div><p class="eyebrow">تدقيق مالي</p><h2>سجل الحركات</h2></div><span class="muted">تحصيل الطالب وصرف المدرس في ترتيب زمني واحد.</span></div>
        <div class="table-wrap"><table><thead><tr><th>التاريخ</th><th>النوع</th><th>صاحب العملية</th><th>التفاصيل</th><th>المنفذ</th><th>القيمة</th></tr></thead><tbody>@forelse ($auditRows as $row)<tr><td>{{ $row['occurred_at']->translatedFormat('j F Y، h:i A') }}</td><td>{{ $row['type'] }}</td><td class="strong">{{ $row['person'] }}</td><td>{{ $row['detail'] }}</td><td>{{ $row['executor'] }}</td><td class="{{ $row['direction'] === 'in' ? 'amount-ok' : 'amount-due' }}">{{ $row['direction'] === 'in' ? '+' : '−' }}{{ number_format($row['amount'], 2) }} ج.م</td></tr>@empty<tr><td colspan="6" class="muted">لا توجد حركات مطابقة للفلاتر.</td></tr>@endforelse</tbody></table></div>
    </section>
</section>
@endsection
