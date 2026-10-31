<?php

use CodeIgniter\Pager\PagerRenderer;

/**
 * Template pagination Bootstrap 5.
 *
 * @var PagerRenderer $pager
 */
$pager->setSurroundCount(2);
?>
<nav aria-label="Navigasi halaman">
    <ul class="pagination pagination-sm mb-0">
        <?php if ($pager->hasPrevious()) : ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="Pertama"><i class="bi bi-chevron-double-left"></i></a></li>
            <li class="page-item"><a class="page-link" href="<?= $pager->getPrevious() ?>" aria-label="Sebelumnya"><i class="bi bi-chevron-left"></i></a></li>
        <?php endif ?>

        <?php foreach ($pager->links() as $link) : ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $link['uri'] ?>"><?= $link['title'] ?></a>
            </li>
        <?php endforeach ?>

        <?php if ($pager->hasNext()) : ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getNext() ?>" aria-label="Berikutnya"><i class="bi bi-chevron-right"></i></a></li>
            <li class="page-item"><a class="page-link" href="<?= $pager->getLast() ?>" aria-label="Terakhir"><i class="bi bi-chevron-double-right"></i></a></li>
        <?php endif ?>
    </ul>
</nav>
