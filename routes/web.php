<?php

use App\Http\Controllers\CenterPageController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TeacherPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/login', LoginController::class)->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.authenticate');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/my-subjects', TeacherPortalController::class)->name('teacher.portal');
    Route::get('/', [CenterPageController::class, 'dashboard'])->middleware('permission:dashboard')->name('dashboard');
    Route::get('/students', [CenterPageController::class, 'students'])->middleware('permission:students')->name('students.index');
    Route::get('/students/{student}', [CenterPageController::class, 'studentProfile'])->whereNumber('student')->middleware('permission:students')->name('students.show');
    Route::put('/students/{student}', [CenterPageController::class, 'updateStudent'])->whereNumber('student')->middleware('permission:students')->name('students.update');
    Route::post('/students/{student}/subjects', [CenterPageController::class, 'storeStudentSubject'])->whereNumber('student')->middleware('permission:enrollments')->name('students.subjects.store');
    Route::delete('/students/{student}', [CenterPageController::class, 'destroyStudent'])->whereNumber('student')->middleware('permission:students')->name('students.destroy');
    Route::post('/enrollments/{enrollment}/cancel', [CenterPageController::class, 'cancelEnrollment'])->whereNumber('enrollment')->middleware('permission:students')->name('enrollments.cancel');
    Route::get('/subscriptions/create', [CenterPageController::class, 'subscription'])->middleware('permission:enrollments')->name('subscriptions.create');
    Route::get('/subscriptions/student-lookup', [CenterPageController::class, 'lookupStudent'])->middleware('permission:enrollments')->name('subscriptions.student-lookup');
    Route::post('/subscriptions', [CenterPageController::class, 'storeSubscription'])->middleware('permission:enrollments')->name('subscriptions.store');
    Route::get('/inventory', [CenterPageController::class, 'inventory'])->middleware('permission:academics')->name('inventory.index');
    Route::get('/academics', [CenterPageController::class, 'academics'])->middleware('permission:academics')->name('academics.index');
    Route::post('/academics/subjects', [CenterPageController::class, 'storeSubject'])->middleware('permission:academics')->name('academics.subjects.store');
    Route::put('/academics/subjects/{subject}', [CenterPageController::class, 'updateSubject'])->whereNumber('subject')->middleware('permission:academics')->name('academics.subjects.update');
    Route::delete('/academics/subjects/{subject}', [CenterPageController::class, 'destroySubject'])->whereNumber('subject')->middleware('permission:academics')->name('academics.subjects.destroy');
    Route::post('/academics/years', [CenterPageController::class, 'storeAcademicYear'])->middleware('permission:academics')->name('academics.years.store');
    Route::post('/academics/years/{academicYear}/close', [CenterPageController::class, 'closeAcademicYear'])->whereNumber('academicYear')->middleware('permission:settings')->name('academics.years.close');
    Route::post('/academics/grades', [CenterPageController::class, 'storeGrade'])->middleware('permission:academics')->name('academics.grades.store');
    Route::get('/teachers', [CenterPageController::class, 'teachers'])->middleware('permission:academics')->name('teachers.index');
    Route::post('/teachers', [CenterPageController::class, 'storeTeacher'])->middleware('permission:academics')->name('teachers.store');
    Route::put('/teachers/{teacher}', [CenterPageController::class, 'updateTeacher'])->whereNumber('teacher')->middleware('permission:academics')->name('teachers.update');
    Route::get('/teachers/{teacher}', [CenterPageController::class, 'teacherProfile'])->whereNumber('teacher')->middleware('permission:academics')->name('teachers.show');
    Route::get('/users', [CenterPageController::class, 'users'])->middleware('permission:users')->name('users.index');
    Route::post('/users', [CenterPageController::class, 'storeUser'])->middleware('permission:users')->name('users.store');
    Route::put('/users/{user}', [CenterPageController::class, 'updateUser'])->whereNumber('user')->middleware('permission:users')->name('users.update');
    Route::delete('/users/{user}', [CenterPageController::class, 'destroyUser'])->whereNumber('user')->middleware('permission:users')->name('users.destroy');
    Route::get('/reports', [CenterPageController::class, 'reports'])->middleware('permission:reports')->name('reports.index');
    Route::get('/collections', [CenterPageController::class, 'collections'])->middleware('permission:collections')->name('collections.create');
    Route::post('/collections', [CenterPageController::class, 'storeCollection'])->middleware('permission:collections')->name('collections.store');
    Route::get('/discounts', [CenterPageController::class, 'discounts'])->middleware('permission:discounts')->name('discounts.index');
    Route::post('/discounts', [CenterPageController::class, 'storeDiscount'])->middleware('permission:discounts')->name('discounts.store');
    Route::get('/daily-cashbook', [CenterPageController::class, 'dailyCashbook'])->middleware('permission:collections')->name('daily-cashbook.index');
    Route::post('/daily-cashbook', [CenterPageController::class, 'storeDailyCashMovement'])->middleware('permission:collections')->name('daily-cashbook.store');
    Route::get('/teacher-payouts', [CenterPageController::class, 'payouts'])->middleware('permission:payouts')->name('teacher-payouts.index');
    Route::post('/teacher-payouts', [CenterPageController::class, 'storePayout'])->middleware('permission:payouts')->name('teacher-payouts.store');
    Route::get('/profile', [CenterPageController::class, 'profile'])->name('profile.edit');
    Route::put('/profile', [CenterPageController::class, 'updateProfile'])->name('profile.update');
    Route::get('/settings', [CenterPageController::class, 'settings'])->middleware('permission:settings')->name('settings.edit');
    Route::put('/settings', [CenterPageController::class, 'updateSettings'])->middleware('permission:settings')->name('settings.update');
    Route::get('/receipts/print', [CenterPageController::class, 'receipt'])->middleware('permission:collections')->name('receipts.print');
    Route::get('/receipts/{payment}/print', [CenterPageController::class, 'storedReceipt'])->middleware('permission:collections')->name('receipts.show');
    Route::get('/search', SearchController::class)->middleware('permission:students')->name('search');
});
