<?php
namespace gtf\view {
  
  class Holder {
    
    private $name;
    
    function __construct($name) {
      $this->name = $name;
    }
    
    function __toString() {
      return $this->name;
    }
  }
  
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
    
    function __invoke($param) {
      $body = '';
      foreach ($this->content as $c) {
        if ($c instanceof Holder && $param) {
          $body .= $param[$c];
        } else {
          $body .= $c;
        }
      }
      return $body;
    }

    function append($content) {
      $this->content[] = $content;
      return $this;
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
        $part = $this->tpl[$name];
        if ($this->stack->isEmpty()) {
          echo $part($opt);
        } else {
          $this->stack->top()->append(ob_get_clean())->append($part);
          ob_start();
        }
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
  
  class CachedItr implements \Iterator {
    
    private $stat;
    private $cached;
    private $index;
    
    function __construct($stat) {
      $this->stat = $stat;
      $this->cached = array();
      $this->rewind();
    }
    
    function key() {
      return $this->index;
    }
    
    function current() {
      return $this->cached[$this->index];
    }
    
    function rewind() {
      $this->index = -1;
      $this->next();
    }
    
    function valid() {
      return $this->stat || ($this->index > -1 && $this->index < count($this->cached));
    }
    
    function next() {
      if ($this->stat) {
        $rs = $this->stat->fetch(\PDO::FETCH_ASSOC);
        if ($rs) {
          $this->cached[] = $rs;
        } else {
          $this->__destruct();
        }
      }
      $this->index ++;
    }
    
    function __destruct() {
      if ($this->stat) {
        $this->stat->closeCursor();
        $this->stat = null;
      }
    }
  }
  
  class Dao {
    
    private $handle;
    
    function __construct($dbConfig) {
      $db = include($dbConfig);
      try {
        $this->handle = new \PDO($db->dsn, $db->username, $db->password, $db->param);
      } catch (\PDOException $e) {
        trigger_error('Error while connecting to database.');
      }
    }
    
    function query($sql, array $attr = array()) {
      $stat = $this->handle->prepare($sql);
      if ($stat) {
        if ($stat->execute($attr)) {
          $rs = $stat->fetchAll(\PDO::FETCH_ASSOC);
          return $rs;
        } else {
          return null;
        }
      } else {
        trigger_error("Error in executing SQL: $sql .");
      }
    }
    
    function iter($sql, array $attr = array()) {
      $stat = $this->handle->prepare($sql);
      if ($stat) {
        if ($stat->execute($attr)) {
          return new CachedItr($stat);
        } else {
          return null;
        }
      } else {
        trigger_error("Error in executing SQL: $sql .");
      }
    }
    
    function unique($sql, array $attr = array()) {
      $stat = $this->handle->prepare($sql);
      if ($stat) {
        $stat->execute($attr);
        $result = $stat->fetch(\PDO::FETCH_ASSOC);
        $stat->closeCursor();
        return $result;
      } else {
        trigger_error("Error in executing SQL: $sql .");
      }
    }
    
    function state($sql) {
      return $this->handle->prepare($sql);
    }
    
    function update($sql, $attr = array()) {
      $stat = $this->handle->prepare($sql);
      if ($stat) {
        $stat->execute($attr);
        return $stat->rowCount();
      } else {
        trigger_error("Error in executing SQL: $sql .");
      }
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
    
    function need($name, $description = "Parameter required!") {
      if (!$name) {
        die($description);
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
    
    static function init($dbConfig) {
      if (! static::$dbh) {
        static::$dbh = new gtf\data\Dao($dbConfig);
      }
    }
    
    static function __callStatic($name, $args) {
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
    $namespace = 'GTF_PHP';
    $viewDir = 'site';
    $dbConfig = 'db.php';
    $startPage = '';
    
    extract($opt, EXTR_IF_EXISTS);
    
    if ($viewDir[0] != '/') {
      $viewDir = getcwd()."/$viewDir";
    }
    
    define($namespace, 'http://githb.com/yfwz100/gtf.php');
    define('XHR', $_SERVER['HTTP_X_REQUESTED_WITH']);
    
    if (file_exists($dbConfig)) {
      Stq::init($dbConfig);
    }
    
    $module = '';
    if (isset($_SERVER['PATH_INFO'])) {
      $module = $_SERVER['PATH_INFO'];
    }

    $module_path = realpath("$viewDir$module.php");
    if ($module_path) {
      Tpl::base($module_path);
    } else if ($module == $startPage) {
      echo "No hompage is found. See " . constant($namespace) . " for more information.";
    } else {
      header("Location: $_SERVER[PHP_SELF]/$startPage");
    }

    Tpl::flush();
  }
}
