<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TeacherPortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->hasRole('teacher'), 403, 'هذه الصفحة متاحة لحسابات المدرسين فقط.');

        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        $teacher = Teacher::query()
            ->with([
                'subjects' => fn ($query) => $query
                    ->when($academicYear, fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id))
                    ->with(['grade', 'enrollments.student', 'enrollments.payments']),
            ])
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($teacher, 403, 'هذه الصفحة متاحة لحسابات المدرسين فقط.');

        return view('screens.teacher-portal', compact('teacher', 'academicYear'));
    }
}
