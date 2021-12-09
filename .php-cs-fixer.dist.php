<?php
/**
 * F3CMS
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    // ->ignoreVCSIgnored(true)
    // ->exclude(['pma', 'tmp', 'vendor'])
    // ->in([__DIR__ . '/libs', __DIR__ . '/modules']) // not always
    ->in([__DIR__ . '/modules'])
    ->append([
        // __DIR__ . '/dev-tools/doc.php',
        // __DIR__.'/php-cs-fixer', disabled, as we want to be able to run bootstrap file even on lower PHP version, to show nice message
        __FILE__,
    ])
;

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        // '@PhpCsFixer'                      => true,
        '@PSR2'                            => true,
        '@PHP73Migration'                  => true,
        'psr_autoloading'                  => false,
        'heredoc_indentation'              => false,
        'strict_comparison'                => false,
        'concat_space'                     => ['spacing' => 'one'],
        'binary_operator_spaces'           => ['operators' => [
            '=>' => 'align',
            '='  => 'align',
        ]],
        'general_phpdoc_annotation_remove' => ['annotations' => ['expectedDeprecation']], // one should use PHPUnit built-in method instead
        'modernize_strpos'                 => true, // needs PHP 8+ or polyfill
    ])
    ->setFinder($finder)
;

// special handling of fabbot.io service if it's using too old PHP CS Fixer version
if (false !== getenv('FABBOT_IO')) {
    try {
        PhpCsFixer\FixerFactory::create()
            ->registerBuiltInFixers()
            ->registerCustomFixers($config->getCustomFixers())
            ->useRuleSet(new PhpCsFixer\RuleSet($config->getRules()))
        ;
    } catch (PhpCsFixer\ConfigurationException\InvalidConfigurationException $e) {
        $config->setRules([]);
    } catch (UnexpectedValueException $e) {
        $config->setRules([]);
    } catch (InvalidArgumentException $e) {
        $config->setRules([]);
    }
}

return $config;
