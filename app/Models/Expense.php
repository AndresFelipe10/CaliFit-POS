<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'supplier_id',
        'created_by',
        'invoice_number',
        'issue_date',
        'due_date',
        'category',
        'description',
        'amount',
        'status',
        'paid_at',
        'payment_method',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== 'pending' || $this->due_date === null) {
            return false;
        }

        return $this->due_date->startOfDay()->lt(Carbon::today());
    }

    public function getCategoryLabelAttribute(): string
    {
        if ($this->category instanceof ExpenseCategory) {
            return $this->category->label();
        }

        return ExpenseCategory::tryFrom((string) $this->category)?->label() ?? (string) $this->category;
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }
}
