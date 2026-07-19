<?php

declare(strict_types=1);

namespace Espo\ORM {
    interface Entity
    {
        public function get(string $name): mixed;
    }
}

namespace {
    use DocumentBuilder\Tests\Support\Assert;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableReferenceValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ProcessedLayout;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\CompiledVariablePublicationValidator;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationValidationContext;
    use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication\PublicationValidationException;
    use Espo\ORM\Entity;

    require dirname(__DIR__) . '/bootstrap.php';
    $module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder';
    require "$module/Layout/ProcessedLayout.php";
    require "$module/DataSource/Variable/VariableReferenceValidator.php";
    foreach (['PublicationBlockerCategory.php', 'PublicationValidationException.php',
        'PublicationValidationContext.php', 'VariablePublicationValidator.php',
        'CompiledVariablePublicationValidator.php'] as $file) {
        require "$module/Publication/$file";
    }

    $template = new class implements Entity {
        public function get(string $name): mixed
        {
            return $name === 'spreadsheetSchema' ? new \stdClass() : null;
        }
    };
    $context = new PublicationValidationContext($template, new ProcessedLayout(
        ['dataSource'=>['type'=>'entity', 'entityType'=>'Contact']],
        '{}',
    ));
    $invalidReferences = new class implements VariableReferenceValidator {
        public function validate(array $layout, mixed $spreadsheetSchema): void
        {
            throw new \InvalidArgumentException('unresolved');
        }
    };

    try {
        (new CompiledVariablePublicationValidator($invalidReferences))->validate($context);
        throw new \RuntimeException('Unresolved publication references were accepted.');
    } catch (PublicationValidationException $exception) {
        Assert::same('variable.unresolved', $exception->blockerCode, 'Wrong publication blocker code.');
        Assert::same('variable', $exception->category->value, 'Wrong publication blocker category.');
    }

    echo "Phase 31 unresolved-variable publication-block tests passed.\n";
}
