<?php

defined('ABSPATH') || exit;
if (!defined('WTRACKT_LOG_DIR')) {
    define('WTRACKT_LOG_DIR', WP_CONTENT_DIR . '/logs/track-cart/');
}

function wtrackt_log_file($msg, $name = '')
{

    $trace = debug_backtrace();

    $name = ('' == $name) ? $trace[1]['function'] : $name;

    $error_dir = WTRACKT_LOG_DIR . '/' . date('Y-m') . '.txt';
    $time = date('d-m-Y H:i:s ');
    $msg = print_r($msg, true);
    $text = $time . ' - ' . $name . "  |  " . $msg;
    @file_put_contents($error_dir, $text . "\n", FILE_APPEND | FILE_TEXT);

}
