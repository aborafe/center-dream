@extends('layouts.app')

@section('content')
<section class="screen reports-screen" aria-labelledby="report-whatsapp-title">
    <x-page-header title="إرسال التقرير عبر واتساب" subtitle="اختر المستلم أو أدخل رقمًا آخر، ثم افتح واتساب لإرفاق مستند التقرير PDF." title-id="report-whatsapp-title" />
    <form class="panel report-share-panel" method="GET" action="{{ route('reports.whatsapp.redirect') }}">
        @foreach ($filters as $name => $value)
            @if (is_scalar($value) && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <div class="panel-heading report-share-heading"><div><p class="eyebrow">تقرير محدد</p><h2>{{ $periodLabel }}</h2></div><div class="report-share-actions"><a class="outline-button" href="{{ route('reports.preview', $filters) }}" target="_blank" rel="noopener">معاينة وحفظ PDF</a><a class="outline-button" href="{{ route('reports.index', $filters) }}">العودة للتقرير</a></div></div>
        <div class="report-share-summary"><span>إجمالي التحصيل <strong class="amount-ok">{{ number_format($collectionTotal, 2) }} ج.م</strong></span><span>صافي التحصيل <strong class="amount-ok">{{ number_format($netCollections, 2) }} ج.م</strong></span></div>
        <fieldset class="recipient-picker" data-recipient-picker><legend>إرسال المستند إلى</legend><p class="muted">افتح مستند PDF أولًا، ثم اختر المستلم. يفتح واتساب دون نص لتُرفق المستند نفسه.</p>
            <div class="recipient-grid">
                @forelse ($recipients as $recipient)
                    <label class="recipient-card"><input type="radio" name="recipient_type" value="{{ $recipient['type'] }}" data-recipient-type required @checked(old('recipient_type') === $recipient['type'] && (int) old('recipient_id') === $recipient['id'])><input type="hidden" name="recipient_id" value="{{ $recipient['id'] }}" @disabled(! (old('recipient_type') === $recipient['type'] && (int) old('recipient_id') === $recipient['id'])) data-recipient-id><span><b>{{ $recipient['name'] }}</b><small>{{ $recipient['label'] }} · <span dir="ltr">{{ $recipient['phone'] }}</span></small></span></label>
                @empty
                    <p class="empty-state">لا يوجد مستخدم أو مدرس له رقم هاتف مسجل. يمكنك استخدام رقم آخر أدناه.</p>
                @endforelse
                <label class="recipient-card recipient-card-custom"><input type="radio" name="recipient_type" value="custom" data-recipient-type required @checked(old('recipient_type') === 'custom')><span><b>رقم واتساب آخر</b><small>اكتب رقمًا غير مسجل في النظام.</small></span></label>
            </div>
            <div class="custom-recipient-phone" data-custom-recipient-phone @if (old('recipient_type') !== 'custom') hidden @endif><label for="recipient_phone">رقم واتساب</label><input id="recipient_phone" name="recipient_phone" type="tel" value="{{ old('recipient_phone') }}" placeholder="مثال: 01012345678…" autocomplete="tel" inputmode="tel" @disabled(old('recipient_type') !== 'custom') data-custom-recipient-input><p>يمكنك إدخال الرقم محليًا أو بصيغة دولية تبدأ بـ <span dir="ltr">+20</span>.</p></div>
            @error('recipient_id')<p class="field-error">{{ $message }}</p>@enderror
            @error('recipient_phone')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <div class="report-document-note"><strong>خطوة أخيرة داخل واتساب</strong><span>بعد فتح المحادثة، اضغط أيقونة المرفقات واختر ملف PDF الذي حفظته من المعاينة.</span></div>
        <div class="form-actions"><button class="primary-button" type="submit">فتح واتساب لإرفاق المستند</button></div>
    </form>
</section>
@endsection
