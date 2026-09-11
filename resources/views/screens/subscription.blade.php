@extends('layouts.app')

@section('content')
<section class="screen" id="subscription" aria-labelledby="subscription-title">
    <x-page-header title="تسجيل اشتراك جديد" subtitle="أضف مواد الطالب وحدد ما دُفع لكل مادة." />

    <form class="panel subscription-form" method="POST" action="{{ route('subscriptions.store') }}" data-subscription-form data-student-lookup-url="{{ route('subscriptions.student-lookup') }}">
        @csrf
        <h2 id="subscription-title">بيانات الطالب</h2>
        <div class="form-grid">
            <x-input label="اسم الطالب" name="student_name" value="{{ old('student_name') }}" placeholder="ابحث بالاسم أو أضف طالبًا جديدًا…" autocomplete="name" data-student-name required />
            <x-input label="رقم الهاتف" name="student_phone" value="{{ old('student_phone') }}" placeholder="01012345678" autocomplete="tel" inputmode="tel" type="tel" data-student-phone required />
        </div>
        <div class="student-lookup" data-student-lookup aria-live="polite" hidden></div>

        <hr>
        <div class="subscription-section-head">
            <div><h2>مواد الاشتراك</h2><p>كل سطر يمثل مادة وتحصلًا مستقلًا.</p></div>
            <button class="outline-button" type="button" data-add-subject>إضافة مادة</button>
        </div>

        <div class="subscription-rows" data-subject-rows>
            @php($oldSubjects = old('subjects', [['subject_id' => '', 'paid_amount' => 0, 'payment_method' => 'cash']]))
            @foreach ($oldSubjects as $index => $oldSubject)
                <article class="subject-entry" data-subject-row>
                    <div class="subject-entry-head"><strong>مادة <span data-row-number>{{ $loop->iteration }}</span></strong><button class="icon-button danger-button" type="button" aria-label="حذف المادة" title="حذف المادة" data-remove-subject>×</button></div>
                    <div class="form-grid subject-entry-grid">
                        <div class="field-group"><label>المادة</label><select name="subjects[{{ $index }}][subject_id]" data-subject-select required><option value="">اختر المادة…</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}" data-fee="{{ $subject->fee }}" data-teacher="{{ $subject->teacher?->name }}" @selected((int) ($oldSubject['subject_id'] ?? 0) === $subject->id)>{{ $subject->name }} — {{ $subject->grade->name }} · {{ number_format($subject->fee, 2) }} ج.م</option>@endforeach</select></div>
                        <div class="field-group"><label>المدرس والسعر</label><p class="field-hint" data-subject-details>اختر مادة لعرض السعر والمدرس.</p></div>
                        <x-input label="المبلغ المدفوع" name="subjects[{{ $index }}][paid_amount]" type="number" min="0" step="0.01" value="{{ $oldSubject['paid_amount'] ?? 0 }}" inputmode="decimal" autocomplete="off" data-paid-input required />
                        <div class="field-group"><label>طريقة الدفع</label><select name="subjects[{{ $index }}][payment_method]" data-method-select required><option value="cash" @selected(($oldSubject['payment_method'] ?? 'cash') === 'cash')>نقدي</option><option value="transfer" @selected(($oldSubject['payment_method'] ?? '') === 'transfer')>تحويل</option><option value="wallet" @selected(($oldSubject['payment_method'] ?? '') === 'wallet')>محفظة</option></select></div>
                    </div>
                </article>
            @endforeach
        </div>

        <template data-subject-template>
            <article class="subject-entry" data-subject-row>
                <div class="subject-entry-head"><strong>مادة <span data-row-number></span></strong><button class="icon-button danger-button" type="button" aria-label="حذف المادة" title="حذف المادة" data-remove-subject>×</button></div>
                <div class="form-grid subject-entry-grid">
                    <div class="field-group"><label>المادة</label><select data-subject-select required><option value="">اختر المادة…</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}" data-fee="{{ $subject->fee }}" data-teacher="{{ $subject->teacher?->name }}">{{ $subject->name }} — {{ $subject->grade->name }} · {{ number_format($subject->fee, 2) }} ج.م</option>@endforeach</select></div>
                    <div class="field-group"><label>المدرس والسعر</label><p class="field-hint" data-subject-details>اختر مادة لعرض السعر والمدرس.</p></div>
                    <div class="form-field"><label>المبلغ المدفوع</label><input type="number" min="0" step="0.01" value="0" inputmode="decimal" autocomplete="off" data-paid-input required></div>
                    <div class="field-group"><label>طريقة الدفع</label><select data-method-select required><option value="cash">نقدي</option><option value="transfer">تحويل</option><option value="wallet">محفظة</option></select></div>
                </div>
            </article>
        </template>

        <div class="payment-summary" aria-live="polite">
            <div><span>إجمالي الرسوم</span><strong data-total-fee>0.00 ج.م</strong></div>
            <div><span>إجمالي المدفوع</span><strong data-total-paid>0.00 ج.م</strong></div>
            <b data-total-remaining>الرصيد المتبقي: 0.00 ج.م</b>
        </div>

        @error('subjects')<p class="form-message" role="alert">{{ $message }}</p>@enderror
        @foreach (['student_name', 'student_phone'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
        <button class="primary-button form-save" type="submit">حفظ الاشتراكات والتحصيل</button>
    </form>
</section>
@endsection
