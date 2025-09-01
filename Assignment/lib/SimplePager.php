<?php

class SimplePager {
    public $limit;      // Page size
    public $page;       // Current page
    public $item_count; // Total item count
    public $page_count; // Total page count
    public $result;     // Result set (array of records)
    public $count;      // Item count on the current page

    public function __construct($query, $params, $limit, $page) {
        global $_db;

        // Set [limit] and [page]

        $this->limit = (is_numeric($limit) && $limit > 0) ? (int)$limit : 12;
        $this->page  = (is_numeric($page) && $page > 0) ? (int)$page : 1;

        // Remove ORDER BY for count query
        $count_query = preg_replace('/ORDER BY[\s\S]+$/i', '', $query);
        // Use subquery for count
        $count_query = "SELECT COUNT(*) FROM ($count_query) AS sub";

        $stm = $_db->prepare($count_query);
        $stm->execute($params);
        $this->item_count = (int) $stm->fetchColumn();

        $this->page_count = (int) ceil($this->item_count / $this->limit);

        // Add LIMIT and OFFSET for pagination
        $offset = ($this->page - 1) * $this->limit;
        $paged_query = $query . " LIMIT $this->limit OFFSET $offset";
        $stm = $_db->prepare($paged_query);
        $stm->execute($params);
        $this->result = $stm->fetchAll(PDO::FETCH_ASSOC);

        // Set [count]
        $this->count = count($this->result);
    }

    public function html($href = '', $attr = '') {
        if ($this->page_count <= 1) return '';

        // Generate pager (html)
        $maxPagesToShow = 5;
        $half = floor($maxPagesToShow / 2);
        $start = max(1, $this->page - $half);
        $end = min($this->page_count, $start + $maxPagesToShow - 1);
        $start = max(1, $end - $maxPagesToShow + 1);

        $prev = max($this->page - 1, 1);
        $next = min($this->page + 1, $this->page_count);

        echo "<nav class='pager' $attr>";

        // First and Previous buttons
        if ($this->page > 1) {
            echo "<a href='?page=1&$href' class='first'>First</a>";
            echo "<a href='?page=$prev&$href' class='prev'>Previous</a>";
        }

        // Page numbers
        for ($p = $start; $p <= $end; $p++) {
            $class = $p == $this->page ? 'active' : '';
            echo "<a href='?page=$p&$href' class='page-num $class'>$p</a>";
        }

        // Next and Last buttons
        if ($this->page < $this->page_count) {
            echo "<a href='?page=$next&$href' class='next'>Next</a>";
            echo "<a href='?page=$this->page_count&$href' class='last'>Last</a>";
        }

        echo "</nav>";
    }
}   
