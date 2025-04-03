# Paragraphs Inline Entity Form Extras

## Adopting embedded paragraphs with two generations

This module prevents embedded paragraphs from being orphaned when they are embedded in a WYSYWYG field, and helps the UI to allow for paragraph behaviors.   The drawbacks for some will be: we add a dedicated and an "extra" edit form field to node types to hold them, and to help with behaviors we wrap each embedded paragraph with a no-op paragraph.

## Managing embedded paragraphs

This module reclaims embedded paragraphs in text_long and text_w_summary fields in node fields and entity reference revisions fields for a node. This prevents embedded paragraphs from being 'orphaned' and exposed to deletion from other processes.

The fields are filtered using entity_embed logic, grabs paragraphs and appends additions to a hidden field on the node. The filtering for this process happens on node-save, if the filter is activated in the ckeditor5 text format config, it will have no impact on the WYSIWYG.  It is possible for a person to instantiate a paragraph and then delete it before saving, and so the filter itself does not alter the node. On node save, the filter is run by a Collector service and any paragraphs not claimed will be adopted.

This depends on a configurable (not base) field being added to each node type that opts-in through a third-party setting, as well as a text profile that includes the button from paragraphs_inline_entity_form.

We also add a extra info field to the node edit form and hide the paragraph field that we assign to the parent properties of the paragraphs.  We present a simple list instead of the paragraph field to prevent the main form state from clobbering edits make to the embedded paragraphs after their initial save.  As it works now, the embeds are saved as they are added or edited, and if a paragraph is edited that was previously saved, that former state would be sitting in the field, and saved over the revisions made while editing in the WYSIWYG.  Without major AJAX wizardry, the real paragraphs field and the WYSIWYG fields won't be in sync and can't coexist in the same form.   The field is configurable, so if admins need to reach the field they via the form managament UI.

There are manual configuration efforts!

- Limit the paragraphs available to the embed in the paragraphs_inline_entity_form admin
- Activate a third party setting in the tabs on the node type form for each node type that should support embeds.

## Paragraphs Behaviors Tabs

The js that looks for the 'Content' and 'Behavior' tabs depends on having the tabs rendered in the right part of the DOM relative to the paragraph form.  The javascript that governs those tabs looks for the top-most set of tabs in the field widget that is presenting the paragraph for editing, accounting for nesting etc.  This presents a challenge because the add and edit actions do not use a field to present the paragraph but rather the paragraph entity is the main element.

To account for the desired field widget structure, this module creates a container with a paragraphs field that holds the target paragraph. We use hook_theme and twig templates to add markup and attributes in order to be a match for the paragraphs.admin.js tab logic.

The carrier paragraph comes with twig that acts as a passthrough. it will only render the target paragraph content with no outer elements.

## Reflecting Node-type Optin in the CKeditor[5] Toolbar

We have an issue when the module is enabled, and the embed button is configured to a text editor profile, but that text editor profile is used on node types that have not opted in to the embedding feature.  The best we can do right now is to attach css to the form that removes the embed button from the toolbar for that form load, on ckeditor and ckeditor5.

## Dependency

drupal/paragraphs_inline_entity_form

## TODO

- A better solution to the situation where the embed button is enabled for a field but the node type has not opted in.
