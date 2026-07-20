// Admin settings for Discussion Banners. Registers plain typed settings only
// (switch / text / textarea / select / number), which both majors' auto-built
// settings pages understand; the registry object is the only 1.x/2.x
// difference (app.registry in 2.x, app.extensionData in 1.x).

const EXT_ID = 'linkrobins-discussion-banners';
const PREFIX = EXT_ID + '.';

window.app.initializers.add(EXT_ID, () => {
  const app = window.app;

  let registry = null;
  try {
    if (app.registry && typeof app.registry.for === 'function') {
      registry = app.registry.for(EXT_ID); // Flarum 2.x
    } else if (app.extensionData && typeof app.extensionData.for === 'function') {
      registry = app.extensionData.for(EXT_ID); // Flarum 1.x
    }
  } catch (e) {}
  if (!registry || typeof registry.registerSetting !== 'function') {
    console.warn('[' + EXT_ID + '] no settings registry available');
    return;
  }

  const trans = (key) => app.translator.trans(EXT_ID + '.admin.settings.' + key);
  // Compose "<placement heading>: <field label>" so the three banner groups
  // stay readable on the flat auto-built settings page.
  const label = (placement, key) => [trans(placement + '_heading'), ': ', trans(key)];

  let priority = 100;
  ['top', 'bottom', 'stream'].forEach((placement) => {
    registry.registerSetting(
      {
        setting: PREFIX + placement + '_enabled',
        type: 'boolean',
        label: label(placement, 'enabled_label'),
      },
      priority
    );
    registry.registerSetting(
      {
        setting: PREFIX + placement + '_label',
        type: 'text',
        label: label(placement, 'label_label'),
        help: trans('label_help'),
      },
      priority - 1
    );
    registry.registerSetting(
      {
        setting: PREFIX + placement + '_content',
        type: 'textarea',
        label: label(placement, 'content_label'),
        help: trans('content_help'),
      },
      priority - 2
    );
    registry.registerSetting(
      {
        setting: PREFIX + placement + '_visibility',
        type: 'select',
        label: label(placement, 'visibility_label'),
        options: {
          everyone: trans('visibility_everyone'),
          guests: trans('visibility_guests'),
          members: trans('visibility_members'),
        },
        default: 'everyone',
      },
      priority - 3
    );
    if (placement === 'stream') {
      registry.registerSetting(
        {
          setting: PREFIX + 'stream_every',
          type: 'number',
          min: 2,
          label: label(placement, 'stream_every_label'),
          help: trans('stream_every_help'),
        },
        priority - 4
      );
    }
    priority -= 10;
  });
});
