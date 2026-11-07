<?php

namespace App\Http\Controllers;

use App\Services\ProductHsnImportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductHsnController extends Controller
{
    public function __construct(
        protected ProductHsnImportService $hsnService
    ) {}

    /**
     * Download products spreadsheet pre-filled with all products in DB.
     */
    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $blank = $request->boolean('sample', false);

        return $this->hsnService->exportTemplate($blank);
    }
}
