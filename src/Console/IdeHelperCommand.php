<?php

namespace Nuwave\Lighthouse\Console;

use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\SchemaPrinter;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Console\Command;
use Nuwave\Lighthouse\Schema\AST\ASTCache;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class IdeHelperCommand extends Command
{
    public const OPENING_PHP_TAG = /** @lang GraphQL */ "<?php\n";

    public const GENERATED_NOTICE = /** @lang GraphQL */ <<<'GRAPHQL'
# File generated by "php artisan lighthouse:ide-helper".
# Do not edit this file directly.
# This file should be ignored by git as it can be autogenerated.


GRAPHQL;

    protected $name = 'lighthouse:ide-helper';

    protected $description = 'Create IDE helper files to improve type checking and autocompletion.';

    public function handle(): int
    {
        $this->laravel->call([$this, 'schemaDirectiveDefinitions']);
        $this->laravel->call([$this, 'programmaticTypes']);
        $this->laravel->call([$this, 'phpIdeHelper']);

        $this->info("\nIt is recommended to add them to your .gitignore file.");

        return 0;
    }

    /**
     * Create and write schema directive definitions to a file.
     */
    protected function schemaDirectiveDefinitions(DirectiveLocator $directiveLocator): void
    {
        $schema = /** @lang GraphQL */ <<<'GRAPHQL'
"""
Placeholder type for various directives such as `@orderBy`.
Will be replaced by a generated type during schema manipulation.
"""
scalar _

GRAPHQL;

        $directiveClasses = $this->scanForDirectives(
            $directiveLocator->namespaces()
        );

        foreach ($directiveClasses as $directiveClass) {
            $definition = $this->define($directiveClass);

            $schema .= /** @lang GraphQL */ <<<GRAPHQL

# Directive class: $directiveClass
$definition

GRAPHQL;
        }

        $filePath = static::schemaDirectivesPath();
        \Safe\file_put_contents($filePath, self::GENERATED_NOTICE.$schema);

        $this->info("Wrote schema directive definitions to $filePath.");
    }

    /**
     * Scan the given namespaces for directive classes.
     *
     * @param  array<string>  $directiveNamespaces
     * @return array<string, class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>>
     */
    protected function scanForDirectives(array $directiveNamespaces): array
    {
        $directives = [];

        foreach ($directiveNamespaces as $directiveNamespace) {
            /** @var array<class-string> $classesInNamespace */
            $classesInNamespace = ClassFinder::getClassesInNamespace($directiveNamespace);

            foreach ($classesInNamespace as $class) {
                $reflection = new \ReflectionClass($class);
                if (! $reflection->isInstantiable()) {
                    continue;
                }

                if (! is_a($class, Directive::class, true)) {
                    continue;
                }
                $name = DirectiveLocator::directiveName($class);

                // The directive was already found, so we do not add it twice
                if (isset($directives[$name])) {
                    continue;
                }

                $directives[$name] = $class;
            }
        }

        return $directives;
    }

    /**
     * @param  class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directiveClass
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    protected function define(string $directiveClass): string
    {
        $definition = $directiveClass::definition();

        // Throws if the definition is invalid
        ASTHelper::extractDirectiveDefinition($definition);

        return trim($definition);
    }

    public static function schemaDirectivesPath(): string
    {
        return base_path().'/schema-directives.graphql';
    }

    /**
     * Users may register types programmatically, e.g. in service providers.
     * In order to allow referencing those in the schema, it is useful to print
     * those types to a helper schema, excluding types the user defined in the schema.
     */
    protected function programmaticTypes(SchemaSourceProvider $schemaSourceProvider, ASTCache $astCache, SchemaBuilder $schemaBuilder): void
    {
        $sourceSchema = Parser::parse($schemaSourceProvider->getSchemaString());
        $sourceTypes = [];
        foreach ($sourceSchema->definitions as $definition) {
            if ($definition instanceof TypeDefinitionNode) {
                $sourceTypes[$definition->name->value] = true;
            }
        }

        $astCache->clear();

        $allTypes = $schemaBuilder->schema()->getTypeMap();

        $programmaticTypes = array_diff_key($allTypes, $sourceTypes);

        $filePath = static::programmaticTypesPath();

        if (count($programmaticTypes) === 0 && file_exists($filePath)) {
            \Safe\unlink($filePath);

            return;
        }

        $schema = implode(
            "\n\n",
            array_map(
                function (Type $type): string {
                    return SchemaPrinter::printType($type);
                },
                $programmaticTypes
            )
        );

        \Safe\file_put_contents($filePath, self::GENERATED_NOTICE.$schema);

        $this->info("Wrote definitions for programmatically registered types to $filePath.");
    }

    public static function programmaticTypesPath(): string
    {
        return base_path().'/programmatic-types.graphql';
    }

    protected function phpIdeHelper(): void
    {
        $filePath = static::phpIdeHelperPath();
        $contents = \Safe\file_get_contents(__DIR__.'/../../_ide_helper.php');

        \Safe\file_put_contents($filePath, $this->withGeneratedNotice($contents));

        $this->info("Wrote PHP definitions to $filePath.");
    }

    public static function phpIdeHelperPath(): string
    {
        return base_path().'/_lighthouse_ide_helper.php';
    }

    protected function withGeneratedNotice(string $phpContents): string
    {
        return substr_replace(
            $phpContents,
            self::OPENING_PHP_TAG.self::GENERATED_NOTICE,
            0,
            strlen(self::OPENING_PHP_TAG)
        );
    }
}
