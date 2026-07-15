<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Symbiote\MultiValueField\Fields\KeyValueField;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;

/**
 * DB field type for the groups store in legacy/multivalue mode: a MultiValueField whose scaffolded
 * form field is a hidden, disabled KeyValueField (the actual editing UI lives in the GridField's
 * enhanced divider rows, persisted via GridFieldGroupable::handleSave).
 *
 * Extends symbiote's MultiValueField so getValue()/getValues() behave consistently (unserialized
 * array, or null when empty) and the composite 'Value' column exists.
 */
class GroupableDataField
    // extends DBComposite  // old: DBComposite base had no composite_db and no getValue() — unusable as an actual DB field (no columns scaffolded, getValue() could yield the field instance itself instead of array|null)
    extends MultiValueField
{

    public function scaffoldFormField($title = null, $params = null)
    {
        return KeyValueField::create($this->name, $title)
            ->addExtraClass('groupable-data groupable-data-hidden')
            ->performDisabledTransformation();
    }

}
