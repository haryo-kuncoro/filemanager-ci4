<?php
// Dipakai oleh penangan pengecualian CI4 (mis. PageNotFoundException).
// Tampilan sama dengan halaman 404 aplikasi.
echo view('errors/not_found', ['message' => $message ?? '']);
