<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 13px;
    color: #222;
    direction: rtl;
    padding: 30px 35px;
    background: #fff;
  }
  .header {
    border-bottom: 3px solid #1565C0;
    padding-bottom: 14px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
  }
  .header h1 { font-size: 20px; color: #1565C0; font-weight: bold; }
  .header .meta { font-size: 11px; color: #666; text-align: left; }
  .period-badge {
    display: inline-block;
    background: #E3F2FD;
    color: #1565C0;
    border: 1px solid #90CAF9;
    border-radius: 4px;
    padding: 3px 10px;
    font-size: 12px;
    margin-bottom: 18px;
  }
  .kpi-grid {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
  }
  .kpi-grid td {
    width: 25%;
    padding: 12px;
    text-align: center;
    border: 1px solid #E0E0E0;
    background: #F9FAFB;
  }
  .kpi-grid .kpi-value {
    font-size: 20px;
    font-weight: bold;
    color: #1565C0;
    display: block;
    margin-bottom: 4px;
  }
  .kpi-grid .kpi-label {
    font-size: 11px;
    color: #555;
  }
  .section-title {
    font-size: 14px;
    font-weight: bold;
    color: #1565C0;
    border-right: 4px solid #1565C0;
    padding-right: 10px;
    margin: 20px 0 10px;
  }
  .data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
  }
  .data-table th {
    background: #1565C0;
    color: #fff;
    padding: 8px 12px;
    font-size: 12px;
    text-align: right;
  }
  .data-table td {
    padding: 8px 12px;
    font-size: 12px;
    border-bottom: 1px solid #EEEEEE;
    text-align: right;
  }
  .data-table tr:nth-child(even) td { background: #F5F5F5; }
  .amount { color: #1B5E20; font-weight: bold; }
  .badge-green { background: #E8F5E9; color: #2E7D32; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
  .badge-red   { background: #FFEBEE; color: #C62828; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
  .footer {
    border-top: 1px solid #E0E0E0;
    margin-top: 30px;
    padding-top: 10px;
    font-size: 10px;
    color: #999;
    text-align: center;
  }
</style>
</head>
<body>

<!-- Header -->
<div class="header">
  <div>
    <h1>اتحاد المقاولين الفلسطينيين — تقرير إحصائي</h1>
    <div style="font-size:12px;color:#555;margin-top:4px;">
      @if($period === 'monthly')
        {{ $month_name }} {{ $year }}
      @else
        السنة {{ $year }}
      @endif
    </div>
  </div>
  <div class="meta">
    تاريخ الإنشاء: {{ $generated_at }}<br>
    النوع: {{ $period === 'monthly' ? 'تقرير شهري' : 'تقرير سنوي' }}
  </div>
</div>

<!-- KPI Cards -->
<table class="kpi-grid">
  <tr>
    <td>
      <span class="kpi-value">{{ number_format($revenue, 0) }} ₪</span>
      <span class="kpi-label">إجمالي الإيرادات</span>
    </td>
    <td>
      <span class="kpi-value">{{ $newContractors }}</span>
      <span class="kpi-label">مقاولون جدد</span>
    </td>
    <td>
      <span class="kpi-value">{{ $activeMemberships }}</span>
      <span class="kpi-label">عضويات نشطة</span>
    </td>
    <td>
      <span class="kpi-value">{{ $tenders }}</span>
      <span class="kpi-label">مناقصات</span>
    </td>
  </tr>
</table>

<!-- Penalties -->
<div class="section-title">الغرامات والمخالفات</div>
<table class="data-table">
  <thead>
    <tr>
      <th>البيان</th>
      <th>القيمة</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>إجمالي الغرامات الصادرة</td>
      <td>{{ $penaltiesIssued }} غرامة</td>
    </tr>
    <tr>
      <td>إجمالي قيمة الغرامات</td>
      <td class="amount">{{ number_format($penaltiesAmount, 2) }} ₪</td>
    </tr>
    <tr>
      <td>الغرامات المدفوعة</td>
      <td><span class="badge-green">{{ number_format($penaltiesPaid, 2) }} ₪</span></td>
    </tr>
    <tr>
      <td>الغرامات المعلقة</td>
      <td><span class="badge-red">{{ number_format($penaltiesAmount - $penaltiesPaid, 2) }} ₪</span></td>
    </tr>
  </tbody>
</table>

<!-- Payment Types -->
@if(!empty($paymentTypes))
<div class="section-title">توزيع أنواع المدفوعات</div>
<table class="data-table">
  <thead>
    <tr>
      <th>نوع الدفع</th>
      <th>الإجمالي</th>
    </tr>
  </thead>
  <tbody>
    @foreach($paymentTypes as $type => $total)
    <tr>
      <td>
        @php
          $typeLabels = [
            'membership_fee' => 'رسوم عضوية',
            'renewal_fee'    => 'رسوم تجديد',
            'other'          => 'أخرى',
          ];
        @endphp
        {{ $typeLabels[$type] ?? $type }}
      </td>
      <td class="amount">{{ number_format($total, 2) }} ₪</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

<!-- Revenue Chart Data (Monthly breakdown) -->
<div class="section-title">الإيرادات الشهرية</div>
<table class="data-table">
  <thead>
    <tr>
      @foreach(['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'] as $mn)
        <th style="font-size:10px;">{{ $mn }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    <tr>
      @foreach($revenueChart as $val)
        <td style="font-size:11px;text-align:center;" class="amount">{{ number_format($val, 0) }}</td>
      @endforeach
    </tr>
  </tbody>
</table>

<div class="footer">
  تم إنشاء هذا التقرير تلقائياً بواسطة نظام اتحاد المقاولين الفلسطينيين — {{ $generated_at }}
</div>

</body>
</html>
