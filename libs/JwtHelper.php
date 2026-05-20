<?php

namespace F3CMS;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use stdClass;
use UnexpectedValueException;

/**
 * JwtHelper 提供 member center 與下游服務之間的短效 session token 發行與驗簽。
 *
 * 設計慣例：
 * - 固定使用 RS256
 * - 預設 TTL 為 300 秒
 * - 標準 claims 由 helper 自動補齊：iss, aud, sub, iat, nbf, exp, jti
 * - 自訂 claims 僅保留最小必要集合：scopes, token_version, token_use
 */
class JwtHelper extends Helper
{
    private const ALGORITHM = 'RS256';
    private const DEFAULT_ISSUER = 'member-center';
    private const DEFAULT_TTL = 300;
    private const DEFAULT_LEEWAY = 30;
    private const TOKEN_USE = 'service_session';

    /** @var array<string, mixed> */
    protected array $config = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct();

        $this->config = array_merge($this->defaultConfig(), $config);
        $this->config['ttl'] = max(60, (int) ($this->config['ttl'] ?? self::DEFAULT_TTL));
        $this->config['leeway'] = max(0, (int) ($this->config['leeway'] ?? self::DEFAULT_LEEWAY));
    }

    /**
     * 建立給指定服務使用的短效 session token。
     *
     * @param string|int $userId
     * @param array<int, string> $scopes
     */
    public function issueServiceSessionToken($userId, array $scopes, int $tokenVersion, string $audience): string
    {
        $subject = trim((string) $userId);
        if ('' === $subject) {
            throw new InvalidArgumentException('userId is required');
        }

        if ($tokenVersion < 0) {
            throw new InvalidArgumentException('tokenVersion must be greater than or equal to 0');
        }

        $audience = trim($audience);
        if ('' === $audience) {
            throw new InvalidArgumentException('audience is required');
        }

        $now = time();
        $payload = [
            'iss' => $this->getIssuer(),
            'aud' => $audience,
            'sub' => $subject,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->getTtl(),
            'jti' => bin2hex(random_bytes(16)),
            'scopes' => $this->normalizeScopes($scopes),
            'token_version' => $tokenVersion,
            'token_use' => self::TOKEN_USE,
        ];

        $headers = [];
        if ('' !== $this->getKeyId()) {
            $headers['kid'] = $this->getKeyId();
        }

        return JWT::encode(
            $payload,
            $this->resolvePrivateKey(),
            self::ALGORITHM,
            $headers['kid'] ?? null,
            $headers
        );
    }

    /**
     * 驗證 token 並回傳標準化後的 session 資訊。
     *
     * @return array<string, mixed>
     */
    public function verifyServiceSessionToken(string $token, string $audience, ?int $expectedTokenVersion = null): array
    {
        $token = trim($token);
        if ('' === $token) {
            throw new InvalidArgumentException('token is required');
        }

        $audience = trim($audience);
        if ('' === $audience) {
            throw new InvalidArgumentException('audience is required');
        }

        $previousLeeway = JWT::$leeway;

        try {
            JWT::$leeway = $this->getLeeway();
            $payload = JWT::decode($token, new Key($this->resolvePublicKey(), self::ALGORITHM));
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        return $this->normalizeVerifiedPayload($payload, $audience, $expectedTokenVersion);
    }

    public function extractBearerToken(string $authorizationHeader): ?string
    {
        $authorizationHeader = trim($authorizationHeader);
        if ('' === $authorizationHeader) {
            return null;
        }

        if (1 !== preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return '' === $token ? null : $token;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'issuer' => $this->readConfigValue('jwt.issuer', self::DEFAULT_ISSUER),
            'private_key' => $this->readConfigValue('jwt.private_key', ''),
            'public_key' => $this->readConfigValue('jwt.public_key', ''),
            'kid' => $this->readConfigValue('jwt.kid', ''),
            'ttl' => (int) $this->readConfigValue('jwt.ttl', self::DEFAULT_TTL),
            'leeway' => (int) $this->readConfigValue('jwt.leeway', self::DEFAULT_LEEWAY),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeScopes(array $scopes): array
    {
        $normalized = [];

        foreach ($scopes as $scope) {
            $scope = trim((string) $scope);
            if ('' === $scope) {
                continue;
            }

            $normalized[$scope] = $scope;
        }

        $normalized = array_values($normalized);
        sort($normalized);

        return $normalized;
    }

    protected function normalizeVerifiedPayload(stdClass $payload, string $audience, ?int $expectedTokenVersion = null): array
    {
        if (($payload->token_use ?? null) !== self::TOKEN_USE) {
            throw new UnexpectedValueException('Invalid token_use');
        }

        if (($payload->iss ?? null) !== $this->getIssuer()) {
            throw new UnexpectedValueException('Invalid issuer');
        }

        if (!$this->audienceMatches($payload->aud ?? null, $audience)) {
            throw new UnexpectedValueException('Invalid audience');
        }

        $subject = trim((string) ($payload->sub ?? ''));
        if ('' === $subject) {
            throw new UnexpectedValueException('Missing subject');
        }

        $tokenVersion = $payload->token_version ?? null;
        if (!is_int($tokenVersion) && !(is_string($tokenVersion) && ctype_digit($tokenVersion))) {
            throw new UnexpectedValueException('Invalid token_version');
        }

        $tokenVersion = (int) $tokenVersion;
        if (null !== $expectedTokenVersion && $tokenVersion !== $expectedTokenVersion) {
            throw new UnexpectedValueException('Unexpected token_version');
        }

        $scopes = $payload->scopes ?? [];
        if (!is_array($scopes)) {
            throw new UnexpectedValueException('Invalid scopes');
        }

        return [
            'issuer' => (string) $payload->iss,
            'audience' => $audience,
            'user_id' => $subject,
            'scopes' => $this->normalizeScopes($scopes),
            'token_version' => $tokenVersion,
            'token_use' => (string) $payload->token_use,
            'jti' => (string) ($payload->jti ?? ''),
            'issued_at' => (int) ($payload->iat ?? 0),
            'not_before' => (int) ($payload->nbf ?? 0),
            'expires_at' => (int) ($payload->exp ?? 0),
        ];
    }

    protected function audienceMatches($audClaim, string $expectedAudience): bool
    {
        if (is_string($audClaim)) {
            return hash_equals($expectedAudience, $audClaim);
        }

        if (is_array($audClaim)) {
            foreach ($audClaim as $audience) {
                if (is_string($audience) && hash_equals($expectedAudience, $audience)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function resolvePrivateKey(): string
    {
        return $this->resolveKeyMaterial((string) ($this->config['private_key'] ?? ''), 'private_key');
    }

    protected function resolvePublicKey(): string
    {
        return $this->resolveKeyMaterial((string) ($this->config['public_key'] ?? ''), 'public_key');
    }

    protected function resolveKeyMaterial(string $configuredKey, string $label): string
    {
        $configuredKey = trim($configuredKey);
        if ('' === $configuredKey) {
            throw new InvalidArgumentException(sprintf('JWT %s is required', $label));
        }

        if (is_readable($configuredKey) && is_file($configuredKey)) {
            $fileContent = file_get_contents($configuredKey);
            if (false === $fileContent || '' === trim($fileContent)) {
                throw new InvalidArgumentException(sprintf('JWT %s file is empty: %s', $label, $configuredKey));
            }

            return $fileContent;
        }

        return $configuredKey;
    }

    protected function getIssuer(): string
    {
        $issuer = trim((string) ($this->config['issuer'] ?? ''));
        if ('' === $issuer) {
            throw new InvalidArgumentException('JWT issuer is required');
        }

        return $issuer;
    }

    protected function getTtl(): int
    {
        return (int) ($this->config['ttl'] ?? self::DEFAULT_TTL);
    }

    protected function getLeeway(): int
    {
        return (int) ($this->config['leeway'] ?? self::DEFAULT_LEEWAY);
    }

    protected function getKeyId(): string
    {
        return trim((string) ($this->config['kid'] ?? ''));
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    protected function readConfigValue(string $key, $default = null)
    {
        if (!function_exists('f3')) {
            return $default;
        }

        $value = f3()->get($key);

        return null === $value || '' === $value ? $default : $value;
    }
}