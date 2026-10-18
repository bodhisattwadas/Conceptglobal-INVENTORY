<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SupplierProfileController extends Controller
{
    public function download(Supplier $supplier): Response
    {
        $supplier->load('companies');
        $filename = Str::slug($supplier->name).'-vendor-profile.pdf';

        return Pdf::loadView('suppliers.profile-pdf', compact('supplier'))
            ->setPaper('a4')
            ->download($filename);
    }
}
