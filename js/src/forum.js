import DOMPurify from 'dompurify';

// Discussion Banners: admin-configured info banners spliced into the post
// stream, in up to three placements: above the first post, below the last
// post, and after every Nth post. Any number of banners can be configured, and
// each one can be targeted at particular discussions or tags.
//
// Targeting is resolved server-side: the discussion payload carries only the
// banners this viewer should see in this discussion, so there is nothing to
// filter here (and nothing to leak from other discussions).
//
// This bundle intentionally imports NOTHING from flarum/* and uses the global
// `app`/`m`/`flarum` objects instead, so the same artifact runs on both
// Flarum 1.8 and 2.x. Which major we're on only matters for how components
// are looked up (2.x lazy-chunk registry vs the 1.x compat map); everything
// else is version-neutral.

const EXT_ID = 'linkrobins-discussion-banners';
const ATTR = 'linkrobinsDiscussionBanners';

// Read fresh on every render so a settings change lands after a reload without
// stale module state.
function bannersFor(discussion) {
  try {
    if (discussion && typeof discussion.attribute === 'function') {
      return discussion.attribute(ATTR) || [];
    }
  } catch (e) {}
  return [];
}

// Admin-supplied HTML runs through DOMPurify before m.trust; cache per input
// string so we don't re-sanitize on every redraw.
const sanitized = new Map();
function sanitize(html) {
  if (!sanitized.has(html)) {
    sanitized.set(html, DOMPurify.sanitize(html));
    if (sanitized.size > 50) {
      // A runaway map means the content keeps changing, so just reset it.
      const keep = sanitized.get(html);
      sanitized.clear();
      sanitized.set(html, keep);
    }
  }
  return sanitized.get(html);
}

// The optional icon beside the content: an admin-uploaded image (served from
// the assets disk) or an emoji rendered as plain text (never trusted HTML).
function bannerIcon(banner) {
  const m = window.m;
  const icon = banner.icon;
  if (!icon) return null;
  if (icon.type === 'image' && icon.url) {
    return m('img', { className: 'LinkRobinsBanners-iconImage', src: icon.url, alt: '', loading: 'lazy' });
  }
  if (icon.type === 'emoji' && icon.emoji) {
    return m('span', { className: 'LinkRobinsBanners-iconEmoji', 'aria-hidden': 'true' }, icon.emoji);
  }
  return null;
}

function bannerCard(banner, variant) {
  const m = window.m;
  const icon = bannerIcon(banner);
  const content = m('div', { className: 'LinkRobinsBanners-content' }, m.trust(sanitize(banner.contentHtml || '')));

  // Optional admin accent color, applied as a CSS custom property the card's
  // tinted variant derives its border/background/label colors from. The
  // server only serializes strict hex, and the regex here is a second guard
  // since this lands in an inline style.
  const attrs = { className: 'LinkRobinsBanners-card LinkRobinsBanners-card--' + variant };
  if (banner.color && /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(banner.color)) {
    attrs.className += ' LinkRobinsBanners-card--tinted';
    attrs.style = '--lrdb-accent: ' + banner.color + ';';
  }

  return m('div', attrs, [
    banner.label ? m('div', { className: 'LinkRobinsBanners-label' }, banner.label) : null,
    icon ? m('div', { className: 'LinkRobinsBanners-row' }, [m('div', { className: 'LinkRobinsBanners-icon' }, icon), content]) : content,
  ]);
}

// A spliced stream row. PostStream renders a keyed list, so every row we
// insert must carry a key of its own.
function bannerItem(key, banner, variant) {
  const m = window.m;
  return m(
    'div',
    {
      className: 'PostStream-item LinkRobinsBanners-item LinkRobinsBanners-item--' + variant,
      key: 'lrBanners-' + key,
    },
    bannerCard(banner, variant)
  );
}

// Resolve a core component on either major: 2.x exposes lazy-chunk modules
// through flarum.reg.onLoad (fires when the chunk loads, or immediately if
// already in); 1.x ships everything eagerly in flarum.core.compat.
function onCoreModule(path, callback) {
  const unwrap = (mod) => (mod && mod.default ? mod.default : mod);
  try {
    const reg = window.flarum && window.flarum.reg;
    if (reg && typeof reg.onLoad === 'function') {
      reg.onLoad('core', path, (mod) => callback(unwrap(mod)));
      return;
    }
  } catch (e) {}
  try {
    const compat = window.flarum && window.flarum.core && window.flarum.core.compat;
    if (compat && compat[path]) callback(unwrap(compat[path]));
  } catch (e) {}
}

// Minimal `extend()` so we don't depend on flarum/common/extend resolving the
// same way on both majors.
function extendMethod(proto, method, callback) {
  const original = proto[method];
  proto[method] = function (...args) {
    const value = original.apply(this, args);
    callback.call(this, value, ...args);
    return value;
  };
}

window.app.initializers.add(EXT_ID, () => {
  onCoreModule('forum/components/PostStream', (PostStream) => {
    if (!PostStream || !PostStream.prototype) return;
    // Never wrap view twice (a re-fired module callback would otherwise
    // duplicate every banner).
    if (PostStream.prototype._lrBannersPatched) return;
    PostStream.prototype._lrBannersPatched = true;

    extendMethod(PostStream.prototype, 'view', function (vnode) {
      if (!vnode || !Array.isArray(vnode.children)) return;

      // Set in PostStream's oninit on both majors; the attrs are the fallback.
      const discussion = this.discussion || (this.attrs && this.attrs.discussion);
      const all = bannersFor(discussion);
      if (!all.length) return;

      const tops = all.filter((b) => b.placement === 'top');
      const bottoms = all.filter((b) => b.placement === 'bottom');
      const streams = all.filter((b) => b.placement === 'stream');

      // Total number of stream entries, for anchoring bottom banners to the
      // discussion's ABSOLUTE last post (the stream only renders a window of a
      // long discussion at a time).
      const state = this.stream || (this.attrs && this.attrs.stream);
      const total = state && typeof state.count === 'function' ? state.count() : null;

      const children = vnode.children;
      const out = [];

      for (let i = 0; i < children.length; i++) {
        const child = children[i];

        // Only anchor to REAL posts: the reply placeholder is also a
        // .PostStream-item with a data-index but no data-number, and without
        // this guard a banner could land below the composer.
        const isPost = child && child.attrs && child.attrs['data-index'] !== undefined && child.attrs['data-number'] !== undefined;
        if (!isPost) {
          out.push(child);
          continue;
        }

        const index = Number(child.attrs['data-index']);

        if (index === 0) tops.forEach((b) => out.push(bannerItem(b.id + '-top', b, 'top')));

        out.push(child);

        streams.forEach((b) => {
          // Suppressed on the last post so it never stacks with a bottom banner.
          if ((index + 1) % b.every === 0 && (total === null || index < total - 1)) {
            out.push(bannerItem(b.id + '-' + index, b, 'stream'));
          }
        });

        if (total !== null && index === total - 1) bottoms.forEach((b) => out.push(bannerItem(b.id + '-bottom', b, 'bottom')));
      }

      // Replace in place: the caller keeps the vnode we were handed.
      children.splice(0, children.length, ...out);
    });
  });
});
