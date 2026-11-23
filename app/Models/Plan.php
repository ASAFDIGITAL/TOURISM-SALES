<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name_he',
        'name_ar',
        'name_en',
        'description_he',
        'description_ar',
        'description_en',
        'monthly_price',
        'yearly_price',
        'currency',
        'included_users',
        'extra_user_price',
        'trial_days',
        'reminders_enabled',
        'allowed_channels',
        'monthly_email_limit',
        'monthly_sms_limit',
        'can_use_employee_reports',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'extra_user_price' => 'decimal:2',
        'included_users' => 'integer',
        'trial_days' => 'integer',
        'reminders_enabled' => 'boolean',
        'allowed_channels' => 'array',
        'monthly_email_limit' => 'integer',
        'monthly_sms_limit' => 'integer',
        'can_use_employee_reports' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function getLocalizedName(?string $locale = null): string
    {
        $lang = $locale ?: app()->getLocale();

        return match ($lang) {
            'he' => $this->name_he ?: $this->name_en,
            'ar' => $this->name_ar ?: $this->name_en,
            default => $this->name_en ?: $this->name_he,
        };
    }

    public function getLocalizedDescription(?string $locale = null): string
    {
        $lang = $locale ?: app()->getLocale();

        return match ($lang) {
            'he' => (string) ($this->description_he ?: $this->description_en),
            'ar' => (string) ($this->description_ar ?: $this->description_en),
            default => (string) ($this->description_en ?: $this->description_he),
        };
    }

    public function getCurrencySymbol(): string
    {
        return match ($this->currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedChannels(): array
    {
        if ($this->allowed_channels === null) {
            return Tenant::ALL_CHANNELS;
        }

        return array_values(array_intersect(Tenant::ALL_CHANNELS, (array) $this->allowed_channels));
    }

    public static function ensureDefaultPlansExist(): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach (static::defaultBlueprints() as $blueprint) {
            static::query()->firstOrCreate(
                ['slug' => $blueprint['slug']],
                $blueprint,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultBlueprints(): array
    {
        return [
            [
                'slug' => 'basic',
                'name_he' => 'סטארט (Start)',
                'name_ar' => 'ستارت (Start)',
                'name_en' => 'Start',
                'description_he' => 'אידיאלי לסוכן עצמאי שרוצה סדר מלא בלקוחות, בטיולים, בקבלות ובגבייה.',
                'description_ar' => 'مثالي للوكيل المستقل لتنظيم العملاء والرحلات والفواتير والتحصيل.',
                'description_en' => 'Ideal for solo travel agents managing customers, trips, receipts, and payments.',
                'monthly_price' => 149,
                'yearly_price' => 1573,
                'currency' => 'ILS',
                'included_users' => 1,
                'extra_user_price' => 59,
                'trial_days' => 14,
                'reminders_enabled' => true,
                'allowed_channels' => ['push', 'email', 'whatsapp', 'sms'],
                'monthly_email_limit' => 100,
                'monthly_sms_limit' => 50,
                'can_use_employee_reports' => false,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro',
                'name_he' => 'פרו (Pro)',
                'name_ar' => 'برو (Pro)',
                'name_en' => 'Pro',
                'description_he' => 'הבחירה הפופולרית לסוכנויות צומחות עם צוות מכירות, תזכורות אוטומטיות ודוחות ביצועים.',
                'description_ar' => 'الخيار الأكثر شعبية للوكالات النامية مع فريق مبيعات وتذكيرات تلقائية وتقارير أداء.',
                'description_en' => 'Most popular for growing agencies with sales staff, smart reminders, and performance reports.',
                'monthly_price' => 299,
                'yearly_price' => 3157,
                'currency' => 'ILS',
                'included_users' => 3,
                'extra_user_price' => 49,
                'trial_days' => 14,
                'reminders_enabled' => true,
                'allowed_channels' => ['push', 'email', 'whatsapp', 'sms'],
                'monthly_email_limit' => 300,
                'monthly_sms_limit' => 100,
                'can_use_employee_reports' => true,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'business',
                'name_he' => 'ביזנס (Business)',
                'name_ar' => 'بيزنس (Business)',
                'name_en' => 'Business',
                'description_he' => 'חבילה מלאה למשרדי נסיעות גדולים כולל עד 10 משתמשים, SMS מלא, הרשאות צוות ותמיכה מועדפת.',
                'description_ar' => 'باقة متكاملة لمكاتب السياحة الكبرى تشمل حتى 10 مستخدمين ورسائل SMS وصلاحيات كاملة.',
                'description_en' => 'Full suite for established travel agencies with 10 seats, full SMS channel, and team analytics.',
                'monthly_price' => 549,
                'yearly_price' => 5797,
                'currency' => 'ILS',
                'included_users' => 10,
                'extra_user_price' => 39,
                'trial_days' => 14,
                'reminders_enabled' => true,
                'allowed_channels' => ['push', 'email', 'whatsapp', 'sms'],
                'monthly_email_limit' => 500,
                'monthly_sms_limit' => 180,
                'can_use_employee_reports' => true,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];
    }
}
