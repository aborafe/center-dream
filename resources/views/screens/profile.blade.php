@extends('layouts.app')

@section('content')
<section class="screen" id="profile">
    <x-page-header title="الملف الشخصي" subtitle="حدّث بياناتك وكلمة المرور الخاصة بك" />
    <form class="panel account-form" method="POST" action="{{ route('profile.update') }}">
        @csrf @method('PUT')
        <div class="account-heading"><span class="account-avatar">{{ mb_substr($user->name, 0, 1) }}</span><div><h2>{{ $user->name }}</h2><p>{{ $user->job_title ?: 'مستخدم النظام' }}</p></div></div>
        <div class="form-grid"><x-input label="الاسم الكامل" name="full_name" value="{{ old('full_name', $user->name) }}" autocomplete="name" /><x-input label="رقم الهاتف" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" inputmode="tel" /><x-input label="البريد الإلكتروني" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" /><x-input label="المسمى الوظيفي" name="job_title" value="{{ old('job_title', $user->job_title) }}" autocomplete="organization-title" /></div>
        <hr><h2>تغيير كلمة المرور</h2>
        <div class="form-grid"><x-input label="كلمة المرور الحالية" name="current_password" type="password" autocomplete="current-password" placeholder="••••••••" /><x-input label="كلمة المرور الجديدة" name="password" type="password" autocomplete="new-password" placeholder="••••••••" /><x-input label="تأكيد كلمة المرور الجديدة" name="password_confirmation" type="password" autocomplete="new-password" placeholder="••••••••" /></div>
        @foreach (['full_name', 'phone', 'email', 'job_title', 'current_password', 'password'] as $field)
            @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror
        @endforeach
        <button class="primary-button form-save" type="submit">حفظ التعديلات</button>
    </form>
</section>
@endsection
