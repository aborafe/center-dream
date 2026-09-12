@extends('layouts.app')

@section('content')
@php($activeYear = $academicYears->firstWhere('is_active', true))
<section class="screen academic-console" aria-labelledby="academics-title">
    <x-page-header title="الإدارة الأكاديمية" subtitle="ابدأ بالسنة والصف، ثم أضف المادة واربطها بالمدرس." title-id="academics-title" />

    <section class="academic-overview" aria-label="ملخص الإعداد الأكاديمي">
        <article class="panel academic-stat academic-stat-primary"><span class="academic-stat-label">السنة النشطة</span><strong>{{ $activeYear?->name ?? 'غير محددة' }}</strong><small>{{ $activeYear ? 'التسجيل مفتوح على هذه السنة.' : 'أضف سنة وحددها كنشطة لبدء التسجيل.' }}</small></article>
        <article class="panel academic-stat"><span class="academic-stat-label">الصفوف الدراسية</span><strong>{{ $grades->count() }}</strong><small>صفوف جاهزة لربط الطلاب والمواد</small></article>
        <article class="panel academic-stat"><span class="academic-stat-label">المواد المُسجلة</span><strong>{{ $managedSubjects->count() }}</strong><small>{{ $managedSubjects->where('is_active', true)->count() }} مادة متاحة للتسجيل الآن</small></article>
    </section>

    @if ($activeYear && auth()->user()->hasPermission('settings'))
        <section class="panel year-closing-panel" aria-labelledby="year-closing-title">
            <div><p class="eyebrow">إقفال السنة</p><h2 id="year-closing-title">قفل {{ $activeYear->name }}</h2><p>قفل الفترة المالية يمنع أي تحصيل أو صرف أو تعديل مالي جديد. بعد تسوية الأرصدة، اقفل السنة الدراسية ثم فعّل السنة التالية.</p></div>
            <div class="year-closing-status"><span class="status {{ $activeYear->financial_closed_at ? 'paid' : 'partial' }}">{{ $activeYear->financial_closed_at ? 'الفترة المالية مقفلة' : 'الفترة المالية مفتوحة' }}</span><span class="status {{ $activeYear->academic_closed_at ? 'paid' : 'partial' }}">{{ $activeYear->academic_closed_at ? 'السنة مقفلة' : 'السنة الدراسية مفتوحة' }}</span></div>
            <div class="year-closing-actions">
                @if (! $activeYear->financial_closed_at)<button class="outline-button" type="button" data-open-dialog="close-financial-year" aria-controls="close-financial-year">قفل الفترة المالية</button>@endif
                @if ($activeYear->financial_closed_at && ! $activeYear->academic_closed_at)<button class="primary-button" type="button" data-open-dialog="close-academic-year" aria-controls="close-academic-year">قفل السنة الدراسية</button>@endif
            </div>
        </section>
        @if (! $activeYear->financial_closed_at)<dialog id="close-financial-year" class="confirm-dialog" aria-labelledby="close-financial-year-title"><form method="POST" action="{{ route('academics.years.close', $activeYear) }}">@csrf<input name="scope" type="hidden" value="financial"><h2 id="close-financial-year-title">قفل الفترة المالية</h2><p>تأكد من تسوية جميع أرصدة الطلاب. بعد القفل لا يمكن تسجيل تحصيل أو صرف أو خصم جديد لهذه السنة.</p><div class="dialog-actions"><button class="outline-button" type="button" data-close-dialog>رجوع</button><button class="danger-button-text" type="submit">تأكيد قفل الفترة المالية</button></div></form></dialog>@endif
        @if ($activeYear->financial_closed_at && ! $activeYear->academic_closed_at)<dialog id="close-academic-year" class="confirm-dialog" aria-labelledby="close-academic-year-title"><form method="POST" action="{{ route('academics.years.close', $activeYear) }}">@csrf<input name="scope" type="hidden" value="academic"><h2 id="close-academic-year-title">قفل السنة الدراسية</h2><p>سيُوقف التسجيل على {{ $activeYear->name }}. بعد ذلك يمكنك إضافة سنة جديدة وتفعيلها.</p><div class="dialog-actions"><button class="outline-button" type="button" data-close-dialog>رجوع</button><button class="danger-button-text" type="submit">تأكيد قفل السنة الدراسية</button></div></form></dialog>@endif
    @endif

    <section class="academic-setup" aria-labelledby="academic-setup-title">
        <div class="academic-section-heading"><div><p class="eyebrow">إعداد سريع</p><h2 id="academic-setup-title">أنشئ الهيكل الأكاديمي</h2><p>كل خطوة تحفظ بشكل مستقل، ويمكنك متابعة إضافة المواد فورًا.</p></div><a class="outline-button" href="#academic-distribution">الانتقال إلى المواد</a></div>
        <div class="academic-setup-grid">
            <form class="panel academic-setup-card" method="POST" action="{{ route('academics.years.store') }}">
                @csrf
                <div class="academic-card-head"><span class="academic-step">1</span><div><h3>السنة الدراسية</h3><p>حدّد الفترة التي تعمل عليها حاليًا.</p></div></div>
                <x-input label="اسم السنة" name="name" value="{{ old('name') }}" placeholder="مثال: 2026 / 2027…" autocomplete="off" required />
                <div class="form-grid"><x-input label="تاريخ البداية" name="starts_on" type="date" value="{{ old('starts_on') }}" autocomplete="off" required /><x-input label="تاريخ النهاية" name="ends_on" type="date" value="{{ old('ends_on') }}" autocomplete="off" required /></div>
                <label class="checkbox-line"><input name="is_active" type="checkbox" value="1" @checked(old('is_active'))> اجعلها السنة النشطة للتسجيل</label>
                @foreach (['name', 'starts_on', 'ends_on'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
                <button class="primary-button form-save" type="submit">حفظ السنة الدراسية</button>
            </form>
            <form class="panel academic-setup-card" method="POST" action="{{ route('academics.grades.store') }}">
                @csrf
                <div class="academic-card-head"><span class="academic-step">2</span><div><h3>الصف الدراسي</h3><p>أضف الصف مرة واحدة ليظهر عند إنشاء المواد والطلاب.</p></div></div>
                <x-input label="اسم الصف" name="name" value="{{ old('name') }}" placeholder="مثال: الصف الأول الثانوي…" autocomplete="off" required />
                <x-input label="ترتيب العرض" name="sort_order" type="number" min="1" max="999" value="{{ old('sort_order') }}" autocomplete="off" inputmode="numeric" placeholder="مثال: 1…" required />
                @foreach (['name', 'sort_order'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
                <button class="primary-button form-save" type="submit">إضافة الصف الدراسي</button>
            </form>
            <aside class="panel academic-reference" aria-labelledby="academic-reference-title">
                <div class="academic-card-head"><span class="academic-step academic-step-muted">✓</span><div><h3 id="academic-reference-title">الحالة الحالية</h3><p>مرجع سريع قبل إضافة مادة جديدة.</p></div></div>
                <div class="academic-reference-row"><span>السنة المستخدمة</span><strong>{{ $activeYear?->name ?? 'لم تُحدد بعد' }}</strong></div>
                <div class="academic-reference-row"><span>الصفوف المتاحة</span><strong>{{ $grades->count() }} صفوف</strong></div>
                <div class="academic-reference-row"><span>المدرسون النشطون</span><strong>{{ $teachers->count() }} مدرسين</strong></div>
                <p class="academic-reference-note">يمكنك ربط المدرس بالمادة الآن أو تحديده لاحقًا من تعديل المادة.</p>
            </aside>
        </div>
    </section>

    <form class="panel academic-subject-form" method="POST" action="{{ route('academics.subjects.store') }}" aria-labelledby="new-subject-title">
        @csrf
        <div class="academic-section-heading"><div class="academic-card-head"><span class="academic-step">3</span><div><p class="eyebrow">الخطوة الأخيرة</p><h2 id="new-subject-title">إضافة مادة تعليمية</h2><p>اختر السنة والصف، ثم أدخل سعر الاشتراك والمسؤول عنها.</p></div></div><span class="academic-form-note">المدرس حقل اختياري</span></div>
        <div class="form-grid academic-subject-grid">
            <div class="field-group"><label for="academic_year_id">السنة الدراسية</label><select id="academic_year_id" name="academic_year_id" autocomplete="off" required><option value="">اختر السنة…</option>@foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id', $activeYear?->id) == $year->id)>{{ $year->name }}</option>@endforeach</select></div>
            <div class="field-group"><label for="grade_id">الصف الدراسي</label><select id="grade_id" name="grade_id" autocomplete="off" required><option value="">اختر الصف…</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}" @selected(old('grade_id') == $grade->id)>{{ $grade->name }}</option>@endforeach</select></div>
            <div class="field-group"><label for="teacher_id">المدرس المسؤول <span class="optional-label">اختياري</span></label><select id="teacher_id" name="teacher_id" autocomplete="off"><option value="">يُحدد لاحقًا</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>
            <x-input label="اسم المادة" name="name" value="{{ old('name') }}" placeholder="مثال: رياضيات…" autocomplete="off" required />
            <x-input label="قيمة الاشتراك" name="fee" type="number" min="0.01" step="0.01" value="{{ old('fee') }}" inputmode="decimal" autocomplete="off" placeholder="مثال: 450.00…" required />
        </div>
        @foreach (['academic_year_id', 'grade_id', 'teacher_id', 'name', 'fee'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
        <div class="academic-form-footer"><p>ستظهر المادة مباشرة في جرد المواد ويمكن فتح التسجيل عليها.</p><button class="primary-button form-save" type="submit">إضافة المادة التعليمية</button></div>
    </form>

    <section id="academic-distribution" class="panel structured-list academic-distribution" aria-labelledby="academic-distribution-title">
        <div class="panel-heading"><div><p class="eyebrow">التوزيع الحالي</p><h2 id="academic-distribution-title">المواد والمدرسون</h2></div><a class="text-link" href="{{ route('inventory.index') }}">فتح جرد المواد</a></div>
        @error('subject')<p class="form-message" role="alert">{{ $message }}</p>@enderror
        <div class="table-wrap"><table><thead><tr><th>المادة</th><th>الصف والسنة</th><th>المدرس المسؤول</th><th>السعر</th><th>الحالة</th><th>إجراءات</th></tr></thead><tbody>
            @forelse ($managedSubjects as $subject)
                <tr><td class="strong">{{ $subject->name }}</td><td>{{ $subject->grade->name }}<span class="table-subline">{{ $subject->academicYear->name }}</span></td><td>{{ $subject->teacher?->name ?? 'غير محدد' }}</td><td class="amount-ok">{{ number_format($subject->fee, 2) }} ج.م</td><td><span class="status {{ $subject->is_active ? 'paid' : 'partial' }}">{{ $subject->is_active ? 'نشطة' : 'موقوفة' }}</span></td><td><div class="table-actions"><button class="icon-button row-action" type="button" data-open-dialog="edit-subject-{{ $subject->id }}" aria-label="تعديل مادة {{ $subject->name }}" title="تعديل"><x-icon name="edit" /></button><button class="icon-button danger-button" type="button" data-open-dialog="delete-subject-{{ $subject->id }}" aria-label="حذف مادة {{ $subject->name }}" title="حذف"><x-icon name="trash" /></button></div></td></tr>
                <dialog id="edit-subject-{{ $subject->id }}" class="form-dialog" aria-labelledby="edit-subject-title-{{ $subject->id }}"><form method="POST" action="{{ route('academics.subjects.update', $subject) }}">@csrf @method('PUT')<div class="panel-heading"><h2 id="edit-subject-title-{{ $subject->id }}">تعديل المادة</h2><button class="icon-button row-action" type="button" data-close-dialog aria-label="إغلاق"><x-icon name="close" /></button></div><x-input label="اسم المادة" name="name" value="{{ $subject->name }}" autocomplete="off" required /><div class="field-group"><label for="subject-teacher-{{ $subject->id }}">المدرس المسؤول</label><select id="subject-teacher-{{ $subject->id }}" name="teacher_id"><option value="">يُحدد لاحقًا</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($subject->teacher_id === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div><x-input label="قيمة الاشتراك" name="fee" type="number" min="0.01" step="0.01" value="{{ $subject->fee }}" inputmode="decimal" autocomplete="off" required /><label class="checkbox-line"><input name="is_active" type="checkbox" value="1" @checked($subject->is_active)> المادة متاحة للتسجيل</label><div class="dialog-actions"><button class="outline-button" type="button" data-close-dialog>إلغاء</button><button class="primary-button" type="submit">حفظ التعديل</button></div></form></dialog>
                <dialog id="delete-subject-{{ $subject->id }}" class="confirm-dialog" aria-labelledby="delete-subject-title-{{ $subject->id }}"><form method="POST" action="{{ route('academics.subjects.destroy', $subject) }}">@csrf @method('DELETE')<h2 id="delete-subject-title-{{ $subject->id }}">حذف {{ $subject->name }}</h2><p>سيُحذف تعريف المادة فقط إذا لم يكن مرتبطًا باشتراكات طلاب. لا يمكن التراجع عن هذا الإجراء.</p><div class="dialog-actions"><button class="chip" type="button" data-close-dialog>رجوع</button><button class="danger-button-text" type="submit">حذف المادة</button></div></form></dialog>
            @empty<tr><td colspan="6" class="muted">لا توجد مواد بعد. أضف المادة الأولى من النموذج أعلاه.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</section>
@endsection
