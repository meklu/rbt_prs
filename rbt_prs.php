<?php
  /// copyleft 2011 meklu (public domain)
  // This tries to check whether a URL should be accessed
  // or not.
  // The latest version should be available at
  //   http://meklu.webege.com/code/rbt_prs.php.bz2
  // Just pass the url (and user agent, if you'd like) to the function.
  // If an argument is NULL, its default value will be used.
  define("RBT_PRS_VER_MAJOR", "1");
  define("RBT_PRS_VER_MINOR", "0");
  define("RBT_PRS_VER_PATCHLEVEL", "3");
  define("RBT_PRS_BRANCH", "testing");
  define("RBT_PRS_VER", RBT_PRS_VER_MAJOR . "." . RBT_PRS_VER_MINOR . "." .
	  RBT_PRS_VER_PATCHLEVEL . "-" . RBT_PRS_BRANCH);
  function isUrlBotSafe($url, $your_useragent = "meklu::isUrlBotSafe",
			$debug = FALSE) {
    if($your_useragent === NULL) $your_useragent = "meklu::isUrlBotSafe";
    if($debug === NULL) $debug = FALSE;

    if($debug === TRUE) {
      error_reporting(E_ALL);
      ini_set('display_errors', true);
      ini_set('html_errors', false);
      echo "PHP version: " . phpversion() . "\n";
      echo "rbt_prs version: " . RBT_PRS_VER . "\n";
    }
    // storing the current ua
    $original_ua=ini_get("user_agent");
    // switching to the given ua
    ini_set("user_agent", $your_useragent);
    // slicing up the given url
    $tmp=parse_url($url);
    // start re-assembling it
    $baseurl=$tmp["scheme"] . "://";
    if(isset($tmp["user"])) {
      $baseurl=$baseurl . $tmp["user"] . ":";
      if(isset($tmp["pass"])) {
	$basurl=$baseurl . $tmp["pass"];
      }
      $baseurl=$baseurl . "@";
    }
    $baseurl=$baseurl . $tmp["host"];
    if(isset($tmp["port"])) {
      $baseurl=$baseurl . ":" . $tmp["port"];
    }
    $baseurl=$baseurl . "/";
    if(isset($tmp["path"])) {
      $checkedpath=$tmp["path"];
    } else {
      $checkedpath="/";
    }
    if(isset($tmp["query"])) {
      $checkedpath=$checkedpath . "?" . $tmp["query"];
    }
    // re-assembling the url is finished
    // do a bit of magic on the checked path
    if(strlen($checkedpath) > 1) {
      if(substr_count($checkedpath, '?') == 0) {
	if(preg_match("#\w$#", $checkedpath))
	  $checkedpath=$checkedpath . "/";
      } elseif(substr_count($checkedpath, "/?") > 0) {
	$checkedpath=str_replace("/?", "/index.stuff?", $checkedpath);
      }
    }
    unset($tmp);
    // checking whether robots.txt can be accessed or not.
    $fhandle=@fopen($baseurl . "robots.txt", "rb");
    if($fhandle === FALSE) {
      unset($fhandle);
      ini_set("user_agent", $original_ua);
      return TRUE;
    } else {
      // we were able to download something!
      $raw=stream_get_contents($fhandle);
      fclose($fhandle);
      unset($fhandle);
      // check if we ran into an html (error) page.
      // we're looking for the closing tag because <html foo="bar">
      if(preg_match("#</html>#i", $raw) > 0) {
	ini_set("user_agent", $original_ua);
	return TRUE;
      }
      // so far so good!
      // fixing some newlines and removing comments on top of which we're
      // escaping a few characters
      // i.e. making all carriage returns newlines and removing duplicates
      //      and trimming the result
      $raw=str_replace("\r", "\n", $raw);
      // remove the comments
      $raw=preg_replace(":(#).*:", "", $raw);
      // first the backslashes
      $raw=str_replace("\\", "\\\\", $raw);
      // then the rest
      $raw=str_replace(".", "\\.", $raw);
      $raw=str_replace("?", "\\?", $raw);
      // remove duplicate newlines
      $raw=preg_replace("#\n+#", "\n", $raw);
      // trim that
      $raw=trim($raw);
    }
    // explode the lines into an array
    $lines=explode("\n", $raw);
    // initialise our multi-dimensional rule array
    $rules=array("*" => array(
			  "/" => TRUE
			)
		);
    // set current user agent to NULL
    // this means that lines before the first declaration of a user agent will
    // be ignored
    $current_agent=NULL;
    // process the lines individually
    foreach($lines as &$line) {
      // explode our lines into two segments
      $rule=explode(":", $line, 2);
      // check if we had enough elements
      // this makes us silently ignore invalid entries
      if(count($rule) == 2) {
	$key=trim($rule[0]);
	$value=trim($rule[1]);
      } else {
	unset($rule);
	continue;
      }
      // is it a user agent?
      if(strcasecmp($key, "user-agent") == 0) {
	$current_agent=$value;
	unset($rule, $key, $value);
	if($debug === TRUE)
	  echo "User agent match.\n";
	continue;
      }
      // is it an allow?
      if(strcasecmp($key, "allow") == 0 && $current_agent !== NULL) {
      	if(strlen($value) > 1) {
	  if(substr_count($value, '?') == 0) {
	    if(preg_match("#\w$#", $value))
	      $value=$value . "/";
	  } else {
	    if(substr_count($value, "/\\?") > 0) {
	      $value=str_replace("/\\?", "/index\\.\w+\\?", $value);
	    }
	  }
	}
	$rules[$current_agent][$value]=TRUE;
	unset($rule, $key, $value);
	if($debug === TRUE)
	  echo "Allow match.\n";
	continue;
      }
      // is it a disallow?
      if(strcasecmp($key, "disallow") == 0 && $current_agent !== NULL) {
      	if(strlen($value) > 1) {
	  if(substr_count($value, '?') == 0) {
	    if(preg_match("#\w$#", $value))
	      $value=$value . "/";
	  } else {
	    if(substr_count($value, "/\\?") > 0) {
	      $value=str_replace("/\\?", "/index\\.\w+\\?", $value);
	    }
	  }
	}
	$rules[$current_agent][$value]=FALSE;
	unset($rule, $key, $value);
	if($debug === TRUE)
	  echo "Disallow match.\n";
	continue;
      }
      if($debug === TRUE)
	echo "No match.\n";
    }
    unset($line);
    unset($current_agent);
    // let's see if we have a match
    // $state is TRUE by default because why not
    $state=TRUE;
    // first checking universal rules
    if(isset($rules["*"])) {
      if(isset($rules["*"]["/"]))
	$state=$rules["*"]["/"];
      reset($rules["*"]);
      foreach($rules["*"] as $key => $value) {
	if(preg_match("#^" . $key . "#", $checkedpath) > 0) {
	  $state=$value;
	}
      }
    }
    // checking rules specific to your user agent
    if(isset($rules[$your_useragent])) {
      if(isset($rules[$your_useragent]["/"]))
	$state=$rules[$your_useragent]["/"];
      reset($rules[$your_useragent]);
      foreach($rules[$your_useragent] as $key => $value) {
	if(preg_match("#^" . $key . "#", $checkedpath) > 0) {
	  $state=$value;
	}
      }
    }
    if($debug === TRUE) {
      echo "Var dumps...\n";
      echo "\$checkedpath:\n";
      var_dump($checkedpath);
      echo "\$baseurl:\n";
      var_dump($baseurl);
      echo "\$raw:\n";
      var_dump($raw);
      echo "\$rules:\n";
      var_dump($rules);
    }
    ini_set("user_agent", $original_ua);
    return $state;
  }
?>
