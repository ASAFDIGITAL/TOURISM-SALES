<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrialEmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'step',
        'sort_order',
        'subject_he',
        'subject_ar',
        'subject_en',
        'heading_he',
        'heading_ar',
        'heading_en',
        'intro_he',
        'intro_ar',
        'intro_en',
        'highlights_he',
        'highlights_ar',
        'highlights_en',
        'cta_label_he',
        'cta_label_ar',
        'cta_label_en',
        'cta_path',
        'is_active',
        'sent_count',
        'last_sent_at',
    ];

    protected $attributes = [
        'is_active' => true,
        'sent_count' => 0,
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'highlights_he' => 'array',
        'highlights_ar' => 'array',
        'highlights_en' => 'array',
        'is_active' => 'boolean',
        'sent_count' => 'integer',
        'last_sent_at' => 'datetime',
    ];

    public static function ensureDefaultsExist(): void
    {
        if (static::query()->count() >= 4) {
            return;
        }

        foreach (static::defaultBlueprints() as $blueprint) {
            static::query()->firstOrCreate(
                ['step' => $blueprint['step']],
                $blueprint,
            );
        }
    }

    /**
     * Render localized subject, heading, intro, highlights & CTA for a tenant (or generic test).
     *
     * @return array{subject: string, heading: string, intro: string, highlights: array<int, string>, cta_label: string, cta_url: string}
     */
    public function renderForTenant(?Tenant $tenant = null, ?string $locale = null): array
    {
        $lang = $locale ?: ($tenant?->language ?: 'he');
        if (! in_array($lang, ['he', 'ar', 'en'], true)) {
            $lang = 'he';
        }

        $baseUrl = rtrim((string) config('app.url', 'https://tourism.asafdigital.com'), '/');
        $path = '/' . ltrim((string) ($this->cta_path ?: '/agent'), '/');

        $replacements = [
            '{agency}' => $tenant?->name ?? 'TOURISM-SALES',
            '{plan}' => $tenant?->plan?->getLocalizedName($lang) ?? 'Pro',
            '{days}' => (string) max(1, $tenant ? ($tenant->getTrialDaysLeft() ?: 14) : 14),
            '{trips}' => (string) ($tenant ? $tenant->trips()->count() : 0),
            '{customers}' => (string) ($tenant ? $tenant->customers()->count() : 0),
        ];

        $replace = fn (?string $text): string => str_replace(
            array_keys($replacements),
            array_values($replacements),
            (string) $text
        );

        $subject = $this->{"subject_{$lang}"} ?: $this->subject_he;
        $heading = $this->{"heading_{$lang}"} ?: $this->heading_he;
        $intro = $this->{"intro_{$lang}"} ?: $this->intro_he;
        $ctaLabel = $this->{"cta_label_{$lang}"} ?: $this->cta_label_he;
        $rawHighlights = $this->{"highlights_{$lang}"} ?: $this->highlights_he ?: [];

        $highlights = array_values(array_filter(array_map(
            fn ($line) => trim($replace(is_array($line) ? ($line['text'] ?? '') : (string) $line)),
            is_array($rawHighlights) ? $rawHighlights : []
        )));

        return [
            'subject' => $replace($subject),
            'heading' => $replace($heading),
            'intro' => $replace($intro),
            'highlights' => $highlights,
            'cta_label' => $replace($ctaLabel),
            'cta_url' => $baseUrl . $path,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultBlueprints(): array
    {
        return [
            [
                'step' => 'day_0',
                'sort_order' => 1,
                'subject_he' => 'ברוכים הבאים ל-TOURISM-SALES, {agency}! ✈️',
                'subject_ar' => 'مرحباً بك في TOURISM-SALES يا {agency}! ✈️',
                'subject_en' => 'Welcome to TOURISM-SALES, {agency}! ✈️',
                'heading_he' => 'ברוכים הבאים ל-TOURISM-SALES, {agency}!',
                'heading_ar' => 'مرحباً بك في TOURISM-SALES، {agency}!',
                'heading_en' => 'Welcome to TOURISM-SALES, {agency}!',
                'intro_he' => 'פתחנו לך {days} ימי ניסיון חינם בחבילת {plan}. הנה 3 צעדים פשוטים שייקחו לך 2 דקות להתחיל:',
                'intro_ar' => 'فتحنا لك تجربة مجانية لمدة {days} يوماً على باقة {plan}. إليك 3 خطوات سريعة للبدء:',
                'intro_en' => 'Your {days}-day free trial on the {plan} plan is active. Here are 3 quick steps to get started:',
                'highlights_he' => [
                    'הוסף את הלקוח הראשון שלך ופתח טיול או הצעת מחיר.',
                    'שתף עם הלקוח את "פורטל הטיול" האישי עם הלוגו שלך.',
                    'הוסף את המערכת למסך הבית בטלפון כדי לקבל התראות גבייה וטיסות.',
                ],
                'highlights_ar' => [
                    'أضف أول عميل وأنشئ رحلة أو عرض سعر.',
                    'شارك العميل رابط بوابة الرحلة بشعار وكالتك.',
                    'أضف التطبيق إلى شاشة هاتفك لاستلام التنبيهات الفورية.',
                ],
                'highlights_en' => [
                    'Add your first customer and create a trip or quote.',
                    'Share the branded Client Trip Portal link with your customer.',
                    'Install the app on your phone for live flight & payment alerts.',
                ],
                'cta_label_he' => 'כניסה למערכת עכשיו',
                'cta_label_ar' => 'الدخول إلى النظام الآن',
                'cta_label_en' => 'Open Your Dashboard',
                'cta_path' => '/agent',
                'is_active' => true,
            ],
            [
                'step' => 'day_7',
                'sort_order' => 2,
                'subject_he' => 'שבוע ראשון ב-TOURISM-SALES — איך מתקדם, {agency}?',
                'subject_ar' => 'أسبوعك الأول في TOURISM-SALES — كيف تسير الأمور؟',
                'subject_en' => 'Week 1 with TOURISM-SALES — How is it going, {agency}?',
                'heading_he' => 'אמצע תקופת הניסיון — טיפים לחסוך שעות עבודה',
                'heading_ar' => 'منتصف الفترة التجريبية — نصائح لتوفير ساعات عمل',
                'heading_en' => 'Mid-Trial Tips to Save Hours Every Week',
                'intro_he' => 'עד כה ניהלת במערכת {trips} טיולים ו-{customers} לקוחות, ונשארו לך עוד {days} ימים בניסיון החינמי.',
                'intro_ar' => 'حتى الآن أدرت {trips} رحلة و{customers} عميل، ويتبقى لك {days} أيام في التجربة.',
                'intro_en' => 'So far you have managed {trips} trips and {customers} customers, with {days} days left in your free trial.',
                'highlights_he' => [
                    'הפעל "חוקי תזכורות אוטומטיים" כדי שהמערכת תגבה ותזכיר ללקוחות לבד.',
                    'עקוב אחרי קריאת מיילים (✓✓ נקרא) בטבלת התזכורות.',
                    'שלח הצעות מחיר עם כפתור אישור בלחיצה אחת ללקוח.',
                ],
                'highlights_ar' => [
                    'فعّل قواعد التذكير التلقائية للتحصيل ومواعيد السفر.',
                    'تابع قراءة العملاء لرسائل البريد (✓✓ تمت القراءة).',
                    'أرسل عروض أسعار مع زر موافقة فورية للعميل.',
                ],
                'highlights_en' => [
                    'Activate Automated Reminder Rules so payments & flights follow up automatically.',
                    'Track when customers open your emails (✓✓ Read) in Reminders.',
                    'Send quotes with 1-click customer approval.',
                ],
                'cta_label_he' => 'הגדר תזכורות אוטומטיות',
                'cta_label_ar' => 'ضبط التذكيرات التلقائية',
                'cta_label_en' => 'Configure Auto Reminders',
                'cta_path' => '/agent/reminder-rules',
                'is_active' => true,
            ],
            [
                'step' => 'day_11',
                'sort_order' => 3,
                'subject_he' => 'נשארו לך {days} ימים בלבד לתקופת הניסיון ב-TOURISM-SALES',
                'subject_ar' => 'يتبقى {days} أيام فقط على انتهاء فترتك التجريبية',
                'subject_en' => 'Only {days} days left in your TOURISM-SALES trial',
                'heading_he' => 'נשארו רק {days} ימים לתקופת הניסיון שלך',
                'heading_ar' => 'يتبقى {days} أيام فقط في الفترة التجريبية',
                'heading_en' => 'Only {days} Days Left in Your Free Trial',
                'intro_he' => 'הסוכנות {agency} פועלת כרגע בחבילת {plan}. בחר מסלול מראש כדי שכל הנתונים והתזכורות ימשיכו לפעול בלי הפרעה.',
                'intro_ar' => 'وكالتك {agency} تعمل حالياً على باقة {plan}. اختر باقتك الآن لضمان استمرارية العمل دون انقطاع.',
                'intro_en' => '{agency} is currently on the {plan} plan. Pick a subscription ahead of time to keep everything running seamlessly.',
                'highlights_he' => [
                    'כל הלקוחות, הטיולים והקבלות שלך נשמרים ב-100%.',
                    'בחר חיוב חודשי או שנתי (12% הנחה) עם חשבונית מס אוטומטית.',
                ],
                'highlights_ar' => [
                    'جميع بياناتك وعملائك وفواتيرك محفوظة 100%.',
                    'اختر اشتراكاً شهرياً أو سنوياً (خصم 12%).',
                ],
                'highlights_en' => [
                    'All your customers, trips, and receipts stay 100% preserved.',
                    'Choose monthly or yearly billing (12% off) with automatic digital invoices.',
                ],
                'cta_label_he' => 'בחר חבילה והפעל מנוי',
                'cta_label_ar' => 'اختر الباقة وفعّل الاشتراك',
                'cta_label_en' => 'Choose Plan & Activate',
                'cta_path' => '/agent/billing-and-invoices',
                'is_active' => true,
            ],
            [
                'step' => 'day_13',
                'sort_order' => 4,
                'subject_he' => 'מחר מסתיימת תקופת הניסיון שלך ב-TOURISM-SALES',
                'subject_ar' => 'غداً تنتهي فترتك التجريبية في TOURISM-SALES',
                'subject_en' => 'Your TOURISM-SALES free trial ends tomorrow',
                'heading_he' => 'מחר תקופת הניסיון מסתיימת — אל תעצור את העבודה',
                'heading_ar' => 'غداً تنتهي التجربة المجانية — فعّل اشتراكك الآن',
                'heading_en' => 'Your Trial Ends Tomorrow — Keep Your CRM Active',
                'intro_he' => 'כדי ש-{agency} תמשיך לעבוד ברצף עם כל התזכורות, הקבלות ופורטל הלקוחות — הפעל עכשיו את המנוי בדקה.',
                'intro_ar' => 'لضمان استمرار عمل {agency} بكل الميزات والتذكيرات — فعّل الباقة خلال دقيقة واحدة.',
                'intro_en' => 'Activate your subscription for {agency} in under a minute so your team and reminders never pause.',
                'highlights_he' => [
                    'הפעלה מיידית בכרטיס אשראי (ללא התחייבות).',
                    'צריך עזרה או שאלה? אנחנו זמינים בוואטסאפ 050-231-1554.',
                ],
                'highlights_ar' => [
                    'تفعيل فوري ببطاقة الائتمان بدون التزام.',
                    'للمساعدة نحن متاحون على واتساب 050-231-1554.',
                ],
                'highlights_en' => [
                    'Instant credit card activation with no long-term lock-in.',
                    'Need help? Chat with us on WhatsApp: 050-231-1554.',
                ],
                'cta_label_he' => 'בחר חבילה והפעל מנוי',
                'cta_label_ar' => 'اختر الباقة وفعّل الاشتراك',
                'cta_label_en' => 'Choose Plan & Activate',
                'cta_path' => '/agent/billing-and-invoices',
                'is_active' => true,
            ],
        ];
    }
}
