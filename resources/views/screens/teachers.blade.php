@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="teachers-title">
    <x-page-header title="المدرسون" subtitle="تابع المحفظة والمواد، وعدّل حالة كل مدرس من نفس الصفحة." />

    <form class="panel account-form" method="POST" action="{{ route('teachers.store') }}">
        @csrf
        <h2>إضافة مدرس</h2>
        <div class="form-grid">
            <x-input label="اسم المدرس" name="name" value="{{ old('name') }}" autocomplete="name" required />
            <x-input label="رقم الهاتف (اختياري)" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" />
        </div>
        @foreach (['name', 'phone'] as $field)
            @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror
        @endforeach
        <button class="primary-button form-save" type="submit">إضافة المدرس</button>
    </form>

    <div class="entity-grid">
        @forelse ($teachers as $teacher)
            <article class="panel entity-card teacher-card {{ $teacher['is_active'] ? '' : 'is-inactive' }}">
                <div class="entity-card-topline">
                    <span class="entity-avatar">{{ mb_substr($teacher['name'], 0, 1) }}</span>
                    <div class="entity-actions">
                        <a class="icon-button row-action" href="{{ route('teachers.show', $teacher['id']) }}" aria-label="فتح ملف {{ $teacher['name'] }}" title="فتح الملف"><x-icon name="profile" /></a>
                        <button class="icon-button row-action" type="button" data-open-dialog="edit-teacher-{{ $teacher['id'] }}" aria-label="تعديل {{ $teacher['name'] }}" title="تعديل"><x-icon name="edit" /></button>
                    </div>
                </div>
                <h2><a href="{{ route('teachers.show', $teacher['id']) }}">{{ $teacher['name'] }}</a></h2>
                <p>{{ $teacher['subjects'] }} · {{ $teacher['students'] }} طالبًا</p>
                <span class="status {{ $teacher['is_active'] ? 'paid' : 'partial' }}">{{ $teacher['is_active'] ? 'نشط' : 'موقوف' }}</span>
                <div class="teacher-finance"><span>تحصيل المواد</span><strong class="amount-ok">{{ $teacher['collections'] }}</strong></div>
                <div class="teacher-finance"><span>المحفظة المتاحة</span><strong>{{ $teacher['wallet'] }}</strong></div>
            </article>

            <dialog id="edit-teacher-{{ $teacher['id'] }}" class="form-dialog" aria-labelledby="edit-teacher-title-{{ $teacher['id'] }}">
                <form method="POST" action="{{ route('teachers.update', $teacher['id']) }}">
                    @csrf
                    @method('PUT')
                    <div class="panel-heading"><h2 id="edit-teacher-title-{{ $teacher['id'] }}">تعديل المدرس</h2><button class="icon-button row-action" type="button" data-close-dialog aria-label="إغلاق"><span aria-hidden="true">×</span></button></div>
                    <x-input label="اسم المدرس" name="name" value="{{ $teacher['name'] }}" autocomplete="name" required />
                    <x-input label="رقم الهاتف" name="phone" type="tel" value="{{ $teacher['phone'] }}" autocomplete="tel" inputmode="tel" />
                    <label class="checkbox-line"><input name="is_active" type="checkbox" value="1" @checked($teacher['is_active'])> الحساب نشط ويمكن ربط مواد جديدة به</label>
                    <div class="dialog-actions"><button class="outline-button" type="button" data-close-dialog>إلغاء</button><button class="primary-button" type="submit">حفظ التعديل</button></div>
                </form>
            </dialog>
        @empty
            <p class="panel empty-state">لا يوجد مدرسون بعد. أضف أول مدرس للبدء.</p>
        @endforelse
    </div>
</section>
@endsection
