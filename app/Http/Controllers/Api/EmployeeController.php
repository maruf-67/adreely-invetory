<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    /**
     * List all employees (staff) for the authenticated user's business.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = User::where('business_id', $user->business_id)
            ->where('user_type', 'staff');

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->get();

        return response()->json([
            'success' => true,
            'data' => $employees
        ]);
    }

    /**
     * Show a specific employee.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $employee = User::where('id', $id)
            ->where('business_id', $user->business_id)
            ->where('user_type', 'staff')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $employee
        ]);
    }

    /**
     * Get salary history for an employee.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function salaryHistory(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $employee = User::where('id', $id)
            ->where('business_id', $user->business_id)
            ->where('user_type', 'staff')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $query = EmployeeSalary::where('employee_id', $id)
            ->where('business_id', $user->business_id)
            ->with('employee');

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('salary_month', $request->year);
        }

        // Filter by month and year
        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('salary_month', $request->month)
                  ->whereYear('salary_month', $request->year);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $salaries = $query->orderByDesc('salary_month')->get();

        return response()->json([
            'success' => true,
            'data' => $salaries
        ]);
    }

    /**
     * Add salary record for an employee.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function addSalary(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $employee = User::where('id', $id)
            ->where('business_id', $user->business_id)
            ->where('user_type', 'staff')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:salary,advance,bonus,deduction',
            'payment_date' => 'required|date',
            'salary_month' => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
            'status' => 'in:pending,paid,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $salary = EmployeeSalary::create([
            'business_id' => $user->business_id,
            'employee_id' => $id,
            'amount' => $request->amount,
            'type' => $request->type,
            'payment_date' => $request->payment_date,
            'salary_month' => $request->salary_month,
            'notes' => $request->notes,
            'reference_number' => $request->reference_number,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Salary record added successfully',
            'data' => $salary->load('employee')
        ], 201);
    }

    /**
     * Update salary record.
     *
     * @param Request $request
     * @param int $employeeId
     * @param int $salaryId
     * @return JsonResponse
     */
    public function updateSalary(Request $request, $employeeId, $salaryId): JsonResponse
    {
        $user = Auth::user();
        
        $salary = EmployeeSalary::where('id', $salaryId)
            ->where('employee_id', $employeeId)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$salary) {
            return response()->json([
                'success' => false,
                'message' => 'Salary record not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|required|numeric|min:0',
            'type' => 'sometimes|required|in:salary,advance,bonus,deduction',
            'payment_date' => 'sometimes|required|date',
            'salary_month' => 'sometimes|required|date_format:Y-m-d',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
            'status' => 'in:pending,paid,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'amount', 'type', 'payment_date', 'salary_month',
            'notes', 'reference_number', 'status'
        ]);

        $salary->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Salary record updated successfully',
            'data' => $salary->load('employee')
        ]);
    }

    /**
     * Delete salary record.
     *
     * @param int $employeeId
     * @param int $salaryId
     * @return JsonResponse
     */
    public function deleteSalary($employeeId, $salaryId): JsonResponse
    {
        $user = Auth::user();
        
        $salary = EmployeeSalary::where('id', $salaryId)
            ->where('employee_id', $employeeId)
            ->where('business_id', $user->business_id)
            ->first();

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
     * Get salary summary for an employee.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function salarySummary(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $employee = User::where('id', $id)
            ->where('business_id', $user->business_id)
            ->where('user_type', 'staff')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $query = EmployeeSalary::where('employee_id', $id)
            ->where('business_id', $user->business_id);

        // Filter by year
        if ($request->has('year')) {
            $query->whereYear('salary_month', $request->year);
        }

        // Calculate totals by type
        $summary = [
            'total_salary' => (clone $query)->where('type', 'salary')->sum('amount'),
            'total_advance' => (clone $query)->where('type', 'advance')->sum('amount'),
            'total_bonus' => (clone $query)->where('type', 'bonus')->sum('amount'),
            'total_deduction' => (clone $query)->where('type', 'deduction')->sum('amount'),
            'total_paid' => (clone $query)->where('status', 'paid')->sum('amount'),
            'total_pending' => (clone $query)->where('status', 'pending')->sum('amount'),
        ];

        $summary['net_amount'] = $summary['total_salary'] + $summary['total_bonus'] - $summary['total_deduction'];

        return response()->json([
            'success' => true,
            'data' => [
                'employee' => $employee,
                'summary' => $summary
            ]
        ]);
    }
}
