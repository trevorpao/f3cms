<?php

namespace F3CMS;

use DeepL\Translator;

class DeepLHelper
{
    /**
     * DeepLHelper 是一個用於與 DeepL 翻譯 API 互動的單例類別。
     * 它提供了翻譯文字的靜態方法，並確保只有一個翻譯器實例被創建。
     */

    /**
     * @var DeepLHelper|null 單例實例
     */
    private static $instance = null;

    /**
     * @var Translator DeepL 翻譯器實例
     */
    private $translator;

    /**
     * 私有建構子，防止外部直接實例化。
     * 此方法會從應用程式配置中讀取 DeepL API 金鑰，並初始化 Translator 實例。
     *
     * @throws \InvalidArgumentException 如果未提供 API 金鑰。
     */
    private function __construct()
    {
        $apiKey = f3()->get('deepl.key');
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('DeepL API key is required.');
        }

        $this->translator = new Translator($apiKey);
    }

    /**
     * 禁止 clone
     */
    private function __clone() {}

    /**
     * 禁止 unserialize
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }

    /**
     * 獲取單例實例。
     *
     * 此方法確保 DeepLHelper 類別的唯一實例，並在需要時進行初始化。
     *
     * @return DeepLHelper 單例實例。
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 靜態翻譯文字。
     *
     * 此方法使用 DeepL API 將文字翻譯為指定的目標語言。
     *
     * @param string $text 要翻譯的文字。
     * @param string $targetLang 目標語言 (例如 'EN', 'ZH', 'JA')。
     * @param string|null $sourceLang 原始語言 (可選，若為 null 則自動偵測)。
     * @return string 翻譯後的文字。
     * @throws \InvalidArgumentException 如果未提供文字或目標語言。
     */
    public static function translate($text, $targetLang, $sourceLang = null)
    {
        if (empty($text) || empty($targetLang)) {
            throw new \InvalidArgumentException('Text and target language are required.');
        }

        if ($targetLang == 'en') {
            $targetLang = 'en-US';
        }

        try {
            $instance = self::getInstance();
            $result = $instance->translator->translateText($text, $sourceLang, $targetLang, [
                'tag_handling' => 'html',
                'split_sentences' => 'nonewlines',
                // 'formality' => 'less',
            ]);
            return $result->text;
        } catch (\Exception $e) {
            // 記錄錯誤日誌或處理例外
            self::_log('DeepL translation error: ' . $e->getMessage());
            return 'Translation failed.';
        }
    }

    /**
     * 記錄錯誤訊息。
     *
     * 此方法將錯誤訊息寫入日誌檔案。
     *
     * @param string|array $str 要記錄的訊息。
     */
    private static function _log($str)
    {
        $logger = new \Log('deepl.log');
        $logger->write((is_string($str)) ? $str : jsonEncode($str));
    }
}
