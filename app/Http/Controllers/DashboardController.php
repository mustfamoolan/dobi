<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, $page)
    {
        $allowedPages = [
            'apps-calendar',
            'apps-chat',
            'apps-course-details',
            'apps-course-overview',
            'apps-course-student-add',
            'apps-course-student-details',
            'apps-course-student-list',
            'apps-course-teacher-add',
            'apps-course-teacher-details',
            'apps-course-teacher-list',
            'apps-create-invoices',
            'apps-email-list',
            'apps-email-view',
            'apps-file-manager',
            'apps-invoices-details',
            'apps-invoices-list',
            'apps-invoices-print',
            'apps-product-cart',
            'apps-product-category-list',
            'apps-product-checkout',
            'apps-product-create',
            'apps-product-details',
            'apps-product-order-details',
            'apps-product-order-list',
            'apps-products',
            'apps-products-list',
            'apps-projects-create',
            'apps-projects-list',
            'apps-projects-overview',
            'apps-social-activity',
            'apps-social-events',
            'apps-social-feeds',
            'apps-social-friends',
            'apps-social-watch-video',
            'apps-tasks-kanban',
            'apps-todo',
            'auth-401',
            'auth-404',
            'auth-500',
            'auth-coming-soon',
            'auth-create-password',
            'auth-lockscreen',
            'auth-offline',
            'auth-reset-password',
            'auth-signin',
            'auth-signup',
            'auth-two-step-verify',
            'chart-apex-line',
            'chart-js-chart',
            'charts-apex-area',
            'charts-apex-bar',
            'charts-apex-boxplot',
            'charts-apex-bubble',
            'charts-apex-candlestick',
            'charts-apex-column',
            'charts-apex-funnel',
            'charts-apex-heatmap',
            'charts-apex-mixed',
            'charts-apex-pie',
            'charts-apex-polar',
            'charts-apex-radar',
            'charts-apex-radialbar',
            'charts-apex-range-area',
            'charts-apex-scatter',
            'charts-apex-slope',
            'charts-apex-timeline',
            'charts-apex-treemap',
            'charts-echarts',
            'dashboard-online-course',
            'dashboard-project-management',
            'dashboard-social-media',
            'demo-layout-compact',
            'demo-layout-horizontal',
            'demo-layout-semibox',
            'demo-layout-small-icon',
            'demo-layout-two-column',
            'google-maps',
            'icons-bootstrap-icons',
            'icons-remix',
            'index',
            'leaflet-maps',
            'pages-blog-create',
            'pages-blog-details',
            'pages-blog-list',
            'pages-faqs',
            'pages-gallery',
            'pages-pricing',
            'pages-privacy-policy',
            'pages-profile-connections',
            'pages-profile-documents',
            'pages-profile-edit-billing-plans',
            'pages-profile-edit-connections',
            'pages-profile-edit-notifications',
            'pages-profile-edit-overview',
            'pages-profile-edit-security',
            'pages-profile-overview',
            'pages-profile-project',
            'pages-search-result',
            'pages-starter',
            'pages-terms-conditions',
            'pages-timeline',
            'ui-accordions',
            'ui-advance-swiper',
            'ui-alert',
            'ui-avatars',
            'ui-badges',
            'ui-block',
            'ui-breadcrumbs',
            'ui-button-group',
            'ui-buttons',
            'ui-card',
            'ui-carousel',
            'ui-cookie',
            'ui-countup',
            'ui-date-picker',
            'ui-draggable-cards',
            'ui-dropdowns',
            'ui-floating-labels',
            'ui-form-advanced',
            'ui-form-checkboxs-radios',
            'ui-form-editor',
            'ui-form-elements',
            'ui-form-file-uploads',
            'ui-form-input-group',
            'ui-form-input-masks',
            'ui-form-layout',
            'ui-form-range',
            'ui-form-select',
            'ui-form-validation',
            'ui-form-wizards',
            'ui-images-figures',
            'ui-links',
            'ui-list',
            'ui-media-player',
            'ui-modal',
            'ui-offcanvas',
            'ui-pagination',
            'ui-placeholders',
            'ui-popover',
            'ui-progress',
            'ui-ratings',
            'ui-scrollspy',
            'ui-separator',
            'ui-sweetalert2',
            'ui-spinner',
            'ui-tables-basic',
            'ui-tables-datatables',
            'ui-tables-gridjs',
            'ui-tables-listjs',
            'ui-tabs',
            'ui-tooltips',
            'ui-toast',
            'ui-tooltips',
            'ui-sortable-js',
            'auth-offine',
            'ui-tour',
            'ui-treeview',
            'ui-typography',
            'ui-utilities',
            'under-maintenance',
            'vector-maps'
        ];

        if (in_array($page, $allowedPages) && view()->exists($page)) {
            if ($page === 'index') {
                $fromDate = $request->query('from_date');
                $toDate = $request->query('to_date');
                $filterType = $request->query('filter', 'all');

                if ($filterType === 'today') {
                    $fromDate = now()->startOfDay()->format('Y-m-d');
                    $toDate = now()->endOfDay()->format('Y-m-d');
                } elseif ($filterType === 'week') {
                    $fromDate = now()->startOfWeek()->format('Y-m-d');
                    $toDate = now()->endOfDay()->format('Y-m-d');
                } elseif ($filterType === 'month') {
                    $fromDate = now()->startOfMonth()->format('Y-m-d');
                    $toDate = now()->endOfDay()->format('Y-m-d');
                } elseif ($filterType === 'year') {
                    $fromDate = now()->startOfYear()->format('Y-m-d');
                    $toDate = now()->endOfDay()->format('Y-m-d');
                }

                $salesQuery = \App\Models\Sale::query();
                $purchasesQuery = \App\Models\Purchase::query();
                $vouchersQuery = \App\Models\Voucher::query();
                $customerLedgerQuery = \App\Models\CustomerLedger::query();
                $supplierLedgerQuery = \App\Models\SupplierLedger::query();

                if ($fromDate && $toDate) {
                    $salesQuery->whereBetween('date', [$fromDate, $toDate]);
                    $purchasesQuery->whereBetween('date', [$fromDate, $toDate]);
                    $vouchersQuery->whereBetween('date', [$fromDate, $toDate]);
                    $customerLedgerQuery->whereBetween('date', [$fromDate, $toDate]);
                    $supplierLedgerQuery->whereBetween('date', [$fromDate, $toDate]);
                }

                $todayDate = now()->format('Y-m-d');
                // Receivables (What customers owe us) - Cumulative up to toDate
                $recQuery = \App\Models\CustomerLedger::query();
                if ($toDate) {
                    $recQuery->where('date', '<=', $toDate);
                }
                $total_receivables_iqd = (clone $recQuery)->where('currency', 'IQD')->selectRaw('SUM(debit) - SUM(credit) as balance')->first()->balance ?? 0;
                $total_receivables_usd = (clone $recQuery)->where('currency', 'USD')->selectRaw('SUM(debit) - SUM(credit) as balance')->first()->balance ?? 0;
                
                // Payables (What we owe suppliers) - Cumulative up to toDate
                $payQuery = \App\Models\SupplierLedger::query();
                if ($toDate) {
                    $payQuery->where('date', '<=', $toDate);
                }
                $total_payables_iqd = (clone $payQuery)->where('currency', 'IQD')->selectRaw('SUM(credit) - SUM(debit) as balance')->first()->balance ?? 0;
                $total_payables_usd = (clone $payQuery)->where('currency', 'USD')->selectRaw('SUM(credit) - SUM(debit) as balance')->first()->balance ?? 0;

                $stats = [
                    'total_sales_iqd' => (clone $salesQuery)->where('currency', 'IQD')->sum('grand_total'),
                    'total_sales_usd' => (clone $salesQuery)->where('currency', 'USD')->sum('grand_total'),
                    'total_purchases_iqd' => (clone $purchasesQuery)->where('currency', 'IQD')->sum('grand_total'),
                    'total_purchases_usd' => (clone $purchasesQuery)->where('currency', 'USD')->sum('grand_total'),
                    'customers_count' => \App\Models\Customer::count(),
                    'products_count' => \App\Models\Product::count(),
                    'recent_sales' => (clone $salesQuery)->with('customer')->latest()->take(5)->get(),
                    'recent_vouchers' => (clone $vouchersQuery)->latest()->take(5)->get(),
                    'treasury_total' => \App\Models\FinancialAccount::where('is_active', true)->where(function($q) {
                        $q->where('currency', 'IQD')->orWhereNull('currency');
                    })->sum('current_balance'),
                    'treasury_total_usd' => \App\Models\FinancialAccount::where('is_active', true)->where('currency', 'USD')->sum('current_balance'),
                    
                    'total_receivables_iqd' => $total_receivables_iqd,
                    'total_receivables_usd' => $total_receivables_usd,
                    'total_payables_iqd' => $total_payables_iqd,
                    'total_payables_usd' => $total_payables_usd,
                    
                    // Sales Today
                    'sales_today' => \App\Models\Sale::where('date', $todayDate)->sum('grand_total'),
                    
                    // Quick Activity
                    'low_stock_products' => \App\Models\Product::whereHas('stockMovements', function($q) {
                        $q->select('product_id')
                          ->groupBy('product_id')
                          ->havingRaw('SUM(qty_in) - SUM(qty_out) <= 5');
                    })->count(),
                    
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'filterType' => $filterType,
                ];
                return view($page, $stats);
            }
            return view($page);
        }

        abort(404);
    }
}
