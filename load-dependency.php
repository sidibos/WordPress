<?php

$vendor = require_once(realpath(dirname(__FILE__)).'/vendor');

$dependencies = [
	$vendor.'/ftlabs/logger/src/FTLabs',
	$vendor.'/ftlabs/helpers/src',
	$vendor.'/ftlabs/memcache/src'
];

foreach($dependencies as $folder){
	include_all_php($folder);
}


function include_all_php($folder){
	foreach (glob("{$folder}/*.php") as $filename)
	{
		if(file_exists($filename)){
			require_once $filename;
		} else {
			echo 'file do not exists '.$filename; die;
		}
	}
}

