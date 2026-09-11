<header class="global-header center-header">
    @if (auth()->user()?->hasPermission('students'))
        <form class="global-search" role="search" action="{{ route('search') }}" method="GET">
            <label class="sr-only" for="global-search">بحث عام</label>
            <x-icon name="search" />
            <input id="global-search" name="q" type="search" autocomplete="off" value="{{ request('q') }}" placeholder="ابحث باسم الطالب أو رقم هاتف…">
        </form>
    @else
        <span aria-hidden="true"></span>
    @endif
    <div class="header-context"><span>الإدارة التعليمية</span><strong>{{ request()->routeIs('teacher.portal') ? 'بوابة المدرس' : 'لوحة الحسابات' }}</strong></div>
</header>
