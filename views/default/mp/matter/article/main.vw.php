<?php
include_once dirname(dirname(dirname(__FILE__))) . '/inmp.vw.php';

$view['params']['layout-body'] = '/mp/matter/article/frame';
$view['params']['menu'] = '/page/mp/matter';
$view['params']['css'] = array(array('/mp/matter/article', 'main'));
$view['params']['global_js'] = array(array('tinymce/tinymce.min', 'resumable'), array('xxt.ui', true));
$view['params']['angular-modules'] = "'ngRoute','channel.matter.mp','ui.xxt'";
$view['params']['js'] = array(array('/mp', 'channel', true), array('/mp/matter/article', 'main', true));