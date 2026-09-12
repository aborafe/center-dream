@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="payouts-title">
    <x-page-header title="صرف مستحقات المدرسين" subtitle="سجّل كل عملية بتاريخ صرفها وطريقة الدفع؛ تظهر مباشرة في التقارير المالية." title-id="payouts-title" />

    @if ($teachers->isNotEmpty())
        <form class="panel collection-form" method="POST" action="{{ route('teacher-payouts.store') }}">
            @csrf
            <h2 id="payouts-title">تسجيل عملية صرف</h2>
            <div class="form-grid">
                <div class="field-group"><label for="teacher_id">المدرس</label><select id="teacher_id" name="teacher_id" data-teacher-select required><option value="">اختر المدرس…</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" data-profile-url="{{ route('teachers.show', $teacher) }}" data-wallet="{{ number_format($teacher->wallet_total, 2) }} ج.م" data-subjects="{{ $teacher->subject_count }}" @selected(old('teacher_id', request()->integer('teacher')) == $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>
                <div class="field-group"><label for="payout-subject">من محفظة مادة (اختياري)</label><select id="payout-subject" name="subject_id" data-payout-subject-select aria-describedby="payout-subject-hint" disabled><option value="">صرف عام من محفظة المدرس</option>
                    @foreach ($teachers as $teacher)
                        @foreach ($teacher->subjects as $subject)
                            <option value="{{ $subject->id }}" data-teacher-id="{{ $teacher->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    @endforeach
                </select><small class="field-help" id="payout-subject-hint" data-payout-subject-hint aria-live="polite">اختر المدرس أولًا لعرض مواده.</small></div>
                <x-input label="مبلغ الصرف" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" inputmode="decimal" autocomplete="off" required />
                <x-input label="تاريخ الصرف" name="payout_date" type="date" value="{{ old('payout_date', now()->format('Y-m-d')) }}" autocomplete="off" required />
                <div class="field-group"><label for="payout-method">طريقة الدفع</label><select id="payout-method" name="method" required><option value="cash" @selected(old('method') === 'cash')>نقدي</option><option value="transfer" @selected(old('method') === 'transfer')>تحويل</option><option value="wallet" @selected(old('method') === 'wallet')>محفظة</option></select></div>
                <x-input label="ملاحظة (اختياري)" name="note" value="{{ old('note') }}" autocomplete="off" />
            </div>
            <aside class="teacher-payout-preview" data-teacher-payout-preview hidden aria-live="polite"><span>محفظة المدرس المتاحة</span><strong data-payout-wallet></strong><small data-payout-subjects></small><a data-payout-profile href="#">فتح ملف المدرس</a></aside>
            @foreach (['teacher_id', 'subject_id', 'amount', 'payout_date', 'method', 'note'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
            <button class="primary-button form-save" type="submit">تسجيل الصرف</button>
        </form>
    @else
        <section class="panel empty-state"><h2>لا يوجد مدرس نشط للصرف</h2><p>أضف مدرسًا نشطًا أولًا، ثم سجّل عملية صرفه هنا.</p><a class="primary-button" href="{{ route('teachers.index') }}">عرض المدرسين</a></section>
    @endif

    <section class="panel structured-list">
        <div class="panel-heading"><h2>عمليات الصرف</h2><span class="muted">مرتبطة بالمدرس والمنفذ وتاريخ العملية.</span></div>
        <div class="table-wrap"><table><thead><tr><th>المدرس</th><th>المادة</th><th>المبلغ</th><th>تاريخ الصرف</th><th>الطريقة</th><th>المنفذ</th></tr></thead><tbody>@forelse ($payouts as $payout)<tr><td class="strong">{{ $payout->teacher->name }}</td><td>{{ $payout->subject?->name ?? 'صرف عام' }}</td><td class="amount-due">{{ number_format((float) $payout->amount, 2) }} ج.م</td><td dir="ltr">{{ \App\Support\DatePresenter::date($payout->paid_at) }}</td><td>{{ ['cash' => 'نقدي', 'transfer' => 'تحويل', 'wallet' => 'محفظة'][$payout->method] }}</td><td>{{ $payout->payer->name }}</td></tr>@empty<tr><td colspan="6" class="muted">لا توجد عمليات صرف مسجلة حتى الآن.</td></tr>@endforelse</tbody></table></div>
    </section>
</section>
@endsection
