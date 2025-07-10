<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get daily transaction summary.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dailyTransaction(Request $request): JsonResponse
    {
        $user = Auth::user();
        $date = $request->input('date', now()->toDateString());
        
        // Get all sales orders for the day
        $salesOrders = SalesOrder::where('business_id', $user->business_id)
            ->whereDate('created_at', $date)
            ->get();
        
        $salesTotal = $salesOrders->sum('total_amount');
        
        // Get all payments received for the day
        $paymentsReceived = Payment::where('business_id', $user->business_id)
            ->whereDate('transaction_date', $date)
            ->whereHasMorph('paymentable', [SalesOrder::class])
            ->where('status', 'clear')
            ->sum('amount');
        
        // Get all payments made for the day
        $paymentsMade = Payment::where('business_id', $user->business_id)
            ->whereDate('transaction_date', $date)
            ->whereNotIn('paymentable_type', [SalesOrder::class])
            ->where('status', 'clear')
            ->sum('amount');
        
        // Get all expenses for the day
        $expenses = Expense::where('business_id', $user->business_id)
            ->whereDate('expense_date', $date)
            ->sum('amount');
        
        // Calculate totals
        $totalIncome = $paymentsReceived;
        $totalExpense = $expenses + $paymentsMade;
        $netAmount = $totalIncome - $totalExpense;
        
        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'income' => [
                    'sales_orders_total' => $salesTotal,
                    'payments_received' => $paymentsReceived,
                    'total_income' => $totalIncome
                ],
                'expense' => [
                    'expenses_total' => $expenses,
                    'payments_made' => $paymentsMade,
                    'total_expense' => $totalExpense
                ],
                'net_amount' => $netAmount
            ]
        ]);
    }
    
    /**
     * Get daily income and expense detailed report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dailyIncomeExpense(Request $request): JsonResponse
    {
        $user = Auth::user();
        $date = $request->input('date', now()->toDateString());
        
        // Get all sales orders for the day
        $salesOrders = SalesOrder::where('business_id', $user->business_id)
            ->whereDate('created_at', $date)
            ->with(['customer:id,name,email,phone', 'payments' => function($query) {
                $query->where('status', 'clear');
            }])
            ->get();
        
        // Get all expenses for the day with categories
        $expenses = Expense::where('business_id', $user->business_id)
            ->whereDate('expense_date', $date)
            ->with('expenseCategory:id,name')
            ->get();
        
        // Get all payments for the day
        $payments = Payment::where('business_id', $user->business_id)
            ->whereDate('transaction_date', $date)
            ->with(['paymentMethod', 'paymentable'])
            ->get();
        
        // Separate payments into received and made
        $paymentsReceived = $payments->filter(function($payment) {
            return $payment->paymentable_type === 'App\\Models\\SalesOrder' && $payment->status === 'clear';
        });
        
        $paymentsMade = $payments->filter(function($payment) {
            return $payment->paymentable_type !== 'App\\Models\\SalesOrder' && $payment->status === 'clear';
        });
        
        // Calculate summary
        $summary = [
            'sales_total' => $salesOrders->sum('total_amount'),
            'payments_received_total' => $paymentsReceived->sum('amount'),
            'payments_made_total' => $paymentsMade->sum('amount'),
            'expenses_total' => $expenses->sum('amount'),
            'income_total' => $paymentsReceived->sum('amount'),
            'expense_total' => $expenses->sum('amount') + $paymentsMade->sum('amount'),
            'net_amount' => $paymentsReceived->sum('amount') - ($expenses->sum('amount') + $paymentsMade->sum('amount'))
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'summary' => $summary,
                'details' => [
                    'sales_orders' => $salesOrders,
                    'payments_received' => $paymentsReceived,
                    'payments_made' => $paymentsMade,
                    'expenses' => $expenses
                ]
            ]
        ]);
    }
}
