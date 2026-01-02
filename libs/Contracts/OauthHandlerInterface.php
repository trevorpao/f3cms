<?php

namespace F3CMS\Contracts;

/**
 * OauthHandlerInterface 描述所有 OAuth Handler 需要提供的基本能力。
 */
interface OauthHandlerInterface
{
    /**
     * 根據指令回傳完整的請求 URL 與序列化參數。
     *
     * @param string                $command      指令名稱（auth/token/...）。
     * @param array<string, mixed>  $request_data 額外傳入的參數。
     *
     * @return array{0:string,1:mixed}|false 失敗時回傳 false。
     */
    public function getURL(string $command, array $request_data = []);

    /**
     * 呼叫對應的 OAuth API。
     *
     * @param string                $command      指令名稱。
     * @param array<string, mixed>  $request_data 額外傳入的參數。
     *
     * @return array|string|int|false 執行結果或錯誤代碼。
     */
    public function call(string $command, array $request_data = []);
}
