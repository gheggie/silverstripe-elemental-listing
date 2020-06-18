<?php

namespace Heggsta\ElementalListing\Elements;

use \Page;
use DNADesign\Elemental\Models\BaseElement;
use HoltMedical\Controllers\ElementListingController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\HTMLEditor\HtmlEditorField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\FormField;
use Symbiote\MultiValueField\Fields\KeyValueField;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\SSViewer;

/**
 * An element that can be configured to list content from other sources
 */
class ElementListing extends BaseElement
{
    private static $table_name = 'Heggsta_ElementListing';

    private static $description = 'Listing element class';

    private static $db = array(
        'PerPage'                   => 'Int',
        'SortBy'                    => "Varchar(64)",
        'CustomSort'                => 'Varchar(64)',
        'SortDir'                   => "Enum('Ascending,Descending')",
        'ListType'                  => 'DBClassName(\'' . DataObject::class . '\', [\'index\' => false])',
        'ListingSourceID'           => 'Int',
        'Depth'                     => 'Int',
        'StrictType'                => 'Boolean',
        'AllowDrilldown'            => 'Boolean',
        //'ContentType'               => 'Varchar',
        //'CustomContentType'         => 'Varchar',
        'ComponentFilterName'       => 'Varchar(64)',
        'ComponentFilterColumn'     => 'Varchar(64)',
        'ComponentFilterWhere'      => MultiValueField::class,
        'ListingTemplate'           => 'Text',
        'ComponentListingTemplate'  => 'Text'
    );

    private static $defaults = [
        'ListType'                  => Page::class,
        'PerPage'                   => 10,
        'ListingTemplate'           => "<% loop \$Items %>\n\t<p>\$Title</p>\n<% end_loop %>"
    ];

    /**
     * A mapping between ListType selected and the type of items that should be shown in the "Source"
     * selection tree. If not specified in this mapping, it is assumed to be 'Page'.
     *
     * @var array
     */
    private static $listing_type_source_map = array(
        'Folder'    => Folder::class
    );

    private static $icon = 'font-icon-block-file-list';

    /**
     * @var string
     */
    private static $controller_class = ElementListingController::class;

    private static $template_sample_pagination = <<<PAGING
<% if \$Items.MoreThanOnePage %>
\t<ul>
\t\t<% if \$Items.NotFirstPage %>
\t\t\t<li><a class="prev" href="\$Items.PrevLink">Previous</a></li>
\t\t<% end_if %>
\t\t<% loop \$Items.PaginationSummary %>
\t\t\t<li>
\t\t\t\t<% if \$CurrentBool %>
\t\t\t\t\t<span>\$PageNum</span>
\t\t\t\t<% else %>
\t\t\t\t\t<% if \$Link %><a href="\$Link">\$PageNum</a><% else %><span>...</span><% end_if %>
\t\t\t\t<% end_if %>
\t\t\t</li>
\t\t<% end_loop %>
\t\t<% if \$Items.NotLastPage %>
\t\t\t<li><a class="next" href="\$Items.NextLink">Next</a></li>
\t\t<% end_if %>
\t</ul>
<% end_if %>
PAGING;

    private static $casting = [
        'Listing' => 'HTMLText'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $listType = $this->ListType ? $this->ListType : Page::class;
        $objFields = $this->getSelectableFields($listType);
        $types = ClassInfo::subclassesFor(DataObject::class);
        array_shift($types);
        $source = array_combine($types, $types);
        asort($source);

        $sourceType = $this->effectiveSourceType();
        $parentType = $this->parentType($sourceType);

        $fields->addFieldsToTab(
            'Root.Settings',
            [
                DropdownField::create(
                    'ListType', 
                    _t(__CLASS__.'.LISTTYPE', 'List items of type'), 
                    $source, 
                    'Any'
                ),
                CheckboxField::create(
                    'StrictType', 
                    _t(__CLASS__.'.STRICTTYPE', 'List JUST this type, not descendents')
                ),
                NumericField::create('PerPage', _t(__CLASS__.'.PERPAGE', 'Items Per Page')),
                DropdownField::create(
                    'SortDir', 
                    _t(__CLASS__.'.SORTDIR', 'Sort direction'), 
                    $this->dbObject('SortDir')->enumValues()
                ),
                DropdownField::create('SortBy', _t(__CLASS__.'.SORTBY', 'Sort by'), $objFields)
            ]
        );
        
        if ($sourceType && $parentType) {
            $fields->addFieldsToTab(
                'Root.Settings', 
                [
                    TreeDropdownField::create(
                        'ListingSourceID', 
                        _t(__CLASS__.'.LISTINGSOURCE', 'Source of content for listing'), 
                        $parentType
                    ),
                    DropdownField::create(
                        'Depth', 
                        _t(__CLASS__.'.DEPTH', 'Depth'), 
                        [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]
                    )
                ]
            );
        }

        $fields->addFieldsToTab(
            'Root.Template',
            [
                TextareaField::create(
                    'ListingTemplate', 
                    _t(__CLASS__.'.LISTINGTEMPLATE', 'Listing template')
                )->setRows(15),
                TextareaField::create(
                    'ComponentListingTemplate', 
                    _t(__CLASS__.'COMPONENTLISTINGTEMPLATE', 'Component listing template')
                )->setRows(15)
            ]
        );

        if (self::config()->template_sample_pagination) {
            $fields->insertAfter(
                'ComponentListingTemplate',
                TextareaField::create(
                    'TemplateSamplePagination', 
                    _t(__CLASS__.'.SAMPLETEMPLATEPAGINATION', 'Sample template pagination'), 
                    self::config()->template_sample_pagination
                )->setRows(15)->setReadonly(true)
            );
        }

        $advancedSettingsTab = $fields->findOrMakeTab('Root.AdvancedSettings');
        $advancedSettingsTab->setTitle(_t(__CLASS__.'.ADVANCEDSETTINGS', 'Advanced settings'));

        $fields->addFieldsToTab(
            'Root.AdvancedSettings',
            [
                TextField::create(
                    'CustomSort', 
                    _t(__CLASS__.'.CUSTOMSORT', 'Custom sort GET parameter name')
                )->setDescription('If set, add this as a URL param to sort the list. Will also look for {name}_dir as the sort direction'),
                CheckboxField::create(
                    'AllowDrilldown', 
                    _t(__CLASS__.'.ALLOWDRILLDOWN', 'Allow request action to provide substitute source ID, e.g. /page-url/43')
                )
            ]
        );

        if ($this->ListType) {
            $componentsManyMany = singleton($this->ListType)->config()->many_many;
            if (!is_array($componentsManyMany)) {
                $componentsManyMany = array();
            }
            $componentNames = array();
            foreach ($componentsManyMany as $componentName => $componentVal) {
                $componentClass = '';
                if (is_string($componentVal)) {
                    $componentClass = " ($componentVal)";
                } elseif (is_array($componentVal) && isset($componentVal['through'])) {
                    $componentClass = " ({$componentVal['through']})";
                }
                $componentNames[$componentName] = FormField::name_to_label($componentName) . $componentClass;
            }

            $fields->addFieldToTab(
                'Root.AdvancedSettings',
                DropdownField::create(
                    'ComponentFilterName', 
                    _t(__CLASS__.'.COMPONENTFILTERNAME', 'Filter by relation'), 
                    $componentNames
                )
                ->setEmptyString(_t(__CLASS__.'.SELECTEMPTY', 'Select...'))
                ->setDescription('Will cause this page to list items based on the last URL part. (i.e. ' . $this->AbsoluteLink() . '{$componentFieldName})')
            );
            $fields->addFieldToTab(
                'Root.AdvancedSettings', 
                $componentColumnField = DropdownField::create(
                    'ComponentFilterColumn', 
                    _t(__CLASS__.'FILTERBYRELATIONFIELD', 'Filter by relation field')
                )->setEmptyString(_t(__CLASS__.'.SELECTRELATIONSAVE', '(Must select a relation and Save)'))
            );

            if ($this->ComponentFilterName) {
                $componentClass = isset($componentsManyMany[$this->ComponentFilterName]) 
                    ? $componentsManyMany[$this->ComponentFilterName] 
                    : '';
                if ($componentClass) {
                    $componentFields = array();
                    foreach ($this->getSelectableFields($componentClass) as $columnName => $type) {
                        $componentFields[$columnName] = $columnName;
                    }
                    $componentColumnField->setSource($componentFields);
                    $componentColumnField->setEmptyString(_t(__CLASS__.'.SELECTEMPTY', 'Select...'));

                    $componentListingField->setSource($templates);
                    $componentListingField->setHasEmptyDefault(false);

                    if (class_exists('KeyValueField')) {
                        $fields->addFieldToTab(
                            'Root.AdvancedSettings',
                            KeyValueField::create(
                                'ComponentFilterWhere', 
                                _t(__CLASS__.'.COMPONENTFILTERWHERE', 'Constrain relation by'), 
                                $componentFields
                            )->setDescription("Filter '{$this->ComponentFilterName}' with these properties.")
                        );
                    }
                }
            }
        }

        return $fields;
    }

    public function getType()
    {
        return _t(__CLASS__ . '.BlockType', 'Listing');
    }

    protected function provideBlockSchema()
    {
        $blockSchema = parent::provideBlockSchema();
        $content = '';
        $type = $this->ListType ? Config::inst()->get($this->ListType, 'plural_name') : '';
        if ($type) {
            $content = _t(
                __CLASS__.'.BLOCKSCHEMACONTENT', 
                'Listing of {type}',
                ['type' => $type]
            );
        }
        $blockSchema['content'] = $content;
        return $blockSchema;
    }

    protected function parentType($type)
    {
        $has_one = Config::inst()->get($type, 'has_one');
        return isset($has_one['Parent']) ? $has_one['Parent'] : null;
    }

    protected function getSelectableFields($listType)
    {
        $objFields = static::getSchema()->fieldSpecs($listType);
        $objFields = array_keys($objFields);
        $objFields = array_combine($objFields, $objFields);

        ksort($objFields);
        return $objFields;
    }

    /**
     * Some subclasses will want to override this.
     *
     * @return DataObject
     */
    protected function getListingSource()
    {
        $sourceType = $this->effectiveSourceType();
        if (!($sourceType && $this->ListingSourceID)) {
            return;
        }

        $source = DataList::create($sourceType)->byID($this->ListingSourceID);
        $newParent = null;
        $newParentId = 0;
        if ($this->AllowDrilldown && Controller::has_curr()) {
            $newParentId = (int)Controller::curr()->getRequest()->param('Action');
        }

        if ($source && $newParentId) {
            /* @var $source DataObject */
            $newParent = $sourceType::get()->byId($newParentId);
            if ($newParent) {
                // Figure out whether it's within the source already configured there by looking up through the
                // tree until we find the listing source ID parent, at which point we can
                // safely swap to it
                //
                // - nyeholt 2017-12-18
                $parentCheck = $newParent;
                while ($parentCheck) {
                    if ($parentCheck->ID == $source->ID) {
                        $source = $newParent;
                        break;
                    }
                    $parentCheck = $parentCheck->Parent();
                }
            }
        }

        if ($source && $source->canView()) {
            return $source;
        }
    }

    /**
     * Sometimes the type of a listing source will be different from that of the item being listed (eg
     * a news article might be beneath a news holder instead of another news article) so we need to
     * figure out what that is based on the settings for this page.
     *
     * @return string
     */
    protected function effectiveSourceType()
    {
        $listType = $this->ListType ? $this->ListType : Page::class;
        $listType = isset($this->config()->listing_type_source_map[$listType]) 
            ? $this->config()->listing_type_source_map[$listType] 
            : ClassInfo::baseDataClass($listType);
        return $listType;
    }

    /**
     * Retrieves all the component/relation listing items
     *
     * @return ArrayList
     */
    public function ComponentListingItems()
    {
        $manyMany = singleton($this->ListType)->config()->many_many;
        $tagClass = isset($manyMany[$this->ComponentFilterName]) ? $manyMany[$this->ComponentFilterName] : '';
        if (!$tagClass) {
            return ArrayList::create();
        }
        $result = DataList::create($tagClass);
        if ($this->ComponentFilterWhere
            && ($componentWhereFilters = $this->ComponentFilterWhere->getValue())
        ) {
            $result = $result->filter($componentWhereFilters);
        }
        return $result;
    }

    /**
     * Retrieves all the listing items within this source
     *
     * @return SS_List
     */
    public function ListingItems()
    {
        // need to get the items being listed
        $source = $this->getListingSource();
        $listType = $this->ListType ? $this->ListType : 'Page';
        $filter = [];
        $objFields = $this->getSelectableFields($listType);

        if ($source) {
            $ids = $this->getIdsFrom($source, 1);
            $ids[] = $source->ID;

            if (isset($objFields['ParentID']) && count($ids)) {
                $filter['ParentID'] = $ids;
            }
        }

        if ($this->StrictType) {
            $filter['ClassName'] = $listType;
        }

        $sortDir = $this->SortDir == 'Ascending' ? 'ASC' : 'DESC';
        $sort = $this->SortBy && isset($objFields[$this->SortBy]) ? $this->SortBy : 'Title';
        $request = Controller::has_curr() ? Controller::curr()->getRequest() : null;

        if (strlen($this->CustomSort) && $request) {
            $sortField = $request->getVar($this->CustomSort);
            if ($sortField) {
                $sort = isset($objFields[$sortField]) ? $sortField : $sort;
                $sortDir = $req->getVar($this->CustomSort . '_dir');
                $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';
            }
        }

        // Bind these variables into the current page object because the
        // template may want to read them out after.
        //
        // - nyeholt 2017-12-19
        $this->CurrentSort = $sort;
        $this->CurrentDir = $sortDir;
        $this->CurrentSource = $source;
        $this->CurrentLink = $request ? $request->getURL() : $this->Link();

        // $sort = $this->CustomSort ? $this->CustomSort : $sort;
        $sort .= ' ' . $sortDir;
        $limit = '';
        $pageUrlVar = 'page' . $this->ID;
        $items = DataList::create($listType)->filter($filter)->sort($sort);

        if ($this->PerPage) {
            $page = isset($_REQUEST[$pageUrlVar]) ? (int) $_REQUEST[$pageUrlVar] : 0;
            $items  = $items->limit($this->PerPage, $page);
        }
        if ($this->ComponentFilterName) {
            $controller = Controller::has_curr() ? Controller::curr() : null;
            $tags = [];
            if ($controller) {
                $tagName = urldecode($controller->getRequest()->latestParam('Action'));
                if ($tagName) {
                    $tags = $this->ComponentListingItems();
                    $tags = $tags->filter([
                        $this->ComponentFilterColumn => $tagName
                    ]);
                    $tags = $tags->column();
                    if (!$tags) {
                        // Workaround cms/#1045
                        // - Stop infinite redirect
                        // @see: https://github.com/silverstripe/silverstripe-cms/issues/1045
                        unset($controller->extension_instances['OldPageRedirector']);
                        return $controller->httpError(404);
                    }
                }
            }

            if ($tags) {
                $items = $items->filter([
                    $this->ComponentFilterName . '.ID' => $tags
                ]);
            } else {
                $tags = ArrayList::create();
            }
        }

        $this->extend('updateListingItems', $items);

        $list = ArrayList::create();
        if ($items) {
            $list = PaginatedList::create($items);
            // ensure the 0 limit is applied if configured as such
            $list->setPageLength($this->PerPage);
            $list->setPaginationGetVar($pageUrlVar);
            if ($items instanceof DataList) {
                $list->setPaginationFromQuery($items->dataQuery()->query());
            }
        }

        return $list;
    }

    /**
     * Recursively find all the child items that need to be listed
     *
     * @param DataObject $parent
     * @param int        $depth
     */
    protected function getIdsFrom($parent, $depth)
    {
        if ($depth >= $this->Depth) {
            return;
        }
        $ids = [];
        foreach ($parent->Children() as $kid) {
            $ids[] = $kid->ID;
            $childIds = $this->getIdsFrom($kid, $depth + 1);
            if ($childIds) {
                $ids = array_merge($ids, $childIds);
            }
        }
        return $ids;
    }

    /**
     * @return string
     */
    public function getListing($action = false)
    {
        if ($action === false) {
            $action = $this->getController()->getActionParam();
        }
        if ($this->ComponentFilterName && !$action) {
            // For a list of relations like tags/categories/etc
            $items = $this->ComponentListingItems();
            $view = SSViewer::fromString($this->ComponentListingTemplate);
        } else {
            $items = $this->ListingItems();
            $view = SSViewer::fromString($this->ListingTemplate);
        }
        $data = $this->customise(['Items' => $items]);
        return $view->process($data);
    }
}
