@extends('Branch.layouts.main')

@section('title', 'Date Wise Report')

@section('css')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" />
@endsection

@section('content')
<style>
   .pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.4rem;
    gap: 0.3rem; /* Adds spacing between buttons */
}

.pagination .page-item .page-link {
    color: #000;
    border: 1px solid #ddd;
    padding: 6px 12px;
    border-radius: 4px;
}

.pagination .page-item.active .page-link {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.pagination .page-link:hover {
    background-color: #ffe58f;
    border-color: #ffc107;
}

</style>

<div class="container mt-4 flex-grow-1">
    <h3 class="mb-3"><i class="bi bi-calendar-check"></i> Date Wise Report</h3>

    <form method="GET" action="{{ url('Report/reports/date-wise') }}" class="row g-3 mb-2 align-items-end">
        <div class="col-md-2">
            <label for="fromDate" class="form-label">From</label>
            <input type="date" class="form-control" name="from_date" required value="{{ request('from_date') }}">
        </div>
        <div class="col-md-2">
            <label for="toDate" class="form-label">To</label>
            <input type="date" class="form-control" name="to_date" id="toDate" required value="{{ request('to_date') }}" />
        </div>
        <div class="col-md-3">
            <label for="reportType" class="form-label">Report Type</label>
            <select class="form-select" name="report_type" id="reportType" required>
                <option value="">-- Select Type --</option>
                <option value="service" {{ request('report_type') == 'service' ? 'selected' : '' }}>Service Wise</option>
                <option value="product" {{ request('report_type') == 'product' ? 'selected' : '' }}>Product Wise</option>
                <option value="cash" {{ request('report_type') == 'cash' ? 'selected' : '' }}>Cash Wise</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-warning w-100">Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover text-center" style="table-layout: fixed; width: 85%;">
            <thead class="table-dark">
                <tr>
                    <th style="width: 5%;">S.No.</th>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 20%;">Customer</th>
                    <th style="width: 15%;">Chair</th>
                    <th style="width: 25%;">Service/Product</th>
                    <th style="width: 10%;">Amount ($)</th>
                    <th style="width: 10%;">Payment Type</th>
                </tr>
            </thead>
            <tbody>
                @if(request()->filled('from_date') || request()->filled('to_date') || request()->filled('report_type'))
                    @if($reports->count())
                        @foreach ($reports as $index => $report)
                            <tr>
                                <td>{{ ($paginatedResults->currentPage() - 1) * $paginatedResults->perPage() + $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($report->date)->format('d/m/Y') }}</td>
                                <td>{{ $report->mobile ?? '-' }}</td>
                                <td>{{ $report->chair_id ?? '-' }}</td>
                                <td>{{ $report->item_name ?? '-' }}</td>
                                <td>${{ number_format($report->amount, 2) }}</td>
                                <td>{{ ucfirst($report->payment_type ?? '-') }}</td>
                            </tr>
                        @endforeach

                        {{-- Show total only on the last page --}}
                        @if ($paginatedResults->currentPage() === $paginatedResults->lastPage())
                        <tr class="fw-bold table-dark">
                            <td colspan="5" class="text-end">Total Amount</td>
                            <td colspan="2">${{ number_format($totalAmount, 2) }}</td>
                        </tr>
                        @endif
                    @else
                        <tr>
                            <td colspan="7" class="text-center">No records found for selected filters.</td>
                        </tr>
                    @endif
                @else
                    <tr>
                        <td colspan="7" class="text-center">Please apply filters to view report data.</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if (isset($paginatedResults))
            <div class="mt-3 d-flex justify-content-center">
                {{ $paginatedResults->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
</div>
@endsection


@section('scripts')
<script src="{{ asset('assets/js/final_main.js') }}"></script>
@endsection
