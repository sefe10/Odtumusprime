<?php
require __DIR__ . '/app/site.php';
show_page(strtolower($_GET['slug'] ?? ''));