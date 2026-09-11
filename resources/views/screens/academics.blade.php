@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="academics-title">
    <x-page-header title="الإدارة الأكاديمية" subtitle="نظّم السنوات والصفوف والمواد قبل فتح التسجيل" />
    <div class="entity-grid">
        <article class="panel entity-card"><span class="entity-label">السنة الدراسية الحالية</span><strong>{{ $academicYears->firstWhere('is_active', true)?->name ?? 'غير محددة' }}</strong><p>الفترة الفعالة لتسجيل المواد والطلاب.</p><span class="status paid">{{ $academicYears->firstWhere('is_active', true) ? 'نشطة' : 'تحتاج إعدادًا' }}</span></article>
        <article class="panel entity-card"><span class="entity-label">الصفوف المفعّلة</span><strong>{{ $grades->count() }} صفوف</strong><p>تُستخدم لربط الطالب والمواد الدراسية.</p><a class="text-link" href="{{ route('inventory.index') }}">مراجعة المواد</a></article>
        <article class="panel entity-card"><span class="entity-label">المواد المفعّلة</span><strong>{{ count($materials) }} مواد</strong><p>كل مادة مرتبطة بمدرس مسؤول.</p><a class="text-link" href="{{ route('teachers.index') }}">مراجعة المدرسين</a></article>
    </div>
    <div class="academic-forms">
        <form class="panel account-form compact-form" method="POST" action="{{ route('academics.years.store') }}">
            @csrf
            <h2>إضافة سنة دراسية</h2>
            <x-input label="اسم السنة" name="name" value="{{ old('name') }}" placeholder="2026 / 2027" autocomplete="off" required />
            <div class="form-grid"><x-input label="تاريخ البداية" name="starts_on" type="date" value="{{ old('starts_on') }}" autocomplete="off" required /><x-input label="تاريخ النهاية" name="ends_on" type="date" value="{{ old('ends_on') }}" autocomplete="off" required /></div>
            <label class="checkbox-line"><input name="is_active" type="checkbox" value="1" @checked(old('is_active'))> اجعلها السنة النشطة</label>
            @foreach (['name', 'starts_on', 'ends_on'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
            <button class="primary-button form-save" type="submit">إضافة السنة</button>
        </form>
        <form class="panel account-form compact-form" method="POST" action="{{ route('academics.grades.store') }}">
            @csrf
            <h2>إضافة صف دراسي</h2>
            <x-input label="اسم الصف" name="name" value="{{ old('name') }}" placeholder="الصف الأول الثانوي" autocomplete="off" required />
            <x-input label="ترتيب العرض" name="sort_order" type="number" min="1" max="999" value="{{ old('sort_order') }}" autocomplete="off" inputmode="numeric" required />
            @foreach (['name', 'sort_order'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
            <button class="primary-button form-save" type="submit">إضافة الصف</button>
        </form>
    </div>
    <form class="panel account-form" method="POST" action="{{ route('academics.subjects.store') }}">
        @csrf
        <h2>إضافة مادة تعليمية</h2>
        <div class="form-grid">
            <div class="field-group"><label for="academic_year_id">السنة الدراسية</label><select id="academic_year_id" name="academic_year_id" required><option value="">اختر السنة…</option>@foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id', $academicYears->firstWhere('is_active', true)?->id) == $year->id)>{{ $year->name }}</option>@endforeach</select></div>
            <div class="field-group"><label for="grade_id">الصف الدراسي</label><select id="grade_id" name="grade_id" required><option value="">اختر الصف…</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}" @selected(old('grade_id') == $grade->id)>{{ $grade->name }}</option>@endforeach</select></div>
            <div class="field-group"><label for="teacher_id">المدرس المسؤول</label><select id="teacher_id" name="teacher_id"><option value="">يُحدد لاحقًا</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>
            <x-input label="اسم المادة" name="name" value="{{ old('name') }}" autocomplete="off" required />
            <x-input label="قيمة الاشتراك" name="fee" type="number" min="0.01" step="0.01" value="{{ old('fee') }}" inputmode="decimal" autocomplete="off" required />
        </div>
        @foreach (['academic_year_id', 'grade_id', 'teacher_id', 'name', 'fee'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
        <button class="primary-button form-save" type="submit">إضافة المادة</button>
    </form>
    <section class="panel structured-list"><div class="panel-heading"><div><p class="eyebrow">التوزيع الحالي</p><h2>المواد والمدرسون</h2></div><a class="text-link" href="{{ route('inventory.index') }}">جرد المواد</a></div><div class="table-wrap"><table><thead><tr><th>المادة</th><th>الصف والسنة</th><th>المدرس المسؤول</th><th>السعر</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>@forelse ($managedSubjects as $subject)<tr><td class="strong">{{ $subject->name }}</td><td>{{ $subject->grade->name }}<span class="table-subline">{{ $subject->academicYear->name }}</span></td><td>{{ $subject->teacher?->name ?? 'غير محدد' }}</td><td class="amount-ok">{{ number_format($subject->fee, 2) }} ج.م</td><td><span class="status {{ $subject->is_active ? 'paid' : 'partial' }}">{{ $subject->is_active ? 'نشطة' : 'موقوفة' }}</span></td><td><button class="icon-button row-action" type="button" data-open-dialog="edit-subject-{{ $subject->id }}" aria-label="تعديل مادة {{ $subject->name }}" title="تعديل"><x-icon name="edit" /></button></td></tr><dialog id="edit-subject-{{ $subject->id }}" class="form-dialog" aria-labelledby="edit-subject-title-{{ $subject->id }}"><form method="POST" action="{{ route('academics.subjects.update', $subject) }}">@csrf @method('PUT')<div class="panel-heading"><h2 id="edit-subject-title-{{ $subject->id }}">تعديل المادة</h2><button class="icon-button row-action" type="button" data-close-dialog aria-label="إغلاق"><span aria-hidden="true">×</span></button></div><x-input label="اسم المادة" name="name" value="{{ $subject->name }}" autocomplete="off" required /><div class="field-group"><label for="subject-teacher-{{ $subject->id }}">المدرس المسؤول</label><select id="subject-teacher-{{ $subject->id }}" name="teacher_id"><option value="">يُحدد لاحقًا</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($subject->teacher_id === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div><x-input label="قيمة الاشتراك" name="fee" type="number" min="0.01" step="0.01" value="{{ $subject->fee }}" inputmode="decimal" autocomplete="off" required /><label class="checkbox-line"><input name="is_active" type="checkbox" value="1" @checked($subject->is_active)> المادة متاحة للتسجيل</label><div class="dialog-actions"><button class="outline-button" type="button" data-close-dialog>إلغاء</button><button class="primary-button" type="submit">حفظ التعديل</button></div></form></dialog>@empty<tr><td colspan="6" class="muted">لا توجد مواد بعد.</td></tr>@endforelse</tbody></table></div></section>
</section>
@endsection
