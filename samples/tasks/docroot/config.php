<?php
set_include_path(realpath(dirname(__FILE__).'/../../../classes') . ';' . get_include_path());
require_once 'outlet/Outlet.php';
Outlet::addConfig(include 'outlet-config.php');