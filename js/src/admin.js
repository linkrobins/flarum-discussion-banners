// Admin settings for Discussion Banners.
//
// Every banner lives in one JSON list stored in a single setting, so admins
// can add as many as they like and target each at particular discussions or
// tags. The whole editor is one callback setting: both majors' auto-built
// settings pages invoke function entries with `this` = the extension page,
// whose setting() streams feed the regular Save button.
//
// Like the forum bundle, this imports nothing from flarum/* and feature-
// detects the globals instead, so one artifact runs on Flarum 1.8 and 2.x.
// The only difference between the majors here is the registry object
// (app.registry in 2.x, app.extensionData in 1.x).

const EXT_ID = 'linkrobins-discussion-banners';
const PREFIX = EXT_ID + '.';
const KEY = PREFIX + 'banners';

const PLACEMENTS = ['top', 'bottom', 'stream'];
const SCOPES = ['all', 'only', 'except'];

// A modest, deliberately common set; anything else can be typed or pasted
// into the free-form box (the OS emoji picker works there too).
const EMOJI_PRESETS = ['📢', 'ℹ️', '⚠️', '💡', '🎉', '⭐', '🔥', '❤️', '✅', '📌', '🚀', '🛠️', '🎁', '💬', '👋', '🏆', '🔔', '🤝'];

// Editor state that isn't part of the saved value: which cards are open, and
// each card's discussion search box.
const expanded = {};
const searches = {};

// Tags are fetched once, and only when a banner actually offers tag targeting.
// A forum without flarum/tags simply has no tag section.
const tags = { status: 'idle', list: [] };

const trans = (key, params) => window.app.translator.trans(EXT_ID + '.admin.settings.' + key, params);

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

function apiUrl(path) {
  return window.app.forum.attribute('apiUrl') + path;
}

function newId() {
  return 'b' + Math.random().toString(36).slice(2, 10);
}

function truthy(value) {
  return value === true || value === 1 || value === '1' || value === 'true';
}

function blankRule() {
  return {
    id: newId(),
    enabled: true,
    placement: 'top',
    every: 8,
    label: '',
    content: '',
    visibility: 'everyone',
    color: '',
    icon: { type: '', path: '', url: '', emoji: '' },
    scope: 'all',
    discussions: [],
    tags: [],
  };
}

// Fill in anything a hand-edited value (or a 1.0.x conversion) left out, so
// the editor never has to guard every field.
function complete(rule, index) {
  const icon = rule.icon && typeof rule.icon === 'object' ? rule.icon : {};

  return Object.assign(blankRule(), rule, {
    id: String(rule.id || '').replace(/[^A-Za-z0-9_-]/g, '') || 'b' + index,
    enabled: truthy(rule.enabled),
    placement: PLACEMENTS.indexOf(rule.placement) >= 0 ? rule.placement : 'top',
    every: Number(rule.every) >= 2 ? Number(rule.every) : 8,
    scope: SCOPES.indexOf(rule.scope) >= 0 ? rule.scope : 'all',
    discussions: Array.isArray(rule.discussions) ? rule.discussions : [],
    tags: Array.isArray(rule.tags) ? rule.tags : [],
    icon: { type: icon.type || '', path: icon.path || '', url: icon.url || '', emoji: icon.emoji || '' },
  });
}

// The three single-placement banners of 1.0.x, shown as regular banners on an
// install whose update migration hasn't run yet. Saving converts them for
// good, exactly as the migration would have.
function legacyRules() {
  const settings = (window.app.data && window.app.data.settings) || {};
  const rules = [];

  PLACEMENTS.forEach((placement) => {
    const get = (key) => settings[PREFIX + placement + key] || '';

    if (!truthy(get('_enabled')) && !String(get('_content')).trim()) return;

    rules.push(
      complete(
        {
          id: placement,
          enabled: truthy(get('_enabled')),
          placement,
          every: Number(settings[PREFIX + 'stream_every']),
          label: get('_label'),
          content: get('_content'),
          visibility: get('_visibility') || 'everyone',
          color: get('_color'),
          icon: { type: get('_icon_type'), path: get('_icon_path'), url: get('_icon_url'), emoji: get('_icon_emoji') },
        },
        rules.length
      )
    );
  });

  return rules;
}

function read(page) {
  const raw = page.setting(KEY)() || '';

  if (String(raw).trim() === '') return legacyRules();

  try {
    const decoded = JSON.parse(raw);
    return Array.isArray(decoded) ? decoded.map(complete) : [];
  } catch (e) {
    return [];
  }
}

function write(page, rules) {
  page.setting(KEY)(JSON.stringify(rules));
}

function update(page, id, patch) {
  const rules = read(page);
  const index = rules.findIndex((rule) => rule.id === id);
  if (index < 0) return;

  rules[index] = Object.assign({}, rules[index], patch);
  write(page, rules);
}

// ---------------------------------------------------------------- targeting

function loadTags() {
  if (tags.status !== 'idle') return;
  tags.status = 'loading';

  window.app
    .request({ method: 'GET', url: apiUrl('/tags') })
    .then((doc) => {
      tags.list = ((doc && doc.data) || []).map((tag) => ({ id: Number(tag.id), name: (tag.attributes && tag.attributes.name) || '#' + tag.id }));
      tags.status = 'ready';
      window.m.redraw();
    })
    .catch(() => {
      // No tags extension (or no permission to list them): the tag section
      // simply doesn't appear.
      tags.status = 'unavailable';
      window.m.redraw();
    });
}

function searchState(id) {
  if (!searches[id]) searches[id] = { term: '', results: [], loading: false, timer: null };
  return searches[id];
}

function searchDiscussions(id, term) {
  const state = searchState(id);
  state.term = term;
  if (state.timer) clearTimeout(state.timer);

  if (term.trim().length < 2) {
    state.results = [];
    state.loading = false;
    return;
  }

  state.loading = true;
  state.timer = setTimeout(() => {
    const url = apiUrl('/discussions') + '?filter%5Bq%5D=' + encodeURIComponent(term.trim()) + '&page%5Blimit%5D=6';

    window.app
      .request({ method: 'GET', url })
      .then((doc) => {
        // Ignore a response that lost the race with newer typing.
        if (state.term !== term) return;
        state.results = ((doc && doc.data) || [])
          .filter((entry) => entry.type === 'discussions')
          .map((entry) => ({ id: Number(entry.id), title: (entry.attributes && entry.attributes.title) || '#' + entry.id }));
        state.loading = false;
        window.m.redraw();
      })
      .catch(() => {
        if (state.term !== term) return;
        state.results = [];
        state.loading = false;
        window.m.redraw();
      });
  }, 300);
}

// An id typed or pasted straight in (including a discussion URL), for admins
// who already know which discussion they want.
function pastedId(term) {
  const match = String(term).match(/^\s*#?(\d+)\s*$/) || String(term).match(/\/d\/(\d+)/);
  return match ? Number(match[1]) : null;
}

function addTarget(page, rule, field, entry) {
  const current = rule[field] || [];
  if (current.some((target) => Number(target.id) === Number(entry.id))) return;

  update(page, rule.id, { [field]: current.concat([entry]) });
}

function removeTarget(page, rule, field, id) {
  update(page, rule.id, { [field]: (rule[field] || []).filter((target) => Number(target.id) !== Number(id)) });
}

function chip(text, onRemove) {
  const m = window.m;
  return m('span', { className: 'LinkRobinsBanners-chip' }, [
    m('span', { className: 'LinkRobinsBanners-chipLabel' }, text),
    m('button', { type: 'button', className: 'LinkRobinsBanners-chipRemove', onclick: onRemove, 'aria-label': trans('target_remove') }, '×'),
  ]);
}

function discussionPicker(page, rule) {
  const m = window.m;
  const state = searchState(rule.id);
  const id = pastedId(state.term);

  const pick = (entry) => {
    addTarget(page, rule, 'discussions', entry);
    state.term = '';
    state.results = [];
  };

  // Resolve the title so the chip reads like the others; the id is what
  // matters, so a failed lookup still adds the target.
  const pickById = (discussionId) => {
    window.app
      .request({ method: 'GET', url: apiUrl('/discussions/' + discussionId) })
      .then((doc) => {
        const title = doc && doc.data && doc.data.attributes && doc.data.attributes.title;
        pick({ id: discussionId, title: title || '#' + discussionId });
        window.m.redraw();
      })
      .catch(() => {
        pick({ id: discussionId, title: '#' + discussionId });
        window.m.redraw();
      });
  };

  return m('div', { className: 'LinkRobinsBanners-picker' }, [
    m('div', { className: 'LinkRobinsBanners-chips' }, [
      (rule.discussions || []).map((target) => chip(target.title || '#' + target.id, () => removeTarget(page, rule, 'discussions', target.id))),
      rule.discussions && rule.discussions.length ? null : m('span', { className: 'LinkRobinsBanners-chipsEmpty' }, trans('target_none')),
    ]),
    m('input', {
      className: 'FormControl',
      value: state.term,
      placeholder: trans('target_search_placeholder'),
      oninput: (e) => searchDiscussions(rule.id, e.target.value),
      onkeydown: (e) => {
        if (e.key !== 'Enter') return;
        // The settings page is a form: Enter must pick, not save.
        e.preventDefault();
        if (id) pickById(id);
        else if (state.results.length) pick(state.results[0]);
      },
    }),
    state.loading ? m('div', { className: 'LinkRobinsBanners-pickerHint' }, trans('target_searching')) : null,
    id || state.results.length
      ? m('ul', { className: 'LinkRobinsBanners-results' }, [
          id ? m('li', {}, m('button', { type: 'button', onclick: () => pickById(id) }, trans('target_use_id', { id }))) : null,
          state.results.map((result) => m('li', {}, m('button', { type: 'button', onclick: () => pick(result) }, result.title))),
        ])
      : null,
  ]);
}

function tagPicker(page, rule) {
  const m = window.m;

  loadTags();

  if (tags.status === 'loading') return m('div', { className: 'LinkRobinsBanners-pickerHint' }, trans('target_tags_loading'));
  if (tags.status !== 'ready' || !tags.list.length) return null;

  const selected = (rule.tags || []).map((tag) => Number(tag.id));

  return m('div', { className: 'LinkRobinsBanners-picker' }, [
    m('label', trans('target_tags_label')),
    m(
      'div',
      { className: 'LinkRobinsBanners-tagGrid' },
      tags.list.map((tag) =>
        m(
          'button',
          {
            type: 'button',
            className: 'LinkRobinsBanners-tagChoice' + (selected.indexOf(tag.id) >= 0 ? ' is-selected' : ''),
            onclick: () =>
              selected.indexOf(tag.id) >= 0
                ? removeTarget(page, rule, 'tags', tag.id)
                : addTarget(page, rule, 'tags', { id: tag.id, name: tag.name }),
          },
          tag.name
        )
      )
    ),
  ]);
}

function targeting(page, rule) {
  const m = window.m;

  const empty = rule.scope !== 'all' && !(rule.discussions || []).length && !(rule.tags || []).length;

  return m('div', { className: 'Form-group' }, [
    m('label', trans('scope_label')),
    m('div', { className: 'helpText' }, trans('scope_help')),
    select(
      rule.scope,
      SCOPES.map((scope) => [scope, trans('scope_' + scope)]),
      (value) => update(page, rule.id, { scope: value })
    ),
    rule.scope === 'all'
      ? null
      : m('div', { className: 'LinkRobinsBanners-targets' }, [
          discussionPicker(page, rule),
          tagPicker(page, rule),
          empty
            ? m('div', { className: 'LinkRobinsBanners-warning' }, trans(rule.scope === 'only' ? 'scope_empty_only' : 'scope_empty_except'))
            : null,
        ]),
  ]);
}

// ------------------------------------------------------------------ fields

// Core's styled select markup (wrapper + caret icon), so the control looks
// like every other dropdown on the page.
function select(value, options, onchange) {
  const m = window.m;

  return m('span', { className: 'Select' }, [
    m(
      'select',
      { className: 'Select-input FormControl', value, onchange: (e) => onchange(e.target.value) },
      options.map(([optionValue, optionLabel]) => m('option', { value: optionValue }, optionLabel))
    ),
    m('i', { className: 'icon fas fa-sort Select-caret', 'aria-hidden': 'true' }),
  ]);
}

function field(label, help, control) {
  const m = window.m;
  return m('div', { className: 'Form-group' }, [m('label', label), help ? m('div', { className: 'helpText' }, help) : null, control]);
}

// One accent color per banner, entered with the same control as tag colors.
// Empty = the theme's primary color. The border, background tint, and small
// heading derive from it on the forum side, so one color stays readable on
// light and dark themes (unlike separate border/background/text pickers).
function colorField(page, rule) {
  const m = window.m;
  const ColorPreviewInput = coreComponent('common/components/ColorPreviewInput');
  const attrs = {
    className: 'FormControl',
    placeholder: '#1ec3d6',
    value: rule.color || '',
    oninput: (e) => update(page, rule.id, { color: e.target.value }),
    onchange: (e) => update(page, rule.id, { color: e.target.value }),
  };

  return field(
    trans('color_label'),
    trans('color_help'),
    ColorPreviewInput ? m(ColorPreviewInput, attrs) : m('input', Object.assign({ type: 'text' }, attrs))
  );
}

// Images upload immediately (the server stores the file and hands back its
// path), but nothing is persisted until Save: the path travels in the banner
// like every other field, and uploads no banner ends up referring to are
// cleaned up server-side on the next save.
function iconField(page, rule) {
  const m = window.m;
  const app = window.app;
  const icon = rule.icon || {};
  const setIcon = (patch) => update(page, rule.id, { icon: Object.assign({}, icon, patch) });

  const upload = (file) => {
    if (!file) return;
    const body = new FormData();
    body.append('icon', file);

    app
      // app.request shows Flarum's error alert for API errors on both majors,
      // so an oversized file doesn't look like a dead button.
      .request({
        method: 'POST',
        url: apiUrl('/linkrobins-discussion-banners/' + encodeURIComponent(rule.id) + '/icon'),
        serialize: (raw) => raw,
        body,
      })
      .then((response) => {
        setIcon({ type: 'image', path: (response && response.path) || '', url: (response && response.url) || '' });
        m.redraw();
      })
      .catch(() => m.redraw());
  };

  let detail = null;

  if (icon.type === 'image') {
    const fileInputKey = 'lrBannersFile_' + rule.id;

    detail = m('div', { className: 'LinkRobinsBanners-iconSetting-controls' }, [
      icon.url ? m('img', { className: 'LinkRobinsBanners-iconPreviewImage', src: icon.url, alt: '' }) : null,
      m('input', {
        type: 'file',
        accept: 'image/png,image/jpeg,image/gif,image/webp',
        style: 'display: none',
        oncreate: (vnode) => (page[fileInputKey] = vnode.dom),
        onchange: (e) => {
          upload(e.target.files && e.target.files[0]);
          e.target.value = '';
        },
      }),
      m(
        'button',
        { type: 'button', className: 'Button', onclick: () => page[fileInputKey] && page[fileInputKey].click() },
        trans(icon.url ? 'icon_replace_image' : 'icon_upload_image')
      ),
      icon.url
        ? m(
            'button',
            { type: 'button', className: 'Button Button--danger', onclick: () => setIcon({ type: '', path: '', url: '' }) },
            trans('icon_remove')
          )
        : null,
    ]);
  } else if (icon.type === 'emoji') {
    detail = m('div', [
      m(
        'div',
        { className: 'LinkRobinsBanners-emojiGrid' },
        EMOJI_PRESETS.map((emoji) =>
          m(
            'button',
            {
              type: 'button',
              className: 'LinkRobinsBanners-emojiChoice' + (icon.emoji === emoji ? ' is-selected' : ''),
              onclick: () => setIcon({ emoji }),
            },
            emoji
          )
        )
      ),
      m('div', { className: 'LinkRobinsBanners-iconSetting-controls' }, [
        m('input', {
          className: 'FormControl LinkRobinsBanners-emojiInput',
          value: icon.emoji || '',
          placeholder: trans('icon_emoji_placeholder'),
          oninput: (e) => setIcon({ emoji: e.target.value }),
        }),
        icon.emoji ? m('span', { className: 'LinkRobinsBanners-iconPreviewEmoji' }, icon.emoji) : null,
      ]),
    ]);
  }

  return m('div', { className: 'Form-group LinkRobinsBanners-iconSetting' }, [
    m('label', trans('icon_label')),
    m('div', { className: 'helpText' }, trans('icon_help')),
    select(
      icon.type || '',
      [
        ['', trans('icon_none')],
        ['image', trans('icon_image')],
        ['emoji', trans('icon_emoji')],
      ],
      (value) => setIcon({ type: value })
    ),
    detail,
  ]);
}

// -------------------------------------------------------------------- card

// What the collapsed header says a banner does, so a long list stays readable.
// Built as a list of children rather than a joined string: translations can
// come back as arrays, and concatenating those gives "[object Object]".
function summary(rule) {
  if (rule.scope === 'all') return trans('summary_all');

  const discussions = (rule.discussions || []).length;
  const tagged = (rule.tags || []).length;
  const parts = [];

  if (discussions) parts.push(trans(discussions === 1 ? 'summary_discussion_one' : 'summary_discussion_many', { count: discussions }));
  if (tagged) parts.push(trans(tagged === 1 ? 'summary_tag_one' : 'summary_tag_many', { count: tagged }));

  if (!parts.length) return trans('summary_nothing');

  const targets = parts.length > 1 ? [parts[0], ', ', parts[1]] : parts;

  return [trans(rule.scope === 'only' ? 'summary_only' : 'summary_except'), ' ', targets];
}

function bannerCard(page, rule) {
  const m = window.m;
  const Switch = coreComponent('common/components/Switch');
  const open = !!expanded[rule.id];

  const remove = () => {
    write(
      page,
      read(page).filter((other) => other.id !== rule.id)
    );
    delete expanded[rule.id];
    delete searches[rule.id];
  };

  // Switch/Checkbox take `state`, not `value`, on both majors.
  const toggle = { state: rule.enabled, onchange: (checked) => update(page, rule.id, { enabled: !!checked }) };

  const header = m('div', { className: 'LinkRobinsBanners-cardHeader' }, [
    m('button', { type: 'button', className: 'LinkRobinsBanners-cardToggle', onclick: () => (expanded[rule.id] = !open) }, [
      m('i', { className: 'icon fas ' + (open ? 'fa-caret-down' : 'fa-caret-right'), 'aria-hidden': 'true' }),
      m('span', { className: 'LinkRobinsBanners-cardTitle' }, rule.label || trans(rule.placement + '_heading')),
      m('span', { className: 'LinkRobinsBanners-cardPlacement' }, trans(rule.placement + '_short')),
    ]),
    m('span', { className: 'LinkRobinsBanners-cardSummary' }, summary(rule)),
    m('span', { className: 'LinkRobinsBanners-cardActions' }, [
      Switch
        ? m(Switch, Object.assign({ className: 'LinkRobinsBanners-cardSwitch' }, toggle))
        : m('input', { type: 'checkbox', checked: rule.enabled, onchange: (e) => toggle.onchange(e.target.checked) }),
      // Deliberately not a core Button--link: that class forces its own hover
      // color, and --alert-error-color is white (it is meant for text on a
      // colored alert), which makes a red-on-hover icon disappear.
      m(
        'button',
        { type: 'button', className: 'LinkRobinsBanners-cardDelete', onclick: remove, title: trans('delete'), 'aria-label': trans('delete') },
        m('i', { className: 'icon fas fa-trash', 'aria-hidden': 'true' })
      ),
    ]),
  ]);

  const body = !open
    ? null
    : m('div', { className: 'LinkRobinsBanners-cardBody' }, [
        field(
          trans('placement_label'),
          trans('placement_help'),
          select(
            rule.placement,
            PLACEMENTS.map((placement) => [placement, trans(placement + '_heading')]),
            (value) => update(page, rule.id, { placement: value })
          )
        ),
        rule.placement !== 'stream'
          ? null
          : field(
              trans('stream_every_label'),
              trans('stream_every_help'),
              m('input', {
                className: 'FormControl',
                type: 'number',
                min: 2,
                value: rule.every,
                oninput: (e) => update(page, rule.id, { every: Number(e.target.value) }),
              })
            ),
        field(
          trans('label_label'),
          trans('label_help'),
          m('input', { className: 'FormControl', value: rule.label, oninput: (e) => update(page, rule.id, { label: e.target.value }) })
        ),
        field(
          trans('content_label'),
          trans('content_help'),
          m('textarea', {
            className: 'FormControl',
            rows: 4,
            value: rule.content,
            oninput: (e) => update(page, rule.id, { content: e.target.value }),
          })
        ),
        targeting(page, rule),
        field(
          trans('visibility_label'),
          null,
          select(
            rule.visibility,
            [
              ['everyone', trans('visibility_everyone')],
              ['guests', trans('visibility_guests')],
              ['members', trans('visibility_members')],
            ],
            (value) => update(page, rule.id, { visibility: value })
          )
        ),
        colorField(page, rule),
        iconField(page, rule),
      ]);

  return m('div', { className: 'LinkRobinsBanners-card' + (rule.enabled ? '' : ' is-disabled'), key: rule.id }, [header, body]);
}

// ------------------------------------------------------------------- entry

function editor() {
  const m = window.m;
  const page = this;
  if (!page || typeof page.setting !== 'function') return null;

  const rules = read(page);

  const add = () => {
    const rule = blankRule();
    expanded[rule.id] = true;
    write(page, rules.concat([rule]));
  };

  return m('div', { className: 'Form-group LinkRobinsBanners-editor' }, [
    m('label', trans('banners_label')),
    m('div', { className: 'helpText' }, trans('banners_help')),
    rules.length
      ? m(
          'div',
          { className: 'LinkRobinsBanners-cards' },
          rules.map((rule) => bannerCard(page, rule))
        )
      : m('div', { className: 'LinkRobinsBanners-empty' }, trans('banners_empty')),
    m('button', { type: 'button', className: 'Button LinkRobinsBanners-add', onclick: add }, [
      m('i', { className: 'icon fas fa-plus', 'aria-hidden': 'true' }),
      ' ',
      trans('banners_add'),
    ]),
  ]);
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

  registry.registerSetting(editor, 100);
});
