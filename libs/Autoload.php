<?php

/**
 * Architecture Purpose
 * - Shared FORKS class-loader entry that resolves type-prefix and namespace/path mapping for F3CMS runtime surfaces.
 *
 * Design Intent
 * - Keep class resolution conventions in one loader so module Feed/Reaction/Outfit/Kit naming remains stable across the codebase and future ports.
 *
 * Strict Constraints
 * - This loader must not become a generic runtime bootstrap coordinator or owner-side dependency container.
 * - It resolves class paths only; module wiring, feature flags, and business initialization stay outside this shared surface.
 *
 * Porting Guidelines
 * - Port as naming-convention and path-resolution infrastructure.
 * - Preserve the FORKS prefix-to-layer mapping even if the target runtime uses a different loader mechanism.
 */

class F3CMS_Autoloader
{
    /**
     * Architecture Purpose
     * - Register the shared class-resolution entry with the host runtime.
     *
     * Design Intent
     * - Centralize loader installation so all downstream FORKS naming rules flow through one bootstrap-safe registration point.
     *
     * Strict Constraints
     * - Registration stays at loader infrastructure level; it must not hide owner-side module bootstrap or service composition.
     *
     * Porting Guidelines
     * - Port as startup-time loader registration compatible with the target runtime's autoload or assembly-resolution hook.
     */
    public static function Register()
    {
        // If a legacy __autoload function exists, register it with SPL.
        if (function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }

        // Register the custom autoloader function based on PHP version.
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            return spl_autoload_register(['F3CMS_Autoloader', 'Load'], true, true);
        } else {
            return spl_autoload_register(['F3CMS_Autoloader', 'Load']);
        }
    }

    /**
     * Architecture Purpose
     * - Expose the canonical FORKS prefix-to-layer mapping used during class detection.
     *
     * Design Intent
     * - Keep feed/outfit/reaction/kit resolution rules explicit so future ports do not silently drift from established layer semantics.
     *
     * Strict Constraints
     * - This map describes shared naming convention only; it must not encode module-specific routing or business ownership.
     *
     * Porting Guidelines
     * - Preserve the semantic mapping even if the target runtime stores it in configuration or metadata instead of code.
     */
    public static function getType()
    {
        return [
            'f' => 'feed',
            'o' => 'outfit',
            'r' => 'reaction',
            'k' => 'kit',
        ];
    }

    /**
     * Architecture Purpose
     * - Expose the inverse layer-to-prefix mapping for FORKS naming resolution.
     *
     * Design Intent
     * - Keep both directions of the naming contract stable so generated or reflected class names preserve the same layer meaning.
     *
     * Strict Constraints
     * - This helper must stay a naming primitive, not a registry of feature-specific aliases.
     *
     * Porting Guidelines
     * - Port alongside getType() so reverse lookup remains deterministic in tooling or reflection helpers.
     */
    public static function getPrefix()
    {
        return [
            'feed'     => 'f',
            'outfit'   => 'o',
            'reaction' => 'r',
            'kit'      => 'k',
        ];
    }

    /**
        * Architecture Purpose
        * - Load the file resolved for a requested shared or module class name.
        *
        * Design Intent
        * - Separate path detection from actual include execution so the loader keeps one narrow responsibility: resolve, then load.
        *
        * Strict Constraints
        * - This method must not infer owner-side dependencies or perform broader bootstrap side effects beyond including the resolved class file.
        *
        * Porting Guidelines
        * - Port as the final loader handoff after path resolution; in compiled runtimes this may become assembly/type lookup instead of file include.
     */
    public static function Load($pClassName)
    {
        // Detect the file path for the given class name.
        $fileName = self::detect($pClassName);

        // If a valid file path is found, include the file.
        if (false !== $fileName) {
            require $fileName;
        }
    }

    /**
        * Architecture Purpose
        * - Resolve a requested class name into the canonical file path implied by FORKS naming conventions.
        *
        * Design Intent
        * - Keep namespace trimming, prefix decoding, and module path shifting in one infrastructure surface so ports can preserve the same layer contract.
        *
        * Strict Constraints
        * - Detection must stay convention-driven; it must not evolve into arbitrary feature lookup, business routing, or container-managed discovery.
        *
        * Porting Guidelines
        * - Port the naming algorithm, not the PHP-specific file include details; preserve how `f/o/r/k` prefixes map into module layer files.
     */
    public static function detect($pClassName)
    {
        // Skip detection if the class already exists or does not belong to the F3CMS or PCMS namespace.
        if (class_exists($pClassName, false) || ((0 !== strpos($pClassName, 'F3CMS')) && (0 !== strpos($pClassName, 'PCMS')))) {
            return false;
        }

        // Normalize the class name and initialize variables for namespace and file path.
        $className = ltrim($pClassName, '\\');
        $fileName  = '';
        $namespace = '';

        // Extract the namespace and class name if a namespace exists.
        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        // Handle module-specific class names with type prefixes.
        if (preg_match('/(^[fork])+([A-Z]\S*)/', $className, $match)) {
            $type       = $match[1];
            $moduleName = $match[2];

            // Construct the file path based on the module type and name.
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $moduleName) . DIRECTORY_SEPARATOR . self::getType()[$type] . '.php';

            // Check for the file in two possible locations.
            $fileName = str_replace('libs', 'modules', __DIR__) . str_replace('F3CMS', '', $fileName);

            // Use the first valid file path found.
            $fileName = $fileName;
        } else {
            // Handle standard class names without type prefixes.
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
            $fileName = __DIR__ . str_replace('F3CMS', '', $fileName);
        }

        // Return false if the file does not exist or is not readable.
        if ((false === file_exists($fileName)) || (false === is_readable($fileName))) {
            return false;
        }

        return $fileName;
    }
}

// Register the autoloader when this file is included.
F3CMS_Autoloader::Register();
