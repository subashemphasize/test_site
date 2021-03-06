<?php

if (!defined('INCLUDED_AMEMBER_CONFIG')) 
    die("Direct access to this location is not allowed");

$notebook_page = 'MultiCards';
config_set_notebook_comment($notebook_page, 'MultiCards Configuration');
if (file_exists($rm = dirname(__FILE__)."/readme.txt"))
    config_set_readme($notebook_page, $rm);

add_config_field('payment.multicards.mer_id', 'MultiCards Merhcant ID',
    'text', "numeric value",
    $notebook_page, 
    '');
add_config_field('payment.multicards.page_id', 'MultiCards Page ID',
    'text', "numeric value",
    $notebook_page, 
    '');
add_config_field('payment.multicards.page_id2', 'Another MultiCards Page ID',
    'text', "numeric value, will be used at member.php page",
    $notebook_page, 
    '');
add_config_field('payment.multicards.title', 'Payment Method Title',
    'text', "displayed on signup page and on renewal page",
    $notebook_page, 
    '', '', '',
    array('default' => 'Multi-Cards'));
add_config_field('payment.multicards.description', 'Payment Method Description',
    'text', "displayed on signup page",
    $notebook_page, 
    '', '', '',
    array('default' => 'Multi-Cards Credit Card Processing'));
?>
