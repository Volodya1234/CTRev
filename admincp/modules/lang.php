<?php

/**
 * Project:            	CTRev
 * @file                admincp/modules/lang.php
 *
 * @page 	  	http://ctrev.cyber-tm.ru/
 * @copyright         	(c) 2008-2012, Cyber-Team
 * @author 	  	The Cheat <cybertmdev@gmail.com>
 * @name 		Управление языками
 * @version           	1.00
 */
if (!defined('INSITE'))
    die("Remote access denied!");

class lang_man {

    /**
     * Инициализация управления языковыми пакетами
     * @return null
     */
    public function init() {
        lang::o()->get('admin/languages');
        $act = $_GET['act'];
        switch ($act) {
            case "add":
            case "edit":
                try {
                    $this->add($_GET['id'], $_GET['file'], $act == "add");
                } catch (EngineException $e) {
                    $e->defaultCatch(true);
                }
                break;
            case "files":
                $this->files($_GET['id'], $_REQUEST['folder']);
                break;
            case "save":
                $_POST['values'] = $POST['values'];
                $this->save($_POST);
                break;
            case "search":
                if ($_GET['results']) {
                    $_POST['search'] = $POST['search'];
                    $this->search($_POST['id'], $_POST);
                }
                else
                    tpl::o()->display('admin/languages/search.tpl');
                break;
            default:
                $this->show();
                break;
        }
    }

    /**
     * Получение массива языковых переменных
     * @param string $file путь к файлу
     * @param array $matches спарсенный массив
     * @return array массив языковых перменных
     */
    public function get($file, &$matches = null) {
        if (!preg_match('/^' . LANGUAGES_PATH . '\/([a-z0-9\_\-]+)\/(.+)\.php$/siu', $file, $matches))
            return false;
        return lang::o()->get($matches[2], $matches[1], false);
    }

    /**
     * Сохранение заменённых перменных
     * @param string $f путь к файлу
     * @param array $arr массив изменённых значений
     * @param array $keys массив старых ключей
     * @return null
     */
    public function replace($f, $arr, $keys = null) {
        $languages = $this->get($f, $matches);
        if ($languages === false)
            return;
        foreach ($arr as $key => $value) {
            if (!validword($key))
                continue;
            if ($keys[$key])
                unset($languages[$keys[$key]]);
            $languages[$key] = $value;
        }
        lang::o()->set($matches[2], $languages, $matches[1]);
    }

    /**
     * Поиск в языковых пакетах
     * @param string $name имя языка
     * @param array $data массив данных
     * @return null
     * @throws EngineException
     */
    protected function search($name, $data) {
        $cols = array(
            'what' => 'search',
            'where',
            'regexp');
        $data = rex($data, $cols);
        extract($data);
        $regexp = (bool) $regexp;
        if (!validfolder($name, LANGUAGES_PATH))
            throw new EngineException;
        if (!$what || mb_strlen($what) < 2)
            throw new EngineException('nothing_selected');
        /* @var $search search */
        $search = n("search");
        $res = $search->search_infiles(LANGUAGES_PATH . '/' . $name, $what, $regexp, $where, array($this, "get"));
        tpl::o()->assign('row', $res);
        tpl::o()->assign('id', $name);
        $data['search'] = $data['what'];
        unset($data['what']);
        tpl::o()->assign('postdata', http_build_query($data));
        tpl::o()->display('admin/languages/results.tpl');
    }

    /**
     * Добавление/Редактирование языкового файла
     * @param string $name языковой пакет
     * @param string $f2e файл
     * @param bool $add добавление?
     * @return null
     * @throws EngineException
     */
    protected function add($name, $f2e, $add = false) {
        if (!validfolder($name, LANGUAGES_PATH))
            throw new EngineException;
        $f2e = validpath($f2e);
        $folder = dirname($f2e) . '/';
        if ($folder == './')
            $folder = '';
        $fpath = ROOT . LANGUAGES_PATH . '/' . $name . '/' . $f2e;
        $f2e = preg_replace('/\.php$/siu', '', $f2e);
        $e = lang::presplitter . 'MAIN';
        if (!$add) {
            if (!file_exists($fpath))
                throw new EngineException;
            tpl::o()->assign('file', $f2e);
            $languages = lang::o()->get($f2e, $name, false);
            reset($languages);
            if (!is_numeric(key($languages)))
                array_unshift($languages, $e);
        }
        if (!$languages || !is_array($languages) || count($languages) < 2)
            $languages = array($e, '');
        tpl::o()->assign('id', $name);
        tpl::o()->assign('parent', $folder);
        tpl::o()->assign('filename', $f2e);
        tpl::o()->assign('is_writable', is_writable($fpath));
        tpl::o()->assign('languages', $languages);
        tpl::o()->register_modifier('cut_langsplitter', array(lang::o(), "cut_splitter"));
        tpl::o()->display('admin/languages/add.tpl');
    }

    /**
     * Выбор языкового файла
     * @param string $name языковой пакет
     * @param string $folder выбранная дирректория
     * @return null
     */
    protected function files($name, $folder = null) {
        display::o()->filechooser(LANGUAGES_PATH, $name, $folder);
    }

    /**
     * Вывод списка категорий
     * @return null 
     */
    protected function show() {
        $rows = file::o()->open_folder(LANGUAGES_PATH, true);
        tpl::o()->assign('rows', $rows);
        tpl::o()->display('admin/languages/index.tpl');
    }

    /**
     * Построение языкового массива
     * @param array $values массив значений
     * @param array $keys массив ключей
     * @return array языковой массив
     */
    protected function build_arr($values, $keys) {
        $was = array();
        $arr = array();
        foreach ($values as $key => $value) {
            if (isset($keys[$key]))
                $key = $keys[$key];
            $key = (string) $key;
            $value = (string) $value;
            if ($key == '0') {
                if (!validword($value))
                    continue;
                $value = mb_strtoupper($value);
                $arr[] = $value;
                continue;
            }
            if (!$key || !$value)
                continue;
            if (!validword($key) || $was[$key])
                continue;
            $was[$key] = true;
            $value = str_replace('\n', "\n", $value);
            $arr[$key] = $value;
        }
        return $arr;
    }

    /**
     * Сохранение языкового файла
     * @param array $data массив данных языкового файла
     * @return null
     * @throws EngineException 
     */
    public function save($data) {
        $admin_file = globals::g('admin_file');
        $cols = array(
            'name' => 'id',
            'of2e' => 'file',
            'f2e' => 'filename',
            'values',
            'keys');
        extract(rex($data, $cols));
        if (!validfolder($name, LANGUAGES_PATH))
            throw new EngineException;
        if (!$values)
            throw new EngineException('languages_no_values');
        $of2e = preg_replace('/\.php$/siu', '', $of2e);
        $of2e = validpath($of2e);
        $pp = LANGUAGES_PATH . '/' . $name . '/';
        $ppr = ROOT . $pp;
        $regexp = '/^([\w\.\/]+)(\.php)?$/si';
        $f2e = validpath($f2e);
        if (!preg_match($regexp, $f2e, $matches) || !preg_match($regexp, $of2e))
            throw new EngineException('languages_wrong_filename');
        $f2e = rtrim($matches[1], '/');
        if ($f2e != $of2e && file_exists($ppr . $f2e . '.php'))
            throw new EngineException('languages_file_exists');
        if ($of2e != $f2e && $of2e)
            unlink($ppr . $of2e . '.php');
        $arr = $this->build_arr($values, $keys);
        lang::o()->set($f2e, $arr, $name);
        if (!$of2e)
            log_add('changed_language_file', 'admin', array($f2e, $of2e, $name));
        else
            log_add('added_language_file', 'admin', array($f2e, $name));
        furl::o()->location($admin_file);
    }

}

class lang_man_ajax {

    /**
     * Инициализация AJAX-части модуля
     * @return null
     */
    public function init() {
        $POST = globals::g('POST');
        $act = $_GET['act'];
        $name = $_POST['id'];
        if (!validfolder($name, LANGUAGES_PATH))
            die();
        switch ($act) {
            case "replace":
                $_POST['search'] = $POST['search'];
                $_POST['with'] = $POST['with'];
                $this->replace($name, $_POST);
                break;
            case "clone":
                $this->copy($name, $_POST['new']);
                break;
            case "delete":
                $this->delete($name);
                break;
            case "delete_file":
                $this->delete_file($name, $_POST['file']);
                break;
            case "default":
                $this->bydefault($name);
                break;
        }
        ok();
    }

    /**
     * Замена в языковых пакетах
     * @param string $name имя языка
     * @param array $data данные поиска
     * @return null
     */
    public function replace($name, $data) {
        $cols = array(
            'what' => 'search',
            'with',
            'where',
            'regexp',
            'files');
        extract(rex($data, $cols));
        if (!$what)
            return;
        $regexp = (bool) $regexp;
        /* @var $obj lang_man */
        $obj = plugins::o()->get_module('lang', 1);
        $dir = LANGUAGES_PATH . '/' . $name;
        if (!$files)
            $files = $dir;
        else {
            if (!is_array($files))
                $files = (array) $files;
            foreach ($files as $k => $v) {
                $v = validpath($v);
                $files[$k] = $dir . '/' . $v;
            }
        }
        $search->replace_infiles($with, array($obj, 'replace'))->search_infiles($files, $what, $regexp, $where, array($obj, "get"));
        log_add('replaced_in_language', 'admin', $name);
    }

    /**
     * Удаление файла/папки
     * @param string $name имя языка
     * @param string $f2d имя файла/папки
     * @return null
     */
    public function delete_file($name, $f2d) {
        $f2d = validpath($f2d);
        file::o()->unlink_folder(LANGUAGES_PATH . '/' . $name . '/' . $f2d);
        log_add('deleted_language_file', 'admin', array($f2d, $name));
    }

    /**
     * Удаление языкового пакета
     * @param string $name имя языка
     * @return null
     */
    public function delete($name) {
        $rows = file::o()->open_folder(LANGUAGES_PATH, true);
        if (count($rows) < 2)
            return;
        if (config::o()->v('default_lang') == $name) {
            $i = array_search($name, $rows);
            unset($rows[$i]);
            $this->bydefault(reset($rows));
        }
        file::o()->unlink_folder(LANGUAGES_PATH . '/' . $name);
        log_add('deleted_language', 'admin', $name);
    }

    /**
     * Клонирование языкового пакета
     * @param string $name имя языка
     * @param string $newname новое имя языка
     * @return null
     * @throws EngineException
     */
    public function copy($name, $newname) {
        lang::o()->get('admin/languages');
        if (!validword($newname))
            throw new EngineException('languages_invalid_new_name');
        file::o()->copy_folder(LANGUAGES_PATH . '/' . $name, LANGUAGES_PATH . '/' . $newname);
        log_add('copied_language', 'admin', array($newname, $name));
    }

    /**
     * Присвоение языкового пакета по-умолчанию
     * @param string $name имя языка
     * @return null
     */
    public function bydefault($name) {
        if (config::o()->v('default_lang') == $name)
            return;
        config::o()->set('default_lang', $name);
        log_add('changed_config', 'admin');
    }

}

?>