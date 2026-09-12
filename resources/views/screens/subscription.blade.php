@extends('layouts.app')

@section('content')
<section class="screen" id="subscription" aria-labelledby="subscription-title">
    <x-page-header title="تسجيل اشتراك جديد" subtitle="أضف مواد الطالب وحدد ما دُفع لكل مادة." title-id="subscription-title" />

    <form class="panel subscription-form subscription-workflow" method="POST" action="{{ route('subscriptions.store') }}" data-subscription-form data-student-lookup-url="{{ route('subscriptions.student-lookup') }}">
        @csrf
        <section class="subscription-step" aria-labelledby="subscription-title">
            <div class="subscription-step-heading">
                <span class="subscription-step-number" aria-hidden="true">1</span>
                <div><h2 id="subscription-title">بيانات الطالب</h2><p>ابحث برقم الهاتف أولًا؛ ستظهر بيانات الطالب المسجل تلقائيًا.</p></div>
            </div>
            <div class="form-grid">
                <x-input label="اسم الطالب" name="student_name" value="{{ old('student_name') }}" placeholder="مثال: أحمد محمد…" autocomplete="name" data-student-name required />
                <x-input label="رقم الهاتف" name="student_phone" value="{{ old('student_phone') }}" placeholder="مثال: 01012345678" autocomplete="tel" inputmode="tel" type="tel" data-student-phone required />
                <div class="field-group subscription-grade-field"><label for="subscription-grade">الصف الأساسي للطالب</label><select id="subscription-grade" name="grade_id" data-subscription-grade required><option value="">اختر الصف الدراسي…</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}" @selected((int) old('grade_id') === $grade->id)>{{ $grade->name }}</option>@endforeach</select><small>يُحفظ كصف الطالب، ويمكن اختيار صف مختلف لكل مادة بالأسفل.</small><input type="hidden" name="grade_id" data-subscription-grade-hidden disabled></div>
            </div>
            <div class="student-lookup" data-student-lookup aria-live="polite" hidden></div>
        </section>

        <section class="subscription-step subscription-subjects" aria-labelledby="subscription-subjects-title">
            <div class="subscription-section-head">
                <div class="subscription-step-heading">
                    <span class="subscription-step-number" aria-hidden="true">2</span>
                    <div><h2 id="subscription-subjects-title">مواد الاشتراك</h2><p>اختر صف المادة أولًا، ثم تظهر مواده فقط. يمكن اختيار مادة من أي صف مناسب للطالب.</p></div>
                </div>
                <button class="outline-button subscription-add-button" type="button" data-add-subject>
                    <span aria-hidden="true">+</span> إضافة مادة
                </button>
            </div>

            <div class="subscription-rows" data-subject-rows>
                @php($oldSubjects = old('subjects', [['subject_id' => '', 'paid_amount' => 0, 'payment_method' => 'cash']]))
                @foreach ($oldSubjects as $index => $oldSubject)
                    <article class="subject-entry" data-subject-row>
                        <div class="subject-entry-head">
                            <div><span class="subject-entry-label">اشتراك</span><strong>مادة <span data-row-number>{{ $loop->iteration }}</span></strong></div>
                            <button class="icon-button danger-button" type="button" aria-label="حذف المادة" title="حذف المادة" data-remove-subject>
                                <x-icon name="trash" />
                            </button>
                        </div>
                        <div class="form-grid subject-entry-grid">
                            <div class="field-group"><label for="subject-grade-{{ $index }}">صف المادة</label><select id="subject-grade-{{ $index }}" name="subjects[{{ $index }}][grade_id]" data-subject-grade-select required><option value="">اختر صف المادة…</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}" @selected((int) ($oldSubject['grade_id'] ?? old('grade_id')) === $grade->id)>{{ $grade->name }}</option>@endforeach</select></div>
                            <div class="field-group"><label for="subject-{{ $index }}">المادة</label><select id="subject-{{ $index }}" name="subjects[{{ $index }}][subject_id]" data-subject-select required><option value="">اختر صف المادة أولًا…</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}" data-grade-id="{{ $subject->grade_id }}" data-fee="{{ $subject->fee }}" data-teacher="{{ $subject->teacher?->name }}" @selected((int) ($oldSubject['subject_id'] ?? 0) === $subject->id)>{{ $subject->name }} · {{ number_format($subject->fee, 2) }} ج.م</option>@endforeach</select></div>
                            <div class="field-group"><span class="field-label">المدرس ورسوم المادة</span><p class="field-hint" data-subject-details>اختر مادة لعرض السعر والمدرس.</p></div>
                            <x-input label="المبلغ المدفوع" name="subjects[{{ $index }}][paid_amount]" type="number" min="0" step="0.01" value="{{ $oldSubject['paid_amount'] ?? 0 }}" inputmode="decimal" autocomplete="off" data-paid-input required />
                            <div class="field-group"><label for="payment-method-{{ $index }}">طريقة الدفع</label><select id="payment-method-{{ $index }}" name="subjects[{{ $index }}][payment_method]" data-method-select required><option value="cash" @selected(($oldSubject['payment_method'] ?? 'cash') === 'cash')>نقدي</option><option value="transfer" @selected(($oldSubject['payment_method'] ?? '') === 'transfer')>تحويل</option><option value="wallet" @selected(($oldSubject['payment_method'] ?? '') === 'wallet')>محفظة</option></select></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <template data-subject-template>
            <article class="subject-entry" data-subject-row>
                <div class="subject-entry-head"><div><span class="subject-entry-label">اشتراك</span><strong>مادة <span data-row-number></span></strong></div><button class="icon-button danger-button" type="button" aria-label="حذف المادة" title="حذف المادة" data-remove-subject><x-icon name="trash" /></button></div>
                <div class="form-grid subject-entry-grid">
                    <div class="field-group"><label data-subject-grade-label>صف المادة</label><select data-subject-grade-select required><option value="">اختر صف المادة…</option>@foreach ($grades as $grade)<option value="{{ $grade->id }}">{{ $grade->name }}</option>@endforeach</select></div>
                    <div class="field-group"><label data-subject-label>المادة</label><select data-subject-select required><option value="">اختر صف المادة أولًا…</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}" data-grade-id="{{ $subject->grade_id }}" data-fee="{{ $subject->fee }}" data-teacher="{{ $subject->teacher?->name }}">{{ $subject->name }} · {{ number_format($subject->fee, 2) }} ج.م</option>@endforeach</select></div>
                    <div class="field-group"><span class="field-label">المدرس ورسوم المادة</span><p class="field-hint" data-subject-details>اختر مادة لعرض السعر والمدرس.</p></div>
                    <div class="form-field"><label data-paid-label>المبلغ المدفوع</label><input type="number" min="0" step="0.01" value="0" inputmode="decimal" autocomplete="off" data-paid-input required></div>
                    <div class="field-group"><label data-method-label>طريقة الدفع</label><select data-method-select required><option value="cash">نقدي</option><option value="transfer">تحويل</option><option value="wallet">محفظة</option></select></div>
                </div>
            </article>
        </template>

        <section class="subscription-summary" aria-labelledby="subscription-summary-title">
            <div class="subscription-step-heading"><span class="subscription-step-number" aria-hidden="true">3</span><div><h2 id="subscription-summary-title">ملخص الحساب</h2><p>راجع الإجماليات قبل حفظ الاشتراكات والتحصيل.</p></div></div>
            <div class="payment-summary" aria-live="polite">
                <div><span>إجمالي الرسوم</span><strong data-total-fee>0.00 ج.م</strong></div>
                <div><span>إجمالي المدفوع</span><strong data-total-paid>0.00 ج.م</strong></div>
                <div class="payment-summary-remaining"><span>الرصيد المتبقي</span><strong data-total-remaining>0.00 ج.م</strong></div>
            </div>
        </section>

        @error('subjects')<p class="form-message" role="alert">{{ $message }}</p>@enderror
        @foreach (['student_name', 'student_phone', 'grade_id'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
        <div class="subscription-submit"><p>سيُسجل التحصيل لكل مادة بشكل مستقل.</p><button class="primary-button form-save" type="submit">حفظ الاشتراكات والتحصيل</button></div>
    </form>
</section>
@endsection
