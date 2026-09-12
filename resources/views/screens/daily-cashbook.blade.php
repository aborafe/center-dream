@extends('layouts.app')

@section('content')
@php($netTotal = $incomeTotal - $expenseTotal)
<section class="screen daily-cashbook" aria-labelledby="daily-cashbook-title">
    <x-page-header title="إيراد ومصروف اليوم" subtitle="سجل دخل السنتر ومصروفاته في دفتر نقدي مستقل." title-id="daily-cashbook-title" />

    <form class="panel daily-date-filter" method="GET" action="{{ route('daily-cashbook.index') }}" data-date-range-picker>
        <input name="from" type="hidden" value="{{ $selectedFrom->toDateString() }}" data-date-range-from>
        <input name="to" type="hidden" value="{{ $selectedTo->toDateString() }}" data-date-range-to>
        <div class="field-group"><span class="field-label" id="cashbook-date-range-label">فترة الدفتر</span><button class="date-range-trigger" type="button" data-date-range-open aria-haspopup="dialog" aria-labelledby="cashbook-date-range-label" aria-describedby="cashbook-date-range-value"><span data-date-range-value id="cashbook-date-range-value"></span><x-icon name="calendar" aria-hidden="true" /></button></div>
        <button class="primary-button" type="submit">عرض الفترة</button>
        <noscript><div class="form-grid"><x-input label="من تاريخ" name="from" type="date" value="{{ $selectedFrom->toDateString() }}" autocomplete="off" /><x-input label="إلى تاريخ" name="to" type="date" value="{{ $selectedTo->toDateString() }}" autocomplete="off" /></div></noscript>
    </form>

    <dialog class="date-range-dialog" data-date-range-dialog aria-labelledby="cashbook-range-dialog-title">
        <div class="date-range-dialog-head"><div><p class="eyebrow">تصفية الدفتر</p><h2 id="cashbook-range-dialog-title">اختر نطاق التاريخ</h2></div><button class="icon-button date-range-close" type="button" data-date-range-close aria-label="إغلاق تقويم الفترة"><x-icon name="close" /></button></div>
        <div class="date-range-calendar-head"><button class="icon-button date-range-nav" type="button" data-date-range-prev aria-label="الشهر السابق">‹</button><strong data-date-range-month></strong><button class="icon-button date-range-nav" type="button" data-date-range-next aria-label="الشهر التالي">›</button></div>
        <div class="date-range-weekdays" aria-hidden="true"><span>أحد</span><span>اثن</span><span>ثلا</span><span>أرب</span><span>خمي</span><span>جمع</span><span>سبت</span></div>
        <div class="date-range-calendar" data-date-range-calendar role="grid" aria-label="تقويم اختيار الفترة"></div>
        <p class="date-range-selection" data-date-range-selection aria-live="polite"></p>
        <div class="dialog-actions"><button class="outline-button" type="button" data-date-range-clear>مسح الفترة</button><button class="primary-button" type="button" data-date-range-apply>عرض الفترة</button></div>
    </dialog>

    <section class="daily-cash-summary" aria-label="ملخص دفتر المركز للفترة المختارة">
        <article class="panel daily-cash-card daily-cash-income"><span>إيراد السنتر</span><strong>+{{ number_format($incomeTotal, 2) }} ج.م</strong><small>دخل المركز من التحصيل اليومي</small></article>
        <article class="panel daily-cash-card daily-cash-expense"><span>مصروفات السنتر</span><strong>−{{ number_format($expenseTotal, 2) }} ج.م</strong><small>إيجار، كهرباء، توريد أو مصروف آخر</small></article>
        <article class="panel daily-cash-card {{ $netTotal >= 0 ? 'daily-cash-net-positive' : 'daily-cash-net-negative' }}"><span>صافي الفترة</span><strong>{{ $netTotal >= 0 ? '+' : '−' }}{{ number_format(abs($netTotal), 2) }} ج.م</strong><small>دخل المركز بعد المصروفات</small></article>
    </section>

    <section class="daily-cash-forms" aria-label="تسجيل حركة جديدة">
        <form class="panel daily-cash-form income-form" method="POST" action="{{ route('daily-cashbook.store') }}">
            @csrf
            <input name="type" type="hidden" value="income">
            <input name="category" type="hidden" value="daily_collection">
            <div class="panel-heading"><div><p class="eyebrow">دخول نقدي</p><h2>تسجيل إيراد للسنتر</h2></div><span class="daily-form-badge income-badge">إيراد سنتر</span></div>
            <p class="muted">هذا المبلغ يُضاف إلى حساب السنتر فقط. اسم الحساب المسجل للدخول يُحفظ تلقائيًا في سجل العملية.</p>
            <div class="form-grid">
                <div class="field-group"><label for="income-date">تاريخ التحصيل</label><input id="income-date" name="movement_date" type="date" value="{{ old('type') === 'income' ? old('movement_date') : $selectedTo->toDateString() }}" autocomplete="off" required></div>
                <div class="field-hint"><strong>حساب الحركة</strong><span>إيراد سنتر دريم</span></div>
                <x-input label="المبلغ المُحصّل" name="amount" type="number" min="0.01" step="0.01" value="{{ old('type') === 'income' ? old('amount') : '' }}" inputmode="decimal" autocomplete="off" placeholder="مثال: 1500.00" required />
                <x-input label="ملاحظة (اختياري)" name="note" value="{{ old('type') === 'income' ? old('note') : '' }}" autocomplete="off" placeholder="مثال: إجمالي تحصيل الفترة المسائية…" />
            </div>
            @foreach (['movement_date', 'amount', 'note'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
            <button class="primary-button form-save" type="submit">حفظ إيراد السنتر</button>
        </form>

        <form class="panel daily-cash-form expense-form" method="POST" action="{{ route('daily-cashbook.store') }}">
            @csrf
            <input name="type" type="hidden" value="expense">
            <div class="panel-heading"><div><p class="eyebrow">خروج نقدي</p><h2>تسجيل مصروف للسنتر</h2></div><span class="daily-form-badge expense-badge">مصروف</span></div>
            <p class="muted">سجل مصروف الكهرباء أو الإيجار أو التوريد لصاحب السنتر وغيرها.</p>
            <div class="form-grid">
                <div class="field-group"><label for="expense-date">تاريخ المصروف</label><input id="expense-date" name="movement_date" type="date" value="{{ old('type') === 'expense' ? old('movement_date') : $selectedTo->toDateString() }}" autocomplete="off" required></div>
                <div class="field-group"><label for="expense-category">نوع المصروف</label><select id="expense-category" name="category" required><option value="">اختر نوع المصروف…</option>@foreach (collect($categories)->except('daily_collection') as $value => $label)<option value="{{ $value }}" @selected(old('type') === 'expense' && old('category') === $value)>{{ $label }}</option>@endforeach</select></div>
                <x-input label="مبلغ المصروف" name="amount" type="number" min="0.01" step="0.01" value="{{ old('type') === 'expense' ? old('amount') : '' }}" inputmode="decimal" autocomplete="off" placeholder="مثال: 650.00" required />
                <x-input label="ملاحظة (اختياري)" name="note" value="{{ old('type') === 'expense' ? old('note') : '' }}" autocomplete="off" placeholder="مثال: فاتورة كهرباء سبتمبر…" />
            </div>
            @foreach (['movement_date', 'category', 'amount', 'note'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
            <button class="danger-button-text form-save" type="submit">حفظ المصروف</button>
        </form>
    </section>

    <section class="panel structured-list">
        <div class="panel-heading"><div><p class="eyebrow">دفتر {{ \App\Support\DatePresenter::date($selectedFrom) }} — {{ \App\Support\DatePresenter::date($selectedTo) }}</p><h2>حركات الفترة</h2></div><span class="muted">{{ $movements->count() }} حركة مسجلة</span></div>
        <div class="table-wrap"><table><thead><tr><th>التاريخ</th><th>النوع</th><th>الحساب</th><th>التصنيف</th><th>المبلغ</th><th>المُسجّل</th><th>الملاحظة</th></tr></thead><tbody>@forelse ($movements as $movement)<tr><td dir="ltr">{{ \App\Support\DatePresenter::date($movement->movement_date) }}</td><td><span class="status {{ $movement->type === 'income' ? 'paid' : 'partial' }}">{{ $movement->type === 'income' ? 'إيراد' : 'مصروف' }}</span></td><td class="strong">سنتر دريم</td><td>{{ $categories[$movement->category] }}</td><td class="{{ $movement->type === 'income' ? 'amount-ok' : 'amount-due' }}">{{ $movement->type === 'income' ? '+' : '−' }}{{ number_format($movement->amount, 2) }} ج.م</td><td>{{ $movement->recorder->name }}</td><td>{{ $movement->note ?: '—' }}</td></tr>@empty<tr><td colspan="7" class="muted">لا توجد حركات مسجلة في الفترة المختارة.</td></tr>@endforelse</tbody></table></div>
    </section>
</section>
@endsection
