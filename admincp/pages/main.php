<?php

/**
 * Project:            	CTRev
 * @file                admincp/pages/main.php
 *
 * @page 	  	http://ctrev.cyber-tm.ru/
 * @copyright         	(c) 2008-2012, Cyber-Team
 * @author 	  	The Cheat <cybertmdev@gmail.com>
 * @name 		Индексная страница АЦ
 * @version           	1.00
 */
if (!defined('INSITE'))
    die("Remote access denied!");

class main_page {

    /**
     * Инициализация индексной страницы АЦ
     * @return null
     */
    public function init() {
        lang::o()->get('admin/pages/main');
        if (!($a = cache::o()->read('admin_stats'))) {
            $uc = db::o()->count_rows('users');
            $tc = db::o()->count_rows('torrents');
            $cc = db::o()->count_rows('comments');
            $a = array('uc' => $uc, 'tc' => $tc, 'cc' => $cc);
            cache::o()->write($a);
        }
        tpl::o()->assign('PHP_VERSION', PHP_VERSION);
        tpl::o()->assign('MYSQL_VERSION', db::o()->version());
        tpl::o()->assign('row', $a);
        tpl::o()->display('admin/pages/main.tpl');
    }

}

class main_page_ajax {

    /**
     * Инициализация AJAX-части модуля
     * @return null
     */
    public function init() {
        lang::o()->get('admin/pages/main');
        if (!users::o()->perm('system'))
            return;
        $act = $_GET["act"];
        users::o()->admin_mode();
        /* @var $etc etc */
        $etc = n("etc");
        switch ($act) {
            case "cleanup":
                /* @var $cleanup cleanup */
                $cleanup = n("cleanup");
                $cleanup->execute(true);
                break;
            case "cache":
                cache::o()->clear();
                break;
            case "cache_tpl":
                cache::o()->clear_tpl();
                break;
            case "stats":
                $st = stats::o()->read();
                foreach ($st as $s => $v)
                    stats::o()->write($s, 0);
                break;
            case "logs":
                /* @var $logs logs_man_ajax */
                $logs = plugins::o()->get_module('logs', 1, true);
                $logs->clear();
                break;
            case "peers":
                db::o()->truncate_table('peers');
                db::o()->update(array('leechers' => 0,
                    'seeders' => 0), 'torrents');
                break;
            case "downloaded":
                db::o()->truncate_table('downloaded');
                db::o()->update(array('downloaded' => 0), 'torrents');
                break;
            case "chat":
                /* @var $chat chat */
                $chat = plugins::o()->get_module('chat');
                $chat->truncate();
                break;
            case "pm":
                /* @var $pm messages_ajax */
                $pm = plugins::o()->get_module('messages', false, true);
                $pm->clear();
                break;
            case "ratings":
                $r = db::o()->query('SELECT toid, type FROM ratings GROUP BY toid, type');
                /* @var $rating rating */
                $rating = n("rating");
                while ($row = db::o()->fetch_assoc($r))
                    $rating->change_type($row['type'])->clear($row['toid']);
                break;

            // Далее: Важная часть сайта, да
            case "torrents":
                $r = db::o()->query('SELECT id FROM torrents');
                while (list($id) = db::o()->fetch_row($r))
                    $etc->delete_torrent($id);
                break;
            case "comments":
                /* @var $comments comments */
                $comments = n("comments");
                $comments->clear(null, true);
                break;
            case "polls":
                /* @var $polls polls */
                $polls = n("polls");
                $polls->clear();
                break;
            case "news":
                /* @var $news news_ajax */
                $news = plugins::o()->get_module('news', false, true);
                $news->clear();
                break;
            case "bans":
                $r = db::o()->query('SELECT id FROM bans');
                while (list($id) = db::o()->fetch_row($r))
                    $etc->unban_user(null, $id);
                break;
            case "warnings":
                $r = db::o()->query('SELECT id FROM warnings');
                while (list($id) = db::o()->fetch_row($r))
                    $etc->unwarn_user(null, null, $id);
                break;
        }
        log_add('system_clean', 'admin', array(lang::o()->v('main_page_clear_' . $act), $act));
        die("OK!");
    }

}

?>