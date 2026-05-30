<?php

namespace F3CMS;

/**
 * Architecture Purpose:
 * Provide the shared FORKS smoke-test contract runner used by module-owned
 * smoke surfaces. This base only resolves a declared surface/contract pair to
 * an owned method and executes it with normalized context.
 *
 * Design Intent:
 * Keep smoke orchestration at the contract-dispatch layer so each module-owned
 * smoke class can declare its own surface map without reimplementing guard
 * logic. The shared base protects the dispatch contract, while the owning
 * smoke subclass remains responsible for actual business assertions.
 *
 * Strict Constraints:
 * - Do not move module-specific smoke assertions into this shared base.
 * - Do not make this class load owner-side persistence context implicitly.
 * - Do not relax contract validation into best-effort fallback behavior.
 *
 * Porting Guidelines:
 * Preserve the two-step contract: module-owned classes declare the map, and
 * the shared runner validates and dispatches only declared methods. Cross-
 * language ports should keep typed error signaling for missing surfaces,
 * missing contracts, and invalid method mappings.
 */
class SmokeContractException extends \RuntimeException
{
    protected $errorKey;

    /**
     * Architecture Purpose:
     * Materialize a stable shared exception shape for smoke contract failures.
     *
     * Design Intent:
     * Carry a machine-readable error key alongside the human-readable message so
     * owner-side callers and ports can branch on contract failure type without
     * parsing message text.
     *
     * Strict Constraints:
     * - Keep the error key as explicit constructor input.
     * - Do not infer the error key from the message string.
     *
     * Porting Guidelines:
     * Preserve both fields as first-class data. If the target language has
     * structured exception types, keep an equivalent stable key accessor.
     */
    public function __construct($errorKey, $message)
    {
        parent::__construct($message);

        $this->errorKey = (string) $errorKey;
    }

    /**
     * Architecture Purpose:
     * Expose the stable machine-readable error key for smoke dispatch failures.
     *
     * Design Intent:
     * Let callers classify smoke contract failures without coupling to message
     * wording or transport-specific exception formatting.
     *
     * Strict Constraints:
     * - Return the stored key directly.
     * - Do not translate or remap keys here.
     *
     * Porting Guidelines:
     * Keep this accessor as a thin read of the stored contract-failure code.
     */
    public function errorKey()
    {
        return $this->errorKey;
    }
}

/**
 * Architecture Purpose:
 * Define the shared runtime contract for FORKS smoke dispatch.
 *
 * Design Intent:
 * Separate shared surface/contract validation from owner-side smoke logic so
 * module implementations only declare supported surfaces and implement the
 * mapped methods.
 *
 * Strict Constraints:
 * - This base does not own smoke content, persistence, or setup rules.
 * - Subclasses must declare contracts explicitly through methodMap().
 * - Missing or invalid mappings must fail loudly rather than silently skip.
 *
 * Porting Guidelines:
 * Preserve the abstract map declaration and the guarded dispatch pipeline.
 * Keep owner-boundary logic in subclass implementations, not in the shared
 * dispatch base.
 */
abstract class Smoke
{
    /**
     * Architecture Purpose:
     * Require each owner-side smoke class to declare its supported contract map.
     *
     * Design Intent:
     * Make supported surfaces and contracts explicit so the shared runner can
     * validate dispatch without knowing module-specific behavior.
     *
     * Strict Constraints:
     * - Return only declared owner-side mappings.
     * - Do not hide contract discovery behind runtime side effects.
     *
     * Porting Guidelines:
     * Keep this as the subclass-owned declaration point for smoke contracts.
     */
    abstract protected function methodMap();

    /**
     * Architecture Purpose:
     * Resolve and execute one declared smoke contract against normalized input.
     *
     * Design Intent:
     * Centralize guardrails for missing surfaces, missing contracts, and invalid
     * method targets so every module-owned smoke implementation inherits the
     * same contract enforcement semantics.
     *
     * Strict Constraints:
     * - Validate surface and contract existence before dispatch.
     * - Only dispatch to callable owner-side methods explicitly declared in the
     *   contract map.
     * - Do not add fallback routing or default contract behavior.
     *
     * Porting Guidelines:
     * Preserve validation order and failure categories. Ports should keep the
     * same distinction between unknown surface, unknown contract, and invalid
     * mapped method.
     */
    public function run($surface, $contract, $context = array())
    {
        $map = $this->methodMap();

        if (!isset($map[$surface])) {
            throw new SmokeContractException('surface_not_found', "Smoke surface '{$surface}' not found.");
        }

        if (!isset($map[$surface][$contract])) {
            throw new SmokeContractException('contract_not_found', "Smoke contract '{$contract}' not found for surface '{$surface}'.");
        }

        $method = $map[$surface][$contract];
        if (!is_string($method) || '' === $method || !method_exists($this, $method) || !is_callable(array($this, $method))) {
            throw new SmokeContractException('invalid_smoke_contract', 'Smoke method mapping is invalid.');
        }

        return $this->{$method}($context);
    }
}