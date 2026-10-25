<?php

namespace App\Services\Mcp;

use Illuminate\Database\Eloquent\Builder;
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
        $take = min($limit, $candidates->count());
        $items = $candidates->take($take)->map(fn ($row) => $project($row))->values()->all();
        $meta = DataDto::meta($context);
        $budget = app(ResponseBudget::class);
        $issue = null;
        do {
            if ($take === 0 && $candidates->isNotEmpty()) {
                throw new DataFailure('result_too_large');
            }
            $more = $candidates->count() > $take;
            $next = null;
            if ($more) {
                $issue ??= $cursor->issuer($context, $tool->name(), $filters);
                $last = $candidates[$take - 1];
                $position = $rows ? ['kind' => 'row_number_asc', 'row_number' => (int) $last->row_number]
                    : ['kind' => 'id_desc', 'id' => (string) $last->getKey()];
                $next = $issue($position);
            }
            $dto = ['data' => $static + ['items' => array_slice($items, 0, $take)], 'meta' => $meta,
                'page' => ['has_more' => $more, 'next_cursor' => $next]];
            if ($budget->fits($tool, $dto)) {
                return $dto;
            }
            $take--;
        } while ($take >= 0);

        throw new DataFailure('result_too_large');
    }
}
