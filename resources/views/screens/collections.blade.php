@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="collection-title">
    <x-page-header title="تحصيل دفعة" subtitle="اختر اشتراكًا قائمًا وسجّل دفعة جديدة مع وصل قابل للطباعة." title-id="collection-title" />
    <form class="panel collection-form" method="POST" action="{{ route('collections.store') }}">
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
        <div class="form-grid">
            <div class="field-group"><label for="enrollment_id">اشتراك الطالب</label><select id="enrollment_id" name="enrollment_id" required data-collection-enrollment>@forelse ($enrollments as $enrollment)<option value="{{ $enrollment->id }}" data-teacher="{{ $enrollment->subject->teacher?->name ?? '' }}" @selected((int) old('enrollment_id', request()->integer('enrollment')) === $enrollment->id)>{{ $enrollment->student->name }} — {{ $enrollment->subject->name }} · المتبقي {{ number_format((float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount'), 2) }} ج.م</option>@empty<option value="">لا توجد اشتراكات لها رصيد</option>@endforelse</select><p class="collection-destination" data-collection-destination aria-live="polite"></p></div>
            <x-input label="المبلغ المستلم" name="amount" value="{{ old('amount') }}" autocomplete="off" inputmode="decimal" />
            <div class="field-group"><label for="collection-method">طريقة الدفع</label><select id="collection-method" name="method"><option value="cash">نقدي</option><option value="transfer">تحويل</option><option value="wallet">محفظة</option></select></div>
            <div class="field-group"><label for="receipt-size">مقاس الوصل</label><select id="receipt-size" name="size"><option value="A5">A5 — نصف ورقة</option><option value="A4">A4 — ورقة كاملة</option></select></div>
        </div>
        @foreach (['enrollment_id', 'amount', 'method', 'size'] as $field)
            @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror
        @endforeach
        <button class="primary-button form-save" type="submit" @disabled($enrollments->isEmpty())>تسجيل الدفعة وفتح الوصل</button>
    </form>
    @if ($completedPayment)
        @php($whatsApp = '20'.ltrim(preg_replace('/\D/', '', $completedPayment->student->phone), '0'))
        <dialog class="success-dialog" data-auto-dialog aria-labelledby="collection-success-title">
            <div class="success-mark">✓</div><h2 id="collection-success-title">تم التحصيل بنجاح</h2>
            <p>تم تسجيل {{ number_format((float) $completedPayment->amount, 2) }} ج.م من {{ $completedPayment->student->name }} لمادة {{ $completedPayment->enrollment->subject->name }}.</p>
            <div class="dialog-actions"><a class="primary-button" href="{{ route('receipts.show', ['payment' => $completedPayment, 'size' => $receiptSize]) }}" target="_blank">طباعة الوصل</a><a class="outline-button" href="https://wa.me/{{ $whatsApp }}?text={{ urlencode('تم استلام '.number_format((float) $completedPayment->amount, 2).' ج.م من حساب مادة '.$completedPayment->enrollment->subject->name.' للطالب '.$completedPayment->student->name.'. رقم الوصل: '.$completedPayment->receipt_number) }}" target="_blank" rel="noopener">إرسال واتساب</a><button class="chip" type="button" data-close-dialog>إغلاق</button></div>
        </dialog>
    @endif
</section>
@endsection
