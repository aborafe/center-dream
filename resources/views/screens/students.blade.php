@extends('layouts.app')

@section('content')
<section class="screen" id="students" aria-labelledby="students-title">
    <x-page-header title="الطلاب" subtitle="ابحث عن الطالب، وحدد المواد أو حالة الحساب، ثم افتح ملفه مباشرة." title-id="students-title" action="تسجيل اشتراك" :action-url="route('subscriptions.create')" />

    <form class="panel student-filter-panel" action="{{ route('students.index') }}" method="GET">
        <div class="student-filter-search">
            <div class="form-field"><label for="student-search">ابحث عن طالب</label><input id="student-search" name="q" type="search" value="{{ $searchQuery }}" autocomplete="off" placeholder="مثال: سارة محمد أو 010…"></div>
            <button class="primary-button" type="submit">تطبيق الفلاتر</button>
            <a class="outline-button" href="{{ route('students.index') }}">إعادة الضبط</a>
        </div>

        <fieldset class="student-filter-group">
            <legend>المواد</legend>
            <div class="student-filter-chips">
                @forelse ($availableSubjects as $subject)
                    <label class="student-filter-chip"><input name="subjects[]" type="checkbox" value="{{ $subject->id }}" @checked($selectedSubjectIds->contains($subject->id))><span>{{ $subject->name }} <small>— {{ $subject->grade?->name ?? 'صف غير محدد' }}</small></span></label>
                @empty
                    <span class="muted">لا توجد مواد نشطة حاليًا.</span>
                @endforelse
            </div>
        </fieldset>

        <fieldset class="student-filter-group">
            <legend>حالة الحساب</legend>
            <div class="student-filter-status">
                <label class="student-filter-chip"><input name="account" type="radio" value="" @checked(! $accountFilter)><span>كل الحسابات</span></label>
                <label class="student-filter-chip"><input name="account" type="radio" value="complete" @checked($accountFilter === 'complete')><span>مكتمل</span></label>
                <label class="student-filter-chip"><input name="account" type="radio" value="due" @checked($accountFilter === 'due')><span>عليه رصيد</span></label>
            </div>
        </fieldset>
    </form>

    <section class="student-filter-summary" aria-label="ملخص الطلاب المعروضين">
        <article class="panel cash-card"><span>الطلاب المعروضون</span><strong>{{ $studentTotals['count'] }}</strong><p>حسب الفلاتر الحالية</p></article>
        <article class="panel cash-card"><span>إجمالي المدفوع</span><strong class="amount-ok">{{ number_format($studentTotals['paid'], 2) }} <small>ج.م</small></strong><p>من الطلاب المعروضين</p></article>
        <article class="panel cash-card"><span>إجمالي المتبقي</span><strong class="amount-due">{{ number_format($studentTotals['due'], 2) }} <small>ج.م</small></strong><p>المبالغ المطلوب تحصيلها</p></article>
    </section>

    <section class="panel student-table">
        <p class="student-filter-sort"><x-icon name="sort" aria-hidden="true" /><span>الترتيب:</span><strong>آخر دفعة من الأحدث إلى الأقدم</strong></p>
        <div class="table-wrap">
            <table data-disable-table-filters>
                <thead><tr><th>الطالب</th><th>الصف الدراسي</th><th>المواد</th><th>الحساب</th><th>المدفوع</th><th>المتبقي</th><th>آخر دفعة ↓</th><th>إجراءات</th></tr></thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr class="clickable-row" data-href="{{ route('students.show', $student['id']) }}" tabindex="0"><td class="student-name"><span class="student-avatar">{{ mb_substr($student['name'], 0, 1) }}</span><strong>{{ $student['name'] }}</strong></td><td>{{ $student['grade'] }}</td><td>{{ $student['subjects'] }}</td><td><span class="status {{ $student['status'] }}">{{ $student['status_label'] }}</span></td><td class="amount-ok">{{ $student['paid'] }}</td><td class="{{ $student['status'] === 'partial' ? 'amount-due' : 'amount-ok' }}">{{ $student['balance'] }}</td><td class="muted">{{ $student['last_payment'] }}</td><td class="table-actions"><a class="icon-button row-action" href="{{ route('students.show', $student['id']) }}" aria-label="فتح ملف {{ $student['name'] }}" title="فتح ملف الطالب"><x-icon name="search" /></a><a class="icon-button row-action" href="{{ route('students.show', ['student' => $student['id'], 'edit' => 1]) }}" aria-label="تعديل الطالب {{ $student['name'] }}" title="تعديل بيانات الطالب"><x-icon name="edit" /></a><form method="POST" action="{{ route('students.destroy', $student['id']) }}" data-delete-student data-student-name="{{ $student['name'] }}">@csrf @method('DELETE')<button class="icon-button danger-button" type="submit" aria-label="حذف الطالب {{ $student['name'] }}" title="حذف الطالب"><x-icon name="trash" /></button></form></td></tr>
                    @empty
                        <tr><td colspan="8" class="muted">لا يوجد طلاب مطابقون للبحث أو الفلاتر الحالية.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>

@endsection
