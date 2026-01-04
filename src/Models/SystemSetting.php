<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * System-wide settings model.
 *
 * Stores global configuration settings managed by Superadmins.
 * Organization settings can override these values for specific organizations.
 *
 * @property string $id
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property string|null $group
 * @property string|null $description
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SystemSetting extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'json',
        'is_public' => 'boolean',
    ];

    /**
     * Get the typed value based on the type field.
     */
    public function getTypedValue(): mixed
    {
        $value = $this->value;

        return match ($this->type) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'float' => (float) $value,
            'array', 'json' => is_array($value) ? $value : json_decode((string) $value, true),
            default => $value,
        };
    }

    /**
     * Set value with type coercion.
     */
    public function setTypedValue(mixed $value): self
    {
        $this->value = match ($this->type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };

        return $this;
    }

    /**
     * Scope query to public settings only.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope query to settings in a specific group.
     */
    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }
}
