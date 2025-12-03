@extends('Branch.layouts.main')

@section('title', 'Staff Wise Report')

@section('content')
    <div class="container mt-4">
        <h3>Staff Attendance Report</h3>

        <!-- Filter Form -->
        <form method="POST" action="{{ route('reports.staff') }}" class="row g-2 mb-4">
            @csrf
            <div class="col-md-2">
                <label for="fromDate" class="form-label">From Date</label>
                <input type="date" id="fromDate" name="fromDate" class="form-control" value="{{ old('fromDate', request('fromDate')) }}" />
            </div>
            <div class="col-md-2">
                <label for="toDate" class="form-label">To Date</label>
                <input type="date" id="toDate" name="toDate" class="form-control" value="{{ old('toDate', request('toDate')) }}" />
            </div>
            <div class="col-md-3">
                <label for="staff" class="form-label">Select Staff</label>
                <select id="staff" name="staff" class="form-select">
                    <option value="">All Staff</option>
                    @foreach ($reportdata as $emp_id => $name)
                        <option value="{{ $emp_id }}" {{ request('staff') == $emp_id ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-warning w-100">Filter</button>
            </div>
        </form>

        <!-- Report Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center" style="table-layout: fixed; width: 85%;">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Hours</th>
                    </tr>
                </thead>
               <tbody>
    @php $totalMinutes = 0; @endphp

    @if (request()->filled(['fromDate', 'toDate']) || request()->filled('staff'))
        @forelse ($attendances as $att)
            <tr>
                <td>{{ $att->date }}</td>
                <td>{{ $att->emp_id }}</td>
                <td>{{ $att->staff_name }}</td>
                <td>{{ $att->check_in }}</td>
                <td>{{ $att->check_out }}</td>
                <td>
                    {{ $att->hours ?? '-' }}
                    @php
                        if (!empty($att->hours) && $att->hours !== '-') {
                            $parts = explode(':', $att->hours);
                            $h = isset($parts[0]) && is_numeric($parts[0]) ? (int) $parts[0] : 0;
                            $m = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : 0;
                            $totalMinutes += ($h * 60) + $m;
                        }
                    @endphp
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">No records found for the selected filters.</td>
            </tr>
        @endforelse

        @if ($attendances->count() > 0)
            <tr style="font-weight: bold; background-color: #f2f2f2;">
                <td colspan="5" class="text-end">Total Hours Worked:</td>
                <td>
                    {{ floor($totalMinutes / 60) }}h {{ $totalMinutes % 60 }}m
                    {{-- ({{ number_format($totalMinutes / 60, 2) }} Hrs) --}}
                </td>
            </tr>
        @endif
    @else
        <tr>
            <td colspan="6" class="text-center text-muted">Please apply filter to view data.</td>
        </tr>
    @endif
</tbody>

            </table>
        </div>
    </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('assets/js/final_main.js') }}"></script>
@endsection
