<?php

namespace App\Services\Reports;

use App\Models\Audit\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AuditLogReportService
{
    public function build(array $filters, ?int $perPage = null): array
    {
        $query = AuditLog::query()->with('actor');

        $this->applyFilters($query, $filters);

        return [
            'title' => 'Audit Logs',
            'columns' => ['Timestamp', 'Actor', 'Action', 'Entity', 'Description', 'Status', 'Metadata'],
            'rows' => $this->mapRows(
                $this->resolveRows((clone $query)->latest('created_at')->latest('id'), $perPage),
                fn (AuditLog $log): array => $this->rowFor($log),
            ),
            'totals' => [
                'logs' => (clone $query)->count(),
                'actors' => (clone $query)->whereNotNull('actor_id')->distinct()->count('actor_id'),
                'actions' => (clone $query)->distinct()->count('event'),
            ],
        ];
    }

    public function filterLookups(): array
    {
        return [
            'actors' => User::query()
                ->whereHas('auditLogs')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'actions' => AuditLog::query()
                ->select('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event')
                ->mapWithKeys(fn (string $event): array => [$event => $this->actionLabel($event)])
                ->all(),
            'entity_types' => AuditLog::query()
                ->whereNotNull('subject_type')
                ->select('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->mapWithKeys(fn (string $type): array => [$type => $this->entityTypeLabel($type)])
                ->all(),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($action = $filters['action'] ?? null) {
            $query->where('event', $action);
        }

        if ($actorId = $filters['actor_id'] ?? null) {
            $query->where('actor_id', $actorId);
        }

        if ($entityType = $filters['entity_type'] ?? null) {
            $query->where('subject_type', $entityType);
        }
    }

    private function resolveRows(Builder $query, ?int $perPage): Collection|LengthAwarePaginator
    {
        if ($perPage === null) {
            return $query->get();
        }

        return $query->paginate($perPage, ['*'], 'audit_page')->withQueryString();
    }

    private function mapRows(
        Collection|LengthAwarePaginator $rows,
        callable $mapper,
    ): Collection|LengthAwarePaginator {
        if ($rows instanceof LengthAwarePaginator) {
            $rows->setCollection($rows->getCollection()->map($mapper));

            return $rows;
        }

        return $rows->map($mapper);
    }

    private function rowFor(AuditLog $log): array
    {
        return [
            optional($log->created_at)->format('Y-m-d H:i:s') ?? '-',
            $this->actorLabel($log),
            $this->actionLabel($log->event),
            $this->entityLabel($log),
            $this->description($log),
            $this->status($log),
            $this->metadataSummary($log),
        ];
    }

    private function actorLabel(AuditLog $log): string
    {
        return $log->actor?->name
            ?? $log->actor?->email
            ?? 'System';
    }

    private function actionLabel(string $event): string
    {
        $segments = explode('.', $event);

        if (count($segments) > 1) {
            array_shift($segments);
        }

        return str(implode(' ', $segments))
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    private function entityLabel(AuditLog $log): string
    {
        $entityType = $this->entityTypeLabel($log->subject_type);
        $metadata = $log->metadata ?? [];
        $reference = $metadata['order_number']
            ?? $metadata['sku']
            ?? $metadata['reference_number']
            ?? null;

        if (filled($reference)) {
            return "{$entityType} - {$reference}";
        }

        return $log->subject_id
            ? "{$entityType} #{$log->subject_id}"
            : $entityType;
    }

    private function entityTypeLabel(?string $subjectType): string
    {
        if (! is_string($subjectType) || trim($subjectType) === '') {
            return 'System';
        }

        return str(class_basename($subjectType))
            ->headline()
            ->toString();
    }

    private function description(AuditLog $log): string
    {
        $metadata = $log->metadata ?? [];

        return match ($log->event) {
            'commerce.online_order.placed' => sprintf(
                '%s placed %s for PHP %s.',
                $metadata['customer_name'] ?: 'A customer',
                $metadata['order_number'] ?: 'an order',
                number_format((float) ($metadata['grand_total'] ?? 0), 2),
            ),
            'commerce.walk_in_sale.completed' => sprintf(
                '%s was completed for PHP %s.',
                $metadata['order_number'] ?: 'A receipt',
                number_format((float) ($metadata['grand_total'] ?? 0), 2),
            ),
            'inventory.stock_changed' => sprintf(
                '%s (%s) changed by %s%d and is now at %d units.',
                $metadata['product_name'] ?? 'Inventory item',
                $metadata['sku'] ?? 'SKU',
                ((int) ($metadata['quantity_delta'] ?? 0)) > 0 ? '+' : '',
                (int) ($metadata['quantity_delta'] ?? 0),
                (int) ($metadata['current_quantity'] ?? 0),
            ),
            default => $this->actionLabel($log->event).' was recorded.',
        };
    }

    private function status(AuditLog $log): string
    {
        $metadata = $log->metadata ?? [];

        if (filled($metadata['payment_status'] ?? null)) {
            return str((string) $metadata['payment_status'])->headline()->toString();
        }

        if (filled($metadata['stock_status'] ?? null)) {
            return str((string) $metadata['stock_status'])->headline()->toString();
        }

        if (filled($metadata['movement_type'] ?? null)) {
            return str((string) $metadata['movement_type'])->replace('_', ' ')->headline()->toString();
        }

        return '-';
    }

    private function metadataSummary(AuditLog $log): string
    {
        $metadata = collect($log->metadata ?? []);

        $summary = collect([
            isset($metadata['source']) ? 'Source: '.str((string) $metadata['source'])->replace('_', ' ')->headline()->toString() : null,
            isset($metadata['payment_method']) ? 'Payment: '.strtoupper((string) $metadata['payment_method']) : null,
            isset($metadata['reference_number']) ? 'Reference: '.$metadata['reference_number'] : null,
            isset($metadata['quantity_delta']) ? 'Delta: '.((int) $metadata['quantity_delta'] > 0 ? '+' : '').(int) $metadata['quantity_delta'] : null,
            isset($metadata['current_quantity']) ? 'On hand: '.(int) $metadata['current_quantity'] : null,
        ])->filter()->implode(' | ');

        return $summary !== '' ? $summary : '-';
    }
}
