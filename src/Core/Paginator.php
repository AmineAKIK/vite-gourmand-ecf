<?php

namespace App\Core;

class Paginator
{
    public readonly int $total;
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $totalPages;
    public readonly int $offset;

    public function __construct(int $total, int $page, int $perPage = 25)
    {
        $this->perPage     = max(1, $perPage);
        $this->total       = max(0, $total);
        $this->totalPages  = (int)ceil($this->total / $this->perPage);
        $this->page        = max(1, min($page, max(1, $this->totalPages)));
        $this->offset      = ($this->page - 1) * $this->perPage;
    }

    public function hasPrev(): bool { return $this->page > 1; }
    public function hasNext(): bool { return $this->page < $this->totalPages; }

    /**
     * Génère les liens de pagination Bootstrap en conservant tous les paramètres GET existants.
     */
    public function renderLinks(string $pageParam = 'page'): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }

        $base   = $this->buildBaseUrl($pageParam);
        $html   = '<nav aria-label="Navigation des pages"><ul class="pagination pagination-sm justify-content-center mb-0">';

        // Prev
        if ($this->hasPrev()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base . ($this->page - 1) . '">‹</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">‹</span></li>';
        }

        // Pages numérotées (fenêtre de 5 autour de la page courante)
        $window = 2;
        $start  = max(1, $this->page - $window);
        $end    = min($this->totalPages, $this->page + $window);

        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base . '1">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $this->page) {
                $html .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $base . $i . '">' . $i . '</a></li>';
            }
        }
        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $base . $this->totalPages . '">' . $this->totalPages . '</a></li>';
        }

        // Next
        if ($this->hasNext()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base . ($this->page + 1) . '">›</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">›</span></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    private function buildBaseUrl(string $pageParam): string
    {
        $params = $_GET ?? [];
        unset($params[$pageParam]);
        $qs = http_build_query($params);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return htmlspecialchars($path . ($qs ? '?' . $qs . '&' : '?') . $pageParam . '=', ENT_QUOTES, 'UTF-8');
    }
}
