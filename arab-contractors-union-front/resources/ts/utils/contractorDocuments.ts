// الاسم الرسمي لكل مستند من مستندات المقاول — مصدر واحد لعنوان حقل الرفع (تسجيل/تعديل)
// ولاسم المرفق في نافذة المعاينة. كانت الأسماء مكرّرة بصيغ مختلفة في كل صفحة
// ("عقد المقر" مقابل "عقد الإيجار أو الملكية لمقر الشركة")، فلا يطابق المرفق المعروض
// الحقل الذي رُفع منه. نسخة الخادم: Contractor::DOCUMENT_LABELS — أي تعديل يُعدَّل بالمكانين.
export const contractorDocumentLabels = {
  cr_file: 'السجل التجاري',
  id_file: 'صورة الهوية',
  company_register: 'مستخرج عن سجل الشركة',
  municipal_license: 'رخصة المهن (الحرف) سارية المفعول',
  bank_dealing_letter: 'شهادة تعامل للشركة مع بنك',
  articles_of_association: 'عقد تأسيس الشركة',
  internal_bylaws: 'النظام الداخلي',
  lease_or_ownership_contract: 'عقد الإيجار أو الملكية لمقر الشركة',
  partners_ids: 'صور هويات الشركاء',
  authorization_letter: 'كتاب تفويض المعتمد بالتوقيع',
  company_approval_letter: 'كتاب من الشركة بالموافقة على الانتساب',
  full_time_engineer_certificate: 'شهادة مهندس متفرغ',
  accountant_certificate_or_contract: 'شهادة تفرغ محاسب من نقابة المحاسبين / أو عقد مع مكتب محاسبين معتمد',
  secretary_contract: 'عقد سكرتير',
} as const

export type ContractorDocumentKey = keyof typeof contractorDocumentLabels
