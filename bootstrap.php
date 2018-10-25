<?php
// file extensions
define('EXT_CLASS', '.class.php');
define('EXT_TPL', '.tpl.html');
define('EXT_MOD', '.inc.php');
define('EXT_HTML', '.html');
define('EXT_UNIT', '.unit.php');
define('EXT_LIB', '.php');

define('ONPHP_VERSION', 'master');


// system settings
error_reporting(E_ALL | E_STRICT);
set_error_handler(
    function ($code, $string, $file, $line, $context)
    {
        if (error_reporting() == 0 && strpos($file, '/vendor/') !== false) {
            // silented by "@", this is only allowed for external libs
            return;
        }
        throw new BaseException($string, $code);
    },
    E_ALL | E_STRICT
);
ignore_user_abort(true);

// paths
define('ONPHP_ROOT_PATH', dirname(__FILE__).DIRECTORY_SEPARATOR);
define('ONPHP_CORE_PATH', ONPHP_ROOT_PATH.'core'.DIRECTORY_SEPARATOR);
define('ONPHP_MAIN_PATH', ONPHP_ROOT_PATH.'main'.DIRECTORY_SEPARATOR);
define('ONPHP_META_PATH', ONPHP_ROOT_PATH.'meta'.DIRECTORY_SEPARATOR);
define('ONPHP_UI_PATH', ONPHP_ROOT_PATH.'UI'.DIRECTORY_SEPARATOR);
define('ONPHP_LIB_PATH', ONPHP_ROOT_PATH.'lib'.DIRECTORY_SEPARATOR);

function onphpInit($tempPath, $defaultTimezone, $ipcPerms) {
    if (!defined('ONPHP_TEMP_PATH')) {
        if (!$tempPath) {
            $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'onPHP'.DIRECTORY_SEPARATOR;
        }
        define('ONPHP_TEMP_PATH', $tempPath);
    }

    date_default_timezone_set($defaultTimezone ?: 'Europe/Moscow');

    if (!defined('ONPHP_IPC_PERMS')) {
        define('ONPHP_IPC_PERMS', $ipcPerms ?: 0660);
    }
}