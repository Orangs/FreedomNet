<?php

define('IDS', DIRECTORY_SEPARATOR);
define('BASE_DIRECTORY', str_replace(IDS.'Install', IDS, getcwd()));
chdir(BASE_DIRECTORY);

if($_SERVER['REQUEST_URI'] != '/Install/complete?true')
    $_ENV['installation_in_progress'] = true;
if(strstr($_SERVER['REQUEST_URI'], 'ajax') || strstr($_SERVER['REQUEST_URI'], 'finalize'))
    unset($_ENV['installation_in_progress']);

require_once('Core'.IDS.'Core.php');

use Core\Libraries\FreedomCore\System\Manager as Manager;
Manager::LoadExtension('Installer', [$Smarty]);
use Core\Extensions\Installer as Installer;
$Installer = new Installer();

$Smarty->translate('Installation');