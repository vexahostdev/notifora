<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class Cursor
{
    /**
     * Paginasi cursor berbasis ULID (urut waktu, terbaru dulu).
     *
     * @return array{0: Collection, 1: ?string}
     */
    public static function paginate(Builder $query, ?string $cursor, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        if ($cursor) {
            $query->where($query->getModel()->getTable().'.id', '<', $cursor);
        }
        $rows = $query->orderByDesc($query->getModel()->getTable().'.id')->limit($limit + 1)->get();
        $next = $rows->count() > $limit ? $rows[$limit - 1]->id : null;

        return [$rows->take($limit), $next];
    }
}
