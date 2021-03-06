<?php defined('BASEPATH') || exit('No direct script access allowed');
/**
 * Bonfire
 *
 * An open source project to allow developers get a jumpstart their development of CodeIgniter applications
 *
 * @package   Bonfire
 * @author    Bonfire Dev Team
 * @copyright Copyright (c) 2011 - 2014, Bonfire Dev Team
 * @license   http://opensource.org/licenses/MIT
 * @link      http://cibonfire.com
 * @since     Version 1.0
 * @filesource
 */

/**
 * Language Helper
 *
 * Includes fucntions to help with the management of the language files.
 *
 * @package    Bonfire\Modules\Translate\Helpers\Languages
 * @author     Bonfire Dev Team
 * @link       http://cibonfire.com/docs/guides
 */

if ( ! function_exists('addLanguageLine')) {
    /**
     * Add one or more lines to an existing language file
     *
     * @param string $filename The name of the file to update
     * @param array $line     An array of key/value pairs containing the language entries to update/add and the values to set them to
     * @param string $language The language of the file to update
     *
     * @return bool    true on successful update, else false
     */
    function addLanguageLine($filename, $line, $language = 'english')
    {
        $orig = load_lang_file($filename, $language);
        foreach ($line as $key => $val) {
            $orig[$key] = $val;
        }

        return save_lang_file($filename, $language, $orig, false, true);
    }
}

if ( ! function_exists('list_languages')) {
	/**
     * List existing languages in the system
     *
	 * Lists the existing languages in the system by examining the core
	 * language folders in bonfire/application/language.
	 *
	 * @return string[] Array of the names of the language directories
	 */
	function list_languages()
	{
        if ( ! function_exists('directory_map')) {
    		$ci =& get_instance();
            $ci->load->helper('directory');
        }

		return directory_map(APPPATH . 'language', 1);
	}
}

if ( ! function_exists('list_lang_files')) {
	/**
     * List all language files for the specified language
     *
	 * Searches the application/languages folder as well as all core modules
	 * for folders matching the language name.
	 *
	 * @param string $language The language
	 *
	 * @return array An array of files.
	 */
	function list_lang_files($language = 'english')
	{
		$ci =& get_instance();

		$lang_files = array();

		// Base language files.
		$lang_files['core'] = find_lang_files(APPPATH . "language/{$language}/");

		// Module directories
		$modules = Modules::list_modules();
        // Application Module directories only
		$custom_modules = Modules::list_modules(true);

		foreach ($modules as $module) {
			$module_langs = Modules::files($module, 'language');
			$type = 'core';

			if (isset($module_langs[$module]['language'][$language])) {
                $path = implode('/', array(Modules::path($module, 'language'), $language));

                $files = find_lang_files($path . '/');
				if (in_array($module, $custom_modules)) {
					$type = 'custom';
				}

				if (is_array($files)) {
					foreach ($files as $file) {
						$lang_files[$type][] = $file;
					}
				}
			}
		}

		return $lang_files;
	}
}

if ( ! function_exists('find_lang_files')) {
	/**
     * Search a folder for language files
     *
	 * Searches an individual folder for any language files and returns an array
	 * appropriate for adding to the $lang_files array in the get_lang_files()
	 * function.
	 *
	 * @param string $path The folder to search
	 *
	 * @return array An array of files
	 */
	function find_lang_files($path = null)
	{
		if ( ! is_dir($path)) {
			return null;
		}

		$files = array();
		foreach (glob("{$path}*_lang.php") as $filename) {
			$files[] = basename($filename);
		}

		return $files;
	}
}

if ( ! function_exists('load_lang_file')) {
	/**
	 * Load a single language file into an array.
	 *
	 * @param string $filename The name of the file to locate. The file will be found by looking in all modules.
	 * @param string $language The language to retrieve.
	 *
	 * @return mixed An array on loading the language file, false on error
	 */
	function load_lang_file($filename = null, $language = 'english')
	{
		if (empty($filename)) {
			return null;
		}

		// Is it a application lang file? Use the English folder to determine.
        $arFiles = scandir(APPPATH . "language/english/");
		if ($filename == 'application_lang.php' || in_array($filename, $arFiles)) {
			$path = APPPATH . "language/{$language}/{$filename}";
		}
		// Look in modules
		else {
			$module = str_replace('_lang.php', '', $filename);
			$path = Modules::file_path($module, 'language', "{$language}/{$filename}");
		}

		// Load the actual array
		if (is_file($path)) {
			include($path);
		}

		if ( ! empty($lang) && is_array($lang)) {
			return $lang;
		}

		return false;
	}
}

if ( ! function_exists('save_lang_file')) {
	/**
	 * Save a language file
	 *
	 * @param string $filename The name of the file to locate. The file will be found by looking in all modules.
	 * @param string $language The language to retrieve.
	 * @param array  $settings An array of the language settings
	 * @param bool   $return   TRUE to return the contents or FALSE to write to file
	 * @param bool   $allowNewValues if true, new values can be added to the file
	 *
	 * @return mixed A string when the $return setting is true
	 */
	function save_lang_file($filename = null, $language = 'english', $settings = null, $return = false, $allowNewValues = false)
	{
		if (empty($filename) || ! is_array($settings)) {
			return false;
		}

		// Is it a application lang file? Use the English folder to determine.
        $arFiles = scandir(APPPATH . "language/english/");
		if ($filename == 'application_lang.php' || in_array($filename, $arFiles)) {
			$orig_path = APPPATH . "language/english/{$filename}";
			$path = APPPATH . "language/{$language}/{$filename}";
		}
		// Look in core modules
		else {
			$module    = str_replace('_lang.php', '', $filename);
			$orig_path = Modules::file_path($module, 'language', "english/{$filename}");
			$path      = Modules::file_path($module, 'language', "{$language}/{$filename}");

			// If it's still empty, grab the module path
			if (empty($path)) {
				$path = Modules::path($module, 'language');
			}
		}

		// Load the file and loop through the lines
		if ( ! is_file($orig_path)) {
			return false;
		}

		$contents = file_get_contents($orig_path);
		$contents = trim($contents) . "\n";

		if ( ! is_file($path)) {
			// Create the folder...
			$folder = basename($path) == 'language' ? "{$path}/{$language}" : dirname($path);
			if ( ! is_dir($folder)) {
				mkdir($folder);
				$path = basename($path) == 'language' ? "{$folder}/{$module}_lang.php" : $path;
			}
		}

		// Save the file.
		foreach ($settings as $name => $val) {
			if ($val !== '') {
				$val = '\'' . addcslashes($val, '\'\\') . '\'';
            }
			// Use strrpos() instead of strpos() so we don't lose data when
            // people have put duplicate keys in the english files
			$start = strrpos($contents, '$lang[\'' . $name . '\']');
			if ($start === false) {
				// Tried to add non-existent value?
                if ($allowNewValues && $val !== '') {
                    $contents .= "\n\$lang['{$name}'] = {$val};";
                    continue;
                } else {
                    return false;
                }
			}
			$end = strpos($contents, "\n", $start) + strlen("\n");

			if ($val !== '') {
				$replace = '$lang[\'' . $name . '\'] = ' . $val . ";\n";
			} else {
				$replace = '// ' . substr($contents, $start, $end-$start);
			}

			$contents = substr($contents, 0, $start) . $replace . substr($contents, $end);
		}

		// Is the produced code OK?
		if ( ! is_null(eval(str_replace('<?php', '', $contents)))) {
			return false;
		}

		// Make sure the file still has the php opening header in it...
		if (strpos($contents, '<?php') === false) {
			$contents = "<?php defined('BASEPATH') || exit('No direct script access allowed');\n\n{$contents}";
		}

        if ($return) {
            return $contents;
        }

		// Write the changes out...
		if ( ! function_exists('write_file')) {
			$CI = get_instance();
			$CI->load->helper('file');
		}

		if (write_file($path, $contents)) {
            return true;
        }

        return false;
	}
}
/* /bonfire/modules/translate/helpers/languages_helper.php */