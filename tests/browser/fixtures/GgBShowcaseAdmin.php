<?php

namespace Restruct\GgBrowser;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * BROWSER-TEST FIXTURE ONLY - demo content for the README screenshots (tests/browser/screenshots/):
 * GET /admin/gg-showcase/seed?mode=do|mv answers {"id": <source record ID>}, a freshly seeded source
 * record with readable groups and items instead of the specs' A1/B1 rows. The specs never call it.
 *
 * Reuses the specs' fixture classes (GgBDOSource, GgBMVSource), so it adds no tables, and stays out
 * of the CMS menu. Access is LeftAndMain's own check, as for GgBResetAdmin. See GgBSection for why
 * this never loads in a real install.
 */
class GgBShowcaseAdmin extends LeftAndMain
{
    private static $url_segment = 'gg-showcase';

    private static $menu_title = 'Groupable showcase';

    # Keep it out of the CMS menu: it is an endpoint, not a screen, and the screenshots show the menu.
    private static $ignore_menuitem = true;

    private static $allowed_actions = ['seed'];

    /** DataObject mode: [section name, code, [item titles]]; a null name holds the unassigned items. */
    private const DO_CONTENT = [
        ['Ordering', 'ORD', ['How do I place an order?', 'Can I change an order after paying?', 'Which payment methods can I use?']],
        ['Delivery', 'DEL', ['How long does delivery take?', 'Do you deliver abroad?']],
        ['Returns', 'RET', ['How do I return an item?']],
        [null, null, ['Is gift wrapping available?']],
    ];

    /** MultiValue mode: group key => [group name, [item titles]]; key '' holds the unassigned items. */
    private const MV_CONTENT = [
        'workshops' => ['Day 1: Workshops', ['Getting started with the CMS', 'Building page types']],
        'talks' => ['Day 2: Talks', ['Content modelling in practice', 'Accessibility for editors', 'Closing keynote']],
        '' => [null, ['Lightning talks (to be scheduled)']],
    ];

    public function seed(HTTPRequest $request): HTTPResponse
    {
        $source = $request->getVar('mode') === 'mv' ? $this->seedMultiValue() : $this->seedDataObject();

        return HTTPResponse::create(json_encode(['id' => $source->ID]))
            ->addHeader('Content-Type', 'application/json');
    }

    private function seedDataObject(): GgBDOSource
    {
        # GgBDOSource::reseed() removes an earlier record of the same title and its rows; its own
        # sections and items are then replaced by the demo content below.
        $source = GgBDOSource::reseed('Help centre FAQ');
        foreach ($source->Items() as $item) {
            $item->delete();
        }
        foreach ($source->Sections() as $section) {
            $section->delete();
        }

        $sort = 1;
        foreach (self::DO_CONTENT as $i => [$name, $code, $titles]) {
            $sectionID = 0;
            if ($name !== null) {
                $section = GgBSection::create(['Name' => $name, 'Code' => $code, 'Sort' => $i + 1, 'SourceID' => $source->ID]);
                $section->write();
                $sectionID = $section->ID;
            }
            foreach ($titles as $title) {
                GgBDOItem::create(['Title' => $title, 'SortOrder' => $sort++, 'SourceID' => $source->ID, 'SectionID' => $sectionID])->write();
            }
        }

        return $source;
    }

    private function seedMultiValue(): GgBMVSource
    {
        # Same approach: reseed() clears an earlier record, then its items are replaced.
        $source = GgBMVSource::reseed('Event programme');
        foreach ($source->Items() as $item) {
            $item->delete();
        }

        $groups = [];
        foreach (self::MV_CONTENT as $key => [$name]) {
            if ($key !== '') {
                $groups[$key] = $name;
            }
        }
        $source->Sections = $groups;
        $source->write();

        $sort = 1;
        foreach (self::MV_CONTENT as $key => [, $titles]) {
            foreach ($titles as $title) {
                $item = GgBMVItem::create(['Title' => $title]);
                $item->write();
                $source->Items()->add($item, ['Section' => $key, 'SortOrder' => $sort++]);
            }
        }

        return $source;
    }
}
