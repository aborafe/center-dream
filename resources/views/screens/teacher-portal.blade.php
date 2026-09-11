@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="teacher-portal-title">
    <x-page-header title="موادي وطلابي" subtitle="عرض المواد المسندة إليك وحالة سداد الطلاب — دون صلاحيات مالية أو إدارية." />
    <div class="entity-grid">
        @forelse ($teacher->subjects as $subject)
            <article class="panel entity-card">
                <span class="entity-label">{{ $subject->grade?->name }}</span>
                <strong>{{ $subject->name }}</strong>
                <p>{{ $subject->enrollments->count() }} طلاب مسجلين في المادة.</p>
                <span class="status paid">مادة نشطة</span>
            </article>
        @empty
            <article class="panel entity-card"><strong>لا توجد مواد مسندة</strong><p>تواصل مع مسؤول المركز لإسناد مادة إلى حسابك.</p></article>
        @endforelse
    </div>
    @foreach ($teacher->subjects as $subject)
        <section class="panel structured-list">
            <div class="panel-heading"><h2>{{ $subject->name }}</h2><span class="muted">قائمة الطلاب وحالة السداد</span></div>
            <div class="table-wrap"><table><thead><tr><th>الطالب</th><th>إجمالي المدفوع</th><th>المتبقي</th><th>آخر دفعة</th></tr></thead><tbody>
                @forelse ($subject->enrollments as $enrollment)
                    @php($paid = (float) $enrollment->payments->sum('amount'))
                    @php($remaining = (float) $enrollment->fee - (float) $enrollment->discount_amount - $paid)
                    <tr><td class="strong">{{ $enrollment->student->name }}</td><td>{{ number_format($paid, 2) }} ج.م</td><td class="{{ $remaining > 0 ? 'amount-due' : 'amount-ok' }}">{{ number_format($remaining, 2) }} ج.م</td><td class="muted">{{ $enrollment->payments->sortByDesc('paid_at')->first()?->paid_at?->translatedFormat('j F Y') ?? 'لا توجد دفعات' }}</td></tr>
                @empty<tr><td colspan="4" class="muted">لا يوجد طلاب مسجلون بعد.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    @endforeach
</section>
@endsection
