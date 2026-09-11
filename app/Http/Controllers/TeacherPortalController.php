<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TeacherPortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->hasRole('teacher'), 403, 'هذه الصفحة متاحة لحسابات المدرسين فقط.');

        $teacher = Teacher::query()
            ->with(['subjects.grade', 'subjects.enrollments.student', 'subjects.enrollments.payments'])
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($teacher, 403, 'هذه الصفحة متاحة لحسابات المدرسين فقط.');

        return view('screens.teacher-portal', compact('teacher'));
    }
}
