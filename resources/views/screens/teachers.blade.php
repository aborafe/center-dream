@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="teachers-title">
    <x-page-header title="المدرسون" subtitle="تابع المحفظة والمواد، وعدّل حالة كل مدرس من نفس الصفحة." title-id="teachers-title" />

    <div class="teachers-workspace">
        <form class="panel management-form teacher-create-form" method="POST" action="{{ route('teachers.store') }}">
            @csrf
            <div class="management-form-heading"><div><p class="eyebrow">فريق العمل</p><h2>إضافة مدرس</h2><p>أضف بيانات التواصل الآن، ثم اربط المدرس بمواده من الإدارة الأكاديمية.</p></div></div>
            <div class="form-grid">
                <x-input label="اسم المدرس" name="name" value="{{ old('name') }}" autocomplete="name" required />
                <x-input label="رقم الهاتف (اختياري)" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" />
            </div>
            @foreach (['name', 'phone'] as $field) @error($field)<p class="form-message" role="alert">{{ $message }}</p>@enderror @endforeach
            <div class="management-form-actions"><p>لا يمكن صرف مستحقات قبل ربط مادة بالمدرس.</p><button class="primary-button form-save" type="submit">إضافة المدرس</button></div>
        </form>
        <aside class="panel teachers-guide" aria-label="دليل إدارة المدرسين">
            <span class="teachers-guide-mark" aria-hidden="true"><x-icon name="teacher" /></span>
            <div><p class="eyebrow">خطوة تالية</p><h2>اربط المواد بالمدرس</h2><p>بعد الحفظ، انتقل إلى الإدارة الأكاديمية وحدد مدرس المادة ورسومها.</p></div>
            <a class="outline-button" href="{{ route('academics.index') }}">إدارة المواد</a>
        </aside>
    </div>

    <div class="entity-grid teachers-grid">
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
                <div class="teacher-finance"><span>مستحقات تحت التسوية</span><strong>{{ $teacher['wallet'] }}</strong></div>
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
