<?php

namespace App\Http\Controllers\Report;

use Illuminate\Contracts\Validation\Validator;
use Carbon\Carbon;
use App\Models\bill;
use App\Models\User;
use App\Models\order;
use App\Models\customer;
use App\Models\add_product;
use App\Models\add_service;
use App\Models\AddBranches;
use App\Models\appointment;
use App\Models\chair_detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\emp_detail;
use Hamcrest\Number\OrderingComparison;
use Illuminate\Support\Facades\Auth;
use App\Models\waiting_list;
use App\Models\pending_bill;
use App\Models\Attendance;
use Illuminate\Pagination\Paginator;


class BranchReportController extends Controller
{
    public function boot()
{
    Paginator::useBootstrapFive(); // For Bootstrap 5
}
    public function report()
    {
        $user = Auth::user();
        return view('Report.report');
    }
    
public function dateWise(Request $request)
{
    $branchId = auth()->user()->branch_id;

    // Default return view with empty data
    if (!$request->filled('from_date') || !$request->filled('to_date') || !$request->filled('report_type')) {
        return view('Report.date-wise-report', [
            'reports' => collect(),
            'paginatedResults' => null,
            'totalAmount' => 0
        ]);
    }

    // Validate filters
    $request->validate([
        'from_date' => 'required|date',
        'to_date' => 'required|date|after_or_equal:from_date',
        'report_type' => 'required|string|in:service,product,cash'
    ]);

    // Step 1: Paginate filtered appointments
    $appointments = DB::table('appointments')
        ->leftJoin('chair_details', 'appointments.chair_id', '=', 'chair_details.chair_id')
        ->where('appointments.branch_id', $branchId)
        ->whereBetween('appointments.date', [$request->from_date, $request->to_date])
        ->select('appointments.id', 'appointments.date', 'appointments.mobile', 'appointments.chair_id')
        ->orderBy('appointments.date', 'desc')
        ->paginate(10)
        ->appends($request->all());

    $reportItems = [];
    $totalAmount = 0;

    // Step 2: For each appointment, get orders and bill info
    foreach ($appointments as $appointment) {
        $ordersQuery = DB::table('orders')
            ->where('appointment_id', $appointment->id);

        // Apply report_type filtering
        if ($request->report_type === 'service') {
            $ordersQuery->whereNotNull('service_name');
        } elseif ($request->report_type === 'product') {
            $ordersQuery->whereNotNull('product_name');
        }

        // Select item info
        $orders = $ordersQuery
            ->select('service_name', 'product_name', 'service_price', 'product_price')
            ->get();

        // Fetch payment type from bill
        $bill = DB::table('bills')
            ->where('appointment_id', $appointment->id)
            ->select('payment_type', 'total')
            ->first();

        // Process cash type: show bill total as single row
        if ($request->report_type === 'cash') {
            foreach ($orders as $order) {
                $itemName = $order->service_name ?? $order->product_name ?? '-';

                $amount = $order->service_price ?? $order->product_price ?? 0;
                $totalAmount += $amount;

                $reportItems[] = (object)[
                    'date'         => $appointment->date,
                    'mobile'       => $appointment->mobile ?? '-',
                    'chair_id'     => $appointment->chair_id ?? '-',
                    'item_name'    => $itemName,
                    'amount'       => $amount,
                    'payment_type' => ucfirst($bill->payment_type ?? '-'),
                ];
            }
        }
        // Product or service type: each item in its own row
        else {
            foreach ($orders as $order) {
                $itemName = $request->report_type === 'service'
                    ? $order->service_name
                    : $order->product_name;

                $amount = $request->report_type === 'service'
                    ? $order->service_price
                    : $order->product_price;

                $totalAmount += $amount;

                $reportItems[] = (object)[
                    'date'         => $appointment->date,
                    'mobile'       => $appointment->mobile ?? '-',
                    'chair_id'     => $appointment->chair_id ?? '-',
                    'item_name'    => $itemName ?? '-',
                    'amount'       => $amount ?? 0,
                    'payment_type' => ucfirst($bill->payment_type ?? '-'),
                ];
            }
        }
    }

    return view('Report.date-wise-report', [
        'reports' => collect($reportItems),
        'paginatedResults' => $appointments,
        'totalAmount' => $totalAmount
    ]);
}

  public function chairWise(Request $request)
    {
        $user = Auth::user();
        $branch = AddBranches::where('branch_id', $user->branch_id)->first();
       $chairs = chair_detail::where('branch_id', $user->branch_id)->get();

        $query = DB::table('bills')
            ->join('appointments', 'bills.appointment_id', '=', 'appointments.id')
            ->join('chair_details', 'appointments.chair_id', '=', 'chair_details.chair_id')
            ->select(
                'bills.final_amount',
                'bills.payment_type',
                'appointments.date',
                'appointments.mobile',
                'appointments.chair_id',
                'chair_details.chair_id as chair_code'
            )
             ->where('chair_details.branch_id', $user->branch_id); 

        if ($request->filled('fromDate')) {
            $query->whereDate('appointments.date', '>=', $request->fromDate);
        }

        if ($request->filled('toDate')) {
            $query->whereDate('appointments.date', '<=', $request->toDate);
        }

        if ($request->filled('chair')) {
            $query->where('appointments.chair_id', $request->chair);
        }

        if ($request->filled('paymentMode')) {
            $query->where('bills.payment_type', strtolower($request->paymentMode));
        }

        $reports = $query->orderBy('appointments.date', 'desc')->paginate(10);

        return view('Report.chair-wise-report', compact('reports', 'chairs'));
    }

    public function serviceReport(Request $request)
    {
        $user = Auth::user();

        // Get all distinct services for the filter dropdown
        $services = DB::table('add_services')
            ->select('service_name')
            ->distinct()
            ->get();

        // Build the query
        $query = DB::table('orders')
            ->join('appointments', 'orders.appointment_id', '=', 'appointments.id')
            ->join('bills', 'appointments.id', '=', 'bills.appointment_id')
            ->where('appointments.branch_id', $user->branch_id) // ✅
            ->select(
                'orders.service_name',
                'orders.service_duration',
                'orders.service_qnty',
                'orders.service_price',
                'appointments.date',
                'appointments.mobile',
                'bills.payment_type',
                'bills.final_amount'
            )
            ->whereNotNull('orders.service_name');

        // ✅ Apply filters if they are set
        if ($request->filled('fromDate')) {
            $query->whereDate('appointments.date', '>=', $request->fromDate);
        }

        if ($request->filled('toDate')) {
            $query->whereDate('appointments.date', '<=', $request->toDate);
        }

        if ($request->filled('service')) {
            $query->where('orders.service_name', $request->service);
        }

        if ($request->filled('paymentMode')) {
            $query->where('bills.payment_type', strtolower($request->paymentMode));
        }

        $records = $query->get();

        return view('Report.service-wise-report', compact('services', 'records'));
    }
    public function productReport(Request $request)
    {
        $user = Auth::user();
        $products = DB::table('add_products')
            ->select('product_name')
            ->distinct()
            ->get();

        $query = DB::table('orders')
            ->join('appointments', 'orders.appointment_id', '=', 'appointments.id')
            ->join('bills', 'appointments.id', '=', 'bills.appointment_id')
            ->where('appointments.branch_id', $user->branch_id) // ✅
            ->select(
                'orders.product_name',
                'orders.product_price',
                'appointments.date',
                'appointments.mobile',
                'bills.payment_type'
            )
            ->whereNotNull('orders.product_name');

        if ($request->filled('fromDate')) {
            $query->whereDate('appointments.date', '>=', $request->fromDate);
        }
        if ($request->filled('toDate')) {
            $query->whereDate('appointments.date', '<=', $request->toDate);
        }
        if ($request->filled('product')) {
            $query->where('orders.product_name', $request->product);
        }
        if ($request->filled('paymentMode')) {
            $query->where('bills.payment_type', strtolower($request->paymentMode));
        }

        $records = $query->get();


        return view('Report.product-wise-report', compact('products', 'records'));
    }

    public function staffReport(Request $request)
    {
        $branchId = auth()->user()->branch_id;

        $staffList = DB::table('attendances')
            ->where('branch_id', $branchId)
            ->pluck('staff_name', 'emp_id');

        $attendances = collect(); // default empty
        $totalMinutes = 0;

        // Show data only if filter is applied
        if ($request->filled(['fromDate', 'toDate']) || $request->filled('staff')) {
            $query = DB::table('attendances')
                ->where('branch_id', $branchId);

            if ($request->filled('fromDate') && $request->filled('toDate')) {
                $query->whereBetween('date', [$request->fromDate, $request->toDate]);
            }

            if ($request->filled('staff')) {
                $query->where('emp_id', $request->staff);
            }

            $attendances = $query->orderBy('date', 'asc')->get();

            // Use 'hours' from database if available
            $attendances->transform(function ($row) use (&$totalMinutes) {
                if ($row->hours) {
                    // Convert formatted string "2h 30m" to minutes if needed
                    // Assuming format: "2h 30m"
                    preg_match('/(\d+)h\s*(\d+)m/', $row->hours, $matches);
                    if (count($matches) === 3) {
                        $minutes = ((int)$matches[1] * 60) + (int)$matches[2];
                        $totalMinutes += $minutes;
                    }
                } else {
                    $row->hours = '-';
                }
                return $row;
            });
        }

        return view('Report.staff-wise-report', [
            'attendances' => $attendances,
            'reportdata' => $staffList,
            'totalHours' => floor($totalMinutes / 60) . 'h ' . ($totalMinutes % 60) . 'm',
        ]);
    }
}
