<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
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

    public function getDisplayLabelAttribute(): string
    {
        $parts = [$this->name];

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
     * Build searchable option array [hotel_name => display_label] for Trip hotel pickers.
     *
     * @return array<string, string>
     */
    public static function tripSelectOptions(?string $search = null, ?string $preferredDestination = null, int $limit = 60): array
    {
        $query = static::query()
            ->active()
            ->when(filled($search), function (Builder $q) use ($search): void {
                $term = trim((string) $search);
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('destination', 'like', "%{$term}%")
                        ->orWhere('default_supplier', 'like', "%{$term}%");
                });
            });

        $hotels = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if (filled($preferredDestination)) {
            $needle = mb_strtolower(trim((string) $preferredDestination));
            $hotels = $hotels->sortByDesc(function (Hotel $hotel) use ($needle): int {
                if (blank($hotel->destination)) {
                    return 0;
                }

                $dest = mb_strtolower($hotel->destination);

                return ($dest === $needle || str_contains($dest, $needle) || str_contains($needle, $dest)) ? 1 : 0;
            });
        }

        $options = [];
        foreach ($hotels as $hotel) {
            $options[$hotel->name] = $hotel->display_label;
        }

        return $options;
    }
}
