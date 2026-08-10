<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/nexus_theme.php';

header('Content-Type: text/css; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'');
header('Cache-Control: private, max-age=300');

echo nexusThemeCustomCss();
