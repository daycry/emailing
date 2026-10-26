<?php

namespace App\Services\Mcp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Mcp\Server\Tool;

final class DataPage
{
    public function make(Tool $tool, DelegatedContext $context, Builder $query, array $arguments, array $filters, callable $project, array $static = [], bool $rows = false): array
    {
        $cursor = app(DataCursor::class);
        $column = $rows ? 'row_number' : $query->getModel()->getQualifiedKeyName();
        $query = clone $query;
        if (isset($arguments['cursor'])) {
            $position = $cursor->after($context, $tool->name(), $filters, $arguments['cursor']);
            $query->where($column, $rows ? '>' : '<', $position[$rows ? 'row_number' : 'id']);
        }
        $limit = $arguments['limit'] ?? 25;
        $candidates = $query->reorder()->orderBy($column, $rows ? 'asc' : 'desc')->limit($limit + 1)->get();
        $meta = DataDto::meta($context);
        $issue = null;
        $next = function ($last) use ($cursor, $context, $tool, $filters, $rows, &$issue): string {
            $issue ??= $cursor->issuer($context, $tool->name(), $filters);

            return $issue($rows ? ['kind' => 'row_number_asc', 'row_number' => (int) $last->row_number]
                : ['kind' => 'id_desc', 'id' => (string) $last->getKey()]);
        };

        return $this->cut($tool, $candidates, $limit, $project,
            fn (array $items, array $page): array => ['data' => $static + ['items' => $items], 'meta' => $meta, 'page' => $page], $next);
    }

    /** Callers fetch at most limit+1 rows; every cut budgets the complete SDK result. */
    public function cut(Tool $tool, Collection $candidates, int $limit, callable $project, callable $envelope, callable $continuation): array
    {
        $take = min($limit, $candidates->count());
        $items = $candidates->take($take)->map(fn ($row) => $project($row))->values()->all();
        $budget = app(ResponseBudget::class);
        do {
            if ($take === 0 && $candidates->isNotEmpty()) {
                throw new DataFailure('result_too_large');
            }
            $more = $candidates->count() > $take;
            $next = $more ? $continuation($candidates[$take - 1]) : null;
            $dto = $envelope(array_slice($items, 0, $take), ['has_more' => $more, 'next_cursor' => $next]);
            if ($budget->fits($tool, $dto)) {
                return $dto;
            }
            $take--;
        } while ($take >= 0);

        throw new DataFailure('result_too_large');
    }
}
