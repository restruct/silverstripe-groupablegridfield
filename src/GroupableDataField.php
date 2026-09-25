<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use SilverStripe\Forms\FormField;
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

    // public function scaffoldFormField($title = null, $params = null)  // old: no return type — fatals on SS6, where multivaluefield 7 declares `(?string $title = null, array $params = []): FormField`
    # Signature valid against BOTH parents: parameters stay untyped (a child may widen, so this
    # satisfies SS6's typed `?string`/`array`, and SS5's untyped parent forbids adding types), and the
    # FormField return type is allowed on SS5 too (a child may add a return type the parent lacks).
    public function scaffoldFormField($title = null, $params = []): FormField
    {
        return KeyValueField::create($this->name, $title)
            ->addExtraClass('groupable-data groupable-data-hidden')
            ->performDisabledTransformation();
    }

}
