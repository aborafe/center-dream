@extends('layouts.app')

@section('content')
<section class="screen" id="students">
    <x-page-header title="الطلاب" subtitle="الاشتراكات، التحصيل، والأرصدة في مكان واحد" action="تسجيل اشتراك" :action-url="route('subscriptions.create')" />
    @if ($subjectId)<p class="filter-context">يعرض السجل الطلاب المسجلين في المادة المختارة. <a href="{{ route('students.index') }}">عرض كل الطلاب</a></p>@endif
    <form class="panel filters" action="{{ route('students.index') }}" method="GET">
        <label class="sr-only" for="student-search">ابحث في الطلاب</label>
        <input id="student-search" name="q" type="search" value="{{ $searchQuery }}" autocomplete="off" placeholder="⌕  ابحث باسم الطالب أو رقم الهاتف">
        <button class="chip" type="submit">بحث</button>
        <a class="chip chip-warn" href="{{ route('students.index') }}">مسح البحث</a>
    </form>
    <section class="panel student-table">
        <div class="table-wrap">
            <table>
                <thead><tr><th>الطالب</th><th>المواد</th><th>إجمالي المدفوع</th><th>المتبقي</th><th>آخر دفع</th><th>إجراءات</th></tr></thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr class="clickable-row" data-href="{{ route('students.show', $student['id']) }}" tabindex="0"><td class="student-name"><span class="student-avatar">{{ mb_substr($student['name'], 0, 1) }}</span><strong>{{ $student['name'] }}</strong></td><td>{{ $student['subjects'] }}</td><td class="amount-ok">{{ $student['paid'] }}</td><td class="{{ $student['status'] === 'partial' ? 'amount-due' : 'amount-ok' }}">{{ $student['balance'] }}</td><td class="muted">{{ $student['last_payment'] }}</td><td class="table-actions"><a class="icon-button row-action" href="{{ route('students.show', $student['id']) }}" aria-label="فتح ملف {{ $student['name'] }}" title="فتح ملف الطالب"><x-icon name="search" /></a><a class="icon-button row-action" href="{{ route('students.show', ['student' => $student['id'], 'edit' => 1]) }}" aria-label="تعديل الطالب {{ $student['name'] }}" title="تعديل بيانات الطالب"><x-icon name="edit" /></a><form method="POST" action="{{ route('students.destroy', $student['id']) }}" data-delete-student data-student-name="{{ $student['name'] }}">@csrf @method('DELETE')<button class="icon-button danger-button" type="submit" aria-label="حذف الطالب {{ $student['name'] }}" title="حذف الطالب"><x-icon name="trash" /></button></form></td></tr>
                    @empty
                        <tr><td colspan="6" class="muted">لا توجد نتائج مطابقة لعبارة البحث.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>

@endsection
