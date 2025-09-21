<?php
/**
 * https://github.com/ezyang/htmlpurifier
 * HTMLPurifier 是一個用於清理和過濾 HTML 的庫，旨在防止 XSS 攻擊和其他安全問題。
 * 它可以自動清理不安全的 HTML 標籤和屬性，並確保輸出的 HTML 符合標準。
 * HTMLPurifier 提供了豐富的配置選項，可以根據需要自定義清理規則。
 * 這個類別中，我們創建了一個 PFHelper 類，該類使用單例模式來確保只有一個 HTMLPurifier 實例被創建。
 **/

namespace F3CMS;

class PFHelper extends Helper
{
    /**
     * @var PFHelper|null
     */
    private static $instance;

    /**
     * @var mixed
     */
    private $purifier;

    private function __construct()
    {
        try {
            $config = \HTMLPurifier_Config::createDefault();

            // $config->set('Cache.DefinitionImpl', null); // use on testing
            $serializerPath = f3()->get('TEMP') . 'purifier/';
            if ((!file_exists($serializerPath) && !FSHelper::mkdir($serializerPath)) || !is_writable($serializerPath)) {
                throw new \Exception('HTMLPurifier serializer path is not writable: ' . $serializerPath);
            }
            $config->set('Cache.SerializerPath', $serializerPath);

            $def = $config->getHTMLDefinition(true);
            $def->addElement('figure', 'Block', 'Flow', 'Common', ['class' => 'Text']);

            $this->purifier = new \HTMLPurifier($config);
        } catch (\Exception $e) {
            error_log('HTMLPurifier initialization failed: ' . $e->getMessage());
        }
    }

    public function purify($dirty_html)
    {
        return $this->purifier->purify($dirty_html);
    }

    /**
     * 單例取得方法
     *
     * @return PFHelper
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 禁止 clone
     */
    private function __clone()
    {
    }

    /**
     * 禁止 unserialize
     */
    public function __wakeup()
    {
    }
}
