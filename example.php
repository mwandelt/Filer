<?php

require_once 'filer.class.php';
$filer = new Filer( __DIR__ );
$filer->lock();
sleep(5);
$filer->write( 'example_data.php', 'This is my first data file' );
sleep(5);
var_dump( $filer->read('content.php') );
$filer->unlock();


// end of file example.php