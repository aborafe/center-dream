@extends('layouts.app')

@section('content')
<section class="screen is-active" id="dashboard">
    <header class="business-page-head">
        <div><p class="eyebrow">نظرة مالية يومية</p><h1>لوحة الحسابات</h1><p dir="ltr">{{ \App\Support\DatePresenter::date(now()) }} · آخر تحديث الآن</p></div>
        <div class="page-actions"><a class="outline-button" href="{{ route('reports.index') }}">عرض التقرير</a><a class="primary-button" href="{{ route('subscriptions.create') }}">تسجيل اشتراك</a></div>
    </header>

    <section class="cash-overview" aria-label="ملخص المبالغ">
        <a class="cash-card cash-card-primary dashboard-card-link" href="{{ route('reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]) }}#movements" aria-label="عرض جدول تحصيل وحركات اليوم"><span>تحصيل اليوم</span><strong>{{ number_format($todayCollections, 2) }} <small>ج.م</small></strong><p>{{ $todayPaymentCount }} حركة تحصيل مسجلة <b>عرض التفاصيل</b></p></a>
        <a class="cash-card dashboard-card-link" href="{{ route('students.index', ['account' => 'due']) }}" aria-label="عرض جدول مديونيات الطلاب"><span>مديونيات الطلاب</span><strong>{{ number_format($studentDebt, 2) }} <small>ج.م</small></strong><p class="warning-text">{{ $studentsWithDebt }} طلاب بحاجة متابعة <b>عرض الطلاب</b></p></a>
        <a class="cash-card dashboard-card-link" href="{{ route('discounts.index', ['date' => now()->toDateString()]) }}" aria-label="عرض جدول خصومات اليوم"><span>خصومات اليوم</span><strong>{{ number_format($todayDiscounts, 2) }} <small>ج.م</small></strong><p>{{ $todayDiscountCount }} عمليات معتمدة <b>عرض التفاصيل</b></p></a>
        <a class="cash-card dashboard-card-link" href="{{ route('inventory.index') }}" aria-label="عرض كل المواد النشطة"><span>مواد نشطة</span><strong>{{ $activeSubjectCount }}</strong><p>مواد متاحة للتسجيل الآن <b>عرض المواد</b></p></a>
    </section>

    <section class="panel recent-panel">
        <div class="panel-heading"><div><p class="eyebrow">سجل اليوم</p><h2>آخر الحركات</h2></div><a class="text-link" href="{{ route('reports.index') }}">كل الحركات</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>الوقت</th><th>الطالب</th><th>المادة</th><th>المبلغ</th><th>الحالة</th></tr></thead>
                <tbody>
                    @forelse ($recentPayments as $payment)
                        <tr><td class="muted">{{ $payment->paid_at->translatedFormat('h:i A') }}</td><td class="strong">{{ $payment->student->name }}</td><td>{{ $payment->enrollment->subject->name }}</td><td class="strong">{{ number_format((float) $payment->amount, 2) }} ج.م</td><td><span class="status paid">تم الدفع</span></td></tr>
                    @empty<tr><td colspan="5" class="muted">لا توجد حركات تحصيل اليوم.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel subjects-panel">
        <div class="panel-heading"><div><p class="eyebrow">الإشغال</p><h2>اشتراكات حسب المادة</h2></div><a class="text-link" href="{{ route('inventory.index') }}">إدارة المواد</a></div>
        @foreach ($subjectSubscriptions as $subject)
            <div class="progress-row"><div class="subject-label"><strong>{{ $subject[0] }}</strong><span>{{ $subject[1] }}</span></div><div class="track"><span class="bar {{ $subject[3] }}" style="width:{{ $subject[2] }}%"></span></div></div>
        @endforeach
    </section>
</section>

@endsection
