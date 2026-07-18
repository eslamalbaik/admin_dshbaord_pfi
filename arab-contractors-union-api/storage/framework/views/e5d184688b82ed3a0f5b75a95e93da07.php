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
    <h1>اتحاد المقاولين العرب — تقرير إحصائي</h1>
    <div style="font-size:12px;color:#555;margin-top:4px;">
      <?php if($period === 'monthly'): ?>
        <?php echo e($month_name); ?> <?php echo e($year); ?>

      <?php else: ?>
        السنة <?php echo e($year); ?>

      <?php endif; ?>
    </div>
  </div>
  <div class="meta">
    تاريخ الإنشاء: <?php echo e($generated_at); ?><br>
    النوع: <?php echo e($period === 'monthly' ? 'تقرير شهري' : 'تقرير سنوي'); ?>

  </div>
</div>

<!-- KPI Cards -->
<table class="kpi-grid">
  <tr>
    <td>
      <span class="kpi-value"><?php echo e(number_format($revenue, 0)); ?> ₪</span>
      <span class="kpi-label">إجمالي الإيرادات</span>
    </td>
    <td>
      <span class="kpi-value"><?php echo e($newContractors); ?></span>
      <span class="kpi-label">مقاولون جدد</span>
    </td>
    <td>
      <span class="kpi-value"><?php echo e($activeMemberships); ?></span>
      <span class="kpi-label">عضويات نشطة</span>
    </td>
    <td>
      <span class="kpi-value"><?php echo e($tenders); ?></span>
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
      <td><?php echo e($penaltiesIssued); ?> غرامة</td>
    </tr>
    <tr>
      <td>إجمالي قيمة الغرامات</td>
      <td class="amount"><?php echo e(number_format($penaltiesAmount, 2)); ?> ₪</td>
    </tr>
    <tr>
      <td>الغرامات المدفوعة</td>
      <td><span class="badge-green"><?php echo e(number_format($penaltiesPaid, 2)); ?> ₪</span></td>
    </tr>
    <tr>
      <td>الغرامات المعلقة</td>
      <td><span class="badge-red"><?php echo e(number_format($penaltiesAmount - $penaltiesPaid, 2)); ?> ₪</span></td>
    </tr>
  </tbody>
</table>

<!-- Payment Types -->
<?php if(!empty($paymentTypes)): ?>
<div class="section-title">توزيع أنواع المدفوعات</div>
<table class="data-table">
  <thead>
    <tr>
      <th>نوع الدفع</th>
      <th>الإجمالي</th>
    </tr>
  </thead>
  <tbody>
    <?php $__currentLoopData = $paymentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $total): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
      <td>
        <?php
          $typeLabels = [
            'membership_fee' => 'رسوم عضوية',
            'renewal_fee'    => 'رسوم تجديد',
            'other'          => 'أخرى',
          ];
        ?>
        <?php echo e($typeLabels[$type] ?? $type); ?>

      </td>
      <td class="amount"><?php echo e(number_format($total, 2)); ?> ₪</td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </tbody>
</table>
<?php endif; ?>

<!-- Revenue Chart Data (Monthly breakdown) -->
<div class="section-title">الإيرادات الشهرية</div>
<table class="data-table">
  <thead>
    <tr>
      <?php $__currentLoopData = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <th style="font-size:10px;"><?php echo e($mn); ?></th>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tr>
  </thead>
  <tbody>
    <tr>
      <?php $__currentLoopData = $revenueChart; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <td style="font-size:11px;text-align:center;" class="amount"><?php echo e(number_format($val, 0)); ?></td>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tr>
  </tbody>
</table>

<div class="footer">
  تم إنشاء هذا التقرير تلقائياً بواسطة نظام اتحاد المقاولين العرب — <?php echo e($generated_at); ?>

</div>

</body>
</html>
<?php /**PATH E:\admin_dshbaord_pfi\arab-contractors-union-api\resources\views/reports/summary.blade.php ENDPATH**/ ?>