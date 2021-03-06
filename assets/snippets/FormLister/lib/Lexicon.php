<?php namespace Helpers;

/**
 * Class Lexicon
 * @package Helpers
 */
class Lexicon
{
    protected $modx = null;
    protected $cfg = array();
    protected $_lang = array();

    /**
     * Lexicon constructor.
     * @param \DocumentParser $modx
     * @param array $cfg
     */
    public function __construct($modx, $cfg = array())
    {
        $this->modx = $modx;
        $this->cfg = $cfg;
    }

    /**
     * Загрузка языкового пакета
     *
     * @param string $name файл языкового пакета
     * @param string $lang имя языкового пакета
     * @param string $langDir папка с языковыми пакетами
     * @return array массив с лексиконом
     */
    public function loadLang($name = 'core', $lang = '', $langDir = '')
    {
        $langDir = empty($langDir) ? MODX_BASE_PATH . \APIhelpers::getkey($this->cfg, 'langDir',
                'lang/') : MODX_BASE_PATH . $langDir;
        if (empty($lang)) {
            $lang = \APIhelpers::getkey($this->cfg, 'lang', $this->modx->config['manager_language']);
        }

        if (is_scalar($name) && !empty($name)) {
            $name = array($name);
        } else {
            return $this->_lang;
        }
        foreach ($name as $n) {
            if (file_exists($langDir . "{$lang}/{$n}.inc.php")) {
                $tmp = include($langDir . "{$lang}/{$n}.inc.php");
                if (is_array($tmp)) {
                    $this->_lang = array_merge($this->_lang, $tmp);
                }
            }
        }

        return $this->_lang;
    }

    /**
     * Получение строк из массива
     *
     * @param $lang
     * @return array
     */
    public function fromArray($lang)
    {
        if (is_array($lang) && $lang) {
            $this->_lang = array_merge($this->_lang, $lang);
        }

        return $this->_lang;
    }

    /**
     * Получение строки из языкового пакета
     *
     * @param string $name имя записи в языковом пакете
     * @param string $def Строка по умолчанию, если запись в языковом пакете не будет обнаружена
     * @return string строка в соответствии с текущими языковыми настройками
     */
    public function getMsg($name, $def = '')
    {
        $out = \APIhelpers::getkey($this->_lang, $name, $def);
        if (class_exists('evoBabel', false) && isset($this->modx->snippetCache['lang'])) {
            $msg = $this->modx->runSnippet('lang', array('a' => $name));
            if (!empty($msg)) {
                $out = $msg;
            }
        }

        return $out;
    }

    /**
     * Замена в шаблоне фраз из лексикона
     *
     * @param string $tpl HTML шаблон
     * @return string
     */
    public function parseLang($tpl)
    {
        if (is_scalar($tpl) && !empty($tpl)) {
            if (preg_match_all("/\[\%([a-zA-Z0-9\.\_\-]+)\%\]/", $tpl, $match)) {
                $langVal = array();
                foreach ($match[1] as $item) {
                    $langVal[] = $this->getMsg($item);
                }
                $tpl = str_replace($match[0], $langVal, $tpl);
            }
        } else {
            $tpl = '';
        }

        return $tpl;
    }

    /**
     * @return bool
     */
    public function isReady()
    {
        return (bool)$this->_lang;
    }
}
