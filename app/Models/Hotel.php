<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory, BelongsToTenant;

    public const TYPE_HOTEL = 'hotel';
    public const TYPE_APARTMENT = 'apartment';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'destination',
        'stars',
        'phone',
        'email',
        'address',
        'default_supplier',
        'notes',
        'is_active',
    ];

    protected $attributes = [
        'type' => self::TYPE_HOTEL,
        'is_active' => true,
    ];

    protected $casts = [
        'stars' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isApartment(): bool
    {
        return $this->type === self::TYPE_APARTMENT;
    }

    public function getDisplayLabelAttribute(): string
    {
        $typeTag = $this->isApartment()
            ? '[' . __('ui.accommodation_type_apartment') . '] '
            : '';

        $parts = [$typeTag . $this->name];

        if (filled($this->destination)) {
            $parts[] = $this->destination;
        }

        $label = implode(' — ', $parts);

        if ($this->stars) {
            $label .= ' (' . $this->stars . '★)';
        }

        return $label;
    }

    /**
     * Build searchable option array [hotel_name => display_label] for Trip accommodation pickers.
     *
     * @return array<string, string>
     */
    public static function tripSelectOptions(
        ?string $search = null,
        ?string $preferredDestination = null,
        int $limit = 60,
        ?string $preferredType = null,
    ): array {
        $query = static::query()
            ->active()
            ->when(filled($search), function (Builder $q) use ($search): void {
                $term = trim((string) $search);
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('destination', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhere('default_supplier', 'like', "%{$term}%");
                });
            });

        $hotels = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if (filled($preferredDestination) || filled($preferredType)) {
            $needle = filled($preferredDestination) ? mb_strtolower(trim((string) $preferredDestination)) : null;
            $hotels = $hotels->sortByDesc(function (Hotel $hotel) use ($needle, $preferredType): int {
                $score = 0;

                if (filled($preferredType) && ($hotel->type ?: self::TYPE_HOTEL) === $preferredType) {
                    $score += 2;
                }

                if ($needle !== null && filled($hotel->destination)) {
                    $dest = mb_strtolower($hotel->destination);
                    if ($dest === $needle || str_contains($dest, $needle) || str_contains($needle, $dest)) {
                        $score += 1;
                    }
                }

                return $score;
            });
        }

        $options = [];
        foreach ($hotels as $hotel) {
            $options[$hotel->name] = $hotel->display_label;
        }

        return $options;
    }
}
