@extends('layouts.app')

@section('content')
<section class="screen" aria-labelledby="users-title">
    <x-page-header title="المستخدمون والصلاحيات" subtitle="أنشئ الحسابات، وراجع بيانات الدخول وحالة كل مستخدم." title-id="users-title" />
    <form class="panel account-form" method="POST" action="{{ route('users.store') }}">
        @csrf
        <h2 id="users-title">إنشاء مستخدم</h2>
        <div class="form-grid">
            <x-input label="الاسم الكامل" name="name" value="{{ old('name') }}" autocomplete="name" required />
            <x-input label="البريد الإلكتروني" name="email" type="email" value="{{ old('email') }}" autocomplete="email" spellcheck="false" required />
            <x-input label="رقم الهاتف (اختياري)" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" />
            <x-input label="المسمى الوظيفي" name="job_title" value="{{ old('job_title') }}" autocomplete="organization-title" required />
            <x-input label="كلمة المرور" name="password" type="password" autocomplete="new-password" required />
            <x-input label="تأكيد كلمة المرور" name="password_confirmation" type="password" autocomplete="new-password" required />
            <div class="field-group"><label for="role_id">الدور</label><select id="role_id" name="role_id" required><option value="">اختر الدور…</option>@foreach ($roles as $role)<option value="{{ $role->id }}" data-role-slug="{{ $role->slug }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>@endforeach</select><p class="field-hint">مدير المركز يملك جميع الصلاحيات تلقائيًا.</p></div>
        </div>
        <hr>
        <div class="permission-head"><h2>صلاحيات إضافية</h2><p>تستخدم للسكرتير عند الحاجة؛ المدرس يقتصر على بوابته التعليمية.</p></div>
        <div class="permission-grid" data-permission-grid>
            @foreach ($permissions as $permission)<label class="permission-option"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permission_ids', [])))><span>{{ $permission->name }}</span></label>@endforeach
        </div>
        <button class="primary-button form-save" type="submit">إنشاء المستخدم</button>
    </form>

    <section class="panel structured-list">
        <div class="panel-heading"><div><p class="eyebrow">الحسابات</p><h2>المستخدمون الحاليون</h2></div><span class="muted">الإيقاف يمنع الدخول ولا يحذف السجل.</span></div>
        <div class="table-wrap"><table><thead><tr><th>المستخدم</th><th>الدور</th><th>نطاق الوصول</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>
            @forelse ($users as $user)
                <tr><td class="strong">{{ $user['name'] }}<span class="table-subline">{{ $user['email'] }}</span></td><td>{{ $user['role'] }}</td><td>{{ $user['scope'] }}</td><td><span class="status {{ $user['is_active'] ? 'paid' : 'partial' }}">{{ $user['is_active'] ? 'نشط' : 'موقوف' }}</span></td><td class="table-actions"><button class="icon-button row-action" type="button" data-open-dialog="edit-user-{{ $user['id'] }}" aria-label="تعديل {{ $user['name'] }}" title="تعديل"><x-icon name="edit" /></button>@if ($user['is_current_user'])<button class="icon-button danger-button" type="button" disabled aria-label="لا يمكن حذف حسابك الحالي" title="لا يمكن حذف الحساب الحالي"><x-icon name="trash" /></button>@else<form method="POST" action="{{ route('users.destroy', $user['id']) }}" data-delete-user data-user-name="{{ $user['name'] }}">@csrf @method('DELETE')<button class="icon-button danger-button" type="submit" aria-label="حذف {{ $user['name'] }}" title="حذف المستخدم"><x-icon name="trash" /></button></form>@endif</td></tr>
                <dialog id="edit-user-{{ $user['id'] }}" class="form-dialog user-edit-dialog" aria-labelledby="edit-user-title-{{ $user['id'] }}">
                    <form method="POST" action="{{ route('users.update', $user['id']) }}">
                        @csrf
                        @method('PUT')
                        <div class="panel-heading dialog-heading">
                            <div><h2 id="edit-user-title-{{ $user['id'] }}">تعديل المستخدم</h2><p class="muted">حدّث بيانات الحساب والصلاحيات من مكان واحد.</p></div>
                            <button class="icon-button dialog-close" type="button" data-close-dialog aria-label="إغلاق"><span aria-hidden="true">×</span></button>
                        </div>
                        <div class="form-grid">
                            <x-input id="edit-user-name-{{ $user['id'] }}" label="الاسم" name="name" value="{{ $user['name'] }}" autocomplete="name" required />
                            <x-input id="edit-user-email-{{ $user['id'] }}" label="البريد الإلكتروني" name="email" type="email" value="{{ $user['email'] }}" autocomplete="email" required />
                            <x-input id="edit-user-phone-{{ $user['id'] }}" label="الهاتف" name="phone" type="tel" value="{{ $user['phone'] }}" autocomplete="tel" inputmode="tel" />
                            <x-input id="edit-user-job-title-{{ $user['id'] }}" label="المسمى الوظيفي" name="job_title" value="{{ $user['job_title'] }}" autocomplete="organization-title" required />
                        </div>
                        <label class="checkbox-line"><input name="is_active" type="checkbox" value="1" @checked($user['is_active'])> السماح بتسجيل الدخول</label>
                        <section class="user-edit-section" aria-labelledby="reset-password-{{ $user['id'] }}">
                            <div class="permission-head"><h3 id="reset-password-{{ $user['id'] }}">إعادة تعيين كلمة المرور</h3><p>اترك الحقلين فارغين للإبقاء على كلمة المرور الحالية.</p></div>
                            <div class="form-grid">
                                <x-input id="edit-user-password-{{ $user['id'] }}" label="كلمة المرور الجديدة" name="password" type="password" autocomplete="new-password" minlength="8" />
                                <x-input id="edit-user-password-confirmation-{{ $user['id'] }}" label="تأكيد كلمة المرور الجديدة" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" />
                            </div>
                        </section>
                        <fieldset class="user-edit-section edit-user-permissions" aria-describedby="permissions-help-{{ $user['id'] }}">
                            <legend>صلاحيات المستخدم</legend>
                            <p id="permissions-help-{{ $user['id'] }}" class="muted">اضغط على الصلاحية لتفعيلها أو أزل تحديدها لسحبها من هذا المستخدم.</p>
                            <div class="permission-grid">
                                @foreach ($permissions as $permission)
                                    <label class="permission-option"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $user['permission_ids'], true)) @disabled($user['permissions_locked'])><span>{{ $permission->name }}</span></label>
                                @endforeach
                            </div>
                            @if ($user['permissions_locked'])<p class="field-hint">مدير المركز يملك جميع الصلاحيات ولا يمكن تقييده.</p>@endif
                        </fieldset>
                        <div class="dialog-actions"><button class="outline-button" type="button" data-close-dialog>إلغاء</button><button class="primary-button" type="submit">حفظ التعديل</button></div>
                    </form>
                </dialog>
            @empty
                <tr><td colspan="5" class="muted">لا يوجد مستخدمون بعد.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</section>
@endsection
