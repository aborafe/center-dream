@extends('layouts.app')

@section('content')
<section class="screen" id="inventory">
    <x-page-header title="جرد المواد اليومي" subtitle="اعرف من اشترك في كل مادة وإجمالي التحصيل" />
    <div class="panel inventory-filter"><span>تعرض المواد النشطة وملخص تحصيلها الفعلي.</span><a class="chip" href="{{ route('students.index') }}">كل الطلاب</a><a class="chip chip-blue" href="{{ route('subscriptions.create') }}">تسجيل اشتراك</a></div>
    <div class="inventory-list">
        @foreach ($materials as $material)
            <article class="panel inventory-card"><div class="accent {{ $material[4] }}"></div><div class="material-title"><h2>{{ $material[0] }}</h2><span>{{ $material[1] }}</span></div><div class="inventory-stat"><span>المشتركون</span><strong>{{ $material[2] }} <small>/ {{ $material[7] }} طالب</small></strong></div><div class="inventory-stat"><span>إجمالي التحصيل</span><strong class="amount-ok">{{ $material[3] }}</strong><small>{{ $material[8] }} دفعات مسجلة</small></div><div class="inventory-stat"><span>أرصدة الطلاب</span><strong class="amount-due">{{ $material[6] }}</strong><small>{{ $material[9] }} اشتراكات ملغاة</small></div><a class="outline-button inventory-students-link" href="{{ route('students.index', ['subject_id' => $material[5]]) }}">عرض الطلاب</a></article>
        @endforeach
    </div>
</section>

@endsection
