<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalSearchRequest;
use Illuminate\Http\RedirectResponse;

class SearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request): RedirectResponse
    {
        return redirect()->route('students.index', [
            'q' => $request->validated('q'),
        ]);
    }
}
