<?php
session_start();
require_once SL_MODULES.'EselAdminPanel/EselPage.php';
class EselAdminPanel extends EselModule
{
    /**
     * Check for permissions on accessing the panel.
     *
     * @return Exception if not allowed
     */
    public static function beforeLoad()
    {
        if (!self::isLoggedIn()) {
          throw new Exception("Operation not permitted");
        }
    }

    public static function isLoggedIn()
    {
      $sessionName=md5(SL_ADMIN_NAME.SL_SECRET);
      $sessionValue=md5($_SERVER['REMOTE_ADDR'].SL_ADMIN_PASSWORD.SL_SECRET);
        if(isset($_SESSION[$sessionName])&&($_SESSION[$sessionName]==$sessionValue)){
          return true;
        }

        if(isset($_COOKIE[$sessionName])&&($_COOKIE[$sessionName]==$sessionValue)){
          return true;
        }

        return false;
    }

    public static function LogIn()
    {
        if((!isset($_POST['login']))||(!isset($_POST['password']))){
          throw new Exception("Not enough paramaters");
        }

        if(($_POST['login']!=SL_ADMIN_NAME)||(md5($_POST['password'])!=SL_ADMIN_PASSWORD)){
          throw new Exception("Invalid credentials");
        }
        $sessionName=md5(SL_ADMIN_NAME.SL_SECRET);
        $sessionValue=md5($_SERVER['REMOTE_ADDR'].SL_ADMIN_PASSWORD.SL_SECRET);
        $_SESSION[$sessionName]=$sessionValue;
        if(isset($_POST['rememberme'])){
          setcookie($sessionName, $sessionValue, time()+60*60*24*30, "/");
        }
    }

    /**
     * Pager for pages in directory.
     *
     * @param string $dir   directory relative to SL_PAGES
     * @param int    $start index to start from, optional, default 0
     * @param int    $limit items per page, optional, default =10
     * @param int    $all   1=>shows folders and all files,0=>only .html files, default to 0;
     *
     * @return [type] [description]
     */
    public static function getPagesList($dir = '', $start = 0, $limit = 10, $all = 0)
    {

        $pattern = '*.html';
        if ($all == 1) {
            $pattern = '*';
        }
        $config=self::getConfig(__CLASS__);
        $constructPages = function ($iterator) use ($config){
            $curr = $iterator->current();
            $file = $curr->getFilename();
            $path = Esel::fixPath($curr->getPath().'/'.$file);
            $page = new EselPage();
            $page->name = $file;
            $page->folder = is_dir($path);
            $page->path = Esel::fixPath(str_replace(SL_PAGES, '/', $path));
            if(isset($config->hidden_pages)){
              foreach($config->hidden_pages as $pat){
                if(($pat==$page->path)||preg_match($pat,$page->path)){
                  $page->hidden=true;
                }
              }
            }
            if ($page->folder) {
                $page->path .= '/';
            } else {
                $tmp = array();
                if (preg_match('/{# @name (.+)? #}/', file_get_contents($path), $tmp)) {
                    $page->name = $tmp[1];
                }
            }
            $page->url = $page->makeUrl($page->path);

            return $page;
        };
        Esel::loadModule('EselPaginator');
        $folder = Esel::fixPath(SL_PAGES.'/'.$dir.'/');

        return EselPaginator::getList($folder, $pattern, $start, $limit, $constructPages);
    }
    /**
     * Templates pager.
     *
     * @param string $dir   [description]
     * @param int    $start [description]
     * @param int    $limit [description]
     *
     * @return [type] [description]
     */
    public static function getTplList($dir = '', $start = 0, $limit = 0)
    {
        self::beforeLoad();

        $pattern = '/*.twig';
        $path = Esel::fixPath(SL_TEMPLATES.$dir);
        $constructTpls = function ($iterator) {
            $tpl = null;
            $curr = $iterator->current();
            $file = $curr->getFilename();
            $path = Esel::fixPath($curr->getPath().'/'.$file);

            $template = file_get_contents($path);
            if (!preg_match('/{# @hidden #}/', $template)) {
                $tpl = new stdClass();
                $tpl->name = $file;
                $tmp = array();
                if (preg_match('/{# @name (.+)? #}/', $template, $tmp)) {
                    $tpl->name = $tmp[1];
                }
                $tpl->path = ltrim(Esel::fixPath(str_replace(SL_TEMPLATES, '/', $path)), '/');
            }

            return $tpl;
        };
        Esel::loadModule('EselPaginator');
        $list = EselPaginator::getList($path, $pattern, $start, $limit, $constructTpls);
        foreach (glob($path.'/*', GLOB_ONLYDIR | GLOB_NOSORT) as $ndir) {
            $nlist = self::getTplList(str_replace($path, '', $ndir).'/', $start, $limit);
            $list['count'] += $nlist['count'];
            $list['items'] = array_merge($nlist['items'], $list['items']);
        }

        return $list;
    }
    /**
     * Page data parser.
     *
     * @param string $page Page path relative to SL_PAGES, if empty - looks for it in the $_GET['page']
     *
     * @return array $pageData of Exception
     */
    public static function getPageData($page = null)
    {
        self::beforeLoad();
        if (empty($page)) {
            if (!isset($_GET['page'])) {
                throw new Exception('You must define a page');
            }
            $page = Esel::clear($_GET['page']);
        }
        $pageData = new stdClass();
        if (isset($_GET['template'])) {
            $pageData->template = Esel::clear($_GET['template']);
        } else {
            $pageData->template = EselPage::getTemplate($page);
        }

        $pageData->path = $page;
        $pageData->url = EselPage::makeUrl($page);
        $pageData->blocks = EselPage::getPageBlocks($page);
        $pageData->name = $page;
        if (is_dir(SL_PAGES.$page)) {
            $pageData->url .= 'new-page/';
            $pageData->name = 'New Page';
            $pageData->new = true;
        }
        $tmp = array();
        if (preg_match('/{# @name (.+)? #}/', file_get_contents(SL_PAGES.$page), $tmp)) {
            $pageData->name = $tmp[1];
        }
        if (!empty($pageData->template)) {
            $pageData->fields = EselPage::getTemplateBlocks($pageData->template);
        }

        return $pageData;
    }

    /**
     * Deletes given page. Forever.
     *
     * @param string $page Page path relative to SL_PAGES
     *
     * @return Exception if couldn't delete it
     */
    public static function deletePage($path)
    {
        $fullpath = SL_PAGES.ltrim($path, '/');
        if (!file_exists($fullpath)) {
            return false;
        }
        if (is_dir($fullpath) || (!unlink($fullpath))) {
            throw new Exception("Couldn't delete page ".$path);
        }

        return true;
    }

    /**
     * Saves given data to specified page.
     *
     * @param string $page     Page path relative to SL_PAGES
     * @param string $template Twig template file path relative to SL_TEMPLATES
     * @param string $name     Page name
     * @param array  $blocks   Page {% blocks %}
     *
     * @return string compiled page
     */
    public static function savePage($path, $template="", $name="", $blocks = array(), $fields = array(), $old_path = '')
    {
        $path = ltrim($path, '/');
        if (empty($old_path)) {
            $old_path = $path;
        }

        self::deletePage($old_path);
        $path = ltrim($path, '/');
        $page='';
        if(!empty($template)){

          $page.= '{% extends "'.$template.'" %}';
        }
        if (!empty($name)) {
            $page .= '{# @name '.$name.' #}';
        }
        foreach ($blocks as $key => $value) {
            $page .= '{% block '.$key.' %}';
            $page .= html_entity_decode($value);
            $page .= '{% endblock %}';
        }

        foreach ($fields as $key => $value) {
          $tmp=explode("_",$key);
          if(count($tmp==2)){
            $table=$tmp[0];
            $field=$tmp[1];
            $entry=Esel::for_table($table)->create();
            $entry->set($field,$value);
            $entry->save();
          }
        }
        $folder = preg_replace("/[^\/]+\.htm(l|)$/", '', SL_PAGES.$path);
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        if (file_put_contents(SL_PAGES.$path, $page) === false) {
            throw new Exception("Couldn't save file ".SL_PAGES.$path.' with '.$page);
        }

        return $page;
    }
    /**
     * Lists modules with their statuses.
     *
     * @return array $list int "count", array "modules" of {"name","description","status"}
     */
    public static function getModulesList()
    {
        $list = array();
        $modules = scandir(SL_MODULES);
        $list['count'] = 0;
        $list['modules'] = array();
        foreach ($modules as $module) {
            if (($module != '.') && ($module != '..')) {
                $item = new stdClass();
                $item->name = $module;
                $item->status = EselModule::isSafe($module, false);
                $item->description = '';
                if (file_exists(SL_MODULES.$module.'/description.txt')) {
                    $item->description = file_get_contents(SL_MODULES.$module.'/description.txt');
                }
                ++$list['count'];
                array_push($list['modules'], $item);
            }
        }

        return $list;
    }


    public static function makePagesDir($name,$dir='')
    {
        self::beforeLoad();
        return mkdir(Esel::fixPath(SL_PAGES.$dir."/".$name),0755);
      }

    public static function removePagesDir($dir)
    {
      self::beforeLoad();
      return rmdir(Esel::fixPath(SL_PAGES.$dir));
    }
}
