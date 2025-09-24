<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use SilverStripe\ORM\FieldType\DBComposite;
use Symbiote\MultiValueField\Fields\KeyValueField;

class GroupableDataField
    extends DBComposite
{

    public function scaffoldFormField($title = null, $params = null)
    {
        return KeyValueField::create($this->name, $title)
            ->addExtraClass('groupable-data groupable-data-hidden')
            ->performDisabledTransformation();
    }

}

