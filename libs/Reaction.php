<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Reaction is the backend request/orchestration boundary in the FORKS module stack.
 *   It receives request input, enforces permission and validation flow, and delegates
 *   entity persistence to Feed or owner-side rules to Kit instead of owning those
 *   responsibilities itself.
 *
 * Design Intent
 * - OntoCMS keeps request handling separate from persistence ownership. Reaction exists
 *   so module actions can coordinate auth, validation, and response shaping while
 *   preserving FORKS boundaries between request orchestration, entity persistence,
 *   runtime evaluators, and owner-side business coordination.
 *
 * Strict Constraints
 * - Reaction does not own Feed persistence semantics, schema decomposition, or generic
 *   runtime services such as WorkflowEngine.
 * - If local convenience conflicts with FORKS owner boundary, keep orchestration in
 *   Reaction and keep persistence or workflow writeback in the correct owner-side
 *   collaborator instead of collapsing everything into a controller-like class.
 * - Do not push shared libs responsibilities back into Reaction, but do not let
 *   Reaction absorb module business truth that should stay in Feed, Kit, or the
 *   owning module path.
 *
 * Porting Guidelines
 * - In .NET or Go, map Reaction to a request handler / application orchestration layer,
 *   not to a repository and not to a domain entity owner.
 * - Preserve the F3CMS split: request parsing, auth gates, and response shaping stay
 *   here; persistence ownership stays with Feed; owner-specific rules stay with Kit or
 *   the owning module boundary.
 */

class Reaction extends Module
{
    const RTN_DONE       = 'Done';       // Indicates a successful operation.
    const RTN_MISSCOLS   = 'MissCols';  // Indicates missing columns in the data.
    const RTN_WRONGDATA  = 'WrongData'; // Indicates invalid data.
    const RTN_UNVERIFIED = 'UnVerified'; // Indicates unverified data.

    /**
     * Architecture Role
     * - Dispatches a backend request to the module-specific Reaction method while
     *   keeping the entry boundary in the shared Reaction layer.
     *
     * Strict Constraints
     * - This method resolves and invokes the owner-side Reaction endpoint, but it must
     *   not absorb module business logic or Feed persistence semantics itself.
     *
     * Porting Note
     * - In other runtimes this maps to a controller/handler dispatcher, not to a
     *   domain coordinator.
     */
    public function do_rerouter($f3, $args)
    {
        try {
            $args = parent::_escape($args, false);

            // Create an instance of the module class.
            $class = '\PCMS\r' . ucfirst($args['module']);

            // Check if the action has a corresponding method.
            $method = sprintf('do_%s', $args['method']);
            if (!method_exists($class, $method)) {
                $class = str_replace('PCMS', 'F3CMS', $class);

                if (!method_exists($class, $method)) {
                    return self::_return(1004, ['class' => $class, 'method' => $method]);
                }
            }

            // Create a reflection instance of the module, and obtaining the action method.
            $reflectionClass = new \ReflectionClass($class);

            $reflectionInstance = $reflectionClass->newInstance();
            $reflectionMethod   = $reflectionClass->getMethod($method);

            // Invoke module action.
            $reflectionMethod->invokeArgs(
                $reflectionInstance,
                [$f3, $args]
            );
        } catch (\Exception $e) {
            return self::_return($e->getCode());
        }
    }

    /**
     * Architecture Role
     * - Orchestrates backend list retrieval by reading request input, applying access
     *   control, and delegating entity query ownership to Feed.
     *
     * Strict Constraints
     * - List shaping belongs here, but query truth and row retrieval stay with Feed;
     *   do not turn this method into an entity query owner.
     *
     * Porting Note
     * - Preserve the split between request pagination orchestration and repository /
     *   Feed query execution.
     */
    public function do_list($f3, $args)
    {
        $req  = parent::_getReq(); // Retrieve the request data.
        $that = get_called_class(); // Get the current class name.
        $feed = parent::_shift($that, 'feed'); // Shift to the Feed module.

        chkAuth($feed::PV_R); // Check read permissions.

        // Set pagination parameters.
        $req['page']  = (isset($req['page'])) ? ($req['page'] - 1) : 0;
        $maxLimit     = ($feed::PAGELIMIT > 24) ? $feed::PAGELIMIT : 24;
        $req['limit'] = (!empty($req['limit'])) ? max(min($req['limit'] * 1, $maxLimit), 12) : $maxLimit;

        if (!isset($req['query'])) {
            $req['query'] = '';
        }

        // Retrieve the records based on the query.
        $rtn = $feed::limitRows($req['query'], $req['page'], $req['limit']);

        // Process each record using a custom iteratee function.
        $rtn['subset'] = \__::map($rtn['subset'], function ($row) use ($that) {
            return $that::handleIteratee($row);
        });

        return self::_return(1, $rtn); // Return the processed records.
    }

    /**
     * Architecture Role
     * - Coordinates the backend save flow by enforcing auth, optional Kit validation,
     *   pre-save normalization, and final response shaping.
     *
     * Strict Constraints
     * - Reaction may orchestrate save order, but Feed remains the persistence owner and
     *   Kit remains the owner-side rule surface; do not collapse both into this method.
     *
     * Porting Note
     * - Map this to an application-layer save handler that delegates validation and
     *   persistence to the correct collaborators.
     */
    public function do_save($f3, $args)
    {
        $req  = parent::_getReq(); // Retrieve the request data.
        $feed = parent::_shift(get_called_class(), 'feed'); // Shift to the Feed module.
        $kit  = parent::_shift(get_called_class(), 'kit'); // Shift to the Kit module.

        chkAuth($feed::PV_U); // Check update permissions.

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if (parent::_exists($kit)) {
            $chkRule = $kit::rule('save');
            if (!empty($chkRule)) {
                // return self::_return(1, $chkRule);
                Validation::return($req, $chkRule);
            }
        }

        $req = self::beforeSave($req); // Preprocess the data before saving.

        $id = $feed::save($req); // Save the data using the Feed module.

        $feed::handleSave($req); // Perform additional save handling.

        return self::_return(1, ['id' => $id]); // Return the ID of the saved record.
    }

    /**
     * Architecture Role
     * - Handles image upload orchestration for backend requests and returns a transport-
     *   ready response payload.
     *
     * Strict Constraints
     * - This method coordinates login and upload adapter usage, but it does not become
     *   the owner of media persistence policy beyond the upload boundary.
     *
     * Porting Note
     * - Keep this as an endpoint-facing upload action, not as a storage domain owner.
     */
    public function do_upload($f3, $args)
    {
        kStaff::_chkLogin(); // Check if the user is logged in.

        $name = str_replace(['F3CMS\\', '\\'], ['', ''], get_called_class()); // Normalize the class name.

        [$type, $className] = preg_split('/(?<=[rfo])(?=[A-Z])/', $name); // Split the class name into type and name.

        $thumb_str = strtolower($className) . '_thn'; // Generate a thumbnail string.

        $default = f3()->exists($thumb_str) ? f3()->get($thumb_str) : f3()->get('default_thn');

        [$filename, $width, $height] = Upload::savePhoto(
            f3()->get('FILES'), [$default, f3()->get('all_thn')]
        );

        return self::_return(1, ['filename' => $filename]);
    }

    /**
     * Architecture Role
     * - Handles generic file upload orchestration for backend callers.
     *
     * Strict Constraints
     * - It should stay a thin request boundary over the upload helper, not grow into a
     *   module-specific file policy coordinator.
     *
     * Porting Note
     * - Preserve this as transport-level upload handling.
     */
    public function do_upload_file($f3, $args)
    {
        kStaff::_chkLogin();

        $filename = Upload::saveFile(f3()->get('FILES'));

        return self::_return(1, ['filename' => $filename]);
    }

    /**
     * Architecture Role
     * - Orchestrates backend delete requests by enforcing permissions and delegating the
     *   actual row deletion to Feed.
     *
     * Strict Constraints
     * - Deletion gating belongs here, but row lifecycle ownership stays with Feed; do
     *   not duplicate deletion semantics inside Reaction.
     *
     * Porting Note
     * - Keep hard-delete policy in the persistence owner and use this method as the
     *   request-facing guard/orchestrator.
     */
    public function do_del($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        chkAuth($feed::PV_D);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if (1 != $feed::HARD_DEL) {
            return self::_return(8008);
        }

        $feed::delRow($req['id']);

        return self::_return(1);
    }

    /**
     * Architecture Role
     * - Orchestrates single-row backend retrieval and normalizes the response payload
     *   for edit/get flows.
     *
     * Strict Constraints
     * - Current-row shaping may happen here, but the entity read remains Feed-owned and
     *   the method must not redefine persistence truth.
     *
     * Porting Note
     * - Model this as an application read handler layered over a repository/Feed read.
     */
    public function do_get($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        chkAuth($feed::PV_R);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if (0 == $req['id']) {
            // set default array
            $cu = ['id' => 0];
        } else {
            $cu = $feed::one($req['id']);
        }

        if (empty($cu)) {
            return self::_return(8106);
        } else {
            // handleCurrentRow
            $cu = $that::handleRow($cu);

            return self::_return(1, $cu);
        }
    }

    /**
     * Architecture Role
     * - Exposes a thin backend options endpoint that forwards option-query intent to
     *   Feed and wraps the response.
     *
     * Strict Constraints
     * - Option generation semantics stay with Feed; this method should remain a thin
     *   request boundary rather than a second query implementation.
     *
     * Porting Note
     * - Preserve this as a lightweight endpoint over owner-side option lookup.
     */
    public function do_get_opts($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        chkAuth($feed::PV_R);

        $req = self::_getReq();

        if (!isset($req['query'])) {
            $req['query'] = '';
        }

        $rows = $feed::getOpts($req['query']);

        return self::_return(1, $rows);
    }

    /**
     * Architecture Role
     * - Provides a pre-persistence hook for owner-side request normalization before the
     *   save flow reaches Feed.
     *
     * Strict Constraints
     * - Keep this hook focused on Reaction-side normalization; persistence semantics and
     *   module business truth should not silently migrate here.
     *
     * Porting Note
     * - Map this to an optional input-preparation hook in the application layer.
     */
    public static function beforeSave($params = [])
    {
        return $params;
    }

    /**
     * Architecture Role
     * - Shapes each row for list output after Feed has already produced entity data.
     *
     * Strict Constraints
     * - Use this for presentation/output adaptation, not for redefining entity
     *   persistence or query semantics.
     *
     * Porting Note
     * - Treat this as a response-mapping hook over already loaded data.
     */
    public static function handleIteratee($row = [])
    {
        return $row;
    }

    /**
     * Architecture Role
     * - Shapes a single current-row payload for backend get/edit style responses.
     *
     * Strict Constraints
     * - This is a response-side adaptation hook; it must not replace Feed as the entity
     *   owner or hide owner-side business coordination.
     *
     * Porting Note
     * - Keep this as an output mapper close to the request boundary.
     */
    public static function handleRow($row = [])
    {
        return $row;
    }

    /**
     * Architecture Role
     * - Normalizes backend query transport syntax before delegating filter generation to
     *   Feed.
     *
     * Strict Constraints
     * - Query string cleanup can happen here, but filter semantics still belong to the
     *   Feed side; do not fork a second filter language in Reaction.
     *
     * Porting Note
     * - Preserve this as a transport-to-filter adapter.
     */
    public static function _parseBackendQuery($query)
    {
        $feed = parent::_shift(get_called_class(), 'feed');

        $query = str_replace(['=', '&'], [':', ','], $query);
        $query = str_replace(',amp;', ',', $query);

        return (!empty($query)) ? $feed::genFilter($query) : [];
    }

    /**
     * Architecture Role
     * - Centralizes backend return message definitions for Reaction-level response
     *   formatting.
     *
     * Strict Constraints
     * - Keep this method limited to response message mapping; it should not become an
     *   owner for business error semantics beyond the backend transport contract.
     *
     * Porting Note
     * - Map this to a response/error catalog local to the request layer.
     */
    public static function formatMsgs()
    {
        return [
            'MissCols'   => [
                'code' => '8002',
                'msg'  => '欄位未填寫，請重新確認!',
            ],
            'WrongData'  => [
                'code' => '8204',
                'msg'  => '欄位資料有誤，請重新確認!',
            ],
            'UnVerified' => [
                'code' => '8205',
                'msg'  => 'TOKEN 不符，請重新取得!',
            ],
        ];
    }

    /**
     * Architecture Role
     * - Emits the canonical backend response envelope and terminates the request with
     *   JSON or JSONP output.
     *
     * Strict Constraints
     * - This is a transport boundary helper; it must not absorb business decision logic
     *   just because every backend path exits through it.
     *
     * Porting Note
     * - Preserve the response envelope contract separately from domain/application logic.
     */
    public static function _return($code = 1, $data = [])
    {
        $return = ['code' => (int) $code];

        if (!empty($data)) {
            $return['data'] = $data;
        }

        f3()->set('SESSION.csrf', getCSRF());

        $return['csrf'] = f3()->get('SESSION.csrf');

        // detect jsonp or json
        if (f3()->get('GET.callback')
            && (0 === strpos(f3()->get('GET.callback'), '__jp') || 0 === strpos(f3()->get('GET.callback'), 'ng_jsonp_callback_'))) {
            header('Content-Type: application/javascript; charset=utf-8');
            exit(f3()->get('GET.callback') . ' (' . json_encode($return) . ');');
        } else {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode($return));
        }
    }

    /**
     * Architecture Role
     * - Builds a request-side hash token using Feed-provided field selection rules.
     *
     * Strict Constraints
     * - Hash material selection stays aligned with Feed ownership; this helper should
     *   not invent a separate integrity model detached from the module's persistence
     *   contract.
     *
     * Porting Note
     * - Keep this as a request-integrity helper adjacent to the backend boundary.
     */
    public static function _hashKey($action, $args, $secret)
    {
        $that      = get_called_class();
        $feed      = parent::_shift($that, 'feed');
        $hash_data = '';
        $keyAry    = $feed::hashGrid($action);

        // Create the string to be hashed.
        foreach ($keyAry as $k) {
            $hash_data .= ($args[$k] ?? '') . '-';
        }

        // Create hash key.
        return hash_hmac('sha256', $hash_data . round(time() / 300), $secret);
    }
}
