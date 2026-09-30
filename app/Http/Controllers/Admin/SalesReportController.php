<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\SaleSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\Location;
use App\Models\User;
use App\Services\SalesReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public function __construct(private SalesReportService $reports) {}

    public function index(SalesReportRequest $request): View
    {
        $filters = $request->validated();
        [$from, $to] = $this->reports->range($filters);

        return view('admin.reports.sales', [
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'totals' => $this->reports->totals($filters),
            'sales' => $this->reports->rows($filters),
            'officers' => User::whereIn('role', [Role::Staff, Role::Admin])->orderBy('last_name')->get(),
            'locations' => Location::orderBy('id')->get(),
            'sources' => SaleSource::cases(),
        ]);
    }

    public function export(SalesReportRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        [$from, $to] = $this->reports->range($filters);
        $filename = 'eprint-sales-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Officer', 'Location', 'Source', 'Payment', 'Claim code', 'Amount', 'Commission rate', 'Commission', 'Sheets']);

            foreach ($this->reports->exportRows($filters) as $sale) {
                fputcsv($out, array_map($this->safeCell(...), [
                    $sale->created_at->format('Y-m-d H:i'),
                    $sale->user->name,
                    $sale->location->name,
                    $sale->source->value,
                    $sale->payment_method === PaymentMethod::Gcash ? 'GCash' : 'Cash',
                    $sale->printJob?->claim_code ?? '',
                    $sale->amount,
                    $sale->commission_rate,
                    $sale->commission_amount,
                    SalesReportService::sheets($sale),
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Prefix values that a spreadsheet would run as a formula.
     */
    private function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)
            ? "'".$value
            : $value;
    }
}
