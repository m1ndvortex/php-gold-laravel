<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Vazirmatn', 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            direction: rtl;
            text-align: right;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .company-details h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .company-details p {
            margin-bottom: 3px;
            color: #6b7280;
        }
        
        .logo {
            max-width: 120px;
            max-height: 80px;
        }
        
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .invoice-details, .customer-details {
            width: 48%;
        }
        
        .invoice-details h2, .customer-details h2 {
            font-size: 16px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: 500;
            color: #6b7280;
        }
        
        .info-value {
            font-weight: 400;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 11px;
        }
        
        .items-table th {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 8px 6px;
            font-weight: 600;
            text-align: center;
        }
        
        .items-table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: center;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-left {
            text-align: left;
        }
        
        .totals-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .gold-summary, .financial-summary {
            width: 48%;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-table th {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 8px;
            font-weight: 600;
            text-align: right;
        }
        
        .summary-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
        }
        
        .total-row {
            background-color: #fef3c7;
            font-weight: 600;
        }
        
        .payments-section {
            margin-bottom: 30px;
        }
        
        .payments-section h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 10px;
        }
        
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        
        .payments-table th {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 6px;
            font-weight: 600;
            text-align: center;
        }
        
        .payments-table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: center;
        }
        
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            margin-top: 30px;
        }
        
        .terms {
            margin-bottom: 15px;
        }
        
        .terms h4 {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .terms p {
            font-size: 10px;
            line-height: 1.3;
            color: #6b7280;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            width: 200px;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #6b7280;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 10px;
            color: #6b7280;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-draft { background-color: #f3f4f6; color: #6b7280; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-paid { background-color: #d1fae5; color: #065f46; }
        .status-partial { background-color: #dbeafe; color: #1e40af; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-cancelled { background-color: #f3f4f6; color: #6b7280; }
        
        .page-break {
            page-break-after: always;
        }
        
        @media print {
            .container {
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <div class="company-info">
                <div class="company-details">
                    <h1>{{ $tenant->name ?? 'نام شرکت' }}</h1>
                    <p>{{ $tenant->address ?? 'آدرس شرکت' }}</p>
                    <p>تلفن: {{ $tenant->phone ?? '021-12345678' }}</p>
                    <p>ایمیل: {{ $tenant->email ?? 'info@company.com' }}</p>
                    @if($tenant->tax_id)
                        <p>شناسه ملی: {{ $tenant->tax_id }}</p>
                    @endif
                </div>
                @if($tenant->logo && $options['include_branding'])
                    <img src="{{ $tenant->logo }}" alt="لوگو" class="logo">
                @endif
            </div>
        </div>

        <!-- Invoice Information -->
        <div class="invoice-info">
            <div class="invoice-details">
                <h2>{{ app(App\Services\InvoicePdfService::class)->getInvoiceTypeDisplay($invoice->type) }}</h2>
                <div class="info-row">
                    <span class="info-label">شماره فاکتور:</span>
                    <span class="info-value">{{ $invoice->invoice_number }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">تاریخ صدور:</span>
                    <span class="info-value">{{ \Morilog\Jalali\Jalalian::fromCarbon($invoice->invoice_date)->format('Y/m/d') }}</span>
                </div>
                @if($invoice->due_date)
                <div class="info-row">
                    <span class="info-label">تاریخ سررسید:</span>
                    <span class="info-value">{{ \Morilog\Jalali\Jalalian::fromCarbon($invoice->due_date)->format('Y/m/d') }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">وضعیت:</span>
                    <span class="status-badge status-{{ $invoice->status }}">
                        @switch($invoice->status)
                            @case('draft') پیش‌نویس @break
                            @case('pending') در انتظار @break
                            @case('paid') پرداخت شده @break
                            @case('partial') پرداخت جزئی @break
                            @case('overdue') سررسید گذشته @break
                            @case('cancelled') لغو شده @break
                            @default {{ $invoice->status }}
                        @endswitch
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">قیمت طلا (گرم):</span>
                    <span class="info-value">{{ app(App\Services\InvoicePdfService::class)->formatCurrency($invoice->gold_price_per_gram) }}</span>
                </div>
            </div>

            <div class="customer-details">
                <h2>اطلاعات مشتری</h2>
                <div class="info-row">
                    <span class="info-label">نام:</span>
                    <span class="info-value">{{ $invoice->customer->name }}</span>
                </div>
                @if($invoice->customer->phone)
                <div class="info-row">
                    <span class="info-label">تلفن:</span>
                    <span class="info-value">{{ $invoice->customer->phone }}</span>
                </div>
                @endif
                @if($invoice->customer->email)
                <div class="info-row">
                    <span class="info-label">ایمیل:</span>
                    <span class="info-value">{{ $invoice->customer->email }}</span>
                </div>
                @endif
                @if($invoice->customer->address)
                <div class="info-row">
                    <span class="info-label">آدرس:</span>
                    <span class="info-value">{{ $invoice->customer->address }}</span>
                </div>
                @endif
                @if($invoice->customer->tax_id)
                <div class="info-row">
                    <span class="info-label">کد ملی:</span>
                    <span class="info-value">{{ $invoice->customer->tax_id }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%">ردیف</th>
                    <th style="width: 25%">نام کالا</th>
                    <th style="width: 10%">کد کالا</th>
                    <th style="width: 8%">تعداد</th>
                    <th style="width: 10%">وزن طلا (گرم)</th>
                    <th style="width: 10%">وزن سنگ (گرم)</th>
                    <th style="width: 12%">قیمت واحد</th>
                    <th style="width: 10%">تخفیف</th>
                    <th style="width: 12%">مبلغ کل</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="text-right">{{ $item->product_name }}</td>
                    <td>{{ $item->product_sku ?? '-' }}</td>
                    <td>{{ app(App\Services\InvoicePdfService::class)->formatPersianNumber($item->quantity, 2) }}</td>
                    <td>{{ app(App\Services\InvoicePdfService::class)->formatPersianNumber($item->gold_weight, 3) }}</td>
                    <td>{{ app(App\Services\InvoicePdfService::class)->formatPersianNumber($item->stone_weight, 3) }}</td>
                    <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($item->unit_price) }}</td>
                    <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($item->discount_amount) }}</td>
                    <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($item->line_total) }}</td>
                </tr>
                @if($item->description)
                <tr>
                    <td></td>
                    <td colspan="8" class="text-right" style="font-size: 10px; color: #6b7280; font-style: italic;">
                        {{ $item->description }}
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="totals-section">
            <div class="gold-summary">
                <h3>خلاصه طلا</h3>
                <table class="summary-table">
                    <tr>
                        <th>مجموع وزن طلا:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatPersianNumber($totals['total_gold_weight'], 3) }} گرم</td>
                    </tr>
                    <tr>
                        <th>مجموع وزن سنگ:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatPersianNumber($totals['total_stone_weight'], 3) }} گرم</td>
                    </tr>
                    <tr>
                        <th>ارزش طلا:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['gold_value']) }}</td>
                    </tr>
                    <tr>
                        <th>هزینه ساخت:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['total_manufacturing_fee']) }}</td>
                    </tr>
                    <tr>
                        <th>سود:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['total_profit_amount']) }}</td>
                    </tr>
                </table>
            </div>

            <div class="financial-summary">
                <h3>خلاصه مالی</h3>
                <table class="summary-table">
                    <tr>
                        <th>جمع کل:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['subtotal']) }}</td>
                    </tr>
                    @if($totals['discount_amount'] > 0)
                    <tr>
                        <th>تخفیف:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['discount_amount']) }}</td>
                    </tr>
                    @endif
                    @if($invoice->vat_percentage > 0)
                    <tr>
                        <th>مالیات ({{ app(App\Services\InvoicePdfService::class)->formatPersianNumber($invoice->vat_percentage, 1) }}%):</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['vat_amount']) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <th>مبلغ نهایی:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['total_amount']) }}</td>
                    </tr>
                    <tr>
                        <th>پرداخت شده:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['paid_amount']) }}</td>
                    </tr>
                    <tr>
                        <th>باقیمانده:</th>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($totals['balance_due']) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payments Section -->
        @if($payment_summary['total_payments'] > 0)
        <div class="payments-section">
            <h3>تاریخچه پرداخت‌ها</h3>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>روش پرداخت</th>
                        <th>مبلغ</th>
                        <th>شماره مرجع</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments()->completed()->get() as $payment)
                    <tr>
                        <td>{{ \Morilog\Jalali\Jalalian::fromCarbon($payment->payment_date)->format('Y/m/d') }}</td>
                        <td>{{ app(App\Services\InvoicePdfService::class)->getPaymentMethodDisplay($payment->payment_method) }}</td>
                        <td>{{ app(App\Services\InvoicePdfService::class)->formatCurrency($payment->amount) }}</td>
                        <td>{{ $payment->reference_number ?? '-' }}</td>
                        <td>تکمیل شده</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            @if($invoice->terms_conditions && $options['include_terms'])
            <div class="terms">
                <h4>شرایط و ضوابط:</h4>
                <p>{{ $invoice->terms_conditions }}</p>
            </div>
            @endif

            @if($invoice->notes)
            <div class="terms">
                <h4>یادداشت:</h4>
                <p>{{ $invoice->notes }}</p>
            </div>
            @endif

            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">امضای مشتری</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">امضای فروشنده</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px; font-size: 10px; color: #6b7280;">
                تاریخ چاپ: {{ \Morilog\Jalali\Jalalian::now()->format('Y/m/d H:i') }}
            </div>
        </div>
    </div>
</body>
</html>