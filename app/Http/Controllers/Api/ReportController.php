<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\InventoryHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get transaction summary with date range support.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dailyTransaction(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Support both single date and date range
        $startDate = $request->input('start_date', $request->input('date', now()->toDateString()));
        $endDate = $request->input('end_date', $startDate);
        
        // Validate date format
        try {
            $startDate = Carbon::parse($startDate)->toDateString();
            $endDate = Carbon::parse($endDate)->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Please use YYYY-MM-DD format.'
            ], 400);
        }
        
        // Ensure start date is not after end date
        if ($startDate > $endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Start date cannot be after end date.'
            ], 400);
        }
        
        // Get all sales orders for the date range
        $salesOrders = SalesOrder::where('business_id', $user->business_id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->get();
        
        $salesTotal = $salesOrders->sum('total_amount');
        
        // Get all payments received for the date range
        $paymentsReceived = Payment::where('business_id', $user->business_id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereHasMorph('paymentable', [SalesOrder::class])
            ->where('status', 'clear')
            ->sum('amount');
        
        // Get all payments made for the date range
        $paymentsMade = Payment::where('business_id', $user->business_id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereNotIn('paymentable_type', [SalesOrder::class])
            ->where('status', 'clear')
            ->sum('amount');
        
        // Get all expenses for the date range
        $expenses = Expense::where('business_id', $user->business_id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');
        
        // Calculate totals
        $totalIncome = $paymentsReceived;
        $totalExpense = $expenses + $paymentsMade;
        $netAmount = $totalIncome - $totalExpense;
        
        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $startDate === $endDate ? 'Daily' : 'Range',
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
     * Get detailed income and expense report with date range support.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dailyIncomeExpense(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Support both single date and date range
        $startDate = $request->input('start_date', $request->input('date', now()->toDateString()));
        $endDate = $request->input('end_date', $startDate);
        
        // Validate date format
        try {
            $startDate = Carbon::parse($startDate)->toDateString();
            $endDate = Carbon::parse($endDate)->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Please use YYYY-MM-DD format.'
            ], 400);
        }
        
        // Ensure start date is not after end date
        if ($startDate > $endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Start date cannot be after end date.'
            ], 400);
        }
        
        // Get all sales orders for the date range
        $salesOrders = SalesOrder::where('business_id', $user->business_id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['customer:id,name,email,phone', 'payments' => function($query) {
                $query->where('status', 'clear');
            }])
            ->get();
        
        // Get all expenses for the date range with categories
        $expenses = Expense::where('business_id', $user->business_id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with('expenseCategory:id,name')
            ->get();
        
        // Get all payments for the date range
        $payments = Payment::where('business_id', $user->business_id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
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
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $startDate === $endDate ? 'Daily' : 'Range',
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

    /**
     * Get sales report with date range support.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function salesReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Support date range
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        // Validate date format
        try {
            $startDate = Carbon::parse($startDate)->toDateString();
            $endDate = Carbon::parse($endDate)->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Please use YYYY-MM-DD format.'
            ], 400);
        }
        
        // Get sales orders with detailed information
        $salesOrders = SalesOrder::where('business_id', $user->business_id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['customer:id,name,email,phone', 'items.product:id,name,sku', 'payments'])
            ->get();
        
        // Calculate summary
        $summary = [
            'total_orders' => $salesOrders->count(),
            'total_revenue' => $salesOrders->sum('total_amount'),
            'total_paid' => $salesOrders->sum('paid_amount'),
            'outstanding_amount' => $salesOrders->sum('total_amount') - $salesOrders->sum('paid_amount'),
            'completed_orders' => $salesOrders->where('status', 'completed')->count(),
            'pending_orders' => $salesOrders->where('status', 'pending')->count(),
            'partial_orders' => $salesOrders->where('status', 'partial')->count(),
            'cancelled_orders' => $salesOrders->where('status', 'cancelled')->count(),
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $startDate === $endDate ? 'Daily' : 'Range',
                'summary' => $summary,
                'orders' => $salesOrders
            ]
        ]);
    }

    /**
     * Get purchase report with date range support.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function purchaseReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Support date range
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        // Validate date format
        try {
            $startDate = Carbon::parse($startDate)->toDateString();
            $endDate = Carbon::parse($endDate)->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Please use YYYY-MM-DD format.'
            ], 400);
        }
        
        // Get purchase orders with detailed information
        $purchaseOrders = PurchaseOrder::where('business_id', $user->business_id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['supplier:id,name,email,phone', 'items.product:id,name,sku', 'payments'])
            ->get();
        
        // Calculate summary
        $summary = [
            'total_orders' => $purchaseOrders->count(),
            'total_cost' => $purchaseOrders->sum('total_amount'),
            'total_paid' => $purchaseOrders->sum('paid_amount'),
            'outstanding_amount' => $purchaseOrders->sum('total_amount') - $purchaseOrders->sum('paid_amount'),
            'completed_orders' => $purchaseOrders->where('status', 'completed')->count(),
            'pending_orders' => $purchaseOrders->where('status', 'pending')->count(),
            'partial_orders' => $purchaseOrders->where('status', 'partial')->count(),
            'cancelled_orders' => $purchaseOrders->where('status', 'cancelled')->count(),
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $startDate === $endDate ? 'Daily' : 'Range',
                'summary' => $summary,
                'orders' => $purchaseOrders
            ]
        ]);
    }

    /**
     * Get inventory movement report with date range support.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function inventoryReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Support date range
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        // Validate date format
        try {
            $startDate = Carbon::parse($startDate)->toDateString();
            $endDate = Carbon::parse($endDate)->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Please use YYYY-MM-DD format.'
            ], 400);
        }
        
        // Get inventory history for the date range
        $inventoryHistory = InventoryHistory::where('business_id', $user->business_id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['product:id,name,sku,quantity', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Calculate summary
        $stockIn = $inventoryHistory->where('type', 'stock-in')->sum('quantity_changed');
        $stockOut = $inventoryHistory->where('type', 'stock-out')->sum('quantity_changed');
        $adjustments = $inventoryHistory->where('type', 'adjustment')->sum('quantity_changed');
        
        $summary = [
            'total_movements' => $inventoryHistory->count(),
            'stock_in' => abs($stockIn),
            'stock_out' => abs($stockOut),
            'adjustments' => $adjustments,
            'net_change' => $stockIn + $stockOut + $adjustments,
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $startDate === $endDate ? 'Daily' : 'Range',
                'summary' => $summary,
                'movements' => $inventoryHistory
            ]
        ]);
    }

    /**
     * Get profit and loss report with date range support.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profitLossReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Support date range
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        // Validate date format
        try {
            $startDate = Carbon::parse($startDate)->toDateString();
            $endDate = Carbon::parse($endDate)->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Please use YYYY-MM-DD format.'
            ], 400);
        }
        
        // Revenue from sales
        $salesRevenue = SalesOrder::where('business_id', $user->business_id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
        
        // Cost of goods sold (from purchase orders)
        $costOfGoodsSold = PurchaseOrder::where('business_id', $user->business_id)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
        
        // Operating expenses
        $operatingExpenses = Expense::where('business_id', $user->business_id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');
        
        // Calculate profit/loss
        $grossProfit = $salesRevenue - $costOfGoodsSold;
        $netProfit = $grossProfit - $operatingExpenses;
        $profitMargin = $salesRevenue > 0 ? ($netProfit / $salesRevenue) * 100 : 0;
        
        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $startDate === $endDate ? 'Daily' : 'Range',
                'revenue' => [
                    'sales_revenue' => $salesRevenue,
                    'total_revenue' => $salesRevenue
                ],
                'costs' => [
                    'cost_of_goods_sold' => $costOfGoodsSold,
                    'operating_expenses' => $operatingExpenses,
                    'total_costs' => $costOfGoodsSold + $operatingExpenses
                ],
                'profitability' => [
                    'gross_profit' => $grossProfit,
                    'net_profit' => $netProfit,
                    'profit_margin_percentage' => round($profitMargin, 2)
                ]
            ]
        ]);
    }
}
