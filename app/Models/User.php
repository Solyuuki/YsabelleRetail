<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Access\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function carts(): HasMany
    {
        return $this->hasMany(\App\Models\Cart\Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\Orders\Order::class);
    }

    public function handledOrders(): HasMany
    {
        return $this->hasMany(\App\Models\Orders\Order::class, 'handled_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(\App\Models\Audit\AuditLog::class, 'actor_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\StockMovement::class, 'actor_id');
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->roles()
            ->whereIn('slug', $slugs)
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin', 'super-admin');
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }
}
