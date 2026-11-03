<script setup lang="ts">
import VerifyCertificate from './landing/verify-certificate.vue'

// pcu.org.ps/verify — الرابط المختصر المطبوع تحت QR شهادة العضوية. بيعرض صفحة التحقق نفسها
// (بتقرأ ?serial= من الرابط) بدل إعادة التوجيه، فيضل الرابط قصيراً بشريط العنوان.
// landing/verify-certificate باقية للشهادات الصادرة قبله. المسار الثابت هذا بيتقدّم على [slug].vue.
definePage({ meta: { layout: 'landing', public: true, unauthenticatedOnly: false } })
</script>

<template>
  <VerifyCertificate />
</template>
