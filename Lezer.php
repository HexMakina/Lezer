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

    /**
     * Parse the user's HTTP header to retrieve the languages they have set.
     */
    private static function parseHTTPHeader() : array
    {
        $languages = [];

        // Parse the header value into an array of languages and qualities
        $language_list = explode(',', $header);
        foreach ($language_list as $language_entry) {
            $quality = 1; // default

            $parts = explode(';', trim($language_entry));
            $lang = trim($parts[0]);

            if (count($parts) > 1) {
                $q_parts = explode('=', $parts[1]);

                // Check for a 'q' part in the language entry
                if (strtolower(trim($q_parts[0])) === 'q') {
                    $quality = (float)trim($q_parts[1]);
                }
            }
            // Keep only the language code, not any other attributes
            $language_code = substr($lang, 0, 2);
            if (!in_array($language_code, $languages)) {
                $languages[$quality] = $language_code;
            }
        }

        return $languages;
    }
}
