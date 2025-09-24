<?php

namespace Restruct\Silverstripe\GroupableGridfield;

use SilverStripe\Core\Convert;
use Symbiote\MultiValueField\Fields\KeyValueField;

class GroupableDataKeyValueField
    extends KeyValueField
{
    public function Field($properties = array()) {
        $field_html = parent::Field($properties);
        $extra_attributes_html = $this->getExtraAttributesHTML();
        $field_html = str_replace('class="multivaluefieldlist', $extra_attributes_html . ' class="multivaluefieldlist', $field_html);

        return $field_html;
    }

    // alternative to make setAttribute work for MultiValue/KeyValueFields
    public function getExtraAttributesHTML() {
        $non_extra_attributes = array(
            'type',
            'name',
            'value',
            'class',
            'id',
            'disabled',
            'readonly',
        );
        $extra_attributes = array();
        foreach(parent::getAttributes() as $key => $val){
            if(!in_array($key, $non_extra_attributes)) $extra_attributes[$key] = $val;
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