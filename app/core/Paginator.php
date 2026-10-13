<?php
namespace JanKavach\Core;

/**
 * Offset pagination with a Bootstrap 5 renderer that preserves the current
 * query string (so filters survive page changes).
 */
class Paginator
{
    public array  $items;
    public int    $total;
    public int    $perPage;
    public int    $currentPage;
    public int    $lastPage;
    public int    $from;
    public int    $to;
    private array $query;

    public function __construct(array $items, int $total, int $perPage, int $currentPage, array $query = [])
    {
        $this->items       = $items;
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->lastPage    = max(1, (int)ceil($this->total / $this->perPage));
        $this->currentPage = min(max(1, $currentPage), $this->lastPage);
        $this->from        = $this->total === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1;
        $this->to          = $this->total === 0 ? 0 : min($this->from + count($items) - 1, $this->total);

        unset($query['page']);
        $this->query = $query;
    }

    /** Resolve and clamp the requested page size. */
    public static function perPageFrom(Request $request): int
    {
        $default = (int)Config::get('pagination.per_page', 25);
        $max     = (int)Config::get('pagination.max_per_page', 500);
        $value   = $request->int('per_page', $default) ?: $default;
        return max(5, min($value, $max));
    }

    public static function pageFrom(Request $request): int
    {
        return max(1, $request->int('page', 1) ?? 1);
    }

    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    public function urlFor(int $page): string
    {
        $query = array_merge($this->query, ['page' => $page]);
        return '?' . http_build_query($query);
    }

    /** "Showing 1 to 25 of 164 records" */
    public function summary(string $noun = 'record'): string
    {
        if ($this->total === 0) {
            return 'No ' . $noun . 's found';
        }
        return sprintf('Showing %s to %s of %s %s%s',
            number_format($this->from),
            number_format($this->to),
            number_format($this->total),
            $noun,
            $this->total === 1 ? '' : 's');
    }

    /**
     * Page numbers to render, with null marking an ellipsis.
     * Always shows first, last, current and its neighbours.
     */
    public function pages(int $eachSide = 2): array
    {
        if ($this->lastPage <= 7) {
            return range(1, $this->lastPage);
        }

        $pages = [1];
        $start = max(2, $this->currentPage - $eachSide);
        $end   = min($this->lastPage - 1, $this->currentPage + $eachSide);

        if ($start > 2) {
            $pages[] = null;
        }
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        if ($end < $this->lastPage - 1) {
            $pages[] = null;
        }
        $pages[] = $this->lastPage;

        return $pages;
    }

    /** Render a Bootstrap 5 pagination component. */
    public function render(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0 flex-wrap">';

        $prevDisabled = $this->currentPage <= 1 ? ' disabled' : '';
        $html .= sprintf(
            '<li class="page-item%s"><a class="page-link" href="%s" aria-label="Previous"><i class="bi bi-chevron-left"></i></a></li>',
            $prevDisabled,
            $this->currentPage <= 1 ? '#' : e($this->urlFor($this->currentPage - 1))
        );

        foreach ($this->pages() as $page) {
            if ($page === null) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                continue;
            }
            $active = $page === $this->currentPage ? ' active' : '';
            $html .= sprintf('<li class="page-item%s"><a class="page-link" href="%s">%d</a></li>',
                $active, e($this->urlFor($page)), $page);
        }

        $nextDisabled = $this->currentPage >= $this->lastPage ? ' disabled' : '';
        $html .= sprintf(
            '<li class="page-item%s"><a class="page-link" href="%s" aria-label="Next"><i class="bi bi-chevron-right"></i></a></li>',
            $nextDisabled,
            $this->currentPage >= $this->lastPage ? '#' : e($this->urlFor($this->currentPage + 1))
        );

        return $html . '</ul></nav>';
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'total'        => $this->total,
                'per_page'     => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page'    => $this->lastPage,
                'from'         => $this->from,
                'to'           => $this->to,
            ],
        ];
    }
}
