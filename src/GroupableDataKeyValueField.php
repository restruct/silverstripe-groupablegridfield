<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use Override;
use SilverStripe\Core\Convert;
use Symbiote\MultiValueField\Fields\KeyValueField;

class GroupableDataKeyValueField extends KeyValueField
{
    #[Override]
    public function Field($properties = []) {
        $field_html = parent::Field($properties);
        $extra_attributes_html = $this->getExtraAttributesHTML();

        return str_replace('class="multivaluefieldlist', $extra_attributes_html . ' class="multivaluefieldlist', $field_html);
    }

    // alternative to make setAttribute work for MultiValue/KeyValueFields
    public function getExtraAttributesHTML() {
        $non_extra_attributes = [
            'type',
            'name',
            'value',
            'class',
            'id',
            'disabled',
            'readonly',
        ];
        $extra_attributes = [];
        foreach(parent::getAttributes() as $key => $val){
            if (!in_array($key, $non_extra_attributes)) {
                $extra_attributes[$key] = $val;
            }
        }

        foreach($extra_attributes as $name => $value) {
            if($value === true) {
                $parts[] = sprintf('%s="%s"', $name, $name);
            } else {
                $parts[] = sprintf('%s="%s"', $name, Convert::raw2att($value));
            }
        }

        return implode(' ', $parts);
    }

}
