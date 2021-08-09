<?php
/*
 * i18n class called Lezer, shorthand L
 * honnors Ludwik *Lejzer* Zamenhof (Polish: Ludwik Łazarz Zamenhof; 15 December [O.S. 3 December] 1859 – 14 April [O.S. 1 April] 1917),
 * a medical doctor, inventor, and writer; most widely known for creating Esperanto.
 *
 * also Lezer is dutch for Reader, and it sounds like LASER, which is kinda cool.
 */
namespace HexMakina\Lezer;

use \HexMakina\LocalFS\FileSystem;
use \HexMakina\Format\Tempo\{Dato,DatoTempo,Tempo};

class Lezer extends \i18n{

  private $detected_language_files = [];
  private $detected_language_env = [];

  // protected $basePath = 'locale';
  // protected $filePath = 'locale/{LANGUAGE}/user_interface.ini'; // uses gettext hierarchy
  // protected $cachePath = 'locale/cache/';
  // protected $fallbackLang = 'fra';  // uses ISO-639-3
  protected $currentLang = null;

  public function one_language()
  {
    $this->detect_language_files();
    $this->detect_language_env();
    $the_one_language = current(array_intersect($this->detect_language_files(), $this->detect_language_env()));

    if($the_one_language)
      $this->setForcedLang($the_one_language);

    return $the_one_language;
  }

  public function detect_language_files()
  {
    $files = FileSystem::preg_scandir(dirname($this->filePath), '/.json$/');
    if(empty($files))
      return [];

    $files = implode('',$files);
    $res = preg_match_all('/([a-z]{3})\.json/', $files, $m);
    if($res) // false or 0 is none found
      $this->detected_language_files = $m[1];
    return $this->detected_language_files;
  }

  /**
   * getUserLangs()
   * Returns the user languages
   * Normally it returns an array like this:
   * 1. Forced language
   * 2. Language in $_GET['lang']
   * 3. Language in $_SESSION['lang']
   * 4. COOKIE
   * 5. Fallback language
   * Note: duplicate values are deleted.
   *
   * @return array with the user languages sorted by priority.
   */
  public function detect_language_env() {
      $userLangs = array();

      // 1. forced language
      if ($this->forcedLang != NULL) {
          $userLangs['forced'] = $this->forcedLang;
      }

      // 2. GET parameter 'lang'
      if (isset($_GET['lang']) && is_string($_GET['lang'])) {
          $userLangs['get'] = $_GET['lang'];
      }

      // 3. SESSION parameter 'lang'
      if (isset($_SESSION['lang']) && is_string($_SESSION['lang'])) {
          $userLangs['session'] = $_SESSION['lang'];
      }

      // 4. COOKIES
      if (isset($_COOKIE['lang']) && is_string($_COOKIE['lang'])) {
          $userLangs['cookie'] = $_COOKIE['lang'];
      }

      // Lowest priority: fallback
      $userLangs['fallback'] = $this->fallbackLang;
      // remove duplicate elements
      $userLangs = array_unique($userLangs);

      // remove illegal userLangs
      foreach ($userLangs as $key => $value) {
          // only allow a-z, A-Z and 0-9 and _ and -
          if (preg_match('/^[a-zA-Z0-9_-]*$/', $value) === 1)
              $this->detected_language_env[$key] = $value;
      }

      return $this->detected_language_env;
  }


  public static function model_type_to_label($form_model)
  {
    return L(sprintf('MODEL_%s_INSTANCE',get_class($form_model)::model_type()));
  }
  public static function field_name_to_label($form_model, $field_name)
  {
    return L(sprintf('MODEL_%s_FIELD_%s',(get_class($form_model))::model_type(), $field_name));
  }

  // options['decimals'] = int
  // options['abbrev'] = mixed: key needs to be set
  public static function when($event, $options=[])
  {
    try {
      $amount_of_days = DatoTempo::days_diff(new \DateTime($event), new \DateTime());

    } catch (\Exception $e) {
      return __FUNCTION__.': error';
    }

    if($amount_of_days === -1)
      return L('DATETIME_RANGE_YESTERDAY');
    elseif($amount_of_days === 0)
      return L('DATETIME_RANGE_TODAY');
    elseif($amount_of_days === 1)
      return L('DATETIME_RANGE_TOMORROW');


    $datetime_parts = [
      'y' => 'DATETIME_UNIT_YEAR',
      'm' => 'DATETIME_UNIT_MONTH',
      'w' => 'DATETIME_UNIT_WEEK',
      'd' => 'DATETIME_UNIT_DAY',
      'h' => 'DATETIME_UNIT_HOUR',
      'i' => 'DATETIME_UNIT_MINUTE',
      's' => 'DATETIME_UNIT_SECOND'
    ];

    $date_diff = DatoTempo::days_diff_in_parts(abs($amount_of_days));
    $ordering = [];
    foreach($datetime_parts as $unit => $label)
    {
      if(!isset($date_diff[$unit]))
        continue;

      $qty = (int)$date_diff[$unit];

      if($qty === 0)
        continue;

      if(isset($options['abbrev']))
        $label.= '_ABBREV';
      elseif($qty > 1)
        $label.= '_PLURAL';

      $ordering[$unit] = $qty.' '.L($label).'.';
    }
    $ret = (isset($amount_of_days) && $amount_of_days >= 0) ? L('DATETIME_RANGE_PREFIX_FUTURE') : L('DATETIME_RANGE_PREFIX_PAST');
    $ret.= ' '.implode(' & ', array_slice($ordering, 0,2));

    return $ret;
  }

  public static function time($time_string, $short=true)
  {
    if ($short === true)
      $time_string = substr($time_string, 0, 5);
    return $time_string;
  }

  public static function human_date($date_string, $short=true)
  {
    if($date_string === '0000-00-00' || empty($date_string))
      return L('MODEL_common_VALUE_EMPTY');

    if(preg_match('/^[0-9]{4}$/', $date_string) === 1)
      return intval($date_string);

    list($year, $month, $day) = explode('-',$date_string);

    $ret = intval($day).' '.L("DATETIME_CALENDAR_MONTH_$month");

    if($short === true && Dato::format(null, 'Y') === $year)
      return $ret;
    else
      return "$ret $year";
  }

  public static function human_month($date_string)
  {
    return L('DATETIME_CALENDAR_MONTH_'.Dato::format($date_string, 'm'));
  }

  public static function human_day($date_string)
  {
    return L('DATETIME_CALENDAR_DAY_'.Dato::format($date_string, 'N'));
  }

  public static function human_seconds($seconds)
  {
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds-$hours*3600) / 60);
    $secs = floor($seconds % 60);

    $hours_format ='%dh %dm %ds';
    return sprintf($hours_format, $hours, $mins, $secs);
  }


}

?>
