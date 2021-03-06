<?php

/**
 * Project:            	CTRev
 * @file                admincp/modules/styles.php
 *
 * @page 	  	http://ctrev.cyber-tm.ru/
 * @copyright         	(c) 2008-2012, Cyber-Team
 * @author 	  	The Cheat <cybertmdev@gmail.com>
 * @name 		Управление темами
 * @version           	1.00
 */
if (!defined('INSITE'))
    die("Remote access denied!");

class styles_man {

    /**
     * Доступные папки
     * @var array $spaths
     */
    public static $spaths = array(
        TEMPLATES_PATH,
        'js',
        'css');

    /**
     * Разрешённые типы файлов для шаблонов
     * @var array $allowed_types
     */
    protected $allowed_types = array(
        TEMPLATES_PATH => array('tpl', 'xtpl'),
        'js' => 'js',
        'css' => 'css'
    );

    /**
     * Инициализация управления темами
     * @return null
     */
    public function init() {
        $POST = globals::g('POST');
        lang::o()->get('admin/styles');
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
                $_POST['content'] = $POST['content'];
                $this->save($_POST);
                break;
            case "search":
                if ($_GET['results']) {
                    $_POST['search'] = $POST['search'];
                    $this->search($_POST['id'], $_POST);
                } else {
                    tpl::o()->assign('apaths', self::$spaths);
                    tpl::o()->display('admin/styles/search.tpl');
                }
                break;
            default:
                $this->show();
                break;
        }
    }

    /**
     * Поиск в темах
     * @param string $name имя темы
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
        if (!validfolder($name, THEMES_PATH))
            throw new EngineException;
        if (!$what || mb_strlen($what) < 2)
            throw new EngineException('nothing_selected');
        $arr = array();
        $where = (array) $where;
        foreach ($where as $w) {
            if (!self::$spaths[$w])
                return;
            /* @var $search search */
            $search = n("search");
            $res = $search->search_infiles(THEMES_PATH . '/' . $name . '/' . self::$spaths[$w], $what, $regexp);
            $a = array();
            foreach ($res as $k => $r)
                $a[self::$spaths[$w] . '/' . $k] = $r;
            if ($arr)
                $arr = array_merge($arr, $a);
            else
                $arr = $a;
        }
        tpl::o()->assign('row', $arr);
        tpl::o()->assign('id', $name);
        $data['search'] = $data['what'];
        unset($data['what']);
        tpl::o()->assign('postdata', http_build_query($data));
        tpl::o()->display('admin/styles/results.tpl');
    }

    /**
     * Добавление/Редактирование темы
     * @param string $name имя темы
     * @param string $f2e файл
     * @param bool $add добавление?
     * @return null
     * @throws EngineException
     */
    protected function add($name, $f2e, $add = false) {
        if (!validfolder($name, THEMES_PATH))
            throw new EngineException;
        $f2e = validpath($f2e, false, self::$spaths);
        $folder = dirname($f2e) . '/';
        $p = mb_strpos($f2e, '/');
        $ffu = mb_substr($f2e, 0, $p);
        $sf2e = mb_substr($f2e, $p + 1);
        if ($folder == './')
            $folder = '';
        $fpath = ROOT . THEMES_PATH . '/' . $name . '/' . $f2e;
        if (is_dir($fpath) || !file_exists($fpath))
            $add = true;
        if (!$add)
            $contents = file_get_contents($fpath);
        tpl::o()->assign('id', $name);
        tpl::o()->assign('parent', $add ? $f2e : $folder);
        tpl::o()->assign('filename', $sf2e);
        tpl::o()->assign('folder', $ffu);
        tpl::o()->assign('file', !$add ? $sf2e : '');
        tpl::o()->assign('is_writable', is_writable($fpath));
        tpl::o()->assign('contents', $contents);
        tpl::o()->display('admin/styles/add.tpl');
    }

    /**
     * Выбор файла темы
     * @param string $name тема
     * @param string $folder выбранная дирректория
     * @return null
     */
    protected function files($name, $folder = null) {
        display::o()->filechooser(THEMES_PATH, $name, $folder, self::$spaths);
    }
    
    /**
     * Проверка, является ли данная тема темой АЦ
     * @param string $row тема
     * @return bool true, если не является
     */
    public function admin_check($row) {
        if (strtolower($row) == strtolower(ADMIN_THEME))
            return false;
        return true;
    }

    /**
     * Вывод списка тем
     * @return null 
     */
    protected function show() {
        $rows = file::o()->open_folder(THEMES_PATH, true);
        $rows = array_filter($rows, array($this, "admin_check"));
        tpl::o()->assign('rows', $rows);
        tpl::o()->register_modifier('get_style_conf', array(tpl::o(), 'init_cfg'));
        tpl::o()->display('admin/styles/index.tpl');
    }

    /**
     * Сохранение файла темы
     * @param array $data массив данных файла темы
     * @return null
     * @throws EngineException 
     */
    public function save($data) {
        $admin_file = globals::g('admin_file');
        $cols = array(
            'name' => 'id',
            'of2e' => 'file',
            'f2e' => 'filename',
            'folder',
            'content');
        extract(rex($data, $cols));
        if (!validfolder($name, THEMES_PATH))
            throw new EngineException;
        if (!in_array($folder, self::$spaths))
            throw new EngineException('styles_wrong_filename');
        $types = implode('|', array_map('mpc', (array) $this->allowed_types[$folder]));
        $regexp = '/^([\w\.\/]+)\.(' . $types . ')$/si';
        $f2e = validpath($f2e);
        $of2e = validpath($of2e);
        if (!preg_match($regexp, $f2e) || ($of2e && !preg_match($regexp, $of2e)))
            throw new EngineException('styles_wrong_filename');
        $opath = THEMES_PATH . "/" . $name . '/' . $folder . '/';
        $path = ROOT . $opath;
        if ($f2e != $of2e && file_exists($path . $f2e))
            throw new EngineException('styles_file_exists');
        if ($of2e != $f2e && $of2e)
            unlink($path . $of2e);
        file::o()->write_file($content, $opath . $f2e);
        if (!$of2e)
            log_add('changed_style_file', 'admin', array($f2e, $of2e, $name));
        else
            log_add('added_style_file', 'admin', array($f2e, $name));
        furl::o()->location($admin_file);
    }

}

class styles_man_ajax {

    /**
     * Инициализация AJAX-части модуля
     * @return null
     * @throws EngineException
     */
    public function init() {
        $POST = globals::g('POST');
        $act = $_GET['act'];
        $name = $_POST['id'];
        if (!validfolder($name, THEMES_PATH))
            throw new EngineException;
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
     * Замена в теме
     * @param string $name имя темы
     * @param array $data данные поиска
     * @return null
     */
    public function replace($name, $data) {
        $cols = array(
            'what' => 'search',
            'with',
            'regexp',
            'files');
        extract(rex($data, $cols));
        if (!$what)
            return;
        $regexp = (bool) $regexp;
        $dir = THEMES_PATH . '/' . $name;
        if (!$files)
            $files = $dir;
        else {
            if (!is_array($files))
                $files = (array) $files;
            foreach ($files as $k => $v) {
                $v = validpath($v, false, styles_man::$spaths);
                $files[$k] = $dir . '/' . $v;
            }
        }
        $search->replace_infiles($with)->search_infiles($files, $what, $regexp);
        log_add('replaced_in_style', 'admin', $name);
    }

    /**
     * Удаление файла/папки
     * @param string $name имя темы
     * @param string $f2d имя файла/папки
     * @return null
     * @throws EngineException
     */
    public function delete_file($name, $f2d) {
        $f2d = validpath($f2d, false, styles_man::$spaths);
        if (preg_match('/^\/*(' . implode('|', array_map('mpc', styles_man::$spaths)) . ')\/*$/siu', trim($f2d)))
            throw new EngineException;
        file::o()->unlink_folder(THEMES_PATH . '/' . $name . '/' . $f2d);
        log_add('deleted_style_file', 'admin', array($f2d, $name));
    }

    /**
     * Удаление темы
     * @param string $name имя темы
     * @return null
     */
    public function delete($name) {
        $rows = file::o()->open_folder(THEMES_PATH, true);
        if (count($rows) < 3) // Стандартная + АЦ + удаляемая
            return;
        if (config::o()->v('default_style') == $name) {
            $i = array_search($name, $rows);
            unset($rows[$i]);
            $this->bydefault(reset($rows));
        }
        file::o()->unlink_folder(THEMES_PATH . '/' . $name);
        log_add('deleted_style', 'admin', $name);
    }

    /**
     * Клонирование темы
     * @param string $name имя темы
     * @param string $newname новое имя темы
     * @return null
     * @throws EngineException
     */
    public function copy($name, $newname) {
        lang::o()->get('admin/styles');
        if (!validword($newname))
            throw new EngineException('styles_invalid_new_name');
        file::o()->copy_folder(THEMES_PATH . '/' . $name, THEMES_PATH . '/' . $newname);
        log_add('copied_style', 'admin', array($newname, $name));
    }

    /**
     * Присвоение темы по-умолчанию
     * @param string $name имя темы
     * @return null
     */
    public function bydefault($name) {
        if (config::o()->v('default_style') == $name)
            return;
        config::o()->set('default_style', $name);
        log_add('changed_config', 'admin');
    }

}

?>