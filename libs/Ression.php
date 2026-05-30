<?php

// https://redis.io/commands
// https://github.com/predis/predis/blob/main/src/ClientInterface.php
// https://github.com/predis/predis/blob/main/src/Session/Handler.php
// https://github.com/symfony/http-foundation/blob/5.3/Session/Storage/Handler/RedisSessionHandler.php
//

namespace F3CMS;

/**
 * Architecture Purpose
 * - Redis-backed shared session and request-fingerprint handler for F3CMS runtime security.
 *
 * Design Intent
 * - Keep session storage, fingerprint validation, suspicious-session rejection, and cookie hygiene in one shared runtime surface instead of scattering security mechanics across modules.
 *
 * Strict Constraints
 * - This handler owns session runtime integrity only; it must not absorb owner-side auth policy, login workflow coordination, or business recovery logic.
 * - Redis is the storage adapter here, not a place to move module-owned security decisions.
 *
 * Porting Guidelines
 * - Port as a session storage/security adapter with explicit fingerprint and suspect-session handling.
 * - Preserve the split between shared runtime checks here and module-owned auth/business responses elsewhere.
 */
class Ression implements \SessionHandlerInterface
{
    // ! Session ID
    protected $sid;
    protected // ! Anti-CSRF token
    $_csrf;
    protected // ! User agent
    $_agent;
    protected // ! IP,
    $_ip;
    protected // ! Suspect callback
    $onsuspect;

    protected $prefix;
    protected $ttl;
    protected $logger;
    protected $debug;
    protected $rtn;
    protected $replacementSid;

    /**
        * Architecture Purpose
        * - Satisfy the session handler open hook for the Redis-backed runtime adapter.
        *
        * Design Intent
        * - Keep open as a minimal lifecycle hook because connection/bootstrap ownership lives in the broader runtime setup, not per-session open.
        *
        * Strict Constraints
        * - Do not add owner-side bootstrap or auth logic here.
        *
        * Porting Guidelines
        * - Port as a no-op or lightweight adapter hook if the target session API requires it.
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
        * Architecture Purpose
        * - Close the current handler cursor for the active session.
        *
        * Design Intent
        * - Reset in-memory handler state without changing owner-side auth or business state.
        *
        * Strict Constraints
        * - Closing the handler must not imply logout semantics beyond the session runtime surface.
        *
        * Porting Guidelines
        * - Port as handler-state cleanup only.
     */
    public function close(): bool
    {
        $this->sid = null;

        return true;
    }

    /**
        * Architecture Purpose
        * - Guard the accepted session-id shape before Redis access or cookie reuse.
        *
        * Design Intent
        * - Reject malformed identifiers early so the session adapter does not trust arbitrary incoming IDs.
        *
        * Strict Constraints
        * - This method validates format only; identity trust and auth policy stay outside this helper.
        *
        * Porting Guidelines
        * - Preserve strict session-id validation in the target runtime's session adapter.
     */
    protected function isValidSessionId(string $id): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9,-]{22,40}$/', $id);
    }

    /**
        * Architecture Purpose
        * - Normalize agent strings before fingerprint comparison.
        *
        * Design Intent
        * - Keep request-fingerprint comparisons stable across superficial user-agent formatting differences.
        *
        * Strict Constraints
        * - This helper normalizes strings only; it must not decide whether a client is trusted.
        *
        * Porting Guidelines
        * - Port as a deterministic normalization helper used by fingerprint checks.
     */
    protected function normalizeAgent(string $agent): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $agent)));
    }

    /**
        * Architecture Purpose
        * - Reduce an IP address to the coarse fingerprint used by strict session checks.
        *
        * Design Intent
        * - Balance session binding against proxy/network churn by comparing a stable subnet-oriented fingerprint instead of full address identity.
        *
        * Strict Constraints
        * - This helper derives a transport fingerprint only; it must not become a general authorization or geo-policy mechanism.
        *
        * Porting Guidelines
        * - Preserve the same coarse IPv4/IPv6 bucketing semantics when porting fingerprint logic.
     */
    protected function ipFingerprint(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return implode('.', array_slice($parts, 0, 3));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', strtolower($ip));
            return implode(':', array_slice($parts, 0, 4));
        }

        return '';
    }

    /**
        * Architecture Purpose
        * - Detect sessions that require stricter fingerprint binding.
        *
        * Design Intent
        * - Apply tighter checks to privileged backend/staff sessions without forcing the same rigidity on every anonymous session.
        *
        * Strict Constraints
        * - This method infers strictness from session payload markers only; role ownership and auth workflow remain outside the handler.
        *
        * Porting Guidelines
        * - Port as a privileged-session classifier tied to the platform's serialized session shape.
     */
    protected function isStrictFingerprintSession(array $session): bool
    {
        $data = (string) ($session['data'] ?? '');

        return strpos($data, 'cu_staff') !== false && strpos($data, 'has_login') !== false;
    }

    /**
        * Architecture Purpose
        * - Compare the current request fingerprint with the stored session fingerprint.
        *
        * Design Intent
        * - Centralize shared anti-hijack comparison rules in the session runtime instead of duplicating them in login or controller flows.
        *
        * Strict Constraints
        * - This method decides runtime fingerprint match only; downstream auth recovery or user messaging stays elsewhere.
        *
        * Porting Guidelines
        * - Port as a deterministic session-fingerprint gate used before trusting stored session data.
     */
    protected function matchesFingerprint(array $session): bool
    {
        $storedAgent = $this->normalizeAgent((string) ($session['agent'] ?? ''));
        $currentAgent = $this->normalizeAgent($this->_agent);

        if ($storedAgent === '' || $currentAgent === '') {
            return false;
        }

        if (!hash_equals($storedAgent, $currentAgent)) {
            return false;
        }

        if (!$this->isStrictFingerprintSession($session)) {
            return true;
        }

        $storedIp = $this->ipFingerprint((string) ($session['ip'] ?? ''));
        $currentIp = $this->ipFingerprint($this->_ip);

        if ($storedIp === '' || $currentIp === '') {
            return false;
        }

        return hash_equals($storedIp, $currentIp);
    }

    /**
        * Architecture Purpose
        * - Remove the current session cookie from request and response state.
        *
        * Design Intent
        * - Ensure rejected or stale sessions do not continue riding an already-known cookie identifier.
        *
        * Strict Constraints
        * - Cookie clearing here is transport hygiene only; broader logout side effects remain outside the handler.
        *
        * Porting Guidelines
        * - Port as explicit cookie invalidation synchronized with session rejection.
     */
    protected function clearSessionCookie(): void
    {
        $cookieName = session_name();

        unset($_COOKIE[$cookieName]);
        unset(f3()->{'COOKIE.' . $cookieName});

        if (!headers_sent()) {
            setcookie($cookieName, '', time() - 3600, '/', '', true, true);
        }
    }

    /**
        * Architecture Purpose
        * - Generate a fresh Redis-safe session identifier for replacement flows.
        *
        * Design Intent
        * - Replace suspicious or invalidated session IDs without reusing attacker-controlled identifiers.
        *
        * Strict Constraints
        * - ID generation must remain randomness-based and collision-checked; it must not encode business meaning.
        *
        * Porting Guidelines
        * - Port using cryptographically strong randomness and equivalent collision checks against the backing session store.
     */
    protected function generateSessionId(): string
    {
        do {
            $id = bin2hex(random_bytes(16));
        } while (!empty(rc()::g($this->prefix . $id)));

        return $id;
    }

    /**
        * Architecture Purpose
        * - Publish a replacement session cookie after suspicious-session rejection.
        *
        * Design Intent
        * - Keep cookie reissue inside the shared session adapter so suspect-session remediation stays consistent.
        *
        * Strict Constraints
        * - This helper updates transport state only; it must not perform login restoration or owner-side workflow recovery.
        *
        * Porting Guidelines
        * - Port with the target runtime's secure cookie flags and same replacement semantics.
     */
    protected function pushSessionCookie(string $id): void
    {
        $cookieName = session_name();
        $params = session_get_cookie_params();

        $_COOKIE[$cookieName] = $id;
        f3()->set('COOKIE.' . $cookieName, $id);

        if (headers_sent()) {
            return;
        }

        setcookie($cookieName, $id, [
            'expires'  => 0,
            'path'     => $params['path'] ?: '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool) ($params['secure'] ?? true),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }

    /**
        * Architecture Purpose
        * - Quarantine a suspicious session and rotate the runtime back to a fresh identifier.
        *
        * Design Intent
        * - Keep the shared anti-hijack response path centralized so suspect-session handling stays consistent across modules.
        *
        * Strict Constraints
        * - This method may reset session runtime state, but owner-side auth messaging or business remediation remains outside the handler.
        *
        * Porting Guidelines
        * - Port as a shared suspect-session response that destroys stored state, clears cookies, and issues a fresh session ID.
     */
    protected function rejectSuspiciousSession(string $id, string $reason): void
    {
        $this->writeLog('suspect------::' . $reason . '::' . $id);

        if ($this->onsuspect) {
            f3()->call($this->onsuspect, [$this, $id, $reason]);
        }

        $this->destroy($id);
        $this->rtn = null;
        $this->clearSessionCookie();
        $this->replacementSid = $this->generateSessionId();
        $this->sid = $this->replacementSid;
        $this->pushSessionCookie($this->replacementSid);
    }

    /**
        * Architecture Purpose
        * - Reject inbound cookie session IDs that are malformed or unknown to Redis.
        *
        * Design Intent
        * - Block fixation or stale-cookie reuse before the runtime trusts an incoming session identifier.
        *
        * Strict Constraints
        * - This check governs adapter trust only; it must not become a general login-state decision layer.
        *
        * Porting Guidelines
        * - Port as a pre-session-start gate against unknown or invalid incoming cookie IDs.
     */
    protected function rejectUnknownIncomingSessionId(): void
    {
        $cookieName = session_name();
        $incomingId = $_COOKIE[$cookieName] ?? '';

        if ($incomingId === '') {
            return;
        }

        if (!$this->isValidSessionId($incomingId)) {
            $this->writeLog('reject-invalid-session-id::' . $incomingId);
            $this->clearSessionCookie();
            session_id('');
            return;
        }

        $currentResult = $this->rtn;
        $existingSession = rc()::g($this->prefix . $incomingId);
        $this->rtn = $currentResult;

        if (empty($existingSession)) {
            $this->writeLog('reject-unknown-session-id::' . $incomingId);
            $this->clearSessionCookie();
            session_id('');
        }
    }

    /**
        * Architecture Purpose
        * - Load and validate the serialized session payload for the requested identifier.
        *
        * Design Intent
        * - Combine store lookup with fingerprint validation so callers never consume unchecked session state.
        *
        * Strict Constraints
        * - Read returns runtime session data only; it must not absorb owner-side auth reconstruction logic.
        *
        * Porting Guidelines
        * - Port as the primary read gate that validates ID format and fingerprint before returning session bytes.
     */
    public function read(string $id): string
    {
        // check session id format
        if (!$this->isValidSessionId($id)) {
            return '';
        }

        $this->sid = $id;
        $this->writeLog('select------::' . $id);
        $this->rtn = rc()::g($this->prefix . $id);
        $this->writeLog('data:' . json_encode($this->rtn));

        if ($this->dry()) {
            return '';
        }

        if (!$this->matchesFingerprint($this->rtn)) {
            $this->rejectSuspiciousSession($id, 'fingerprint-mismatch');
            return '';
        }

        return $this->rtn['data'];
    }

    /**
        * Architecture Purpose
        * - Persist serialized session state to Redis under the active or replacement session identifier.
        *
        * Design Intent
        * - Keep session write routing, TTL persistence, and replacement-ID handling centralized in the storage adapter.
        *
        * Strict Constraints
        * - This method stores runtime session state only; it must not decide what business data should enter the session.
        *
        * Porting Guidelines
        * - Port as a storage-adapter write with TTL and replacement-ID support.
     */
    public function write(string $id, string $data): bool
    {
        $targetId = $this->replacementSid ?: $id;

        if (!$this->isValidSessionId($targetId)) {
            $this->writeLog('reject-write-invalid-session-id::' . $targetId);
            return false;
        }

        if ($targetId !== $id) {
            $this->writeLog('reroute-write-session-id::' . $id . '::' . $targetId);
        }

        if ($this->dry()) {
            $this->writeLog('insert------start');
            rc()::s($this->prefix . $targetId, [
                'data'  => $data,
                'ip'    => $this->_ip,
                'agent' => $this->_agent,
                'stamp' => time(),
            ], $this->ttl);
            $this->writeLog('insert------::' . $targetId);
        } else {
            $this->writeLog('update------start');
            $this->writeLog($_SERVER['REQUEST_URI']);
            $this->writeLog('data:' . $data);

            rc()::s($this->prefix . $targetId, [
                'data'  => $data,
                'ip'    => $this->_ip,
                'agent' => $this->_agent,
                'stamp' => time(),
            ], $this->ttl);
        }

        $this->sid = $targetId;
        $this->replacementSid = null;

        return true;
    }

    /**
        * Architecture Purpose
        * - Remove a session record from Redis.
        *
        * Design Intent
        * - Keep storage deletion as a narrow adapter primitive used by logout or suspect-session flows.
        *
        * Strict Constraints
        * - Destroy removes session state only; broader owner-side logout consequences stay outside this helper.
        *
        * Porting Guidelines
        * - Port as a backing-store delete primitive.
     */
    public function destroy(string $id): bool
    {
        rc()->del($this->prefix . $id);

        return true;
    }

    /**
        * Architecture Purpose
        * - Maintain compatibility with older cleanup call sites by delegating to gc().
        *
        * Design Intent
        * - Keep legacy lifecycle entry points thin while the actual cleanup contract stays in gc().
        *
        * Strict Constraints
        * - This wrapper must remain a compatibility bridge only.
        *
        * Porting Guidelines
        * - Port only if legacy callers still need a cleanup alias.
     */
    public function cleanup(int $max)
    {
        return $this->gc($max);
    }

    /**
        * Architecture Purpose
        * - Fulfill the session GC contract for a TTL-managed Redis store.
        *
        * Design Intent
        * - Signal success while deferring expiry enforcement to Redis TTL rather than duplicating cleanup scans in PHP.
        *
        * Strict Constraints
        * - Do not bolt owner-side retention policy onto this adapter hook.
        *
        * Porting Guidelines
        * - Port as the storage-specific GC contract; when the backend has native TTL, keep this method minimal.
     */
    public function gc(int $max): int|false
    {
        // Redis handles expiration via TTL; return success indicator.
        return 1;
    }

    /**
        * Architecture Purpose
        * - Expose the current runtime session identifier.
        *
        * Design Intent
        * - Provide a narrow accessor for already-established session state.
        *
        * Strict Constraints
        * - This accessor must not infer or create auth state.
        *
        * Porting Guidelines
        * - Port as a lightweight session-id getter.
     */
    public function sid()
    {
        return $this->sid;
    }

    /**
        * Architecture Purpose
        * - Expose the session-scoped CSRF token managed by this runtime adapter.
        *
        * Design Intent
        * - Keep token retrieval coupled to the session runtime surface that created it.
        *
        * Strict Constraints
        * - This accessor returns existing token state only; CSRF policy ownership stays at the broader app boundary.
        *
        * Porting Guidelines
        * - Port as a simple token getter tied to the session adapter.
     */
    public function csrf()
    {
        return $this->_csrf;
    }

    /**
        * Architecture Purpose
        * - Expose the client IP captured by the session runtime adapter.
        *
        * Design Intent
        * - Make captured transport metadata available without re-reading mutable global state.
        *
        * Strict Constraints
        * - This accessor does not decide trust or policy.
        *
        * Porting Guidelines
        * - Port as a stored-metadata getter.
     */
    public function ip()
    {
        return $this->_ip;
    }

    /**
        * Architecture Purpose
        * - Expose the stored session timestamp from the current session record.
        *
        * Design Intent
        * - Provide a stable accessor for session freshness checks without reopening storage semantics elsewhere.
        *
        * Strict Constraints
        * - This accessor must not backdoor session revalidation policy.
        *
        * Porting Guidelines
        * - Port as a lazy metadata getter if the target runtime still exposes session timestamps.
     */
    public function stamp()
    {
        if (!$this->sid) {
            session_start();
        }

        return $this->dry() ? false : $this->rtn['stamp'];
    }

    /**
        * Architecture Purpose
        * - Expose the user-agent string captured for fingerprint checks.
        *
        * Design Intent
        * - Keep fingerprint metadata accessible from the same adapter that normalizes and compares it.
        *
        * Strict Constraints
        * - This accessor returns captured transport metadata only.
        *
        * Porting Guidelines
        * - Port as a stored-metadata getter.
     */
    public function agent()
    {
        return $this->_agent;
    }

    /**
     * Architecture Purpose
     * - Report whether the handler currently has a loaded session record.
     *
     * Design Intent
     * - Centralize empty-session detection for the storage adapter lifecycle.
     *
     * Strict Constraints
     * - This method speaks only about adapter record presence, not login or business state.
     *
     * Porting Guidelines
     * - Port as a narrow record-existence helper if still useful in the target runtime.
     */
    public function dry()
    {
        return empty($this->rtn) ? true : false;
    }

    /**
     * Architecture Purpose
     * - Emit session runtime diagnostics when debug logging is enabled.
     *
     * Design Intent
     * - Keep adapter-level tracing close to the session handler so debugging does not leak into owner modules.
     *
     * Strict Constraints
     * - Logging here must remain observational and must not influence session policy.
     *
     * Porting Guidelines
     * - Port as optional adapter-local debug tracing.
     */
    public function writeLog($str)
    {
        ($this->debug) ? $this->logger->write($str) : '';
    }

    /**
     * Architecture Purpose
     * - Bootstrap the Redis-backed session/security adapter for the current request.
     *
     * Design Intent
     * - Centralize cookie flags, handler registration, suspect-session precheck, and CSRF seed setup in one shared runtime constructor.
     *
     * Strict Constraints
     * - Constructor bootstrap must stay at shared session-runtime level; login workflow, role policy, and business recovery remain outside this class.
     *
     * Porting Guidelines
     * - Port as request-start session adapter initialization that registers the handler and applies secure-cookie defaults.
     */
    public function __construct($force = true, $onsuspect = null, $key = null)
    {
        $headers   = f3()->HEADERS;
        $agentBots = ['bot', 'crawl', 'curl', 'dataprovider', 'search', 'get', 'spider', 'find', 'java', 'majesticsEO', 'google', 'yahoo', 'teoma', 'contaxe', 'yandex', 'libwww-perl', 'facebookexternalhit'];
        $blockIps  = [];

        f3()->JAR = [
            'lifetime' => (86400 * f3()->get('token_expired')),
            'samesite' => 'Strict',          // [重點] 限制跨站請求 (防範 CSRF)，可視需求改為 Lax || Strict
            'secure' => true,
            'httponly' => true,
        ];

        $this->_agent = $headers['User-Agent'] ?? '';
        $this->_ip    = f3()->IP;

        if (!preg_match('/' . implode('|', $agentBots) . '/i', $this->_agent) && !in_array($this->_ip, $blockIps)) {
            $this->onsuspect = $onsuspect;

            $this->prefix = 'sess_';
            $this->ttl    = ini_get('session.gc_maxlifetime');

            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');
            ini_set('session.cookie_samesite', 'Strict');

            session_set_save_handler($this, true);
            $this->rejectUnknownIncomingSessionId();

            register_shutdown_function('session_commit');
            $this->_csrf = f3()->SEED . '.' . f3()->hash(mt_rand());

            if ($key) {
                f3()->$key = $this->_csrf;
            }

            $this->logger = new \Log('session.log');

            $this->debug = false;

            session_start();
        }
    }
}
