<?php
/*
  Archive.org Site Downloader
  The goal being to download a
*/

class ArchiveDownloader
{
  // The url to copy ex: https://web.archive.org/web/20141002023932/http://www.bonnaroo.com/
  // These variables must be set before calling init
  public $timestamp    = ""; //"20141002023932";
  public $website      = ""; //http://www.bonnaroo.com/";

  // Used variables
  private $baseUrl     = "https://web.archive.org/web/";
  private $initUrl     = "";
  private $fileList    = [];
  private $currentUrl  = "";
  private $archiveDir  = "";
  private $domain      = "";
  private $path        = "";
  private $name        = "";
  private $data        = "";
  private $verbose     = true;
  private $mediaTypes  = ['jpg','jpeg','gif','png','svg', 'png', 'pdf','mp3','mp4', 'ai', 'avi', 'qtf'];
  private $fontTypes   = ['woff', 'woff3', 'otf', 'ttf', 'eot', 'svg'];
  private $cssTypes    = ['css'];
  private $jsTypes     = ['js'];
  private $pageCount   = 0;
  private $fileCount   = 0;
  //private $urlParts    = [];

  // Initiate the process
  public function init()
  {
    $this->printToScreen("\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\nSTARTING DOWNLOAD...\n\n\n\n\n\n\n\n\n\n\n\n\n");
    $this->printToScreen('init()');
    $this->verifySettings(); // Verify the settings
    $this->formatDomain(); // Get the raw domain
    $this->archiveDir = "archives/".$this->timestamp.".".$this->domain."/"; // Build the archive directory
    $this->makeDirs();
    $this->initUrl = $this->baseUrl.$this->timestamp."/".$this->website; // The first url to download
    $this->currentUrl = $this->initUrl; // Set the current url to the init url
    $this->downloadUrl(); // Start the download
  }

  // make dirs
  private function makeDirs()
  {
    $this->cleanUp();

    if (!mkdir($this->archiveDir, 0777, true)) {
      die('Error: Failed to create _media folder.');
    }

    if (!mkdir($this->archiveDir."_media", 0777, true)) {
      die('Error: Failed to create _media folder.');
    }

    if (!mkdir($this->archiveDir."_css", 0777, true)) {
      die('Error: Failed to create _media folder.');
    }

    if (!mkdir($this->archiveDir."_js", 0777, true)) {
      die('Error: Failed to create _media folder.');
    }

    if (!mkdir($this->archiveDir."_fonts", 0777, true)) {
      die('Error: Failed to create _media folder.');
    }
  }

  // clearnup function
  private function cleanUp()
  {
    //
    $this->rrmdir($this->archiveDir);
  }

  // remove a directory tree
  private function rrmdir($dir)
  {
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (filetype($dir."/".$object) == "dir") {
            $this->rrmdir($dir."/".$object);
          } else {
            unlink($dir."/".$object);
          }
        }
      }
      reset($objects);
      rmdir($dir);
    }
  }

  // verify the settings
  private function verifySettings()
  {
    $this->printToScreen('verifySettings()');
    if($this->timestamp == "" || $this->website == "") {
      die("Error: no timestamp or website set.");
    }
  }

  // format the domain
  // trim trailing slash, make lowercase, and remove http:// or https://
  private function formatDomain()
  {
    $this->printToScreen('formatDomain()');
    $this->domain = rtrim(str_replace("https://","",str_replace("http://","",strtolower($this->website))),"/");
    $this->printToScreen($this->domain);
  }

  // Download the url and save it as index.html
  // urls should be saved as index.html files followint the
  // directory structure of the url
  // ex. /about/us -> /about/us/index.html
  // Download the url and save it as /_css/[filename].css
  // Download the url and save it as /_js/[filename].js
  // Download the url and save it as /_fonts/[filename].[fileextension]
  // .woff, .woff3, .otf, .ttf, .eot, .svg
  // Download the url and save it as /_media/[filename].[fileextension]
  // .jpg, .jpeg, .gif, .png, .svg, .pdf, .mp3, .mp4, .avi, [anything not js,css,font,etc]
  private function downloadUrl()
  {
    $this->printToScreen('downloadUrl()');
    $this->printToScreen($this->currentUrl);
    $this->getNameAndPath();
    $this->curlUrl();
    $this->saveFile();
  }

  // get the filename from the current url
  private function getNameAndPath() {
    $this->printToScreen('getNameAndPath()');
    // Break up the url parts
    // ex url: https://web.archive.org/web/20141002023932/http://www.bonnaroo.com/
    // part 0: http(s):
    // part 1:
    // part 2: web.archive.org
    // part 3: web
    // part 4: 20141002023932
    // part 5: http(s):
    // part 6:
    // part 7: www.bonnaroo.com
    // part 8 and up: [these are parts of the directory structure]
    $urlParts = explode("/", $this->currentUrl);

    // get the pathinfo
    $pathInfo = pathinfo($this->currentUrl);

    // dirname
    $this->printToScreen('Dirname: '.$pathInfo['dirname']);

    // basename
    $this->printToScreen('Basename: '.$pathInfo['basename']);

    // extension
    if(isset($pathInfo['extension']) && $pathInfo['extension'] != 'com') {
      $this->printToScreen('Extension: '.$pathInfo['extension']);
      $this->name = $pathInfo['basename'];
      $pathInfo['extension'] = strtolower($pathInfo['extension']);

      // Media paths...
      if(in_array($pathInfo['extension'],$this->mediaTypes)) {
        $this->path = $this->archiveDir.'_media/';
      }

      // JS Paths...
      if(in_array($pathInfo['extension'],$this->jsTypes)) {
        $this->path = $this->archiveDir.'_js/';
      }

      // CSS Paths...
      if(in_array($pathInfo['extension'],$this->cssTypes)) {
        $this->path = $this->archiveDir.'_css/';
      }

      // font paths
      if(in_array($pathInfo['extension'],$this->fontTypes)) {
        $this->path = $this->archiveDir.'_fonts/';
      }

    }
    // no extension, or extension == 'com', file is probably index.html, path is directory structure
    else {
      $this->name = "index.html";

      // get the dir structure by removing all other parts of the url...
      //$this->path = $this->archiveDir.str_replace($this->initUrl,"",$pathInfo['dirname']);
      $this->path = str_replace("http://", "", $this->currentUrl);
      $this->path = str_replace("https://", "", $this->path);
      $this->path = str_replace("web.archive.org/web/", "", $this->path);
      $this->path = str_replace($this->timestamp."/", "", $this->path);
      $this->path = str_replace($this->website, "", $this->path);
      $this->path = str_replace($this->domain, "", $this->path);
      $this->path = $this->archiveDir.$this->path;

    }

    $this->printToScreen('Path: '.$this->path);
    $this->printToScreen('Name: '.$this->name);
  }

  // Save a file
  private function saveFile()
  {
    $handle = fopen($this->path.$this->name, 'w') or die('Error: cannot open file: '.$this->path.$this->name.'.');
    fwrite($handle, $this->data);

    $this->scanFile();
  }

  // Scan the file for URLs
  private function scanFile()
  {

  }

  // find all <a href=""> links in html
  private function findLinks($html)
  {
    $url = "http://www.example.net/somepage.html";
    $input = @file_get_contents($url) or die("Could not access file: $url");
    $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
    if(preg_match_all("/$regexp/siU", $input, $matches, PREG_SET_ORDER)) {
      foreach($matches as $match) {
        // $match[2] = link address
        // $match[3] = link text
      }
    }
  }

  public function findCss()
  {
    //
    //
  }

  public function findJs()
  {
    //
    //
  }

  public function findFonts()
  {
    //
    //
  }

  public function findImgInHtml()
  {
    //
    //
  }

  public function findImgInCss()
  {
    //
    //
  }

  // Curl a url
  private function curlUrl()
  {
    $url = $this->currentUrl;
    /*
    $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    //Some other options I use:
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    */

    /*
    //In order to read sites encrypted by SSL, like Google Calendar feeds, you must set these CURL options:
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
    */

    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    $this->data = $data;
  }

  //$string = 'Some valid and <script>some invalid</script> text!';
//$out = delete_all_between('<script>', '</script>', $string);
//print($out);

  // remove Way Back Machine Toolbar
  private function removeWayBackToolbar() {
    $beginningPos = strpos($this->data, "<!-- BEGIN WAYBACK TOOLBAR INSERT -->");
    $endPos = strpos($this->data, "<!-- END WAYBACK TOOLBAR INSERT -->");
    if ($beginningPos === false || $endPos === false) {
      return;
    }
    $textToDelete = substr($this->data, $beginningPos, ($endPos + strlen($end)) - $beginningPos);
    $this->data = str_replace($textToDelete, '', $this->data);
  }


  // Rewrite all the js, img, css, etc...
  private function rewritePaths($file)
  {
    //
    //
  }

  // Prints to screen if verbose true
  private function printToScreen($msg)
  {
    if(!$this->verbose) { return false; }
    echo $msg."\n";
    sleep(1);
  }

  // Download all the css and save it to /css

  // Download all the js and save it to /js

  // follow all relative / same domain links

  /*
  preserve file structure
  */

}

?>
