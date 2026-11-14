<?php

namespace App\Support;

use App\Models\Contractor;

/**
 * اقتراح اسم الشركة وعنوانها بالإنجليزي من العربي (لشهادة العضوية الإنجليزية).
 *
 * بدون أي خدمة خارجية — بيشتغل على السيرفر لحاله:
 *   - كلمات الشركات المعروفة بتنترجم (للمقاولات → Contracting، شركة → Co.، المحدودة → Ltd.)
 *   - أسماء الأماكن المعروفة (غزة، خان يونس، الرمال، شارع عمر المختار…) من قاموس
 *   - الباقي (أسماء العائلات والأعلام) بينكتب بحروف لاتينية (نقل حرفي، مش ترجمة):
 *     "النور" → Al-Noor، "مشتهى" → Mushtaha (من القاموس) أو تقريب صوتي لو مش فيه.
 *
 * الناتج اقتراح بس — الأدمن بيراجعه ويعدّله قبل الحفظ، فالأولوية إنه يكون مقروء ومعقول.
 */
class EnglishNameSuggester
{
    /** كلمات بتنترجم (بعد شيل "ال" و/أو "و"/"ل" من أولها) */
    private const WORDS = [
        'مقاولات'      => 'Contracting', 'مقاولة' => 'Contracting', 'مقاول' => 'Contractor', 'مقاولين' => 'Contractors',
        'عامة'         => 'General', 'عامه' => 'General',
        'تجارة'        => 'Trading', 'تجارية' => 'Trading', 'تجاري' => 'Trading',
        'هندسة'        => 'Engineering', 'هندسية' => 'Engineering', 'هندسي' => 'Engineering', 'هندسيه' => 'Engineering',
        'إنشاءات'      => 'Construction', 'انشاءات' => 'Construction', 'إنشائية' => 'Construction', 'انشائية' => 'Construction',
        'بناء'         => 'Building', 'تعمير' => 'Reconstruction', 'إعمار' => 'Reconstruction', 'اعمار' => 'Reconstruction',
        'تطوير'        => 'Development', 'استثمار' => 'Investment', 'استثمارات' => 'Investments',
        'طرق'          => 'Roads', 'أشغال' => 'Works', 'اشغال' => 'Works', 'خدمات' => 'Services',
        'توريدات'      => 'Supplies', 'توريد' => 'Supply', 'تجهيزات' => 'Equipment', 'معدات' => 'Equipment',
        'كهرباء'       => 'Electrical', 'كهربائية' => 'Electrical', 'كهروميكانيك' => 'Electromechanical',
        'كهروميكانيكية' => 'Electromechanical', 'ميكانيك' => 'Mechanical', 'مياه' => 'Water', 'آبار' => 'Wells',
        'ديكور'        => 'Decor', 'تشطيبات' => 'Finishing', 'صيانة' => 'Maintenance', 'نقل' => 'Transport',
        'عقارات'       => 'Real Estate', 'عقاري' => 'Real Estate', 'عقارية' => 'Real Estate',
        'مجموعة'       => 'Group', 'مكتب' => 'Office', 'مصنع' => 'Factory', 'مصانع' => 'Factories',
        'عربية'        => 'Arab', 'عربي' => 'Arab', 'فلسطينية' => 'Palestinian', 'فلسطيني' => 'Palestinian',
        'دولية'        => 'International', 'دولي' => 'International', 'متحدة' => 'United', 'حديثة' => 'Modern',
        'وطنية'        => 'National', 'وطني' => 'National', 'أهلية' => 'National', 'اهلية' => 'National',
        'متكاملة'      => 'Integrated', 'متقدمة' => 'Advanced', 'ذهبي' => 'Golden', 'ذهبية' => 'Golden',
        'شرق'          => 'East', 'غرب' => 'West', 'شمال' => 'North', 'جنوب' => 'South', 'أوسط' => 'Middle', 'اوسط' => 'Middle',
        'إخوان'        => 'Brothers', 'اخوان' => 'Brothers', 'أخوان' => 'Brothers', 'أولاده' => 'Sons', 'اولاده' => 'Sons',
        'شركاه'        => 'Partners', 'شركائه' => 'Partners', 'أبناء' => 'Sons', 'ابناء' => 'Sons',
    ];

    /** كلمات بتتحوّل لاحقة بآخر الاسم */
    private const SUFFIXES = [
        'شركة' => 'Co.', 'شركه' => 'Co.', 'مؤسسة' => 'Est.', 'مؤسسه' => 'Est.',
        'المحدودة' => 'Ltd.', 'محدودة' => 'Ltd.', 'المحدوده' => 'Ltd.', 'المساهمة' => 'PLC', 'الخاصة' => 'Ltd.',
    ];

    /** أعلام وأماكن معروفة بكتابتها الشائعة (المفتاح بدون "ال") */
    private const NAMES = [
        // أماكن
        'غزة' => 'Gaza', 'غزه' => 'Gaza', 'خانيونس' => 'Khan Younis', 'رفح' => 'Rafah', 'جباليا' => 'Jabalia',
        'نصيرات' => 'Nuseirat', 'بريج' => 'Bureij', 'مغازي' => 'Maghazi', 'زوايدة' => 'Zawaida', 'مصدر' => 'Musaddar',
        'رمال' => 'Rimal', 'شجاعية' => 'Shujaiya', 'زيتون' => 'Zaitoun', 'شاطئ' => 'Shati', 'درج' => 'Daraj',
        'تفاح' => 'Tuffah', 'صبرة' => 'Sabra', 'زهراء' => 'Zahra', 'مغراقة' => 'Mughraqa', 'نصر' => 'Nasr',
        'شوكة' => 'Shokah', 'قرارة' => 'Qarara', 'خزاعة' => "Khuza'a", 'فخاري' => 'Fukhari', 'عبسان' => 'Abasan',
        'كرامة' => 'Karama', 'صفطاوي' => 'Saftawi', 'تلالسلطان' => 'Tal al-Sultan', 'سودانية' => 'Sudaniya',
        'فلسطين' => 'Palestine', 'قدس' => 'Quds', 'نابلس' => 'Nablus', 'خليل' => 'Hebron', 'جنين' => 'Jenin',
        'طولكرم' => 'Tulkarm', 'قلقيلية' => 'Qalqilya', 'أريحا' => 'Jericho', 'اريحا' => 'Jericho', 'سلفيت' => 'Salfit',
        'طوباس' => 'Tubas', 'بيرة' => 'Bireh', 'رامالله' => 'Ramallah', 'مختار' => 'Mukhtar', 'جلاء' => 'Jalaa',
        'وحدة' => 'Wehda', 'ثلاثيني' => 'Thalathini', 'نفق' => 'Nafaq', 'سرايا' => 'Saraya', 'ساحة' => 'Saha',
        // أعلام شائعة
        'محمد' => 'Mohammed', 'أحمد' => 'Ahmed', 'احمد' => 'Ahmed', 'محمود' => 'Mahmoud', 'مصطفى' => 'Mustafa',
        'علي' => 'Ali', 'حسن' => 'Hassan', 'حسين' => 'Hussein', 'خالد' => 'Khaled', 'يوسف' => 'Yousef', 'عمر' => 'Omar',
        'إبراهيم' => 'Ibrahim', 'ابراهيم' => 'Ibrahim', 'إسماعيل' => 'Ismail', 'اسماعيل' => 'Ismail', 'سعيد' => 'Saeed',
        'صالح' => 'Saleh', 'سليم' => 'Salim', 'سالم' => 'Salem', 'جمال' => 'Jamal', 'كمال' => 'Kamal', 'فؤاد' => 'Fouad',
        'نور' => 'Noor', 'أمل' => 'Amal', 'امل' => 'Amal', 'فجر' => 'Fajr', 'هدى' => 'Huda', 'رحمة' => 'Rahma', 'بركة' => 'Baraka',
        'أقصى' => 'Aqsa', 'اقصى' => 'Aqsa', 'رواد' => 'Rowad', 'رائد' => 'Raed', 'بناء' => 'Binaa', 'إتقان' => 'Itqan', 'اتقان' => 'Itqan',
        'سقا' => 'Saqqa', 'مشتهى' => 'Mushtaha', 'شوا' => 'Shawwa', 'فرا' => 'Farra', 'حلو' => 'Helou', 'عشي' => 'Ashi',
        'كحلوت' => 'Kahlout', 'نخالة' => 'Nakhala', 'سراج' => 'Siraj', 'غصين' => 'Ghussein', 'خضري' => 'Khudari',
        'طويل' => 'Taweel', 'شنطي' => 'Shanti', 'بطش' => 'Batsh', 'حايك' => 'Hayek', 'دحلان' => 'Dahlan', 'عجلة' => 'Ajla',
        'رحمن' => 'Rahman', 'له' => 'lah', 'كريم' => 'Kareem', 'رحيم' => 'Raheem', 'عزيز' => 'Aziz', 'قادر' => 'Qader',
        'لطيف' => 'Latif', 'حميد' => 'Hamid', 'ناصر' => 'Nasser', 'هادي' => 'Hadi', 'سلام' => 'Salam', 'فتاح' => 'Fattah',
        'رزاق' => 'Razzaq', 'جواد' => 'Jawad', 'حكيم' => 'Hakeem', 'مجيد' => 'Majeed', 'رؤوف' => 'Raouf', 'باسط' => 'Baset',
        'اتحاد' => 'Ittihad', 'أخوة' => 'Ukhuwa', 'اخوة' => 'Ukhuwa', 'مهندس' => 'Muhandes', 'كرمل' => 'Karmel',
        'أندلس' => 'Andalus', 'اندلس' => 'Andalus', 'مروة' => 'Marwa', 'صفا' => 'Safa', 'بنا' => 'Banna', 'عيدة' => 'Eida',
        'دار' => 'Dar', 'قمة' => 'Qimma', 'نجاح' => 'Najah', 'إسراء' => 'Israa', 'اسراء' => 'Israa', 'أمين' => 'Ameen', 'امين' => 'Ameen',
        'سلامة' => 'Salama', 'شروق' => 'Shorouk', 'أفق' => 'Ofoq', 'افق' => 'Ofoq', 'مستقبل' => 'Mustaqbal', 'نخبة' => 'Nukhba',
        'صرح' => 'Sarh', 'بيان' => 'Bayan', 'منار' => 'Manar', 'ريادة' => 'Riyada', 'وفاء' => 'Wafaa', 'فرسان' => 'Fursan',
        'عهد' => 'Ahd', 'وعد' => 'Waad', 'أصيل' => 'Aseel', 'اصيل' => 'Aseel', 'ياسمين' => 'Yasmeen', 'نخيل' => 'Nakheel',
        'كوثر' => 'Kawthar', 'فردوس' => 'Firdaws', 'جزيرة' => 'Jazeera', 'خليج' => 'Khaleej', 'بحر' => 'Bahr', 'شام' => 'Sham',
        'يافا' => 'Yafa', 'حيفا' => 'Haifa', 'عكا' => 'Akka', 'رملة' => 'Ramla', 'مجدل' => 'Majdal', 'أسدود' => 'Isdud',
        'بيسان' => 'Beisan', 'صفد' => 'Safad', 'عسقلان' => 'Asqalan', 'عودة' => 'Awda', 'تحرير' => 'Tahrir', 'نهضة' => 'Nahda',
        'أمانة' => 'Amana', 'امانة' => 'Amana', 'ثقة' => 'Thiqa', 'إيمان' => 'Iman', 'ايمان' => 'Iman', 'هلال' => 'Hilal',
        'شمس' => 'Shams', 'قمر' => 'Qamar', 'أنصار' => 'Ansar', 'انصار' => 'Ansar', 'إسلامي' => 'Islami', 'رشيد' => 'Rasheed',
        'ديرالبلح' => 'Deir al-Balah', 'تلالهوى' => 'Tal al-Hawa', 'عمرالمختار' => 'Omar Al-Mukhtar',
        'عبد' => 'Abd', 'أبو' => 'Abu', 'ابو' => 'Abu', 'بن' => 'Bin', 'بني' => 'Bani', 'دير' => 'Deir', 'بيت' => 'Beit',
        'خان' => 'Khan', 'يونس' => 'Younis', 'بلح' => 'Balah', 'لاهيا' => 'Lahia', 'حانون' => 'Hanoun', 'سهيلا' => 'Suheila',
        'شيخ' => 'Sheikh', 'رضوان' => 'Radwan', 'عجلين' => 'Ijlin', 'تل' => 'Tal', 'هوى' => 'Hawa', 'لحم' => 'Lahm',
        'كبيرة' => 'Kabira', 'صغيرة' => 'Saghira', 'جديد' => 'Jadeed', 'جديدة' => 'Jadeeda', 'قديم' => 'Qadeem',
    ];

    /** كلمات العنوان: [الإنجليزي، يُكتب بعد الاسم اللي بعده؟] */
    private const ADDRESS_WORDS = [
        'شارع' => ['St.', true], 'ش' => ['St.', true], 'طريق' => ['Rd.', true], 'ميدان' => ['Square', true], 'دوار' => ['Roundabout', true],
        'مفترق' => ['Junction', true], 'مخيم' => ['Camp', true], 'برج' => ['Tower', true], 'عمارة' => ['Building', true],
        'عماره' => ['Building', true], 'مجمع' => ['Complex', true], 'حي' => ['', false], 'مدينة' => ['', false],
        'محافظة' => ['', false], 'محافظه' => ['', false], 'منطقة' => ['', false], 'بجوار' => ['near', false], 'قرب' => ['near', false],
        'مقابل' => ['opposite', false], 'خلف' => ['behind', false], 'بالقرب' => ['near', false], 'من' => ['', false],
        'الطابق' => ['Floor', false], 'طابق' => ['Floor', false], 'وسط' => ['Central', false], 'البلد' => ['', false],
    ];

    /** نقل حرفي للحروف العربية */
    private const LETTERS = [
        'ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'aa', 'ء' => "'", 'ؤ' => "'", 'ئ' => "'", 'ب' => 'b', 'ت' => 't', 'ث' => 'th',
        'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'th', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => "'", 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k',
        'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'ة' => 'a', 'و' => 'w', 'ي' => 'y', 'ى' => 'a',
    ];

    public static function forContractor(Contractor $contractor): array
    {
        return [
            'name_en'    => self::companyName((string) $contractor->name),
            'address_en' => self::address(self::arabicAddress($contractor)),
        ];
    }

    /** العنوان العربي اللي بنترجمه: المدينة (أو المحافظة) + الحي + الشارع */
    public static function arabicAddress(Contractor $contractor): string
    {
        $place = $contractor->city ?: $contractor->cityModel?->name ?: $contractor->governorate?->name;

        $parts = array_filter([$place, $contractor->district, $contractor->address], fn ($p) => filled($p));
        $parts = array_values(array_unique(array_map(fn ($p) => trim((string) $p), $parts)));

        return implode(' - ', $parts);
    }

    public static function companyName(string $arabic): ?string
    {
        $words = self::words($arabic);
        if (! $words) {
            return null;
        }

        $out = $suffixes = [];
        foreach ($words as $w) {
            if (isset(self::SUFFIXES[$w])) {
                $suffixes[] = self::SUFFIXES[$w];
                continue;
            }
            if (preg_match('/^[A-Za-z0-9&.\-\']+$/', $w)) { // مكتوب لاتيني أصلاً
                $out[] = $w;
                continue;
            }
            $out[] = self::word($w);
        }

        $name = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($out))));
        $name = preg_replace('/(?:^|\s)&\s*$/', '', $name);     // "&" يتيمة بالآخر
        $name = preg_replace('/^&\s*/', '', $name);
        // "Contracting General" → "General Contracting" (بالعربي الصفة بعد الموصوف)
        $name = preg_replace('/\bContracting General\b/', 'General Contracting', $name);
        $name = preg_replace('/\bTrading General\b/', 'General Trading', $name);
        // "A & B & C & D" → "A, B, C & D"
        $parts = explode(' & ', $name);
        if (count($parts) > 2) {
            $last = array_pop($parts);
            $name = implode(', ', $parts) . ' & ' . $last;
        }

        $suffixes = array_values(array_unique($suffixes));
        // Est. وCo. مع بعض ما بيصيروا — بنكتفي بالأول
        if (in_array('Co.', $suffixes, true) && in_array('Est.', $suffixes, true)) {
            $suffixes = array_values(array_diff($suffixes, ['Est.']));
        }
        usort($suffixes, fn ($a, $b) => array_search($a, ['Co.', 'Est.', 'PLC', 'Ltd.']) <=> array_search($b, ['Co.', 'Est.', 'PLC', 'Ltd.']));

        return self::clean(trim($name . ' ' . implode(' ', $suffixes)));
    }

    public static function address(string $arabic): ?string
    {
        if (trim($arabic) === '') {
            return null;
        }

        // كل جزء (مفصول بـ - أو / أو ، أو ,) بينترجم لحاله
        $segments = preg_split('/\s*[-\/،,]\s*/u', $arabic, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($segments as $segment) {
            $words = self::words($segment);
            $parts = $after = [];
            foreach ($words as $w) {
                $bare = self::stripAl($w);
                if (isset(self::ADDRESS_WORDS[$w]) || isset(self::ADDRESS_WORDS[$bare])) {
                    [$en, $postfix] = self::ADDRESS_WORDS[$w] ?? self::ADDRESS_WORDS[$bare];
                    if ($en === '') {
                        continue;
                    }
                    $postfix ? $after[] = $en : $parts[] = $en;
                    continue;
                }
                $parts[] = preg_match('/^[A-Za-z0-9]+$/', $w) ? $w : self::word($w, place: true);
            }
            $text = trim(implode(' ', array_filter($parts)) . ' ' . implode(' ', $after));
            if ($text !== '' && ! in_array($text, $out, true)) {
                $out[] = $text;
            }
        }

        return self::clean(implode(' - ', $out));
    }

    // ─── مساعدات ─────────────────────────────────────────────────────────────

    /** كلمات النص بعد توحيد الحروف وشيل التطويل والتشكيل وعلامات الترقيم */
    private static function words(string $text): array
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text);            // تشكيل وتطويل
        $text = str_replace(['م.خ.م', 'م . خ . م', 'م.ع.م', 'م.خ', 'ذ.م.م'], ' المحدودة ', $text);
        $text = preg_replace('/[\/()"\'«»،,.:;]+/u', ' ', $text);
        $text = str_replace(['عبد ال', 'أبو ', 'ابو '], ['عبدال', 'أبو_', 'ابو_'], $text);
        $text = str_replace(
            ['خان يونس', 'رام الله', 'تل السلطان', 'دير البلح', 'تل الهوى', 'عمر المختار'],
            ['خانيونس', 'رامالله', 'تلالسلطان', 'ديرالبلح', 'تلالهوى', 'عمرالمختار'],
            $text,
        );

        return array_values(array_filter(preg_split('/\s+/u', trim($text)), fn ($w) => $w !== '' && $w !== '-'));
    }

    /** ترجمة/نقل كلمة وحدة، مع "ال" و"و" و"ل" و"لل" بأولها */
    private static function word(string $w, bool $place = false): string
    {
        // أبو فلان / عبدالله: قطعتين
        if (preg_match('/^(أبو|ابو)_(.+)$/u', $w, $m)) {
            return 'Abu ' . self::properName($m[2]);
        }
        if (preg_match('/^عبدال(.+)$/u', $w, $m)) {
            return 'Abdul' . strtolower(self::properName($m[1]));
        }

        $prefix = '';
        $core = $w;
        // و/ل/لل/وال/بال… بأول الكلمة — بنشيلها بس لو الباقي كلمة معروفة
        if (preg_match('/^(وال|ولل|لل|بال|ال|و|ل|ب)(.+)$/u', $w, $m)) {
            $rest = $m[2];
            $known = isset(self::WORDS[$rest]) || isset(self::SUFFIXES['ال' . $rest]);
            if ($known) {
                $core = $rest;
                $prefix = str_starts_with($m[1], 'و') ? '& ' : '';
            } elseif (in_array($m[1], ['ال', 'وال'], true)) {
                $core = $rest;
                $prefix = $m[1] === 'وال' ? '& ' : '';

                return $prefix . (isset(self::WORDS[$rest]) ? self::WORDS[$rest] : ($place && isset(self::NAMES[$rest]) ? self::NAMES[$rest] : 'Al-' . self::properName($rest)));
            }
        }

        if (isset(self::WORDS[$core])) {
            return $prefix . self::WORDS[$core];
        }

        return $prefix . self::properName($core);
    }

    private static function properName(string $w): string
    {
        $w = str_replace('_', '', $w);
        if (isset(self::NAMES[$w])) {
            return self::NAMES[$w];
        }
        $bare = self::stripAl($w);
        if ($bare !== $w && isset(self::NAMES[$bare])) {
            return 'Al-' . self::NAMES[$bare];
        }

        return ucfirst(self::transliterate($w));
    }

    private static function stripAl(string $w): string
    {
        return preg_match('/^ال(.{2,})$/u', $w, $m) ? $m[1] : $w;
    }

    /**
     * تقريب صوتي: العربي بيكتب الحروف الساكنة بس، فبنحط "a" بين حرفين ساكنين متتاليين،
     * و"و"/"ي" بين ساكنين بيصيروا حروف علّة (oo/ee → o/i).
     */
    private static function transliterate(string $w): string
    {
        $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        $out = '';
        $prevConsonant = false;

        foreach ($chars as $i => $c) {
            $isFirst = $i === 0;
            $isLast = $i === $n - 1;

            if ($c === 'و' || $c === 'ي') {
                if ($isFirst) {
                    $out .= $c === 'و' ? 'w' : 'y';
                    $prevConsonant = true;
                } elseif ($prevConsonant) {
                    $out .= $c === 'و' ? 'o' : ($isLast ? 'i' : 'ee');
                    $prevConsonant = false;
                } else {
                    $out .= $c === 'و' ? 'w' : 'y';
                    $prevConsonant = true;
                }
                continue;
            }
            if (in_array($c, ['ا', 'ى', 'ة', 'آ'], true)) {
                $out .= $isFirst ? ($c === 'آ' ? 'Aa' : 'A') : (self::LETTERS[$c] ?? '');
                $prevConsonant = false;
                continue;
            }
            if (in_array($c, ['أ', 'إ'], true)) {
                $out .= self::LETTERS[$c];
                $prevConsonant = false;
                continue;
            }

            $latin = self::LETTERS[$c] ?? (preg_match('/[A-Za-z0-9]/', $c) ? $c : '');
            if ($latin === '') {
                continue;
            }
            if ($prevConsonant && $latin !== "'") {
                $out .= 'a';
            }
            $out .= $latin;
            $prevConsonant = $latin !== "'";
        }

        $out = str_replace(["''", 'aaa'], ["'", 'aa'], $out);
        $out = trim($out, "'");

        return $out;
    }

    /** يطابق قيود الحقل (حروف إنجليزية وأرقام وعلامات بسيطة) وطوله */
    private static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = preg_replace("/[^A-Za-z0-9 .,&'()\\/\\-#]/", '', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text), " -");

        return $text === '' ? null : mb_substr($text, 0, 255);
    }
}
