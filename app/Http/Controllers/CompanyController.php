<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function show(Company $company): View
    {
        $company->loadCount(['suppliers']);

        return view('companies.show', compact('company'));
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', 'Brand / company deleted successfully.');
    }
}
