<?php
/*
Project constants
*/

const LAYOUT_PATH = '/layout/dist';
const LAYOUT_DIR = '/local/layout/dist/';
const AJAX_PATH = '/local/ajax/';
// Счётчик Яндекс.Метрики, см. header.php (ym(...,'init'...) и getClientID)
const YANDEX_METRIKA_COUNTER_ID = 44219954;

// Секреты (API-ключи) — в constants_local.php, файл не в git
require_once __DIR__ . '/constants_local.php';
