<?php
[$script, $file, $method, $json] = $argv + [null, 'start_game.php', 'GET', '{}'];

$_SERVER['REQUEST_METHOD'] = $method;
$_POST = json_decode($json, true) ?? [];  

require __DIR__ . '/../../api/' . $file;