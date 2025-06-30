<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SalaryController extends Controller
{
    /**
     * Display a listing of salaries for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Salary::where('business_id', $user->business_id)
            ->with(['employee', 'creator']);

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by month/year
        if ($request->has('month')) {
            $query->whereMonth('month', $request->month);
        }
        if ($request->has('year')) {
            $query->whereYear('month', $request->year);
        }

        $salaries = $query->latest('month')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $salaries
        ]);
    }

    /**
     * Store a newly created salary record.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m-d',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:advance,salary',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Validate employee belongs to same business and is staff
        $employee = User::where('id', $request->employee_id)
            ->where('business_id', $user->business_id)
            ->where('user_type', 'staff')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid employee selected'
            ], 400);
        }

        // Check if salary record already exists for this employee and month (for salary type)
        if ($request->type === 'salary') {
            $existingSalary = Salary::where('business_id', $user->business_id)
                ->where('employee_id', $request->employee_id)
                ->where('month', $request->month)
                ->where('type', 'salary')
                ->first();

            if ($existingSalary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary record already exists for this employee and month'
                ], 400);
            }
        }

        $salary = Salary::create([
            'business_id' => $user->business_id,
            'employee_id' => $request->employee_id,
            'month' => $request->month,
            'amount' => $request->amount,
            'type' => $request->type,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Salary record created successfully',
            'data' => $salary->load(['employee', 'creator'])
        ], 201);
    }

    /**
     * Display the specified salary record.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $salary = Salary::where('business_id', $user->business_id)
            ->with(['employee', 'creator'])
            ->find($id);

        if (!$salary) {
            return response()->json([
                'success' => false,
                'message' => 'Salary record not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $salary
        ]);
    }

    /**
     * Update the specified salary record.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $salary = Salary::where('business_id', $user->business_id)->find($id);

        if (!$salary) {
            return response()->json([
                'success' => false,
                'message' => 'Salary record not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m-d',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:advance,salary',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate employee belongs to same business and is staff
        $employee = User::where('id', $request->employee_id)
            ->where('business_id', $user->business_id)
            ->where('user_type', 'staff')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid employee selected'
            ], 400);
        }

        // Check for duplicate salary records (excluding current)
        if ($request->type === 'salary') {
            $existingSalary = Salary::where('business_id', $user->business_id)
                ->where('employee_id', $request->employee_id)
                ->where('month', $request->month)
                ->where('type', 'salary')
                ->where('id', '!=', $id)
                ->first();

            if ($existingSalary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary record already exists for this employee and month'
                ], 400);
            }
        }

        $salary->update($request->only([
            'employee_id',
            'month',
            'amount',
            'type'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Salary record updated successfully',
            'data' => $salary->load(['employee', 'creator'])
        ]);
    }

    /**
     * Remove the specified salary record.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $salary = Salary::where('business_id', $user->business_id)->find($id);

        if (!$salary) {
            return response()->json([
                'success' => false,
                'message' => 'Salary record not found'
            ], 404);
        }

        $salary->delete();

        return response()->json([
            'success' => true,
            'message' => 'Salary record deleted successfully'
        ]);
    }

    /**
     * Get employee salary summary.
     */
    public function employeeSummary($employeeId, Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Validate employee
        $employee = User::where('id', $employeeId)
            ->where('business_id', $user->business_id)
            ->where('user_type', 'staff')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $query = Salary::where('business_id', $user->business_id)
            ->where('employee_id', $employeeId);

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('month', $request->year);
        }

        $totalSalary = $query->where('type', 'salary')->sum('amount');
        $totalAdvances = $query->where('type', 'advance')->sum('amount');
        
        $monthlyBreakdown = $query->selectRaw('YEAR(month) as year, MONTH(month) as month, type, SUM(amount) as total')
            ->groupBy('year', 'month', 'type')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => $employee,
                'total_salary' => $totalSalary,
                'total_advances' => $totalAdvances,
                'net_amount' => $totalSalary - $totalAdvances,
                'monthly_breakdown' => $monthlyBreakdown
            ]
        ]);
    }

    /**
     * Get salary analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Salary::where('business_id', $user->business_id);

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('month', $request->year);
        }

        $totalSalaries = $query->where('type', 'salary')->sum('amount');
        $totalAdvances = $query->where('type', 'advance')->sum('amount');

        $employeeBreakdown = $query->join('users', 'salaries.employee_id', '=', 'users.id')
            ->selectRaw('users.name, SUM(CASE WHEN salaries.type = "salary" THEN salaries.amount ELSE 0 END) as total_salary')
            ->selectRaw('SUM(CASE WHEN salaries.type = "advance" THEN salaries.amount ELSE 0 END) as total_advances')
            ->groupBy('users.id', 'users.name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_salaries' => $totalSalaries,
                'total_advances' => $totalAdvances,
                'net_salary_expense' => $totalSalaries - $totalAdvances,
                'employee_breakdown' => $employeeBreakdown
            ]
        ]);
    }
}
