<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Override;
use SilverStripe\Forms\FormField;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;
use Symbiote\MultiValueField\Fields\KeyValueField;

class GroupableDataField extends MultiValueField
{
    #[Override]
    public function scaffoldFormField(?string $title = null, array $params = []): FormField
    {
        return KeyValueField::create($this->name, $title)
            ->addExtraClass('groupable-data groupable-data-hidden')
            ->performDisabledTransformation();
    }

    /**
     * Get the key-value array of groups
     */
    #[Override]
    public function getValues(): array
    {
        $value = $this->getValue();

        // Ensure we return an array
        if (!is_array($value)) {
            return [];
        }

        return $value;
    }
}

