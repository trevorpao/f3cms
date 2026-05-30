<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Database-backed shared session and request-fingerprint handler for F3CMS runtime security.
 *
 * Design Intent
 * - Keep session storage, fingerprint checks, suspicious-session rejection, and CSRF/session metadata handling in one shared adapter when sessions are persisted in SQL.
 *
 * Strict Constraints
 * - This class owns session runtime integrity only; it must not absorb owner-side auth policy, login workflow coordination, or business recovery logic.
 * - SQL-backed storage here is an adapter concern, not a reason to merge session runtime with module-specific business behavior.
 *
 * Porting Guidelines
 * - Port as a DB-backed session/security adapter with the same fingerprint and suspect-session boundaries.
 * - Preserve the split between shared session runtime here and module-owned auth/business flows elsewhere.
 */
class Mession extends MHelper implements \SessionHandlerInterface
{
    // Session ID
    protected $sid;

    // Anti-CSRF token
    protected $_csrf;

    // User agent string of the client
    protected $_agent;

    // Client's IP address
    protected $_ip;

    // Callback function for handling suspicious activity
    protected $onsuspect;

    // Database table for storing session data
    protected $tbl;

    // Logger instance for debugging and logging session activities
    protected $logger;

    // Debug mode flag
    protected $debug;

    // Stores the result of the last database query
    protected $rtn;

    /**
        * Architecture Purpose
        * - Satisfy the session handler open hook for the DB-backed adapter.
        *
        * Design Intent
        * - Keep open as a minimal lifecycle hook because storage bootstrap occurs at constructor/runtime level, not per-open call.
        *
        * Strict Constraints
        * - Do not add owner-side bootstrap or auth logic here.
        *
        * Porting Guidelines
        * - Port as a no-op or lightweight adapter hook if required by the target session API.
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
        * Architecture Purpose
        * - Close the current handler cursor for the active DB-backed session.
        *
        * Design Intent
        * - Reset in-memory session adapter state without implying broader auth/business logout semantics.
        *
        * Strict Constraints
        * - Closing the handler must remain adapter-local cleanup only.
        *
        * Porting Guidelines
        * - Port as handler-state cleanup.
     */
    public function close(): bool
    {
        $this->sid = null;
        return true;
    }

    /**
        * Architecture Purpose
        * - Guard the accepted session-id shape before DB lookup or cookie reuse.
        *
        * Design Intent
        * - Reject malformed identifiers early so the SQL session adapter does not trust arbitrary inbound IDs.
        *
        * Strict Constraints
        * - This helper validates format only; trust and auth policy remain outside it.
        *
        * Porting Guidelines
        * - Preserve strict session-id validation in the target runtime's DB-backed session adapter.
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
        * - This helper normalizes strings only; it does not decide client trust.
        *
        * Porting Guidelines
        * - Port as a deterministic normalization helper shared by fingerprint checks.
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
        * - Balance hijack resistance against normal network churn by using subnet-like fingerprints instead of full address identity.
        *
        * Strict Constraints
        * - This helper derives transport fingerprint only; it is not a general auth or geo-policy mechanism.
        *
        * Porting Guidelines
        * - Preserve the same coarse IPv4/IPv6 bucketing semantics when porting.
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
        * - Detect sessions that require strict fingerprint enforcement.
        *
        * Design Intent
        * - Apply tighter runtime checks to privileged backend sessions without making every anonymous session equally brittle.
        *
        * Strict Constraints
        * - This method classifies strictness from stored session content only; role ownership remains outside the handler.
        *
        * Porting Guidelines
        * - Port as a privileged-session classifier tied to the target session payload shape.
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
        * - Centralize anti-hijack checks in the shared session adapter instead of spreading them across login or controller flows.
        *
        * Strict Constraints
        * - This method decides runtime fingerprint match only; auth recovery and user-facing handling remain elsewhere.
        *
        * Porting Guidelines
        * - Port as a deterministic fingerprint gate used before trusting stored session data.
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
        * - Ensure stale or rejected DB-backed session IDs do not remain active in client transport state.
        *
        * Strict Constraints
        * - Cookie clearing here is transport hygiene only, not a broader logout workflow.
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
        * - Quarantine a suspicious DB-backed session and terminate the current request path.
        *
        * Design Intent
        * - Keep the shared anti-hijack fail-closed response path centralized for all DB-backed sessions.
        *
        * Strict Constraints
        * - This method may clear session runtime state and raise a transport-level error, but owner-side auth/business recovery stays outside the handler.
        *
        * Porting Guidelines
        * - Port as a shared suspect-session response that destroys stored state, clears cookies, and stops request continuation.
     */
    protected function rejectSuspiciousSession(string $id, string $reason): void
    {
        $this->writeLog('suspect------::' . $reason . '::' . $id);

        if ($this->onsuspect) {
            f3()->call($this->onsuspect, [$this, $id, $reason]);
        }

        $this->destroy($id);
        $this->rtn = null;
        $this->close();
        $this->clearSessionCookie();
        f3()->error(403);
    }

    /**
        * Architecture Purpose
        * - Reject inbound cookie session IDs that are malformed or absent from DB storage.
        *
        * Design Intent
        * - Block fixation or stale-cookie reuse before the runtime trusts an incoming session identifier.
        *
        * Strict Constraints
        * - This check governs adapter trust only; broader login-state decisions stay elsewhere.
        *
        * Porting Guidelines
        * - Port as a pre-session-start gate against invalid or unknown incoming cookie IDs.
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
        $existingSession = $this->get($this->tbl, '*', ['session_id' => $incomingId]);
        $this->rtn = $currentResult;

        if (empty($existingSession)) {
            $this->writeLog('reject-unknown-session-id::' . $incomingId);
            $this->clearSessionCookie();
            session_id('');
        }
    }

    /**
        * Architecture Purpose
        * - Load and validate the serialized session payload for the requested DB-backed identifier.
        *
        * Design Intent
        * - Combine storage lookup with fingerprint validation so callers never consume unchecked session state.
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
        $this->rtn = $this->get($this->tbl, '*', ['session_id' => $id]);
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
        * - Persist serialized session state to the SQL session table.
        *
        * Design Intent
        * - Keep insert/update switching and stored fingerprint metadata inside the DB-backed adapter.
        *
        * Strict Constraints
        * - This method stores runtime session state only; it must not decide what business data enters the session.
        *
        * Porting Guidelines
        * - Port as a storage-adapter write that preserves the same fingerprint metadata fields.
     */
    public function write(string $id, string $data): bool
    {

        // check session id format
        if (!$this->isValidSessionId($id)) {
            $this->writeLog('reject-write-invalid-session-id::' . $id);
            return false;
        }

        if ($this->dry()) {
            $this->writeLog('insert------start');
            $this->insert($this->tbl, [
                'session_id' => $id,
                'data'       => $data,
                'ip'         => $this->_ip,
                'agent'      => $this->_agent,
                'stamp'      => time(),
            ]);
            $this->writeLog('insert------::' . $this->id());
        } else {
            $this->writeLog('update------start');
            $this->writeLog('data:' . $data);

            $this->update($this->tbl, [
                'data'  => $data,
                'ip'    => $this->_ip,
                'agent' => $this->_agent,
                'stamp' => time(),
            ], [
                'session_id' => $id,
            ]);
        }

        return true;
    }

    /**
        * Architecture Purpose
        * - Remove a session record from SQL storage.
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
        $this->delete($this->tbl, [
            'session_id' => $id,
        ]);

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
        * - Port only if older callers still depend on a cleanup alias.
     */
    public function cleanup(int $max)
    {
        return $this->gc($max);
    }

    /**
        * Architecture Purpose
        * - Fulfill the session GC contract for SQL-backed session storage.
        *
        * Design Intent
        * - Keep expiry cleanup inside the storage adapter when the backing store does not provide native TTL eviction.
        *
        * Strict Constraints
        * - Do not bolt owner-side retention or audit policy onto this adapter hook.
        *
        * Porting Guidelines
        * - Port as the storage-specific cleanup hook that prunes expired session rows.
     */
    public function gc(int $max): int|false
    {
        $this->delete($this->tbl, [
            'stamp[<]' => time() - 86400 * 30,
        ]);

        return 1;
    }

    /**
        * Architecture Purpose
        * - Expose the current runtime session identifier.
        *
        * Design Intent
        * - Provide a narrow accessor for the active DB-backed session state.
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
        * - Expose the session-scoped CSRF token managed by this adapter.
        *
        * Design Intent
        * - Keep token retrieval coupled to the session runtime surface that created it.
        *
        * Strict Constraints
        * - This accessor returns existing token state only; broader CSRF policy ownership stays outside.
        *
        * Porting Guidelines
        * - Port as a simple token getter.
     */
    public function csrf()
    {
        return $this->_csrf;
    }

    /**
        * Architecture Purpose
        * - Expose the client IP captured by the session adapter.
        *
        * Design Intent
        * - Make stored transport metadata available without re-reading mutable globals.
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
        * - Expose the stored session timestamp from the current DB row.
        *
        * Design Intent
        * - Provide a stable freshness accessor without reopening storage semantics elsewhere.
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
        * - Centralize empty-session detection for the DB-backed adapter lifecycle.
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
        * - Bootstrap the DB-backed session/security adapter for the current request.
        *
        * Design Intent
        * - Centralize cookie flags, table binding, handler registration, suspect-session precheck, and CSRF seed setup in one shared runtime constructor.
        *
        * Strict Constraints
        * - Constructor bootstrap must stay at shared session-runtime level; login workflow, role policy, and business recovery remain outside this class.
        *
        * Porting Guidelines
        * - Port as request-start session adapter initialization that registers the handler and applies secure-cookie defaults.
     */
    public function __construct($force = true, $onsuspect = null, $key = null)
    {
        // TODO: create table

        parent::__construct();

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
            $this->tbl       = 'sessions';

            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');

            if (PHP_VERSION_ID >= 70300) {
                ini_set('session.cookie_samesite', 'Strict');
            }

            session_set_save_handler($this, true);
            $this->rejectUnknownIncomingSessionId();

            register_shutdown_function('session_commit');
            $this->_csrf = f3()->SEED . '.' . f3()->hash(mt_rand());

            if ($key) {
                f3()->$key = $this->_csrf;
            }

            $this->_agent = $headers['User-Agent'] ?? '';

            $this->logger = new \Log('session.log');

            $this->debug = false;

            session_start();
        }
    }
}
