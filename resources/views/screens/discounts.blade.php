@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="discounts-title">
    <x-page-header title="الخصومات" subtitle="سجل الخصومات المعتمدة على اشتراكات الطلاب." title-id="discounts-title" />
    @if ($enrollments->isNotEmpty())
    <form class="panel collection-form" method="POST" action="{{ route('discounts.store') }}">
        @csrf
        <h2>اعتماد خصم</h2>
        <div class="form-grid">
            <div class="field-group"><label for="enrollment_id">اشتراك الطالب</label><select id="enrollment_id" name="enrollment_id" required>@foreach ($enrollments as $enrollment)<option value="{{ $enrollment->id }}" @selected(old('enrollment_id') == $enrollment->id)>{{ $enrollment->student->name }} — {{ $enrollment->subject->name }}</option>@endforeach</select></div>
            <div class="field-group"><label for="discount-type">نوع الخصم</label><select id="discount-type" name="type" required><option value="amount" @selected(old('type') === 'amount')>مبلغ</option><option value="percentage" @selected(old('type') === 'percentage')>نسبة مئوية</option></select></div>
            <x-input label="قيمة الخصم" name="value" type="number" min="0.01" step="0.01" value="{{ old('value') }}" inputmode="decimal" autocomplete="off" />
            <x-input label="سبب الخصم" name="reason" value="{{ old('reason') }}" autocomplete="off" />
        </div>
        @foreach (['enrollment_id', 'type', 'value', 'reason'] as $field)
            @error($field)
                <p class="form-message" role="alert">{{ $message }}</p>
            @enderror
        @endforeach
        <button class="primary-button form-save" type="submit">اعتماد الخصم</button>
    </form>
    @else
        <section class="panel empty-state"><h2>لا يوجد اشتراك متاح للخصم</h2><p>سجّل طالبًا في مادة أولًا، ثم ستظهر عملية الاشتراك هنا لاعتماد الخصم.</p><a class="primary-button" href="{{ route('subscriptions.create') }}">تسجيل اشتراك</a></section>
    @endif
    <section class="panel structured-list">
        <div class="panel-heading"><div><p class="eyebrow">سجل مالي</p><h2>الخصومات المسجلة</h2></div><span class="muted">تظهر العملية مع صاحب الاعتماد.</span></div>
        @if ($discountDate)<p class="filter-context">يعرض الجدول خصومات يوم {{ \App\Support\DatePresenter::date($discountDate) }} فقط. <a href="{{ route('discounts.index') }}">عرض كل الخصومات</a></p>@endif
        <div class="table-wrap"><table><thead><tr><th>الطالب</th><th>المادة</th><th>نوع الخصم</th><th>القيمة</th><th>المبلغ</th><th>اعتمدها</th><th>السبب</th></tr></thead><tbody>
            @forelse ($discounts as $discount)
                <tr><td class="strong"><a class="student-link" href="{{ route('students.show', $discount->enrollment->student) }}">{{ $discount->enrollment->student->name }}</a></td><td>{{ $discount->enrollment->subject->name }}</td><td>{{ $discount->type === 'percentage' ? 'نسبة' : 'قيمة' }}</td><td>{{ $discount->type === 'percentage' ? $discount->value.'%' : number_format((float) $discount->value, 2).' ج.م' }}</td><td class="amount-ok">{{ number_format((float) $discount->amount, 2) }} ج.م</td><td>{{ $discount->approver?->name ?? 'غير محدد' }}</td><td>{{ $discount->reason }}</td></tr>
            @empty
                <tr><td colspan="7" class="muted">لا توجد خصومات مسجلة حتى الآن.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</section>
@endsection
