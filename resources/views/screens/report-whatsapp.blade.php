@extends('layouts.app')

@section('content')
<section class="screen reports-screen" aria-labelledby="report-whatsapp-title">
    <x-page-header title="إرسال التقرير عبر واتساب" subtitle="اختر المستلم ثم سيفتح واتساب برسالة التقرير الجاهزة." title-id="report-whatsapp-title" />
    <form class="panel report-share-panel" method="GET" action="{{ route('reports.whatsapp.redirect') }}">
        @foreach ($filters as $name => $value)
            @if (is_scalar($value) && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
        @endforeach
        <div class="panel-heading"><div><p class="eyebrow">تقرير محدد</p><h2>{{ $periodLabel }}</h2></div><a class="outline-button" href="{{ route('reports.index', $filters) }}">العودة للتقرير</a></div>
        <div class="report-share-summary"><span>إجمالي التحصيل <strong class="amount-ok">{{ number_format($collectionTotal, 2) }} ج.م</strong></span><span>صافي التحصيل <strong class="amount-ok">{{ number_format($netCollections, 2) }} ج.م</strong></span></div>
        <fieldset class="recipient-picker" data-recipient-picker><legend>إرسال إلى</legend><p class="muted">سيتم استخدام رقم الهاتف المسجل للمستخدم أو المدرس.</p>
            <div class="recipient-grid">
                @forelse ($recipients as $recipient)
                    <label class="recipient-card"><input type="radio" name="recipient_type" value="{{ $recipient['type'] }}" data-recipient-type required><input type="hidden" name="recipient_id" value="{{ $recipient['id'] }}" disabled data-recipient-id><span><b>{{ $recipient['name'] }}</b><small>{{ $recipient['label'] }} · <span dir="ltr">{{ $recipient['phone'] }}</span></small></span></label>
                @empty
                    <p class="empty-state">لا يوجد مستخدم أو مدرس له رقم هاتف مسجل لإرسال التقرير.</p>
                @endforelse
            </div>
            @error('recipient_id')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <div class="form-actions"><button class="primary-button" type="submit" @disabled($recipients->isEmpty())>متابعة إلى واتساب</button></div>
    </form>
</section>
@endsection
