<?php
$pagination_total = (int) $total_row['total'];
$pagination_last = (int) $total_pages;
$pagination_start = $start < $pagination_total ? $start + 1 : 0;
$pagination_end = min($start + $limit, $pagination_total);
$pagination_numbers = [];

if ($pagination_last <= 7 && $pagination_last > 0) {
    $pagination_numbers = range(1, $pagination_last);
} elseif ($pagination_last > 7 && $page <= 3) {
    $pagination_numbers = [...range(1, max(3, $page + 1)), $pagination_last - 1, $pagination_last];
} elseif ($pagination_last > 7 && $page >= $pagination_last - 2) {
    $pagination_numbers = [1, 2, ...range(min($page - 1, $pagination_last - 2), $pagination_last)];
} elseif ($pagination_last > 7) {
    $pagination_numbers = [1, $page - 1, $page, $page + 1, $pagination_last];
}

$pagination_href = static function (int $number) use ($pagination_filters): string {
    return htmlspecialchars('?' . http_build_query(['page' => $number] + $pagination_filters), ENT_QUOTES, 'UTF-8');
};
?>
<div class="dashboard-pagination">
    <p class="dashboard-pagination-summary">
        Menampilkan
        <?php if ($pagination_start === 0): ?>
            0
        <?php elseif ($pagination_start === $pagination_end): ?>
            <?= $pagination_start ?>
        <?php else: ?>
            <?= $pagination_start ?>&ndash;<?= $pagination_end ?>
        <?php endif; ?>
        dari <?= $pagination_total ?>
    </p>
    <?php if ($pagination_last > 1): ?>
        <nav class="dashboard-pagination-links" aria-label="Navigasi halaman daftar">
            <?php if ($page > 1): ?>
                <a class="dashboard-pagination-direction" href="<?= $pagination_href(min($page - 1, $pagination_last)) ?>"
                   rel="prev" aria-label="Halaman sebelumnya">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i><span>Sebelumnya</span>
                </a>
            <?php endif; ?>
            <?php $pagination_previous = 0; ?>
            <?php foreach ($pagination_numbers as $number): ?>
                <?php if ($number - $pagination_previous > 1): ?>
                    <span class="dashboard-pagination-gap" aria-hidden="true">&hellip;</span>
                <?php endif; ?>
                <a href="<?= $pagination_href($number) ?>" aria-label="Halaman <?= $number ?>"
                    <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                <?php $pagination_previous = $number; ?>
            <?php endforeach; ?>
            <?php if ($page < $pagination_last): ?>
                <a class="dashboard-pagination-direction" href="<?= $pagination_href($page + 1) ?>"
                   rel="next" aria-label="Halaman selanjutnya">
                    <span>Selanjutnya</span><i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
