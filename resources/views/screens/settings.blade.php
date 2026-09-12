@extends('layouts.app')

@section('content')
<section class="screen" id="settings" aria-labelledby="settings-title">
    <x-page-header title="إعدادات المركز" subtitle="عدّل معلومات المركز والتفضيلات المستخدمة داخل النظام." title-id="settings-title" />
    <form class="panel management-form settings-form" method="POST" action="{{ route('settings.update') }}">
        @csrf @method('PUT')
        <div class="management-form-heading">
            <div><p class="eyebrow">هوية المركز</p><h2 id="center-details-title">بيانات المركز</h2><p>تظهر هذه البيانات في شاشات النظام والمستندات المالية.</p></div>
        </div>
        <div class="settings-layout">
            <section class="settings-section" aria-labelledby="center-details-title">
                <div class="form-grid">
                    <x-input label="اسم المركز" name="center_name" value="{{ old('center_name', $settings->center_name) }}" autocomplete="organization" required />
                    <x-input label="رقم التواصل" name="center_phone" type="tel" value="{{ old('center_phone', $settings->center_phone) }}" autocomplete="tel" inputmode="tel" required />
                    <x-input label="العنوان" name="address" value="{{ old('address', $settings->address) }}" autocomplete="street-address" required />
                    <x-input label="العملة" name="currency" value="{{ old('currency', $settings->currency) }}" autocomplete="off" required />
                </div>
                @foreach (['center_name', 'center_phone', 'address', 'currency'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
            </section>
            <section class="settings-section settings-preferences" aria-labelledby="preferences-title">
                <div class="management-form-heading"><div><p class="eyebrow">تشغيل النظام</p><h2 id="preferences-title">التفضيلات</h2><p>حدّد التنبيهات والملخصات التي يحتاجها فريق المركز يوميًا.</p></div></div>
                <div class="settings-options">
                    <label><input name="balance_alerts" type="checkbox" value="1" @checked(old('balance_alerts', $settings->balance_alerts))><span><strong>تنبيه الأرصدة المستحقة</strong><small>إظهار متابعة عند وجود مبالغ مطلوبة من الطلاب.</small></span></label>
                    <label><input name="daily_summary" type="checkbox" value="1" @checked(old('daily_summary', $settings->daily_summary))><span><strong>ملخص التحصيل اليومي</strong><small>إظهار ملخص الإيرادات المسجلة خلال اليوم.</small></span></label>
                    <label><input name="daily_report_copy" type="checkbox" value="1" @checked(old('daily_report_copy', $settings->daily_report_copy))><span><strong>نسخة التقرير اليومي</strong><small>تجهيز نسخة يمكن الرجوع إليها ضمن التقارير.</small></span></label>
                </div>
            </section>
        </div>
        <div class="management-form-actions"><p>تُحفظ التغييرات فور تأكيد العملية.</p><button class="primary-button form-save" type="submit">حفظ الإعدادات</button></div>
    </form>
</section>
@endsection
