<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        'src',
        'tests'
    ])
    ->ignoreVCSIgnored(true)
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHPUnit60Migration:risky' => false,
        '@Symfony' => true,
        '@Symfony:risky' => false,
        'align_multiline_comment' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => true,
        'concat_space' => ['spacing' => 'one'],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'compact_nullable_typehint' => true,
        'declare_strict_types' => true,
        'final_class' => false,
        'final_public_method_for_abstract_class' => false,
        'function_typehint_space' => true,
        'heredoc_to_nowdoc' => true,
        'is_null' => true,
        'list_syntax' => ['syntax' => 'short'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'native_function_type_declaration_casing' => true,
        'no_extra_blank_lines' => ['tokens' => ['break', 'continue', 'extra', 'return', 'throw', 'use', 'parenthesis_brace_block', 'square_brace_block', 'curly_brace_block']],
        'no_null_property_initialization' => true,
        'echo_tag_syntax' => ['format' => 'long'],
        'no_superfluous_elseif' => true,
        'no_superfluous_phpdoc_tags' => true,
        'no_unneeded_curly_braces' => true,
        'no_unneeded_final_method' => true,
        'no_unreachable_default_argument_value' => false,
        'no_unset_cast' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_whitespace_before_comma_in_array' => true,
        'nullable_type_declaration_for_default_null_value' => false,
        'ordered_class_elements' => false,
        'ordered_imports' => true,
        'ordered_interfaces' => false,
        'phpdoc_to_comment' => false,
        'php_unit_strict' => false,
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_no_package' => true,
        'phpdoc_order' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_var_annotation_correct_order' => true,
        'protected_to_private' => false,
        'self_static_accessor' => true,
        'semicolon_after_instruction' => true,
        'simple_to_complex_string_variable' => true,
        'single_line_comment_style' => true,
        'single_line_throw' => false,
        'single_trait_insert_per_statement' => true,
        'strict_comparison' => false,
        'strict_param' => false,
        'yoda_style' => false,
        'class_attributes_separation' => true,
    ])
    ->setFinder($finder);
