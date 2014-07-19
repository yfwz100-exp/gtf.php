<?php
namespace gtf\view {
  
  class PartDef {
    public $name;
    private $content;

    function __construct($name) {
      $this->name = $name;
      $this->content = array();
    }

    function need() {
      return $this;
    }

    function append($content) {
      $this->content[] = $content;
      return $this;
    }

    function __toString() {
      $body = '';
      foreach ($this->content as $c) {
        $body .= $c;
      }
      return $body;
    }
  }
  
  class BaseDef {
    private $file;
    private $back;
    
    function __construct($name) {
      $this->file = $name;
    }
    
    function cond($name, $file) {
      if (!$this->file && $name) {
        $this->file = $file;
      }
      return $this;
    }
    
    function fallback($file) {
      $this->back = $file;
      return $this;
    }
    
    function __toString() {
      $file = $this->file ? $this->file : $this->back;
      return $file;
    }
  }

  class Holder {
    private $name;
    function __construct($name) {
      $this->name = $name;
    }
    function __toString() {
      return ${$this->name};
    }
  }

  class Page {

    private $stack;
    private $tpl;
    private $file;
    private $env;

    function __construct() {
      $this->stack = new \SplStack();
      $this->tpl = array();
      $this->file = null;
      $this->env = array();
    }

    function usePart($name, $opt = null) {
      if (array_key_exists($name, $this->tpl)) {
        if (is_array($opt)) {
          extract($opt);
        }
        echo $this->tpl[$name];
      }
    }

    function part($name = null) {
      if ($name) {
        $part = new PartDef($name);
        $this->stack->push($part);
        ob_start();
      } else {
        $part = $this->stack->pop()->append(ob_get_clean());
        if (! array_key_exists($part->name, $this->tpl)) {
          $this->tpl[$part->name] = $part;
        }
      }
      return $part;
    }

    function param($name) {
      if (!$this->stack->isEmpty()) {
        $this->stack->top()->append(ob_get_clean())->append(new Holder($name));
        ob_start();
      }
    }

    function base($file = null, array $env = null) {
      if (is_array($file)) {
        $env = $file;
        $file = null;
      }
      $this->file = new BaseDef($file);
      if (is_array($env)) {
        foreach($env as $key => $val) {
          if (!array_key_exists($key, $this->env)) {
            $this->env[$key] = $val;
          }
        }
      }
      return $this->file;
    }
    
    function env($key, $value, $text) {
      if ($this->env[$key] == $value) {
        echo $text;
      }
    }

    function flush() {
      $old = null;
      while ($this->file && $this->file != $old) {
        if ($old) {
          chdir(dirname($old));
        }
        $old = $this->file;
        
        include $this->file;
      }
      $this->file = null;
    }
  }
}

namespace gtf\data {
  
  class Dao {
    
    private $handle;
    
    function __construct() {
      $db = include('db.php');
      try {
        $this->handle = new \PDO($db->dsn, $db->username, $db->password, $db->param);
      } catch (\PDOException $e) {
        die('Error while connecting to database.');
      }
    }
    
    function query($sql, array $attr = array()) {
      $stat = $this->handle->prepare($sql);
      $stat->execute($attr);
      $rs = array();
      while($prop = $stat->fetch(\PDO::FETCH_ASSOC)) {
        $rs[] = $prop;
      }
      return $rs;
    }
    
    function unique($sql, array $attr = array()) {
      $stat = $this->handle->prepare($sql);
      $stat->execute($attr);
      $result = $stat->fetch(\PDO::FETCH_ASSOC);
      $stat->closeCursor();
      return $result;
    }
    
    function state($sql) {
      return $this->handle->prepare($sql);
    }
    
    function update($sql, $attr = array()) {
      $stat = $this->handle->prepare($sql);
      $stat->execute($attr);
      return $stat->rowCount();
    }
    
    function quote($str) {
      return $this->handle->quote($str);
    }
    
    function getHandle() {
      return $this->handle;
    }

  }
  
}

namespace gtf\resource {
  
  class Service {
    
    function import($name, $param = null) {
      if ($param) {
        extract($param);
      }
      if (file_exists($name)) {
        return include($name);
      }
    }
    
    function need($name) {
      if (!$name) {
        die("Parameter required!");
      }
    }
    
  }
  
}

namespace {

  class Tpl {

    private static $tpl;

    static function __callStatic($name, $args) {
      if (! static::$tpl) {
        static::$tpl = new gtf\view\Page();
      }
      return call_user_func_array(array(static::$tpl, $name), $args);
    }
  }

  class Stq {
    
    private static $dbh;
    
    static function __callStatic($name, $args) {
      if (! static::$dbh) {
        static::$dbh = new gtf\data\Dao();
      }
      return call_user_func_array(array(static::$dbh, $name), $args);
    }

  }
  
  class Res {
    
    private static $res;
    
    static function __callStatic($name, $args) {
      if (! static::$res) {
        static::$res = new gtf\resource\Service();
      }
      return call_user_func_array(array(static::$res, $name), $args);
    }
  }

  /**
   * The entrance function.
   */
  function poweredByGtf($opt) {
    define('GTF.PHP', 'http://githb.com/yfwz100/gtf.php');
    define('XHR', $_SERVER['HTTP_X_REQUESTED_WITH']);
    
    $curDir = __DIR__;
    $viewDir = '/view';
    $module = '';
    
    extract($opt, EXTR_IF_EXISTS);
    
    if (isset($_SERVER['PATH_INFO'])) {
      $module = $_SERVER['PATH_INFO'];
    }

    $module_path = realpath("$curDir$viewDir$module.php");
    if ($module_path) {
      Tpl::base($module_path);
    } else {
      header('Location: /site.php/home');
    }

    Tpl::flush();
  }
}