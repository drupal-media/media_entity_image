## About Media entity

Media entity provides a 'base' entity for a media element. This is a very basic
entity which can reference to all kinds of media-objects (local files, YouTube
videos, tweets, CDN-files, ...). This entity only provides a relation between
Drupal (because it is an entity) and the resource. You can reference to this
entity within any other Drupal entity.

## About Media entity image

This module provides local image integration for Media entity (i.e. media type provider plugin). The user can map fields from image's Exif data to media bundle fields.

### Storing field values

You will have to create fields on the media bundle first, save them and map them to the Exif fields on the bundle edit screen.

You will also have to map the fields for Media entity. At the momemnt there is no GUI for that, so the only method of doing that for now is via CMI.

This whould be an example of that (the field_map section):

```
langcode: en
status: true
dependencies:
  module:
    - media_entity_image
id: photo
label: Photo
description: 'Photo to be used with content.'
type: image
type_configuration:
  source_field: field_image
  exif_field_map:
    field_make: Make
    field_model: Model
field_map:
  field_model: field_model
  field_make: field_make
```

Project page: http://drupal.org/project/media_entity_image (if you can't find it, it will be created shortly).

Maintainers:
 - Janez Urevc (@slashrsm) drupal.org/user/744628
 - Primož Hmeljak (@primsi) drupal.org/user/282629

IRC channel: #drupal-media
