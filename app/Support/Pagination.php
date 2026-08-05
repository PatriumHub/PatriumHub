<?php

declare(strict_types=1);

final class Pagination
{
    /**
     * @return array{
     *   page:int,per_page:int,offset:int,total:int,total_pages:int,
     *   from:int,to:int,has_prev:bool,has_next:bool
     * }
     */
    public static function make(int $total, ?int $page = null, int $perPage = 50): array
    {
        $perPage = max(10, min(200, $perPage));
        $total = max(0, $total);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = $page ?? (int) ($_GET['page'] ?? 1);
        $page = max(1, min($totalPages, $page));
        $offset = ($page - 1) * $perPage;
        $from = $total === 0 ? 0 : $offset + 1;
        $to = min($total, $offset + $perPage);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => $from,
            'to' => $to,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ];
    }

    /** @param array<string, scalar|null> $query */
    public static function url(string $route, array $query, int $page): string
    {
        $query['page'] = $page;
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        return url($route . ($query !== [] ? ('?' . http_build_query($query)) : ''));
    }
}
