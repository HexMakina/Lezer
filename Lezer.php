<?php

/**
 * honnors Ludwik *Lejzer* Zamenhof (Polish: Ludwik Łazarz Zamenhof);
 * 15 December [O.S. 3 December] 1859 – 14 April [O.S. 1 April] 1917),
 * a medical doctor, inventor, and writer; most widely known for creating Esperanto.
 *
 * also Lezer is dutch for Reader, and it sounds like LASER, which is kinda cool.
 */
namespace HexMakina\Lezer;

class Lezer
{
    private $translations = [];
  
    public function __construct(string $pathToTranslations) {
        if (file_exists($pathToTranslations)) {
            $json = file_get_contents($pathToTranslations);
            $this->translations = json_decode($json, true) ?: [];
        }
    }


    public function l(string $key, array $context = []): string
    {
        // no translation available, returns the key
        $translation = $this->translations[$key] ?? null;

        if (empty($translation)) {
            return $key;
        }
    
        // translate context
        if (empty($context)) {
            return $translation;
        }
    
        return vsprintf($translation, array_map([$this, 'l'], $context));
    }

    /**
     * Provides a method to detect languages that is requested from the client.
     * First it looks for the language values in $_GET, $_SESSION and $_COOKIE. 
     * For each found language, it assigns a quality value
     *      1000 for $_GET, 
     *      100 for $_SESSION and 
     *      10 for $_COOKIE
     * 
     * If no languages are found, it calls parseHTTPHeader()
     * 
     * Then it removes duplicates and sorts the array of detected languages by Quality, 
     * highest first
     *
     * @return array with the user languages sorted by priority.
     */

    public static function detectLanguages($key='lang') : array
    {
        $ret = [];
        foreach(['$_GET' => 1000, '$_SESSION' => 100, '$_COOKIE' => 10] as $source => $quality){
            $lang = $$source[$key] ?? null;
            if(!empty($lang) && preg_match('/^[a-zA-Z0-9_-]*$/', $lang) === 1){
                $ret[$quality] = $lang;
            }
        }

        if(empty($ret))
            $ret = self::parseHTTPHeader();

        $ret = array_unique($ret);
        // Sort the detected languages by quality, with the highest quality first
        arsort($ret);

        return $ret;
    }

    public function time($time_string, $short = true)
    {
        if ($short === true) {
            $time_string = substr($time_string, 0, 5);
        }
        return $time_string;
    }

    public function date($date_string, $short = true)
    {
        if ($date_string === '0000-00-00' || empty($date_string)) {
            return $this->l('MODEL_common_VALUE_EMPTY');
        }

        if (preg_match('/^[0-9]{4}$/', $date_string) === 1) {
            return intval($date_string);
        }

        list($year, $month, $day) = explode('-', $date_string);

        $ret = intval($day) . ' ' . $this->l("DATETIME_CALENDAR_MONTH_$month");

        if ($short === true && Dato::format(null, 'Y') === $year) {
            return $ret;
        } else {
            return "$ret $year";
        }
    }

    public function month($date_string)
    {
        return $this->l('DATETIME_CALENDAR_MONTH_' . Dato::format($date_string, 'm'));
    }

    public function day($date_string)
    {
        return $this->l('DATETIME_CALENDAR_DAY_' . Dato::format($date_string, 'N'));
    }

    public function seconds($seconds)
    {
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds - $hours * 3600) / 60);
        $secs = floor($seconds % 60);

        $hours_format = '%dh %dm %ds';
        return sprintf($hours_format, $hours, $mins, $secs);
    }

    public function init() {
        if ($this->isInitialized()) {
            throw new \BadMethodCallException('This object from class ' . __CLASS__ . ' is already initialized. It is not possible to init one object twice!');
        }

        $this->isInitialized = true;

        $this->userLangs = $this->getUserLangs();

        // search for language file
        $this->appliedLang = NULL;
        foreach ($this->userLangs as $priority => $langcode) {
            $this->langFilePath = $this->getConfigFilename($langcode);
            if (file_exists($this->langFilePath)) {
                $this->appliedLang = $langcode;
                break;
            }
        }
        if ($this->appliedLang == NULL) {
            throw new \RuntimeException('No language file was found.');
        }

        // search for cache file
        $this->cacheFilePath = $this->cachePath . '/php_i18n_' . md5_file(__FILE__) . '_' . $this->prefix . '_' . $this->appliedLang . '.cache.php';

        // whether we need to create a new cache file
        $outdated = !file_exists($this->cacheFilePath) ||
            filemtime($this->cacheFilePath) < filemtime($this->langFilePath) || // the language config was updated
            ($this->mergeFallback && filemtime($this->cacheFilePath) < filemtime($this->getConfigFilename($this->fallbackLang))); // the fallback language config was updated

        if ($outdated) {
            $config = $this->load($this->langFilePath);
            if ($this->mergeFallback)
                $config = array_replace_recursive($this->load($this->getConfigFilename($this->fallbackLang)), $config);

            $compiled = "<?php class " . $this->prefix . " {\n"
            	. $this->compile($config)
            	. 'public static function __callStatic($string, $args) {' . "\n"
            	. '    return vsprintf(constant("self::" . $string), $args);'
            	. "\n}\n}\n"
              . $this->compileFunction();

			if( ! is_dir($this->cachePath))
				mkdir($this->cachePath, 0755, true);

            if (file_put_contents($this->cacheFilePath, $compiled) === FALSE) {
                throw new \Exception("Could not write cache file to path '" . $this->cacheFilePath . "'. Is it writable?");
            }
            try{
                chmod($this->cacheFilePath, 0755);
              }
              catch(\Throwable $t){
                throw new \Exception("Could chmod cache file '" . $this->cacheFilePath . "'");
              }


        }

        require_once $this->cacheFilePath;
    }

    public function compileFunction()
    {
        return ''
        . "function " . $this->prefix . '($string, $args=NULL) {' . "\n"
        . '    if (!defined("' . $this->prefix . '::".$string))'
        . '       return $string;'
        . '    $return = constant("' . $this->prefix . '::".$string);' . "\n"
        . '    return $args ? vsprintf($return,$args) : $return;'
        . "\n}";
    }

    public function l($message, $context = []): string
    {
        foreach($context as $i => $context_message)
          $context[$i] = $this->l($context_message);

        return call_user_func($this->prefix, $message, $context);
    }
}
