<?php
import("lib.pkp.classes.plugins.GenericPlugin");
class MlurlPlugin extends GenericPlugin {
  function register($category, $path, $mainContextId = null) {
    $success = parent::register($category, $path, $mainContextId);
    if ($success && $this->getEnabled($mainContextId)) {
      // router
      HookRegistry::register('LoadHandler', array($this, 'handleLocales'));
      // filters
      HookRegistry::register('TemplateManager::display', array($this, 'handleTemplateDisplay'));
      // redirect
      HookRegistry::register('Request::redirect', array($this, 'redirectLocaleUrl'));
    }
    $this->_registerTemplateResource();
    return $success;
  }

  function getDisplayName() {
    return "Multilingual Url Plugin";
  }

  function getDescription() {
    return "Multilingual Url Plugin";
  }

  function getHandlerPath() {
    return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'pages';
  }

  function handleTemplateDisplay($hookName, $args) {

    $templateMgr =& $args[0];
    $template =& $args[1];
    $request = PKPApplication::getRequest();

    $templateMgr->registerFilter("output", array($this, 'languageUrlFilter'));

    return false;
  }

  function handleLocales($hookName, $args) {
    $page =& $args[0];
    $op =& $args[1];
    $path =& $args[2];

    $urlLocales = self::convertLocalesToUrl(AppLocale::getSupportedLocales());
    $pageLocale = self::convertLocale($page,true);

    if($this->getEnabled()) {
      if(in_array($page, $urlLocales)) {
          $request = Application::getRequest();

          // flip page and op
          $url_parts = array_filter(explode("/", parse_url($request->getCompleteUrl())['path']));
          if(in_array('index.php', $url_parts)) {
            $key = array_search('index.php', $url_parts);
            unset($url_parts[$key]);
          }
          $url_parts = array_values($url_parts);
          if(isset($url_parts[2])) {
            $page = $url_parts[2];
          }
          if(isset($url_parts[3])) {
            $op = $url_parts[3];
          } else {
            $op = 'index';
          }
          // some pages can have other path
          $page = $this->pageFilter($page);
          // rewrite locale in session
          $session = $request->getSession();
          $currentLocale = $session->getSessionVar('currentLocale');
          if($currentLocale != $pageLocale) {
            $session->setSessionVar('currentLocale', $pageLocale);
            $request->redirectUrl($request->getCompleteUrl());
          }
          switch($page) {
            case 'article': require_once(__DIR__ . "/pages/article/index.php");
            break;
            default:  if(is_file("pages/{$page}/index.php")) {
                        require_once("pages/{$page}/index.php");
                      } else {
                        require_once("pages/{$op}/index.php");
                      }
            break;
          }

          return true;
      }
    }

    return false;
  }

  function languageUrlFilter($output, $templateMgr) {

    require_once("phpQuery.php");

    $request = Application::getRequest();
    $journalPath = $request->getRequestedJournalPath();
    $currentUrl = $request->getCompleteUrl();
    $baseUrl = $request->getIndexUrl() . "/" . $journalPath;
    $session = $request->getSession();
    $currentLocale = self::convertLocale($session->getSessionVar('currentLocale'));
    $document = phpQuery::newDocument($output);

    $lang_links = $document->find("a");

    foreach($lang_links as $link) {
      $pqLink = pq($link);
      $href = $pqLink->attr('href');
      if(strpos($href, '/' . $currentLocale . '/') === false
         && strpos($href, 'setLocale') === false
         && strpos($href, $baseUrl . '/admin') === false
         && strpos($href, $baseUrl . '/submission') === false
         && strpos($href, $baseUrl . '/submissions') === false
         && strpos($href, $baseUrl . '/login') === false
         && strpos($href, $baseUrl . '/user') === false
         && strpos($href, $baseUrl . '/management') === false
         && strpos($href, $baseUrl . '/stats') === false
         && strpos($href, $baseUrl . '/manageIssues') === false
         && strpos($href, $baseUrl . '/$$$call$$$') === false
       ) {
          $pqLink->attr('href', str_replace($baseUrl . "/", $baseUrl . "/" . $currentLocale . '/', $href));
      }
      continue;
    }

    return $document;

  }

  function redirectLocaleUrl($hookName, $args) {
    $url =& $args[0];
    $request = Application::getRequest();
    $path = parse_url($request->getCompleteUrl())['path'];
    if(strpos($path, 'setLocale') !== false) {
      $session = $request->getSession();
      $currentLocale = $session->getSessionVar('currentLocale');
      $base = basename($path);
      $localePartUrl = self::convertLocale($base);
      // if index.php
      $replacePartUrl = explode("/", trim($_GET['source'],"/"))[2];
      $url = str_replace('/' . $replacePartUrl . '/', '/' . $localePartUrl . '/', $url);
      // fix
      if(substr($url, -1) != '/') {
        $url .= '/';
      }
    }
    return false;
  }

  static function convertLocale($locale, $return_key = false) {
    $language_array = array(
      'ca_ES' => 'caes',
      'cs_CZ' => 'cz',
      'da_DK' => 'dk',
      'de_DE' => 'de',
      'en_US' => 'en',
      'es_ES' => 'es',
      'eu_ES' => 'eues',
      'fi_FI' => 'fi',
      'fr_CA' => 'ca',
      'fr_FR' => 'fr',
      'hr_HR' => 'hr',
      'id_ID' => 'id',
      'it_IT' => 'it',
      'nb_NO' => 'no',
      'nl_NL' => 'nl',
      'pl_PL' => 'pl',
      'pt_BR' => 'br',
      'pt_PT' => 'pt',
      'ru_RU' => 'ru',
      'sl_SI' => 'sl',
      'sr_RS@cyrillic' => 'srcyr',
      'sr_RS@latin' => 'srlat',
      'sv_SE' => 'se',
      'tr_TR' => 'tr',
      'uk_UA' => 'ua',
      'zh_CN' => 'cn',
      'ar_IQ' => 'iq',
      'fa_IR' => 'ir',
    );
    if($return_key) $language_array = array_flip($language_array);
    return (isset($language_array[$locale])) ? $language_array[$locale] : false;
  }

  static function convertLocalesToUrl($locales) {
    $urlLocales = array();
    foreach($locales as $code=>$locale) {
      $urlLocales[] = self::convertLocale($code);
    }
    return $urlLocales;
  }

  function pageFilter($page) {
    switch(strtolower($page)) {
      case 'wizard' :
      case 'submissions' : $page = 'submission';
      break;
    }
    return $page;
  }

  function opFilter($page, $op) {

  }

}