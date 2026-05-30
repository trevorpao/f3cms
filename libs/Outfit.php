<?php

namespace F3CMS;

/**
 * Architecture Purpose:
 * Hold the shared FORKS frontend route/render boundary that sits above
 * module-owned page logic. Outfit centralizes request lifecycle hooks, static
 * page delivery, view rendering adapters, and shared presentation filters.
 *
 * Design Intent:
 * Keep cross-module frontend delivery mechanics in one shared layer while
 * leaving owner-side data loading, page decisions, and persistence truth in
 * modules, Feed, Reaction, or other owner-boundary classes.
 *
 * Strict Constraints:
 * - Do not move entity persistence or workflow writeback into Outfit.
 * - Do not turn shared rendering helpers into a generic business-logic sink.
 * - Do not let convenience rendering shortcuts override owner-boundary rules.
 *
 * Porting Guidelines:
 * Preserve Outfit as the shared frontend delivery shell. Cross-language ports
 * may split router, template, and response adapters, but should keep owner-side
 * page orchestration outside this shared layer.
 */

class Outfit extends Module
{
    const JUST_RETURN_STR = true; // Constant to indicate whether to return a string directly.

    /**
     * Architecture Purpose:
     * Expose the shared entry point for static-file-first outfit delivery.
     *
     * Design Intent:
     * Route the outward-facing entry directly into the shared static/render
     * pipeline so module outfits inherit one consistent frontend bootstrap path.
     *
     * Strict Constraints:
     * - Keep this as a thin entry wrapper.
     * - Do not add owner-side page logic here.
     *
     * Porting Guidelines:
     * Preserve this public entry as the adapter into the shared render path.
     */
    public static function show($args)
    {
        $that = get_called_class();
        $that::_staticFile($args);
    }

    /**
     * Architecture Purpose:
     * Wrap dynamic outfit actions with the shared request lifecycle envelope.
     *
     * Design Intent:
     * Ensure every owner-side outfit action flows through the same before/
     * middleware/after chain and timing instrumentation instead of reimplementing
     * request plumbing per module.
     *
     * Strict Constraints:
     * - Preserve the lifecycle ordering around the owner action.
     * - Do not inline owner-specific behavior into this wrapper.
     *
     * Porting Guidelines:
     * Keep one shared dynamic-dispatch envelope even if the target runtime uses
     * explicit controllers instead of PHP magic dispatch.
     */
    public function __call($method, $args)
    {
        $time_start = microtime(true);
        $that       = get_called_class();

        $that::_beforeRoute($args[1]);

        $response = $that::_middleware($args[1], $method);

        $that::_afterRoute($args[1]);

        $time_end = microtime(true);

        $spent = $time_end - $time_start;

        echo '<!-- spent: ' . $spent . ' -->';

        return $response;
    }

    /**
        * Architecture Purpose:
        * Normalize shared frontend request state before owner routing begins.
        *
        * Design Intent:
        * Handle globally shared concerns such as coming-soon gating, device/lang
        * setup, and CSRF seed creation once, before module outfits execute.
        *
        * Strict Constraints:
        * - Keep only shared pre-route concerns here.
        * - Do not add page-specific data loading or persistence logic.
        *
        * Porting Guidelines:
        * Preserve this as the shared pre-handler normalization hook for frontend
        * delivery, regardless of the router framework used by the target runtime.
     */
    public static function _beforeRoute($args)
    {
        if (f3()->exists('siteBeginDate')) {
            $ts  = strtotime(f3()->get('siteBeginDate'));
            $now = time();
            if ($now < $ts) {
                if (empty($args) || '/comingsoon' != $args[0]) {
                    f3()->reroute(f3()->get('uri') . '/comingsoon');
                }
            }
        }

        parent::_mobile_user_agent();
        Module::_lang($args);
        f3()->set('lang', Module::_lang());
        f3()->set('SESSION.csrf', getCSRF());
    }

    /**
        * Architecture Purpose:
        * Reserve the shared post-route extension point for outfit delivery.
        *
        * Design Intent:
        * Keep lifecycle symmetry with the pre-route hook even when the current
        * implementation does not need shared post-processing.
        *
        * Strict Constraints:
        * - Do not silently accumulate unrelated cleanup logic here.
        * - If future logic is added, it must remain shared lifecycle work only.
        *
        * Porting Guidelines:
        * Keep an explicit post-handler hook even if it remains a no-op.
     */
    public static function _afterRoute($args)
    {
    }

    /**
        * Architecture Purpose:
        * Resolve the target outfit handler and dispatch it through shared guards.
        *
        * Design Intent:
        * Apply class resolution and request escaping once in the shared layer so
        * module outfits receive normalized input instead of duplicating adapter code.
        *
        * Strict Constraints:
        * - Fail loudly when the target handler cannot be resolved.
        * - Keep escaping and dispatch generic; do not fold in owner-side rules.
        *
        * Porting Guidelines:
        * Preserve explicit class/method resolution plus normalized-input dispatch,
        * even if the target runtime uses different reflection or routing APIs.
     */
    public static function _middleware($args, string $next)
    {
        $class  = get_called_class();
        $method = str_replace('do_', '', $next);

        $class = str_replace('F3CMS', 'PCMS', $class);

        if (!method_exists($class, $method)) {
            $class = str_replace('PCMS', 'F3CMS', $class);
            if (!method_exists($class, $method)) {
                throw new \Exception('(1004) ' . $class . '::' . $next . ' not found');
            }
        }

        $args = parent::_escape($args, false);

        return call_user_func_array([$class, $method], [$args]);
    }

    /**
        * Architecture Purpose:
        * Serve a frontend response through the shared static-cache-or-render path.
        *
        * Design Intent:
        * Prefer the shared static snapshot when available, fall back to rendering
        * when needed, and expose debug variables after the response is emitted.
        *
        * Strict Constraints:
        * - Keep cache lookup/save behavior inside the shared delivery path.
        * - Do not move owner-side page composition into this method.
        * - Do not change cache miss behavior into silent fallback outside policy.
        *
        * Porting Guidelines:
        * Preserve the high-level contract: resolve cache path, reuse static output
        * when valid, otherwise render and optionally persist the snapshot.
     */
    public static function _staticFile($args, $force = false)
    {
        $that = get_called_class();

        // echo '<pre>';
        // FSHelper::mkdir(['/var/www/html/static/s/nQ/nQaKWfP9KsJ7/tw/press/2/test-news']);
        // FSHelper::mkdir(['/var/www/html/theme/default/assets/tw/press/2/test-news']);
        // FSHelper::mirror('/var/www/html/theme/default/assets', '/var/www/html/static/s/nQ/nQaKWfP9KsJ7');
        // print_r(FSHelper::ls('/var/www/html/static/s/nQ/nQaKWfP9KsJ7', true));
        // print_r(FSHelper::ls('/var/www/html/theme/default/assets', true));
        // die;

        // new cache file path
        // new file name rule

        if (f3()->get('DEBUG') > 2) {
            $html = $that::_render($args);
        } else {
            $path = dirname($args[0]);
            if (0 !== strpos($path, '/' . parent::_lang() . '/')) {
                $path = '/' . parent::_lang() . $path;
            }
            $html = SRHelper::get($path);

            if (empty($html)) {
                if (1 || f3()->get('DEBUG') > 0) { // only for test // || rStaff::_isLogin()
                    $html = $that::_render($args);
                    SRHelper::save($path, $html);
                } else {
                    f3()->error(403);
                }
            }
        }

        echo $html;
        $that::_showVariables();
    }

    /**
        * Architecture Purpose:
        * Prepare the shared spreadsheet response adapter for frontend exports.
        *
        * Design Intent:
        * Centralize XLS header setup and shared template filter registration so
        * export-oriented outfits reuse one transport contract.
        *
        * Strict Constraints:
        * - Keep this limited to response/header preparation.
        * - Do not add export business rules or owner-side query logic here.
        *
        * Porting Guidelines:
        * Preserve the separation between transport header setup and owner-side data
        * generation in the target export stack.
     */
    public static function _setXls($filename)
    {
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
        header('Pragma: no-cache'); // HTTP/1.0
        header('Content-Disposition:filename=' . $filename . '.xls');
        header('Content-type:application/vnd.ms-excel; charset=UTF-8');
        header('Content-Language:content=zh-tw');
        echo '<META HTTP-EQUIV="Content-Type" CONTENT="text/html; CHARSET=UTF-8">';

        $tp = \Template::instance();
        $tp->filter('date', '\F3CMS\Outfit::date');

        return $tp;
    }

    /**
        * Architecture Purpose:
        * Emit a shared XML response through the template adapter.
        *
        * Design Intent:
        * Keep XML response framing and UTF-8 XML sanitization in the shared render
        * layer instead of scattering transport details through owner outfits.
        *
        * Strict Constraints:
        * - Limit this method to shared XML response shaping.
        * - Do not add owner-side payload construction here.
        *
        * Porting Guidelines:
        * Preserve explicit XML header emission plus invalid-character sanitization.
     */
    public static function _echoXML($filename)
    {
        $tp = \Template::instance();
        $tp->filter('date', '\F3CMS\Outfit::date');

        echo '<';
        echo '?xml version="1.0" encoding="UTF-8"?';
        echo '>';

        echo self::utf8Xml($tp->render($filename . '.xml', 'application/xml'));
    }

    /**
        * Architecture Purpose:
        * Expose shared frontend debug variables when debug mode allows it.
        *
        * Design Intent:
        * Keep developer-facing debug rendering behind one shared gate instead of
        * spreading ad hoc dumps across module outfits.
        *
        * Strict Constraints:
        * - Only emit debug state under the shared debug policy.
        * - Do not turn this into a general logging sink.
        *
        * Porting Guidelines:
        * Preserve the debug-only exposure contract, even if rendered through a
        * different diagnostics facility.
     */
    public static function _showVariables()
    {
        if (f3()->get('DEBUG') > 1) {
            echo '<div class="pretty-code">' . hJsonEncode(f3()->get('_dzv')) . '</div>';
        }
    }

    /**
        * Architecture Purpose:
        * Normalize breadcrumb lineage into shared view data.
        *
        * Design Intent:
        * Build a stable breadcrumb structure from owner-provided page lineage so
        * rendering code can remain simple and consistent across outfits.
        *
        * Strict Constraints:
        * - Return normalized breadcrumb data, not owner-side page decisions.
        * - Do not query persistence or infer missing lineage here.
        *
        * Porting Guidelines:
        * Preserve the recursive lineage-to-array transformation before rendering.
     */
    public static function breadcrumb($ary, $isLi = true, $home = '')
    {
        $rtn  = [];
        $tmpl = ' > <a href="/' . parent::_lang() . '%slug$s">%str$s</a>';
        if ($isLi) {
            $tmpl = '<li class="breadcrumb-item"><a href="/' . parent::_lang() . '%slug$s">%str$s</a></li>';
        }

        if (empty($ary['sire'])) {
            if ('' != $home) {
                $rtn[] = ['slug' => '/', 'str' => $home];
            }
        } else {
            $rtn = self::breadcrumb($ary['sire'], $isLi);
        }

        if (!empty($ary['title'])) {
            $rtn[] = ['slug' => $ary['slug'], 'str' => $ary['title']];
        }

        return $rtn;
    }

    /**
        * Architecture Purpose:
        * Materialize normalized breadcrumb items into shared HTML fragments.
        *
        * Design Intent:
        * Keep the final breadcrumb markup format centralized so owner outfits only
        * provide normalized breadcrumb items instead of duplicating templates.
        *
        * Strict Constraints:
        * - Render only the provided breadcrumb array.
        * - Do not collapse this into owner-specific navigation policy.
        *
        * Porting Guidelines:
        * Preserve the separation between breadcrumb normalization and string/HTML
        * rendering even if a different template system is used.
     */
    public static function breadcrumb_str($ary, $isLi = true)
    {
        $str  = '';
        $tmpl = ' > <a href="/' . parent::_lang() . '%slug$s">%str$s</a>';
        if ($isLi) {
            $tmpl = '<li class="breadcrumb-item"><a href="/' . parent::_lang() . '%slug$s">%str$s</a></li>';
        }

        foreach ($ary as $val) {
            $str .= self::_sprintf($tmpl, ['slug' => $val['slug'], 'str' => $val['str']]);
        }

        return $str;
    }

    /**
        * Architecture Purpose:
        * Sanitize shared XML payloads to characters allowed by XML parsers.
        *
        * Design Intent:
        * Keep XML-character filtering in one shared helper so XML responses from
        * different outfits preserve the same transport safety rules.
        *
        * Strict Constraints:
        * - Only remove invalid XML characters.
        * - Do not add content-shaping policy beyond XML safety.
        *
        * Porting Guidelines:
        * Preserve equivalent XML character filtering in the target runtime.
     */
    public static function utf8Xml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }

    /**
        * Architecture Purpose:
        * Resolve the shared thumbnail path convention for frontend assets.
        *
        * Design Intent:
        * Keep filename derivation for shared thumbnail variants centralized so page
        * rendering can request variants without duplicating path rules.
        *
        * Strict Constraints:
        * - Only derive the path naming convention.
        * - Do not perform storage I/O or image generation here.
        *
        * Porting Guidelines:
        * Preserve the same naming contract expected by existing asset pipelines.
     */
    public static function thumbnail($path, $type)
    {
        $tmp = explode('.', $path);

        if ('sm' == $type) {
            $newpath = $tmp[0] . '_sm.' . $tmp[1];
        } else {
            [$w, $h] = f3()->get($type . '_thn');
            $newpath = $tmp[0] . '_' . $w . 'x' . $h . '.' . $tmp[1];
        }

        return $newpath;
    }

    /**
        * Architecture Purpose:
        * Apply a shared presentation transform that turns raw URLs into links.
        *
        * Design Intent:
        * Keep this text-to-markup transform in the shared presentation layer so
        * outfits can reuse one safe URL-linking rule.
        *
        * Strict Constraints:
        * - Limit this helper to presentation shaping.
        * - Do not fold business semantics or persistence rules into it.
        *
        * Porting Guidelines:
        * Preserve safe escaping around generated links in the target runtime.
     */
    public static function convertUrlsToLinks($text)
    {
        // 正則表達式匹配模式，用於識別 https 開頭的 URL
        $regex = '@(?<!["\'])((https)://[^\s/$.].([\w./])*\??[\w&=-]*)@';

        // 使用 preg_replace_callback 來替換匹配的 URL 為 HTML <a> 標籤
        $textWithLinks = preg_replace_callback($regex, function ($matches) {
            $url = $matches[1];

            // 返回替換後的 <a> 標籤
            return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a>';
        }, $text);

        return $textWithLinks;
    }

    /**
        * Architecture Purpose:
        * Resolve device-specific asset paths using the shared thumbnail convention.
        *
        * Design Intent:
        * Couple device detection to shared asset path derivation in one place so
        * outfits can render device variants without reimplementing filename rules.
        *
        * Strict Constraints:
        * - Only rewrite the asset path when a device-specific variant is expected.
        * - Do not make owner-side content decisions here.
        *
        * Porting Guidelines:
        * Preserve the current contract between shared device detection and asset
        * naming, even if device handling is refactored elsewhere.
     */
    public static function pathByDevice($path, $type)
    {
        $device = parent::_mobile_user_agent();

        if ('unknown' != $device) {
            [$w, $h] = f3()->get($type . '_thn');

            $tmp = explode('.', $path);

            $path = $tmp[0] . '_' . $w . 'x' . $h . '.' . $tmp[1];
        }

        return $path;
    }

    /**
        * Architecture Purpose:
        * Normalize tag payloads into shared frontend view state.
        *
        * Design Intent:
        * Decode the stored tag structure once and expose common page keyword state
        * used by shared rendering and SEO helpers.
        *
        * Strict Constraints:
        * - Treat this as presentation/view-state shaping only.
        * - Do not make taxonomy ownership or persistence updates here.
        *
        * Porting Guidelines:
        * Preserve the decode-and-publish contract to shared page state rather than
        * hiding it inside owner-specific templates.
     */
    public static function handleTag($tags)
    {
        $ary = [];
        if (!empty($tags)) {
            $items = json_decode($tags);
            foreach ($items as $item) {
                $ary[] = $item->title;
            }
            f3()->set('rel_tag', $ary);
            f3()->set('pageKeyword', implode(',', $ary));
        }

        return $ary;
    }

    /**
     * Architecture Purpose:
     * Expose one shared date-formatting helper to the frontend layer.
     *
     * Design Intent:
     * Keep template-facing date formatting consistent across shared filters and
     * responses without pushing date presentation rules into owner outfits.
     *
     * Strict Constraints:
     * - Restrict this helper to formatting.
     * - Do not add locale/business policy beyond the passed format.
     *
     * Porting Guidelines:
     * Preserve caller-supplied formatting semantics in the target runtime.
     */
    public static function date($val, $format)
    {
        return date($format, strtotime($val));
    }

    /**
     * Architecture Purpose:
     * Format a shared human-readable time-range string for frontend display.
     *
     * Design Intent:
     * Centralize a common presentation rule for same-day versus cross-day time
     * ranges so templates remain thin.
     *
     * Strict Constraints:
     * - Keep this as display formatting only.
     * - Do not attach scheduling or business validation rules here.
     *
     * Porting Guidelines:
     * Preserve the same-day formatting distinction because templates may assume
     * the condensed output shape.
     */
    public static function during($start, $end)
    {
        if (date('Y-m-d', strtotime($start)) == date('Y-m-d', strtotime($end))) {
            return date('Y-m-d(D) H:i', strtotime($start)) . ' ~ ' . date('H:i', strtotime($end));
        } else {
            return date('Y-m-d(D) H:i', strtotime($start)) . ' ~ ' . date('Y-m-d(D) H:i', strtotime($end));
        }
    }

    /**
     * Architecture Purpose:
     * Convert shared slug tokens into a frontend-readable label.
     *
     * Design Intent:
     * Keep this lightweight presentation transform in the shared view layer
     * instead of repeating slug prettification in templates.
     *
     * Strict Constraints:
     * - Treat the slug as display input only.
     * - Do not reinterpret slug ownership or routing semantics here.
     *
     * Porting Guidelines:
     * Preserve the same delimiter replacement and title-casing behavior.
     */
    public static function fixSlug($val)
    {
        return ucwords(preg_replace('/[-_]/', ' ', $val));
    }

    /**
     * Architecture Purpose:
     * Safely render line breaks in shared frontend text output.
     *
     * Design Intent:
     * Normalize stored newline encodings before passing them to `nl2br()` so
     * shared template output stays consistent.
     *
     * Strict Constraints:
     * - Limit this helper to newline normalization plus rendering.
     * - Do not add HTML policy beyond the existing transform.
     *
     * Porting Guidelines:
     * Preserve the current newline normalization before HTML break insertion.
     */
    public static function nl2brSecurity($val)
    {
        $val = str_replace(['&#13;&#10;', '&#13;', '&#10;'], [PHP_EOL, PHP_EOL, PHP_EOL], $val);

        return nl2br($val);
    }

    /**
     * Architecture Purpose:
     * Provide a shared uppercase presentation transform.
     *
     * Design Intent:
     * Keep trivial text-formatting helpers centralized for consistent template
     * usage instead of duplicating formatting snippets.
     *
     * Strict Constraints:
     * - Keep this helper presentation-only.
     * - Do not attach locale/business semantics.
     *
     * Porting Guidelines:
     * Preserve straightforward uppercase behavior for template callers.
     */
    public static function upper($val)
    {
        return strtoupper($val);
    }

    /**
     * Architecture Purpose:
     * Join shared frontend lists into one presentation string.
     *
     * Design Intent:
     * Offer a thin shared helper for template-friendly list joining.
     *
     * Strict Constraints:
     * - Keep this helper as a pure string join.
     * - Do not infer missing data or labels.
     *
     * Porting Guidelines:
     * Preserve caller-controlled glue semantics.
     */
    public static function join($val, $glue = ',')
    {
        return implode($glue, $val);
    }

    /**
     * Architecture Purpose:
     * Generate a shared avatar asset URL for frontend rendering.
     *
     * Design Intent:
     * Keep external avatar URL conventions centralized so templates and outfits
     * reuse one transport-safe avatar contract.
     *
     * Strict Constraints:
     * - Only construct the URL.
     * - Do not add owner identity logic beyond provided inputs.
     *
     * Porting Guidelines:
     * Preserve the existing provider split between gravatar and robohash.
     */
    public static function avatar($email, $size = 80, $type = 'avatar')
    {
        return (('cat' !== $type) ? '//www.gravatar.com/avatar/' . md5($email) . '.jpg?s=' : '//robohash.org/' . md5($email) . '?set=set4&s=') . $size;
    }

    /**
     * Architecture Purpose:
     * Format numbers through one shared frontend presentation helper.
     *
     * Design Intent:
     * Keep numeric display formatting consistent across filters and templates.
     *
     * Strict Constraints:
     * - Limit this helper to output formatting.
     * - Do not mix in currency or owner-specific rules.
     *
     * Porting Guidelines:
     * Preserve the caller-controlled decimal precision contract.
     */
    public static function numFormat($num, $decimals = 0)
    {
        return number_format($num, $decimals);
    }

    /**
     * Architecture Purpose:
     * Convert seconds into a shared minute/second display string.
     *
     * Design Intent:
     * Centralize this small presentation rule so templates do not duplicate
     * time-span formatting logic.
     *
     * Strict Constraints:
     * - Keep this as display formatting only.
     * - Do not repurpose it for duration math policy.
     *
     * Porting Guidelines:
     * Preserve the current Chinese minute/second output contract.
     */
    public static function s2m($num)
    {
        $s = $num % 60;
        $m = floor($num / 60);

        return ($s > 0) ? $m . ' 分 ' . $s . ' 秒' : $m . ' 分';
    }

    /**
        * Architecture Purpose:
        * Convert seconds into a rounded shared minute count.
        *
        * Design Intent:
        * Provide a thin presentation helper for rounded minute display.
        *
        * Strict Constraints:
        * - Keep this helper as a simple conversion.
        * - Do not add business thresholds or labels.
        *
        * Porting Guidelines:
        * Preserve the ceiling-based minute conversion rule.
     */
    public static function s2ms($num)
    {
        $m = ceil($num / 60);

        return $m;
    }

    /**
        * Architecture Purpose:
        * Convert seconds into a shared hour/minute display string.
        *
        * Design Intent:
        * Keep the mixed hour/minute presentation rule centralized for templates.
        *
        * Strict Constraints:
        * - Treat this as display formatting only.
        * - Do not attach scheduling semantics here.
        *
        * Porting Guidelines:
        * Preserve the existing Chinese hour/minute output shape.
     */
    public static function s2h($num)
    {
        $m = ceil(($num % 3600) / 60);
        $h = floor($num / 3600);

        return (($h > 0) ? $h . ' 小時' : '') . (($h > 0 && $m > 0) ? ' ' : '') . (($m > 0) ? $m . ' 分' : '');
    }

    /**
     * Architecture Purpose:
     * Keep the shared hook point for image URI rewriting in frontend output.
     *
     * Design Intent:
     * Preserve a stable adapter seam even while the current implementation is a
     * pass-through, so ports can keep URI rewrite policy isolated.
     *
     * Strict Constraints:
     * - Do not fold owner-specific storage logic into this helper.
     * - Keep pass-through semantics unless the shared asset contract changes.
     *
     * Porting Guidelines:
     * Preserve this seam as the shared image-URI adapter, even if it remains a
     * no-op in the target runtime.
     */
    public static function repathImg($str)
    {
        return $str; // str_replace('/upload/', f3()->get('picUri'), empty($str) ? '' : $str);
    }

    /**
     * Architecture Purpose:
     * Keep the shared hook point for generic URI rewriting in frontend output.
     *
     * Design Intent:
     * Mirror the image rewrite seam for non-image URIs without forcing owner
     * templates to know whether rewriting is active.
     *
     * Strict Constraints:
     * - Keep this helper generic and shared.
     * - Do not inject owner-side routing rules here.
     *
     * Porting Guidelines:
     * Preserve this no-op adapter seam for future shared URI policy changes.
     */
    public static function repathUri($str)
    {
        return $str;
    }

    /**
     * Architecture Purpose:
     * Convert shared text rows into table markup for frontend rendering.
     *
     * Design Intent:
     * Keep this structured-text presentation transform in the shared render
     * layer so templates can reuse one convention.
     *
     * Strict Constraints:
     * - Treat this strictly as presentation shaping.
     * - Do not infer owner-side schema or validation rules.
     *
     * Porting Guidelines:
     * Preserve the newline-splitting and full-width-colon parsing contract.
     */
    public static function str2tbl($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $row) {
            $row = explode('：', $row);
            $str .= '<tr><td class="title">' . $row[0] . '：</td><td>' . $row[1] . '</td></tr>';
        }

        return '<table class="normal-tbl">' . $str . '</table>';
    }

    /**
        * Architecture Purpose:
        * Convert shared multiline text into list-item markup.
        *
        * Design Intent:
        * Keep this frontend-only text-to-list transform centralized.
        *
        * Strict Constraints:
        * - Restrict this helper to presentation formatting.
        * - Do not add owner-specific list semantics.
        *
        * Porting Guidelines:
        * Preserve the newline-to-list-item transformation contract.
     */
    public static function str2li($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $idx => $row) {
            $str .= '<li>' . $row . '</li>';
        }

        return $str;
    }

    /**
     * Architecture Purpose:
     * Convert shared multiline text into hashtag link markup.
     *
     * Design Intent:
     * Centralize hashtag presentation rules so owner outfits do not duplicate
     * trimming and link construction behavior.
     *
     * Strict Constraints:
     * - Keep this helper at the presentation layer.
     * - Do not reassign search or taxonomy ownership here.
     *
     * Porting Guidelines:
     * Preserve current trimming and search-link generation semantics.
     */
    public static function str2hashtag($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $row) {
            $row = trim(str_replace(['＃', '#'], ['', ''], $row));
            if (!empty($row)) {
                $str .= ' <a href="/search?q=' . $row . '" target="_blank" aria-label="另開新視窗" class="hero-tag">#' . $row . '</a> ';
            }
        }

        return $str;
    }

    /**
     * Architecture Purpose:
     * Convert shared multiline text into VTT-styled paragraph markup.
     *
     * Design Intent:
     * Keep this frontend presentation rule centralized for templates that render
     * transcript-like content.
     *
     * Strict Constraints:
     * - Keep this helper markup-focused only.
     * - Do not add content ownership or timing rules here.
     *
     * Porting Guidelines:
     * Preserve the line-by-line paragraph generation contract.
     */
    public static function str2vtt($val)
    {
        $val = str_replace(['&#13;', '&#10;'], [PHP_EOL, ''], $val);
        $ary = explode(PHP_EOL, $val);
        $str = '';
        foreach ($ary as $row) {
            $str .= '<p class="hero-podcastList">' . $row . '</p>';
        }

        return $str;
    }

    /**
     * Architecture Purpose:
     * Decode stored HTML entities for shared frontend output handling.
     *
     * Design Intent:
     * Provide one shared decode rule used by other presentation helpers.
     *
     * Strict Constraints:
     * - Limit this helper to entity decoding.
     * - Do not mix in sanitization policy here.
     *
     * Porting Guidelines:
     * Preserve UTF-8 entity-decoding behavior and flags.
     */
    public static function htmlDecode($val)
    {
        return html_entity_decode($val, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    }

    /**
     * Architecture Purpose:
     * Expose a shared raw URL-encoding helper to templates and filters.
     *
     * Design Intent:
     * Keep transport-safe URL encoding centralized instead of duplicating it in
     * owner outfits or templates.
     *
     * Strict Constraints:
     * - Only encode the provided value.
     * - Do not add routing semantics here.
     *
     * Porting Guidelines:
     * Preserve raw-url-encoding semantics.
     */
    public static function urlencode($val)
    {
        return rawurlencode($val);
    }

    /**
     * Architecture Purpose:
     * Provide a shared safe-raw text transform for frontend output.
     *
     * Design Intent:
     * Decode stored entities and then neutralize selected raw characters so
     * templates can opt into controlled unsafe-text display.
     *
     * Strict Constraints:
     * - Keep the transform explicit and narrow.
     * - Do not broaden this into a general sanitization engine.
     *
     * Porting Guidelines:
     * Preserve the exact decode-then-replace behavior because templates may rely
     * on this specific display contract.
     */
    public static function safeRaw($val)
    {
        $val = self::htmlDecode($val);

        return str_replace(['<', '>', '@'], ['&lt;', '&gt;', '©'], $val);

        // return html_entity_decode($val, ENT_QUOTES);
        // return str_replace(['&rsquo;', '&quot;'], ['\'', '"'], $val);
    }

    /**
     * Architecture Purpose:
     * Crop shared frontend text to a fixed display length.
     *
     * Design Intent:
     * Keep this multibyte-safe truncation rule centralized for templates.
     *
     * Strict Constraints:
     * - Restrict this helper to substring extraction.
     * - Do not add ellipsis or business display policy here.
     *
     * Porting Guidelines:
     * Preserve UTF-8 multibyte substring behavior.
     */
    public static function crop($val, $len)
    {
        $val = empty($val) ? '' : $val;

        return mb_substr($val, 0, $len, 'utf-8');
    }

    /**
        * Architecture Purpose:
        * Apply shared frontend HTML minification before delivery.
        *
        * Design Intent:
        * Keep whitespace/comment reduction in one shared post-render transform while
        * respecting debug mode as the escape hatch.
        *
        * Strict Constraints:
        * - Only minify rendered output under the shared debug policy.
        * - Do not alter owner-side content decisions here.
        *
        * Porting Guidelines:
        * Preserve debug bypass behavior and the narrow HTML whitespace cleanup
        * semantics if output minification is kept in the target stack.
     */
    public static function minify($buffer)
    {
        if (f3()->get('DEBUG') > 0) {
            return $buffer;
        }

        $search = [
            '/\>[^\S ]+/s', // strip whitespaces after tags, except space
            '/[^\S ]+\</s', // strip whitespaces before tags, except space
            '/(\s)+/s', // shorten multiple whitespace sequences
            '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s',
        ];

        $replace = [
            '>',
            '<',
            '\\1',
            '',
        ];

        $buffer = preg_replace($search, $replace, $buffer);

        return $buffer;
    }

    /**
     * Architecture Purpose:
     * Resolve a shared absolute assets URI for frontend templates.
     *
     * Design Intent:
     * Keep asset-root composition centralized so templates do not hardcode the
     * current assets host/path contract.
     *
     * Strict Constraints:
     * - Limit this helper to URI composition.
     * - Do not add storage or deployment branching here.
     *
     * Porting Guidelines:
     * Preserve the shared assets-root plus relative-path composition contract.
     */
    public static function assetsSite($val)
    {
        return f3()->get('_dzv.assetsUri') . $val;
    }

    /**
     * Architecture Purpose:
     * Wrap rendered frontend content with the shared page shell.
     *
     * Design Intent:
     * Centralize shared UI path setup, navigation preload, SEO defaults, and
     * final template rendering so owner outfits only provide page-specific body
     * content and metadata.
     *
     * Strict Constraints:
     * - Keep shared shell composition here.
     * - Do not move owner-side page querying or business rules into this method.
     * - Preserve return-vs-echo behavior as the shared response contract.
     *
     * Porting Guidelines:
     * Preserve the separation between shared page shell composition and owner-
     * supplied page content when migrating to another template stack.
     */
    public static function wrapper($html, $title = '', $slug = '', $rtn = false)
    {
        $that = get_called_class();

        f3()->set('UI', f3()->get('UI') . f3()->get('theme') . '/');

        f3()->set('nav', rMenu::sort_menus(1, 0, '', 0));
        f3()->set('footernav', rMenu::sort_menus(85, 0, '', 0));

        $that::_seoMeta($title);

        f3()->set('page.canonical', '/' . Module::_lang() . $slug);
        f3()->set('page.share_link', f3()->get('uri') . $slug);

        f3()->set('year', date('Y'));

        if (!f3()->exists('act_link')) {
            f3()->set('act_link', '');
        }

        $tp = self::_origin();

        if (!$rtn) {
            echo self::minify($tp->render($html));
        } else {
            return self::minify($tp->render($html));
        }
    }

    /**
        * Architecture Purpose:
        * Build the shared legacy template instance used by wrapper-oriented output.
        *
        * Design Intent:
        * Register shared presentation filters once so wrapper rendering remains
        * consistent across outfits that still use the legacy template engine.
        *
        * Strict Constraints:
        * - Keep this method limited to shared template adapter wiring.
        * - Do not inject owner-specific filters here.
        *
        * Porting Guidelines:
        * Preserve the concept of one shared template adapter with common filters,
        * even if the concrete template engine changes.
     */
    public static function _origin()
    {
        $tp = \Template::instance();
        $tp->filter('nl2brSecurity', '\F3CMS\Outfit::nl2brSecurity');
        $tp->filter('crop', '\F3CMS\Outfit::crop');
        $tp->filter('date', '\F3CMS\Outfit::date');
        $tp->filter('str2tbl', '\F3CMS\Outfit::str2tbl');
        $tp->filter('thumbnail', '\F3CMS\Outfit::thumbnail');
        $tp->filter('avatar', '\F3CMS\Outfit::avatar');
        $tp->filter('s2m', '\F3CMS\Outfit::s2m');
        $tp->filter('s2ms', '\F3CMS\Outfit::s2ms');
        $tp->filter('htmlDecode', '\F3CMS\Outfit::htmlDecode');
        $tp->filter('urlencode', '\F3CMS\Outfit::urlencode');
        $tp->filter('safeRaw', '\F3CMS\Outfit::safeRaw');

        return $tp;
    }

    /**
        * Architecture Purpose:
        * Render a frontend page through the shared Twig-based delivery pipeline.
        *
        * Design Intent:
        * Collect shared page context, theme settings, breadcrumb data, and delivery
        * diagnostics once, then hand the final payload to the shared Twig adapter.
        *
        * Strict Constraints:
        * - Keep this method focused on shared rendering context assembly.
        * - Do not move owner-side entity loading or business branching here.
        * - Preserve the echo/return dual contract expected by callers.
        *
        * Porting Guidelines:
        * Preserve the shared context-assembly step before final template render,
        * even if the target runtime replaces Twig or `_dzv` state storage.
     */
    public static function render($html, $title = '', $slug = '', $rtn = false)
    {
        $that = get_called_class();

        _dzv('layout', str_replace(['/', '.html', '.twig'], ['-', '', ''], $html));

        $that::_seoMeta($title);

        f3()->set('page.canonical', $slug);
        f3()->set('page.share_link', f3()->get('uri') . $slug);

        _dzv('page', f3()->get('page'));

        _dzv('page.breadcrumb', self::breadcrumb(['title' => $title, 'slug' => $slug, 'sire' => f3()->get('breadcrumb_sire')]));

        $opts    = fOption::load('', 'Preload');
        $lang = f3()->get('lang');

        if (!f3()->exists('themeSetting')) {
            $setting = FSHelper::loadConfig('themeSetting.json');

            if ($lang  ==  'tw') {
                $setting['langScript'] = 'zh-Hant';
            } else {
                $setting['langScript'] = $lang;
            }

            if (!empty($opts['default']['color_name'])) {
                $setting['colorName'] = $opts['default']['color_name'];
            }

            f3()->set('themeSetting', $setting, 7200);
        } else {
            $setting = f3()->get('themeSetting');
        }

        _dzv('lang', $lang);
        _dzv('setting', $setting);

        f3()->set('opts', $opts);
        _dzv('opts', $opts);

        _dzv('feVersion', f3()->get('feVersion'));
        _dzv('theme', f3()->get('theme'));
        _dzv('uri', f3()->get('uri'));
        _dzv('main_domain', f3()->get('main_domain'));
        _dzv('csrf', f3()->get('SESSION.csrf'));
        _dzv('assetsUri', '/assets/');
        // _dzv('liffID', f3()->get('line_liff'));
        _dzv('year', date('Y'));

        if (!$rtn) {
            echo self::_twig()->render($html, f3()->get('_dzv'));
            self::_showVariables();
        } else {
            return self::_twig()->render($html, f3()->get('_dzv'));
        }
    }

    /**
        * Architecture Purpose:
        * Construct the shared Twig environment for frontend delivery.
        *
        * Design Intent:
        * Centralize loader paths, cache policy, and shared filter registration so
        * all outfits render through one consistent Twig adapter.
        *
        * Strict Constraints:
        * - Keep this method to Twig adapter wiring only.
        * - Do not register owner-specific rendering behavior here.
        *
        * Porting Guidelines:
        * Preserve one shared frontend template environment with equivalent filter
        * registration, even if the target runtime swaps out Twig.
     */
    public static function _twig()
    {
        $loader = new \Twig\Loader\FilesystemLoader(f3()->get('UI') . '/' . f3()->get('theme'));

        $loader->addPath(f3()->get('UI') . '/' . f3()->get('theme') . '/partial', 'partial');

        $twig = new Twig($loader, [
            'debug'       => true,
            'cache'       => f3()->get('TEMP') . (('cli' == php_sapi_name()) ? 'twig_cli/' : 'twig/'),
            'charset'     => 'utf-8',
            'auto_reload' => true,
        ]);

        // if (f3()->get('DEBUG') > 0) {
        //     $twig->addExtension(new \Twig_Extension_Debug());
        // }

        $filter = new \Twig\TwigFilter('nl2brSecurity', '\F3CMS\Outfit::nl2brSecurity');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('staticSite', '\F3CMS\Outfit::staticSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('assetsSite', '\F3CMS\Outfit::assetsSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('downloadSite', '\F3CMS\Outfit::downloadSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('assets', '\F3CMS\Outfit::assetsSite');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('thumbnail', '\F3CMS\Outfit::thumbnail');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('router', '\F3CMS\Outfit::router');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('breadcrumb', '\F3CMS\Outfit::breadcrumb_str');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('t', '\F3CMS\Outfit::trans');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('safeRaw', '\F3CMS\Outfit::safeRaw');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('avatar', '\F3CMS\Outfit::avatar');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('s2m', '\F3CMS\Outfit::s2m');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('s2ms', '\F3CMS\Outfit::s2ms');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('s2h', '\F3CMS\Outfit::s2h');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('numFormat', '\F3CMS\Outfit::numFormat');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('crop', '\F3CMS\Outfit::crop');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('repathImg', '\F3CMS\Outfit::repathImg');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('repathUri', '\F3CMS\Outfit::repathUri');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('gmapI18n', '\F3CMS\Outfit::gmapI18n');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('str2hashtag', '\F3CMS\Outfit::str2hashtag');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('urlencode', '\F3CMS\Outfit::urlencode');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('str2vtt', '\F3CMS\Outfit::str2vtt');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('str2li', '\F3CMS\Outfit::str2li');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('htmlDecode', '\F3CMS\Outfit::htmlDecode');
        $twig->addFilter($filter);

        $filter = new \Twig\TwigFilter('fixSlug', '\F3CMS\Outfit::fixSlug');
        $twig->addFilter($filter);

        return $twig;
    }

    /**
        * Architecture Purpose:
        * Normalize shared SEO-related page state before rendering.
        *
        * Design Intent:
        * Merge default site/page metadata with owner-supplied overrides in one
        * shared step so frontend rendering sees a stable page meta contract.
        *
        * Strict Constraints:
        * - Keep this method to shared meta-state assembly.
        * - Do not add owner-specific content loading or ranking logic here.
        *
        * Porting Guidelines:
        * Preserve the layered merge order between site defaults, page state, and
        * `_dzv` overrides.
     */
    public static function _seoMeta($title = '')
    {
        $page = fOption::load('page');

        f3()->set('page.site_name', $page['title']);
        if (empty($page['subtitle'])) {
            $page['subtitle'] = '';
        }
        f3()->set('page.site_subtitle', $page['subtitle']);

        if (f3()->exists('page')) {
            $new  = f3()->get('page', $page);
            $page = array_merge($page, $new);
        }

        if (f3()->exists('_dzv.page')) {
            $new  = f3()->get('_dzv.page', $page);
            $page = array_merge($page, $new);
        }

        f3()->set('page', $page);
        f3()->set('page.title', $title);

        f3()->set('social', fOption::load('social'));
    }

    /**
        * Architecture Purpose:
        * Reserve the shared alternate-link hook for multilingual frontend output.
        *
        * Design Intent:
        * Keep alternate-link shaping behind one shared method even while the
        * current implementation remains a placeholder.
        *
        * Strict Constraints:
        * - Keep this as shared SEO plumbing only.
        * - Do not let placeholder behavior leak owner-specific rules here.
        *
        * Porting Guidelines:
        * Preserve an explicit alternate-link seam so future multilingual handling
        * does not sprawl across owner outfits.
     */
    public static function _setAlternate($slug = '')
    {
        // TODO:
        f3()->set('page.alternate', '<1-- <link rel="alternate" href="' . $slug . '" hreflang="zh-tw" />' .
            '<link rel="alternate" href="' . $slug . '" hreflang="en" /> -->');
    }

    /**
        * Architecture Purpose:
        * Provide a shared named-placeholder formatting helper for frontend output.
        *
        * Design Intent:
        * Let shared markup helpers format templates by semantic keys rather than
        * positional indexes, keeping string construction readable in the shared
        * presentation layer.
        *
        * Strict Constraints:
        * - Keep this helper generic and formatting-only.
        * - Do not couple it to owner-side business objects.
        *
        * Porting Guidelines:
        * Preserve named-argument placeholder support because breadcrumb and other
        * shared rendering helpers depend on this calling convention.
     */
    public static function _sprintf($str, $args)
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }

        $map     = array_flip(array_keys($args));
        $new_str = preg_replace_callback('/(^|[^%])%([a-zA-Z0-9_-]+)\$/',
            function ($m) use ($map) {return $m[1] . '%' . ($map[$m[2]] + 1) . '$'; },
            $str);

        return vsprintf($new_str, $args);
    }
}
