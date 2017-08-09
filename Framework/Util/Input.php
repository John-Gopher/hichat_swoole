<?php
namespace Framework\Util;

use \Framework\Base\Object;

class Input extends Object
{

    function Input()
    {
        /* 数据过滤 */
        if (!get_magic_quotes_gpc()) {
            $_GET = addslashes_deep($_GET);
            $_POST = addslashes_deep($_POST);
            $_COOKIE = addslashes_deep($_COOKIE);
        }
    }

    function post($name, $empty = false)
    {
        $name = $this->clean($name);
        if (empty($_POST[$name])) {
            if (!$empty) {
                throw new \Exception("{$name}字段不能为空");
            }
            return '';
        }
        return $this->clean($_POST[$name]);
    }

    function get($name, $empty = false)
    {
        $name = $this->clean($name);
        if (empty($_GET[$name])) {
            if (!$empty) {
                throw new \Exception("{$name}字段不能为空");
            }
            return '';
        }
        return $this->clean($_GET[$name]);
    }

    function clean($val)
    {
        $scurity = load('Safety');
        return $scurity->clean($val);
    }

    function cookie($name)
    {
        if (!isset($_COOKIE[$name])) {
            return '';
        }
        return $this->clean($_COOKIE[$name]);
    }


}