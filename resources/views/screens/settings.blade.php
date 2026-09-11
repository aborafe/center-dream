@extends('layouts.app')

@section('content')
<section class="screen" id="settings" aria-labelledby="settings-title">
    <x-page-header title="إعدادات المركز" subtitle="عدّل معلومات المركز والتفضيلات المستخدمة داخل النظام." />
    <form class="panel account-form" method="POST" action="{{ route('settings.update') }}">
        @csrf @method('PUT')
        <h2 id="settings-title">بيانات المركز</h2>
        <div class="form-grid">
            <x-input label="اسم المركز" name="center_name" value="{{ old('center_name', $settings->center_name) }}" autocomplete="organization" required />
            <x-input label="رقم التواصل" name="center_phone" type="tel" value="{{ old('center_phone', $settings->center_phone) }}" autocomplete="tel" inputmode="tel" required />
            <x-input label="العنوان" name="address" value="{{ old('address', $settings->address) }}" autocomplete="street-address" required />
            <x-input label="العملة" name="currency" value="{{ old('currency', $settings->currency) }}" autocomplete="off" required />
        </div>
        <hr>
        <h2>تفضيلات النظام</h2>
        <div class="settings-options">
            <label><input name="balance_alerts" type="checkbox" value="1" @checked(old('balance_alerts', $settings->balance_alerts))> إظهار تنبيه عند وجود رصيد مستحق</label>
            <label><input name="daily_summary" type="checkbox" value="1" @checked(old('daily_summary', $settings->daily_summary))> إظهار ملخص التحصيل اليومي</label>
            <label><input name="daily_report_copy" type="checkbox" value="1" @checked(old('daily_report_copy', $settings->daily_report_copy))> تجهيز نسخة من التقرير اليومي</label>
        </div>
        @foreach (['center_name', 'center_phone', 'address', 'currency'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
        <button class="primary-button form-save" type="submit">حفظ الإعدادات</button>
    </form>
</section>
@endsection
