@php
    /** @var \App\Models\User $currentUser */
    $currentUser = auth()->user();
    $avatarLetter = mb_substr(trim($currentUser->name), 0, 1);
    $jobTitle = $currentUser->job_title ?: ($currentUser->hasRole('admin') ? 'مسؤول المركز' : 'مستخدم النظام');
@endphp

<aside class="sidebar center-sidebar" id="app-sidebar">
    <div class="brand center-brand">
        <div class="brand-copy">
            <h1>سنتر دريم</h1>
            <p>إدارة التعليم والتحصيل</p>
        </div>
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="طي القائمة الجانبية" title="طي القائمة">
            <svg class="menu-toggle-icon" viewBox="0 0 24 24" aria-hidden="true"><path class="menu-line menu-line-top" d="M4 7h16"/><path class="menu-line menu-line-middle" d="M4 12h16"/><path class="menu-line menu-line-bottom" d="M4 17h16"/></svg>
        </button>
    </div>
    <nav class="side-nav center-nav" aria-label="التنقل الرئيسي">
        @if ($currentUser->hasRole('teacher'))<a @class(['nav-item', 'is-active' => request()->routeIs('teacher.portal')]) href="{{ route('teacher.portal') }}"><span class="nav-icon"><x-icon name="academic" /></span><span class="nav-label">موادي وطلابي</span></a>@endif
        @if ($currentUser->hasPermission('dashboard'))<a @class(['nav-item', 'is-active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><span class="nav-icon"><x-icon name="dashboard" /></span><span class="nav-label">لوحة التحكم</span></a>@endif
        @if ($currentUser->hasPermission('students'))<a @class(['nav-item', 'is-active' => request()->routeIs('students.*', 'subscriptions.*', 'collections.*', 'discounts.*')]) href="{{ route('students.index') }}"><span class="nav-icon"><x-icon name="students" /></span><span class="nav-label">الطلاب والتحصيل</span></a>@endif
        @if ($currentUser->hasPermission('payouts'))<a @class(['nav-item', 'is-active' => request()->routeIs('teacher-payouts.*')]) href="{{ route('teacher-payouts.index') }}"><span class="nav-icon"><x-icon name="wallet" /></span><span class="nav-label">صرف المدرسين</span></a>@endif
        @if ($currentUser->hasPermission('academics'))<a @class(['nav-item', 'is-active' => request()->routeIs('inventory.*')]) href="{{ route('inventory.index') }}"><span class="nav-icon"><x-icon name="inventory" /></span><span class="nav-label">جرد المواد</span></a><a @class(['nav-item', 'is-active' => request()->routeIs('academics.*')]) href="{{ route('academics.index') }}"><span class="nav-icon"><x-icon name="academic" /></span><span class="nav-label">الإدارة الأكاديمية</span></a><a @class(['nav-item', 'is-active' => request()->routeIs('teachers.*')]) href="{{ route('teachers.index') }}"><span class="nav-icon"><x-icon name="teacher" /></span><span class="nav-label">المدرسون</span></a>@endif
        @if ($currentUser->hasPermission('users'))<a @class(['nav-item', 'is-active' => request()->routeIs('users.*')]) href="{{ route('users.index') }}"><span class="nav-icon"><x-icon name="users" /></span><span class="nav-label">المستخدمون</span></a>@endif
        @if ($currentUser->hasPermission('reports'))<a @class(['nav-item', 'is-active' => request()->routeIs('reports.*')]) href="{{ route('reports.index') }}"><span class="nav-icon"><x-icon name="report" /></span><span class="nav-label">التقارير</span></a>@endif
        @if ($currentUser->hasPermission('settings'))<a @class(['nav-item', 'is-active' => request()->routeIs('settings.*')]) href="{{ route('settings.edit') }}"><span class="nav-icon"><x-icon name="settings" /></span><span class="nav-label">إعدادات المركز</span></a>@endif
    </nav>
    <div class="profile-row center-profile-row">
        <a class="profile" href="{{ route('profile.edit') }}" aria-label="فتح الملف الشخصي">
            <span class="avatar" aria-hidden="true">{{ $avatarLetter }}</span>
            <span class="profile-copy"><strong>{{ $currentUser->name }}</strong><small>{{ $jobTitle }}</small></span>
        </a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="sidebar-logout" type="submit" aria-label="تسجيل الخروج" title="تسجيل الخروج"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3.75h9.5v16.5H5zM14.5 12H3.5m0 0 3-3m-3 3 3 3"/></svg></button></form>
    </div>
</aside>
