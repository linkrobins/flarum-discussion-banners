// Admin settings for Discussion Banners. Registers plain typed settings only
// (switch / text / textarea / select / number), which both majors' auto-built
// settings pages understand; the registry object is the only 1.x/2.x
// difference (app.registry in 2.x, app.extensionData in 1.x).

const EXT_ID = 'linkrobins-discussion-banners';
const PREFIX = EXT_ID + '.';

// A modest, deliberately common set; anything else can be typed or pasted
// into the free-form box (the OS emoji picker works there too).
const EMOJI_PRESETS = ['📢', 'ℹ️', '⚠️', '💡', '🎉', '⭐', '🔥', '❤️', '✅', '📌', '🚀', '🛠️', '🎁', '💬', '👋', '🏆', '🔔', '🤝'];

// Track freshly-uploaded icon URLs per placement so the preview updates
// without waiting for a settings reload.
const uploadedIconUrl = {};

// Resolve a core component on either major (2.x registry / 1.x compat map).
function coreComponent(path) {
  const unwrap = (mod) => (mod && mod.default ? mod.default : mod);
  try {
    const reg = window.flarum && window.flarum.reg;
    if (reg && typeof reg.get === 'function') {
      const mod = reg.get('core', path);
      if (mod) return unwrap(mod);
    }
  } catch (e) {}
  try {
    const compat = window.flarum && window.flarum.core && window.flarum.core.compat;
    if (compat && compat[path]) return unwrap(compat[path]);
  } catch (e) {}
  return null;
}

// One accent color per banner, entered with the same control as tag colors.
// Empty = the theme's primary color. The border, background tint, and small
// heading derive from it on the forum side, so one color stays readable on
// light and dark themes (unlike separate border/background/text pickers).
function registerColorSetting(registry, placement, label, trans, priority) {
  registry.registerSetting(function () {
    const m = window.m;
    const page = this;
    if (!page || typeof page.setting !== 'function') return null;

    const stream = page.setting(PREFIX + placement + '_color');
    const ColorPreviewInput = coreComponent('common/components/ColorPreviewInput');
    const inputAttrs = {
      className: 'FormControl',
      placeholder: '#1ec3d6',
      value: stream() || '',
      oninput: (e) => stream(e.target.value),
      onchange: (e) => stream(e.target.value),
    };

    return m('div', { className: 'Form-group' }, [
      m('label', label(placement, 'color_label')),
      m('div', { className: 'helpText' }, trans('color_help')),
      ColorPreviewInput ? m(ColorPreviewInput, inputAttrs) : m('input', Object.assign({ type: 'text' }, inputAttrs)),
    ]);
  }, priority);
}

// The icon picker is a callback setting (both majors' settings pages invoke
// function entries with `this` = the extension page, whose setting() streams
// feed the regular Save button). Image uploads hit our own endpoint and are
// stored server-side immediately; emoji and "none" persist via Save.
function registerIconSetting(registry, placement, label, trans, priority) {
  registry.registerSetting(function () {
    const app = window.app;
    const m = window.m;
    const page = this;
    if (!page || typeof page.setting !== 'function') return null;

    const typeStream = page.setting(PREFIX + placement + '_icon_type');
    const emojiStream = page.setting(PREFIX + placement + '_icon_emoji');
    const type = typeStream() || '';
    const savedUrl = page.setting(PREFIX + placement + '_icon_url')() || '';
    const currentUrl = uploadedIconUrl[placement] || savedUrl;
    const apiBase = app.forum.attribute('apiUrl') + '/linkrobins-discussion-banners/' + encodeURIComponent(placement) + '/icon';

    const uploadIcon = (file) => {
      if (!file) return;
      const body = new FormData();
      body.append('icon', file);
      app
        .request({ method: 'POST', url: apiBase, serialize: (raw) => raw, body })
        .then((resp) => {
          uploadedIconUrl[placement] = resp && resp.url;
          typeStream('image');
          m.redraw();
        })
        .catch(() => {});
    };

    const removeIcon = () => {
      app
        .request({ method: 'DELETE', url: apiBase })
        .then(() => {
          delete uploadedIconUrl[placement];
          typeStream('');
          m.redraw();
        })
        .catch(() => {});
    };

    let detail = null;
    if (type === 'image') {
      detail = m('div', { className: 'LinkRobinsBanners-iconSetting-controls' }, [
        currentUrl ? m('img', { className: 'LinkRobinsBanners-iconPreviewImage', src: currentUrl, alt: '' }) : null,
        m('input', {
          type: 'file',
          accept: 'image/png,image/jpeg,image/gif,image/webp',
          style: 'display: none',
          oncreate: (vnode) => (page['lrBannersFile_' + placement] = vnode.dom),
          onchange: (e) => {
            uploadIcon(e.target.files && e.target.files[0]);
            e.target.value = '';
          },
        }),
        m(
          'button',
          {
            type: 'button',
            className: 'Button',
            onclick: () => page['lrBannersFile_' + placement] && page['lrBannersFile_' + placement].click(),
          },
          trans(currentUrl ? 'icon_replace_image' : 'icon_upload_image')
        ),
        currentUrl ? m('button', { type: 'button', className: 'Button Button--danger', onclick: removeIcon }, trans('icon_remove')) : null,
      ]);
    } else if (type === 'emoji') {
      const chosen = emojiStream() || '';
      detail = m('div', [
        m('div', { className: 'LinkRobinsBanners-emojiGrid' }, [
          EMOJI_PRESETS.map((emoji) =>
            m(
              'button',
              {
                type: 'button',
                className: 'LinkRobinsBanners-emojiChoice' + (chosen === emoji ? ' is-selected' : ''),
                onclick: () => emojiStream(emoji),
              },
              emoji
            )
          ),
        ]),
        m('div', { className: 'LinkRobinsBanners-iconSetting-controls' }, [
          m('input', {
            className: 'FormControl LinkRobinsBanners-emojiInput',
            value: chosen,
            placeholder: trans('icon_emoji_placeholder'),
            oninput: (e) => emojiStream(e.target.value),
          }),
          chosen ? m('span', { className: 'LinkRobinsBanners-iconPreviewEmoji' }, chosen) : null,
        ]),
      ]);
    }

    return m('div', { className: 'Form-group LinkRobinsBanners-iconSetting' }, [
      m('label', label(placement, 'icon_label')),
      m('div', { className: 'helpText' }, trans('icon_help')),
      // Core's styled select markup (wrapper + caret icon), so the control
      // looks like every other dropdown on the page.
      m('span', { className: 'Select', style: 'max-width: 240px' }, [
        m(
          'select',
          {
            className: 'Select-input FormControl',
            value: type,
            onchange: (e) => typeStream(e.target.value),
          },
          [
            m('option', { value: '' }, trans('icon_none')),
            m('option', { value: 'image' }, trans('icon_image')),
            m('option', { value: 'emoji' }, trans('icon_emoji')),
          ]
        ),
        m('i', { className: 'icon fas fa-sort Select-caret', 'aria-hidden': 'true' }),
      ]),
      detail,
    ]);
  }, priority);
}

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
    registerColorSetting(registry, placement, label, trans, priority - 4);
    registerIconSetting(registry, placement, label, trans, priority - 5);
    if (placement === 'stream') {
      registry.registerSetting(
        {
          setting: PREFIX + 'stream_every',
          type: 'number',
          min: 2,
          label: label(placement, 'stream_every_label'),
          help: trans('stream_every_help'),
        },
        priority - 6
      );
    }
    priority -= 10;
  });
});
