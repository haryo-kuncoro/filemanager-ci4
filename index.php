<?php
// Cadangan terakhir bila mod_rewrite tidak tersedia: arahkan ke folder public/.
// Disarankan mengaktifkan mod_rewrite atau mengarahkan document root ke public/.
header('Location: public/', true, 302);
exit;
