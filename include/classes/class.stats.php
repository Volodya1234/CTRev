<?php

/**
 * Project:            	CTRev
 * @file                include/classes/class.stats.php
 *
 * @page 	  	http://ctrev.cyber-tm.ru/
 * @copyright         	(c) 2008-2012, Cyber-Team
 * @author 	  	The Cheat <cybertmdev@gmail.com>
 * @name                Ведение статистики на сайте
 * @version           	1.00
 */
if (!defined('INSITE'))
    die("Remote access denied!");

final class stats {

    /**
     * Массив значений статистики
     * @var array $res
     */
    private $res = array();

    /**
     * Чтение значения из статистики
     * @param string $name поле
     * @return string значение
     */
    public function read($name = null) {
        if ($name)
            return $this->res[$name];
        else
            return $this->res;
    }

    /**
     * Запись значения в статистику
     * @param string $name поле
     * @param string $value значение
     * @return null
     */
    public function write($name, $value) {
        if ($name == DATABASE_STATS && !defined('ININSTALL'))
            return;
        if (!isset($this->res[$name])) {
            $this->res[$name] = $value;
            $r = db::o()->insert(array("name" => $name, "value" => $value), "stats");
            return;
        }
        $this->res[$name] = $value;
        db::o()->p($name)->update(array("value" => $value), "stats", "WHERE name=? LIMIT 1");
    }

    /**
     * Удаление поля статистики
     * @param string $name поле
     * @return null
     */
    public function remove($name) {
        if ($name == DATABASE_STATS && !defined('ININSTALL'))
            return;
        if (!isset($this->res[$name]))
            return;
        unset($this->res[$name]);
        db::o()->p($name)->delete("stats", "WHERE name=? LIMIT 1");
    }

    // Реализация Singleton

    /**
     * Объект данного класса
     * @var tpl $o
     */
    private static $o = null;

    /**
     * Конструктор? А где конструктор? А нет его.
     * @return null 
     */
    private function __construct() {
        if (!$this->res) {
            $res = db::o()->query("SELECT * FROM stats");
            $this->res = db::o()->fetch2array($res, null, array("name" => "value"));
        }
    }

    /**
     * Не клонируем
     * @return null 
     */
    private function __clone() {
        
    }

    /**
     * И не десериализуем
     * @return null 
     */
    private function __wakeup() {
        
    }

    /**
     * Получение объекта класса
     * @return stats $this
     */
    public static function o() {
        if (!self::$o)
            self::$o = new self();
        return self::$o;
    }

}

?>