# SilverStripe Elemental Listing

The module provides a [Silverstripe Elemental](https://www.github.com/dnadesign/silverstripe-elemental.git) element that allows CMS users to configure listings of arbitrary content. The core implementation is based on the [ListingPage ](https://www.github.com/nyeholt/silverstripe-listingpage.git) module by Marcus Nyeholt.

## Requirements

- SilverStripe CMS 4.3+
- Elemental
- MultiValueField

## Installation

```
composer require heggsta/silverstripe-elemental-listing
```

## Configuration options

### Overview

```yml
Heggsta\ElementalListing\Elements\ElementListing
  sample_template_pagination: '<%-- .ss template pagination sample code here --%>'
  cms_templates_disabled: true
  file_template_sources:
    - 'themes/mytheme'
```

### Descriptions

**sample_template_pagination**

String to display in a TextareaField for example pagination - this simply provides some helper template code for CMS users to add pagination to a listing template. This field won't display if the value is false or empty string.

Default:

```
<% if $Items.MoreThanOnePage %>
    <ul>
        <% if $Items.NotFirstPage %>
            <li><a class="prev" href="\$Items.PrevLink">Previous</a></li>
        <% end_if %>
        <% loop $Items.PaginationSummary %>
            <li>
                <% if $CurrentBool %>
                    <span>$PageNum</span>
                <% else %>
                    <% if $Link %><a href="$Link">$PageNum</a><% else %><span>...</span><% end_if %>
                <% end_if %>
            </li>
        <% end_loop %>
        <% if $Items.NotLastPage %>
            <li><a class="next" href="$Items.NextLink">Next</a></li>
        <% end_if %>
    </ul>
<% end_if %>
```

**cms_templates_disabled**

Set to true to disable fields for editing the listing template in the CMS.

Default: `false`

**file_template_sources**

An array of locations relative to the project root directory to be scanned for listing templates. If any templates exist, CMS users can select one to be used for rendering the listing.

Within a source directory, templates must be placed in a `templates/Heggsta/ElementalListing/ListingTemplates/` directory, e.g. `templates/Heggsta/ElementalListing/ListingTemplates/MyTemplate.ss`

Default: `[]` (empty array)

## Additional credits

Marcus Nyeholt (https://github.com/nyeholt/)
