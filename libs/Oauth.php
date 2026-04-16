<?php

namespace F3CMS;

use F3CMS\Contracts\OauthHandlerInterface;
use F3CMS\OauthHandler\FBOauthHandler;
use F3CMS\OauthHandler\GoogleOauthHandler;
use F3CMS\OauthHandler\LineOauthHandler;
use F3CMS\OauthHandler\MOEOIDCHelper;
use InvalidArgumentException;

/**
 * Oauth 統一管理多種第三方登入流程，並以 DI 注入 Handler 與 Opauth Factory。
 */
class Oauth extends Helper
{
    /** @var array<string, OauthHandlerInterface> */
    protected array $handlers = [];

    /** @var callable */
    protected $opauthFactory;

    protected static ?self $instance = null;

    /**
     * @param array<string, OauthHandlerInterface> $handlers
     * @param callable|null                        $opauthFactory 可注入自訂 Opauth 初始化工廠。
     */
    public function __construct(array $handlers = [], ?callable $opauthFactory = null)
    {
        foreach ($handlers as $alias => $handler) {
            $this->addHandler($alias, $handler);
        }

        if (empty($this->handlers)) {
            $this->handlers = self::buildDefaultHandlers();
        }

        $this->opauthFactory = $opauthFactory ?? [static::class, 'createOpauthBridge'];
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 設定 Opauth 路由，並註冊成功/失敗的事件處理。
     *
     * @param string $configPath Opauth 設定檔路徑。
     */
    public static function setRoute($configPath): void
    {
        self::instance()->configureRoute((string) $configPath);
    }

    public function configureRoute(string $configPath): void
    {
        f3()->config($configPath, true);

        $config = f3()->exists('opauth') ? f3()->get('opauth') : (f3()->get('opauth_config') ?? []);
        $factory = $this->opauthFactory;
        $opauth  = $factory($config);

        $opauth->onAbort(function ($data) {
            header('Content-Type: text/plain');
            echo 'Auth request was canceled.' . "\n";
            print_r($data);
        });

        $opauth->onSuccess('\F3CMS\oMember::_oauth');
    }

    /**
     * 透過 access token 或 id token 驗證第三方登入。
     */
    public static function byToken($way, $token)
    {
        return self::instance()->validateToken((string) $way, (string) $token);
    }

    public function validateToken(string $provider, string $token): array
    {
        $provider = $this->normalizeProvider($provider);

        try {
            $handler = $this->getHandler($provider);
        } catch (InvalidArgumentException $exception) {
            return ['error' => 'bad provider', 'error_description' => sprintf('provider: %s', $provider)];
        }

        $result = $this->dispatchTokenFlow($provider, $handler, $token);

        if (!isset($result['error'])) {
            $formatted = $this->formatProfile($provider, $result);
            if (null !== $formatted) {
                return $formatted;
            }
        }

        return $result;
    }

    public function addHandler(string $alias, OauthHandlerInterface $handler): void
    {
        $this->handlers[$this->normalizeProvider($alias)] = $handler;
    }

    public function hasHandler(string $alias): bool
    {
        return isset($this->handlers[$this->normalizeProvider($alias)]);
    }

    protected function getHandler(string $alias): OauthHandlerInterface
    {
        $alias = $this->normalizeProvider($alias);
        if (!isset($this->handlers[$alias])) {
            throw new InvalidArgumentException(sprintf('OAuth provider "%s" is not registered.', $alias));
        }

        return $this->handlers[$alias];
    }

    protected function dispatchTokenFlow(string $provider, OauthHandlerInterface $handler, string $token): array
    {
        switch ($provider) {
            case 'google':
                return $this->decodeResponse($handler->call('tokeninfo', ['id_token' => $token]), 'google', 'tokeninfo');
            case 'facebook':
                return $this->decodeResponse($handler->call('userinfo', ['access_token' => $token]), 'facebook', 'userinfo');
            case 'line':
                return $this->decodeResponse($handler->call('verify', ['id_token' => $token]), 'line', 'verify');
            case 'oidc':
                $profile = $this->decodeResponse($handler->call('userinfo', ['access_token' => $token]), 'oidc', 'userinfo');
                if (isset($profile['error'])) {
                    return $profile;
                }

                $eduInfo = $handler->call('eduinfo', ['access_token' => $token]);
                $decoded = $this->normalizeResponse($eduInfo);
                if (is_array($decoded) && !isset($decoded['error'])) {
                    $profile['eduinfo'] = $decoded;
                }

                return $profile;
            default:
                return ['error' => 'unsupported_provider', 'error_description' => sprintf('provider: %s', $provider)];
        }
    }

    protected function decodeResponse($response, string $provider, string $context): array
    {
        if (is_int($response)) {
            return ['error' => 'http_error', 'error_description' => sprintf('%s status: %d', $provider, $response)];
        }

        $decoded = $this->normalizeResponse($response);
        if (null === $decoded) {
            return ['error' => 'bad response', 'error_description' => sprintf('%s %s', $provider, $context)];
        }

        return $decoded;
    }

    protected function formatProfile(string $provider, array $data): ?array
    {
        switch ($provider) {
            case 'google':
                if (!empty($data['sub'])) {
                    return [
                        'provider' => 'Google',
                        'uid'      => $data['sub'],
                        'info'     => [
                            'name'  => $data['name'] ?? '',
                            'image' => $data['picture'] ?? '',
                            'email' => $data['email'] ?? '',
                        ],
                    ];
                }
                break;
            case 'facebook':
                if (!empty($data['id'])) {
                    return [
                        'provider' => 'Facebook',
                        'uid'      => $data['id'],
                        'info'     => [
                            'name'  => $data['name'] ?? '',
                            'image' => 'https://graph.facebook.com/' . $data['id'] . '/picture?type=large',
                            'email' => $data['email'] ?? '',
                        ],
                    ];
                }
                break;
            case 'line':
                if (!empty($data['sub'])) {
                    return [
                        'provider' => 'Line',
                        'uid'      => $data['sub'],
                        'info'     => [
                            'name'  => $data['name'] ?? '',
                            'image' => $data['picture'] ?? '',
                            'email' => $data['email'] ?? '',
                        ],
                    ];
                }
                break;
            case 'oidc':
                if (!empty($data['sub'])) {
                    return [
                        'provider' => 'OIDC',
                        'uid'      => $data['sub'],
                        'info'     => [
                            'name'    => $data['name'] ?? '',
                            'email'   => $data['email'] ?? '',
                            'eduinfo' => $data['eduinfo'] ?? [],
                        ],
                    ];
                }
                break;
        }

        return null;
    }

    protected function normalizeProvider(string $alias): string
    {
        return strtolower(trim($alias));
    }

    protected function normalizeResponse($response): ?array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (JSON_ERROR_NONE === json_last_error()) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<string, OauthHandlerInterface>
     */
    protected static function buildDefaultHandlers(): array
    {
        $handlers = [];

        if (class_exists(GoogleOauthHandler::class)) {
            $handlers['google'] = new GoogleOauthHandler();
        }

        if (class_exists(FBOauthHandler::class)) {
            $handlers['facebook'] = new FBOauthHandler();
        }

        if (class_exists(LineOauthHandler::class)) {
            $handlers['line'] = new LineOauthHandler();
        }

        if (class_exists(MOEOIDCHelper::class)) {
            $handlers['oidc'] = new MOEOIDCHelper();
        }

        return $handlers;
    }

    protected static function createOpauthBridge(array $config)
    {
        return \call_user_func(['\OpauthBridge', 'instance'], $config);
    }
}
