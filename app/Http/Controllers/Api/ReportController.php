<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get dashboard analytics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Date range for calculations
        $fromDate = $request->get('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->get('to_date', now()->endOfMonth()->toDateString());

        // Sales analytics
        $salesQuery = SalesOrder::where('business_id', $user->business_id)
            ->whereBetween('order_date', [$fromDate, $toDate]);
        
        $totalSales = $salesQuery->sum('total_amount');
        $totalSalesOrders = $salesQuery->count();
        $pendingSalesAmount = $salesQuery->where('status', 'pending')->sum('total_amount');

        // Purchase analytics
        $purchaseQuery = PurchaseOrder::where('business_id', $user->business_id)
            ->whereBetween('order_date', [$fromDate, $toDate]);
        
        $totalPurchases = $purchaseQuery->sum('total_amount');
        $totalPurchaseOrders = $purchaseQuery->count();
        $pendingPurchaseAmount = $purchaseQuery->where('status', 'pending')->sum('total_amount');

        // Expense analytics
        $totalExpenses = Expense::where('business_id', $user->business_id)
            ->whereBetween('expense_date', [$fromDate, $toDate])
            ->sum('amount');

        // Payment analytics
        $totalPaymentsReceived = Payment::where('business_id', $user->business_id)
            ->where('paymentable_type', 'App\Models\SalesOrder')
            ->where('status', 'clear')
            ->whereBetween('transaction_date', [$fromDate, $toDate])
            ->sum('amount');

        $totalPaymentsMade = Payment::where('business_id', $user->business_id)
            ->where('paymentable_type', 'App\Models\PurchaseOrder')
            ->where('status', 'clear')
            ->whereBetween('transaction_date', [$fromDate, $toDate])
            ->sum('amount');

        // Inventory analytics
        $totalProducts = Product::where('business_id', $user->business_id)->count();
        $lowStockProducts = Product::where('business_id', $user->business_id)
            ->whereRaw('quantity <= low_stock_threshold')
            ->count();

        // Calculate profit/loss
        $grossProfit = $totalSales - $totalPurchases;
        $netProfit = $grossProfit - $totalExpenses;

        // Outstanding amounts
        $outstandingReceivables = SalesOrder::where('business_id', $user->business_id)
            ->whereRaw('paid_amount < total_amount')
            ->sum(DB::raw('total_amount - paid_amount'));

        $outstandingPayables = PurchaseOrder::where('business_id', $user->business_id)
            ->whereRaw('paid_amount < total_amount')
            ->sum(DB::raw('total_amount - paid_amount'));

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => [
                    'total_amount' => $totalSales,
                    'total_orders' => $totalSalesOrders,
                    'pending_amount' => $pendingSalesAmount,
                    'payments_received' => $totalPaymentsReceived,
                    'outstanding_receivables' => $outstandingReceivables
                ],
                'purchases' => [
                    'total_amount' => $totalPurchases,
                    'total_orders' => $totalPurchaseOrders,
                    'pending_amount' => $pendingPurchaseAmount,
                    'payments_made' => $totalPaymentsMade,
                    'outstanding_payables' => $outstandingPayables
                ],
                'expenses' => [
                    'total_amount' => $totalExpenses
                ],
                'inventory' => [
                    'total_products' => $totalProducts,
                    'low_stock_count' => $lowStockProducts
                ],
                'profit_loss' => [
                    'gross_profit' => $grossProfit,
                    'net_profit' => $netProfit,
                    'profit_margin' => $totalSales > 0 ? round(($netProfit / $totalSales) * 100, 2) : 0
                ],
                'cash_flow' => [
                    'cash_in' => $totalPaymentsReceived,
                    'cash_out' => $totalPaymentsMade + $totalExpenses,
                    'net_cash_flow' => $totalPaymentsReceived - ($totalPaymentsMade + $totalExpenses)
                ]
            ]
        ]);
    }

    /**
     * Get profit and loss report.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $fromDate = $request->get('from_date', now()->startOfYear()->toDateString());
        $toDate = $request->get('to_date', now()->endOfYear()->toDateString());

        // Revenue
        $salesRevenue = SalesOrder::where('business_id', $user->business_id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('order_date', [$fromDate, $toDate])
            ->sum('total_amount');

        // Cost of Goods Sold (based on purchase prices of sold items)
        $cogs = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('products', 'sales_order_items.product_id', '=', 'products.id')
            ->where('sales_orders.business_id', $user->business_id)
            ->where('sales_orders.status', '!=', 'cancelled')
            ->whereBetween('sales_orders.order_date', [$fromDate, $toDate])
            ->sum(DB::raw('sales_order_items.quantity * products.purchase_price'));

        $grossProfit = $salesRevenue - $cogs;

        // Operating Expenses
        $expenses = Expense::where('business_id', $user->business_id)
            ->whereBetween('expense_date', [$fromDate, $toDate])
            ->with('expenseCategory')
            ->get();

        $totalExpenses = $expenses->sum('amount');
        $expenseBreakdown = $expenses->groupBy('expenseCategory.name')->map(function ($items) {
            return $items->sum('amount');
        });

        $netProfit = $grossProfit - $totalExpenses;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'revenue' => [
                    'sales_revenue' => $salesRevenue
                ],
                'cost_of_goods_sold' => $cogs,
                'gross_profit' => $grossProfit,
                'gross_profit_margin' => $salesRevenue > 0 ? round(($grossProfit / $salesRevenue) * 100, 2) : 0,
                'operating_expenses' => [
                    'total' => $totalExpenses,
                    'breakdown' => $expenseBreakdown
                ],
                'net_profit' => $netProfit,
                'net_profit_margin' => $salesRevenue > 0 ? round(($netProfit / $salesRevenue) * 100, 2) : 0
            ]
        ]);
    }

    /**
     * Get sales report.
     */
    public function salesReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $fromDate = $request->get('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->get('to_date', now()->endOfMonth()->toDateString());

        $salesQuery = SalesOrder::where('business_id', $user->business_id)
            ->whereBetween('order_date', [$fromDate, $toDate]);

        // Overall sales metrics
        $totalSales = $salesQuery->sum('total_amount');
        $totalOrders = $salesQuery->count();
        $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        // Sales by status
        $salesByStatus = $salesQuery->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('status')
            ->get();

        // Top customers
        $topCustomers = $salesQuery->join('users', 'sales_orders.customer_id', '=', 'users.id')
            ->selectRaw('users.name, COUNT(sales_orders.id) as order_count, SUM(sales_orders.total_amount) as total_amount')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        // Top selling products
        $topProducts = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('products', 'sales_order_items.product_id', '=', 'products.id')
            ->where('sales_orders.business_id', $user->business_id)
            ->whereBetween('sales_orders.order_date', [$fromDate, $toDate])
            ->selectRaw('products.name, SUM(sales_order_items.quantity) as total_quantity, SUM(sales_order_items.total_price) as total_revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Daily sales trend
        $dailySales = $salesQuery->selectRaw('DATE(order_date) as date, COUNT(*) as orders, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'overview' => [
                    'total_sales' => $totalSales,
                    'total_orders' => $totalOrders,
                    'average_order_value' => round($averageOrderValue, 2)
                ],
                'sales_by_status' => $salesByStatus,
                'top_customers' => $topCustomers,
                'top_products' => $topProducts,
                'daily_trend' => $dailySales
            ]
        ]);
    }

    /**
     * Get inventory report.
     */
    public function inventoryReport(): JsonResponse
    {
        $user = Auth::user();
        
        $products = Product::where('business_id', $user->business_id)
            ->with(['category', 'brand', 'unit'])
            ->get();

        $totalProducts = $products->count();
        $totalStock = $products->sum('quantity');
        $lowStockProducts = $products->filter(function ($product) {
            return $product->quantity <= $product->low_stock_threshold;
        });

        $stockByCategory = $products->groupBy('category.name')->map(function ($items) {
            return [
                'product_count' => $items->count(),
                'total_quantity' => $items->sum('quantity'),
                'total_value' => $items->sum(function ($item) {
                    return $item->quantity * $item->purchase_price;
                })
            ];
        });

        $zeroStockProducts = $products->where('quantity', 0);
        $overStockProducts = $products->where('quantity', '>', 100); // Configurable threshold

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_products' => $totalProducts,
                    'total_stock_quantity' => $totalStock,
                    'low_stock_count' => $lowStockProducts->count(),
                    'zero_stock_count' => $zeroStockProducts->count(),
                    'overstock_count' => $overStockProducts->count()
                ],
                'stock_by_category' => $stockByCategory,
                'low_stock_products' => $lowStockProducts->values(),
                'zero_stock_products' => $zeroStockProducts->values(),
                'top_value_products' => $products->sortByDesc(function ($product) {
                    return $product->quantity * $product->selling_price;
                })->take(10)->values()
            ]
        ]);
    }

    /**
     * Get customer balance report.
     */
    public function customerBalances(): JsonResponse
    {
        $user = Auth::user();
        
        $customers = User::where('business_id', $user->business_id)
            ->whereIn('user_type', ['retailer', 'dealer', 'wholesaler', 'guest'])
            ->with(['customerSalesOrders' => function ($query) {
                $query->selectRaw('customer_id, SUM(total_amount) as total_sales, SUM(paid_amount) as total_paid')
                    ->groupBy('customer_id');
            }])
            ->get();

        $customerBalances = $customers->map(function ($customer) {
            $totalSales = $customer->customerSalesOrders->sum('total_sales');
            $totalPaid = $customer->customerSalesOrders->sum('total_paid');
            $outstandingAmount = $totalSales - $totalPaid;

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'user_type' => $customer->user_type,
                'party_type' => $customer->party_type,
                'total_sales' => $totalSales,
                'total_paid' => $totalPaid,
                'outstanding_amount' => $outstandingAmount,
                'current_balance' => $customer->current_balance
            ];
        });

        $totalOutstanding = $customerBalances->sum('outstanding_amount');
        $totalOverdue = $customerBalances->where('outstanding_amount', '>', 0)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_customers' => $customers->count(),
                    'total_outstanding' => $totalOutstanding,
                    'customers_with_outstanding' => $totalOverdue
                ],
                'customer_balances' => $customerBalances
            ]
        ]);
    }

    /**
     * Get comprehensive business analytics.
     */
    public function businessAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $fromDate = $request->get('from_date', now()->subMonths(12)->startOfMonth()->toDateString());
        $toDate = $request->get('to_date', now()->endOfMonth()->toDateString());

        // Monthly trends
        $monthlyData = [];
        $current = now()->parse($fromDate)->startOfMonth();
        $end = now()->parse($toDate)->endOfMonth();

        while ($current <= $end) {
            $monthStart = $current->toDateString();
            $monthEnd = $current->endOfMonth()->toDateString();

            $monthlySales = SalesOrder::where('business_id', $user->business_id)
                ->whereBetween('order_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $monthlyExpenses = Expense::where('business_id', $user->business_id)
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $monthlyData[] = [
                'month' => $current->format('Y-m'),
                'sales' => $monthlySales,
                'expenses' => $monthlyExpenses,
                'profit' => $monthlySales - $monthlyExpenses
            ];

            $current->addMonth();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'monthly_trends' => $monthlyData
            ]
        ]);
    }
}
