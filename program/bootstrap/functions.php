<?php

// View helper function: escaping. All strings must go through this function unless it is already proven to be XSS-safe.

function e($input, $preserve_line_breaks = false)
{
    $escaped = htmlspecialchars($input, ENT_COMPAT, 'UTF-8', false);
    $output = $preserve_line_breaks ? nl2br($escaped, true) : $escaped;
    echo $output;
    return $output;
}

// View helper function: filesize formatting.

function f($input)
{
    $input = (int)$input;
    if ($input < 1024) return e($input . 'B');
    if ($input < 1048576) return e(number_format($input / 1024, 1, '.', '') . 'K');
    if ($input < 1073741824) return e(number_format($input / 1048576, 1, '.', '') . 'M');
    if ($input < 1099511627776) return e(number_format($input / 1073741824, 1, '.', '') . 'G');
    return e(number_format($input / 1099511627776, 1, '.', '') . 'T');
}

// View helper function: time formatting.

function t($input)
{
    if (!ctype_digit($input)) $input = strtotime($input);
    $diff = time() - $input;
    if ($diff < 0) return e('the future');
    if ($diff < 60) return e('just now');
    if ($diff < 3600) return e(($i = round($diff / 60)) . ' minute' . ($i == 1 ? '' : 's') . ' ago');
    if ($diff < 86400) return e(($i = round($diff / 3600)) . ' hour' . ($i == 1 ? '' : 's') . ' ago');
    if ($diff < 864000) return e(($i = round($diff / 86400)) . ' day' . ($i == 1 ? '' : 's') . ' ago');
    return e(date('d M Y', $input));
}

// View helper function: relative URL generator.

function u( /* args */ )
{
    static $base = false;
    if (!$base) $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $args = func_get_args();
    return e(str_replace('//', '/', $base . '/' . implode('/', $args)));
}

// Check for HTTPS status.

function is_https()
{
    if (isset($_SERVER['HTTP_X_NFSN_HTTPS']) && $_SERVER['HTTP_X_NFSN_HTTPS'] === 'yes') return true;
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] > 0)) return true;
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443) return true;
    return false;
}

// Third-party library loader.

function load_third_party($library)
{
    switch ($library)
    {
        case 'beaver':
            include_once BASEDIR . '/program/thirdparty/beaver/beaver.php';
            break;
        
        case 'mimeparser':
            include_once BASEDIR . '/program/thirdparty/mimeparser/rfc822_addresses.php';
            break;
        
        case 'phpass':
            include_once BASEDIR . '/program/thirdparty/phpass/PasswordHash.php';
            break;
        
        case 'swiftmailer':
            include_once BASEDIR . '/program/thirdparty/swiftmailer/lib/swift_required.php';
            break;
    }
}
