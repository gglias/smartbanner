(function () {
	'use strict';

	const boot = window.smbAdmin;
	const form = document.getElementById('smb-form');
	if (!boot || !form) return;

	const t = boot.i18n;
	const $ = (sel, ctx) => (ctx || document).querySelector(sel);
	const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));
	const cssUrl = (u) => 'url("' + String(u).replace(/["\\\n]/g, encodeURIComponent) + '")';
	const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
	// Greek slugs arrive percent-encoded; show them the way the browser bar does.
	const readableUrl = (u) => { try { return decodeURI(u); } catch (e) { return u; } };

	/* ---------- Form access ---------- */

	const shared = (n) => form.elements['smartbanner_settings[' + n + ']'];
	const sval = (n) => { const f = shared(n); return f ? String(f.value) : ''; };
	const son = (n) => { const f = shared(n); return !!(f && f.checked); };
	const lfield = (lang, n) => form.elements['smartbanner_settings[content][' + lang + '][' + n + ']'];
	const lval = (lang, n) => { const f = lfield(lang, n); return f ? String(f.value) : ''; };
	// Sites without WPML have no per-language switch: always on.
	const lon = (lang, n) => { const f = lfield(lang, n); return f ? f.checked : true; };

	const langs = boot.languages;
	const langInfo = {};
	langs.forEach((l) => { langInfo[l.code] = l; });
	let activeLang = langs[0].code;
	const panel = (lang) => $('.smb-langpanel[data-lang="' + CSS.escape(lang) + '"]');

	const posts = {};
	const order = {};
	langs.forEach((l) => {
		posts[l.code] = {};
		order[l.code] = (boot.posts[l.code] || []).map((p) => { posts[l.code][p.id] = p; return p.id; });
	});
	const images = Object.assign({}, boot.images);
	const autoPicked = {}; // lang => post id we filled in as a translation

	const currentPost = (lang) => (lval(lang, 'source') === 'post' ? posts[lang][parseInt(lval(lang, 'post_id'), 10)] || null : null);

	/** What the banner will actually show in a language: own fields first, then the post's values. */
	function content(lang) {
		const post = currentPost(lang);
		const title = lval(lang, 'title').trim() || (post ? post.title : '');
		return {
			active: lon(lang, 'active'),
			post,
			title,
			label: lval(lang, 'label').trim(),
			text: lval(lang, 'text').trim() || (post ? post.excerpt : ''),
			img: parseInt(lval(lang, 'image_id'), 10) ? images[lang] : (post ? post.thumb : ''),
			btn: lval(lang, 'button_text').trim(),
			hasUrl: !!(lval(lang, 'button_url').trim() || post),
			valid: !!title && (lval(lang, 'source') === 'custom' || !!post),
		};
	}
	const isLive = (lang) => { const c = content(lang); return c.active && c.valid; };

	/* ---------- Post dropdown ---------- */

	const CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

	function closeDropdowns(except) {
		$$('.smb-dd').forEach((dd) => { if (dd !== except && dd._close) dd._close(false); });
	}
	document.addEventListener('mousedown', (e) => {
		$$('.smb-dd').forEach((dd) => { if (!dd.contains(e.target) && dd._close) dd._close(false); });
	});

	function initDropdown(lang) {
		const root = $('.smb-dd', panel(lang));
		const trigger = $('.smb-dd__trigger', root);
		const list = $('.smb-dd__panel', root);
		const ids = order[lang];

		let month = null;
		ids.forEach((id) => {
			const p = posts[lang][id];
			if (p.month !== month) {
				month = p.month;
				const g = document.createElement('div');
				g.className = 'smb-dd__group';
				g.setAttribute('role', 'presentation');
				g.textContent = month;
				list.appendChild(g);
			}
			const o = document.createElement('div');
			o.className = 'smb-dd__opt';
			o.id = 'smb-opt-' + lang + '-' + id;
			o.dataset.id = id;
			o.setAttribute('role', 'option');
			o.setAttribute('aria-selected', 'false');

			const th = document.createElement('span');
			th.className = 'smb-dd__thumb';
			if (p.thumb) th.style.backgroundImage = cssUrl(p.thumb);
			else th.innerHTML = boot.icons.image;
			const text = document.createElement('span');
			text.className = 'smb-dd__text';
			const title = document.createElement('span');
			title.className = 'smb-dd__title';
			title.textContent = p.title;
			const meta = document.createElement('span');
			meta.className = 'smb-dd__meta';
			meta.textContent = p.date;
			text.append(title, meta);
			const check = document.createElement('span');
			check.className = 'smb-dd__check';
			check.innerHTML = CHECK;
			o.append(th, text, check);
			list.appendChild(o);
		});
		trigger.disabled = !ids.length;

		const opts = () => $$('.smb-dd__opt', list);
		let active = null;
		function setActive(o, scroll) {
			if (active) active.classList.remove('is-active');
			active = o || null;
			if (!active) return;
			active.classList.add('is-active');
			list.setAttribute('aria-activedescendant', active.id);
			if (scroll) active.scrollIntoView({ block: 'nearest' });
		}
		// At most 380px tall (about six posts), less on short screens; opens upwards when there is no room below.
		function place() {
			const r = trigger.getBoundingClientRect();
			const below = window.innerHeight - r.bottom - 16;
			const above = r.top - 16 - 60; // keep clear of the admin bar and the sticky header
			const up = below < 240 && above > below;
			root.classList.toggle('is-up', up);
			list.style.maxHeight = Math.max(160, Math.min(380, (up ? above : below) - 8)) + 'px';
		}
		function open() {
			if (trigger.disabled) return;
			closeDropdowns(root);
			place();
			list.hidden = false;
			root.classList.add('is-open');
			trigger.setAttribute('aria-expanded', 'true');
			setActive($('.smb-dd__opt[aria-selected="true"]', list) || opts()[0], true);
			list.focus({ preventScroll: true });
		}
		function close(focusTrigger) {
			if (list.hidden) return;
			list.hidden = true;
			root.classList.remove('is-open');
			trigger.setAttribute('aria-expanded', 'false');
			if (focusTrigger) trigger.focus();
		}
		function choose(o) {
			selectPost(lang, parseInt(o.dataset.id, 10), true);
			close(true);
		}
		root._close = close;

		trigger.addEventListener('click', () => (list.hidden ? open() : close(false)));
		trigger.addEventListener('keydown', (e) => {
			if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(e.key) !== -1) {
				e.preventDefault();
				open();
			}
		});
		list.addEventListener('keydown', (e) => {
			const all = opts();
			const i = all.indexOf(active);
			const go = (o) => { e.preventDefault(); setActive(o, true); };
			if (e.key === 'ArrowDown') go(all[Math.min(all.length - 1, i + 1)]);
			else if (e.key === 'ArrowUp') go(all[Math.max(0, i - 1)]);
			else if (e.key === 'Home') go(all[0]);
			else if (e.key === 'End') go(all[all.length - 1]);
			else if (e.key === 'PageDown') go(all[Math.min(all.length - 1, i + 6)]);
			else if (e.key === 'PageUp') go(all[Math.max(0, i - 6)]);
			else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (active) choose(active); }
			else if (e.key === 'Escape') { e.preventDefault(); close(true); }
			else if (e.key === 'Tab') close(false);
		});
		list.addEventListener('click', (e) => { const o = e.target.closest('.smb-dd__opt'); if (o) choose(o); });
		list.addEventListener('mousemove', (e) => { const o = e.target.closest('.smb-dd__opt'); if (o && o !== active) setActive(o, false); });
	}

	function renderDropdown(lang) {
		const P = panel(lang);
		const root = $('.smb-dd', P);
		const id = parseInt(lval(lang, 'post_id'), 10);
		const p = posts[lang][id];
		$$('.smb-dd__opt', root).forEach((o) => o.setAttribute('aria-selected', String(parseInt(o.dataset.id, 10) === id)));

		const th = $('.smb-dd__trigger .smb-dd__thumb', root);
		th.style.backgroundImage = p && p.thumb ? cssUrl(p.thumb) : '';
		th.innerHTML = p && p.thumb ? '' : boot.icons.image;
		const count = order[lang].length;
		$('.smb-dd__trigger .smb-dd__title', root).textContent = p ? p.title : (count ? t.pick_post : t.no_posts);
		$('.smb-dd__trigger .smb-dd__meta', root).textContent = p ? p.date : (count ? t.posts_count.replace('%d', count) : '');
		root.classList.toggle('is-empty', !p);

		const note = $('.smb-autotr', P);
		note.hidden = !(p && autoPicked[lang] === id);
		note.textContent = t.auto_tr_note;
	}

	function selectPost(lang, id, byUser) {
		const prev = posts[lang][parseInt(lval(lang, 'post_id'), 10)];
		lfield(lang, 'post_id').value = id;
		if (byUser) {
			delete autoPicked[lang];
			const p = posts[lang][id];
			const filled = [];
			langs.forEach((l) => {
				if (l.code === lang) return;
				const tid = p && p.tr && p.tr[l.code];
				if (!tid || !posts[l.code][tid]) return;
				const cur = parseInt(lval(l.code, 'post_id'), 10);
				const prevTr = prev && prev.tr && prev.tr[l.code];
				// Only touch languages that are empty or still follow the previous choice.
				if (!cur || autoPicked[l.code] === cur || prevTr === cur) {
					lfield(l.code, 'post_id').value = tid;
					autoPicked[l.code] = tid;
					if (cur !== tid) filled.push(l.name);
				}
			});
			if (filled.length) toast(t.auto_tr.replace('%s', filled.join(', ')));
		}
		changed();
	}

	/* ---------- Image ---------- */

	function initImage(lang) {
		const box = $('.smb-image', panel(lang));
		let frame;
		$('.smb-image-choose', box).addEventListener('click', () => {
			if (!window.wp || !wp.media) return;
			if (!frame) {
				frame = wp.media({ title: t.img_choose, button: { text: t.img_use }, library: { type: 'image' }, multiple: false });
				frame.on('select', () => {
					const a = frame.state().get('selection').first().toJSON();
					const size = a.sizes && (a.sizes.medium || a.sizes.thumbnail);
					lfield(lang, 'image_id').value = a.id;
					images[lang] = size ? size.url : a.url;
					changed();
				});
			}
			frame.open();
		});
		$('.smb-image-remove', box).addEventListener('click', () => {
			lfield(lang, 'image_id').value = '0';
			images[lang] = '';
			changed();
		});
	}

	function renderImage(lang) {
		const box = $('.smb-image', panel(lang));
		const custom = parseInt(lval(lang, 'image_id'), 10) > 0;
		const post = currentPost(lang);
		let url = '';
		let title = t.img_none;
		let sub = t.img_none_sub;
		if (custom) {
			url = images[lang];
			title = t.img_custom;
			sub = post ? t.img_custom_sub : '';
		} else if (post && post.thumb) {
			url = post.thumb;
			title = t.img_post;
			sub = t.img_post_sub;
		}
		const thumb = $('.smb-image__thumb', box);
		thumb.style.backgroundImage = url ? cssUrl(url) : '';
		thumb.classList.toggle('has-img', !!url);
		$('.smb-image__title', box).textContent = title;
		$('.smb-image__sub', box).textContent = sub;
		$('.smb-image-choose', box).textContent = url ? t.img_change : t.img_choose;
		const rm = $('.smb-image-remove', box);
		rm.hidden = !custom;
		rm.textContent = post ? t.img_use_post : t.img_remove;
	}

	/* ---------- Per-language panel ---------- */

	function renderPanel(lang) {
		const P = panel(lang);
		const on = lon(lang, 'active');
		$$('.smb-lang-body', P).forEach((el) => { el.hidden = !on; });
		$('.smb-lang-off', P).hidden = on;

		const isPost = lval(lang, 'source') === 'post';
		$$('[data-show-when]', P).forEach((el) => { el.hidden = el.dataset.showWhen !== (isPost ? 'post' : 'custom'); });
		const p = currentPost(lang);
		// The placeholder shows exactly what will be used if the field stays empty.
		lfield(lang, 'title').placeholder = p ? p.title : t.ph_title_custom;
		lfield(lang, 'text').placeholder = p ? p.excerpt : t.ph_text_custom;
		lfield(lang, 'button_url').placeholder = p ? readableUrl(p.link) : 'https://';
		$('.smb-content-sub', P).textContent = isPost ? t.content_sub_post : t.content_sub_custom;

		const n = lval(lang, 'text').length;
		const counter = $('.smb-counter', P);
		counter.textContent = n ? n + ' / 140' : '';
		counter.classList.toggle('is-over', n > 140);

		renderDropdown(lang);
		renderImage(lang);
	}

	/* ---------- Language tabs ---------- */

	const tabs = $$('[data-lang-tab]');
	function renderTabs() {
		tabs.forEach((b) => {
			const l = b.dataset.langTab;
			const selected = l === activeLang;
			b.setAttribute('aria-selected', String(selected));
			b.tabIndex = selected ? 0 : -1;
			$('.smb-langtab__dot', b).dataset.state = !lon(l, 'active') ? 'off' : (content(l).valid ? 'live' : 'warn');
		});
		$$('.smb-langpanel').forEach((p) => { p.hidden = p.dataset.lang !== activeLang; });
	}
	tabs.forEach((b, i) => {
		b.addEventListener('click', () => {
			activeLang = b.dataset.langTab;
			closeDropdowns();
			renderAll();
		});
		b.addEventListener('keydown', (e) => {
			const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
			if (!d) return;
			e.preventDefault();
			const next = tabs[(i + d + tabs.length) % tabs.length];
			next.focus();
			next.click();
		});
	});

	/* ---------- Status ---------- */

	const pill = $('#smb-pill');
	const statusCard = $('#smb-status');
	function renderStatus() {
		const enabled = son('enabled');
		const live = langs.filter((l) => isLive(l.code));
		const state = !enabled ? 'off' : (live.length ? 'live' : 'warn');
		pill.dataset.state = state;
		pill.textContent = t['pill_' + state];
		statusCard.classList.toggle('is-live', state === 'live');
		statusCard.classList.toggle('is-warn', state === 'warn');
		$('#smb-status-text').textContent = state === 'live' && boot.multilingual
			? t.status_live_langs.replace('%s', live.map((l) => l.name).join(', '))
			: t['status_' + state];
		form.classList.toggle('is-off', !enabled);
	}

	/* ---------- Live preview ---------- */

	const stage = $('#smb-stage');
	const pv = $('#smb-preview');
	const num = (name, min, max) => { const n = parseInt(sval(name), 10); return isNaN(n) ? 0 : Math.max(min, Math.min(max, n)); };

	const browser = $('.smb-browser', stage);
	const page = $('.smb-browser__page', stage);
	const viewport = $('#smb-viewport');

	/**
	 * Lays the mock page out at a real screen width and scales it down to the space available,
	 * so the banner keeps its true size relative to the page instead of being squeezed.
	 */
	function fitStage() {
		const cs = getComputedStyle(stage);
		const avail = stage.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
		if (avail <= 0) return;
		const mobile = stage.dataset.device === 'mobile';
		const width = mobile ? 375 : Math.max(390 + 2 * num('offset_x', 0, 300) + 60, avail);
		const height = mobile ? 667 : 440;
		const scale = Math.min(1, avail / width);
		browser.style.width = (mobile ? width * scale : avail) + 'px';
		viewport.style.width = width + 'px';
		viewport.style.height = height + 'px';
		viewport.style.transform = 'scale(' + scale + ')';
		page.style.height = Math.round(height * scale) + 'px';
	}
	if (window.ResizeObserver) new ResizeObserver(fitStage).observe(stage);

	function renderPreview() {
		fitStage();
		const c = content(activeLang);
		const info = langInfo[activeLang];
		const hiddenOnMobile = stage.dataset.device === 'mobile' && !son('show_mobile');
		const msg = !c.active ? t.lang_off_preview : !c.valid ? t.preview_empty : hiddenOnMobile ? t.hidden_mobile : '';

		$('#smb-preview-address').textContent = info.address;
		$('#smb-preview-lang').textContent = boot.multilingual ? info.name : '';
		if (/^https?:\/\//i.test(info.previewUrl)) $('#smb-preview-link').href = info.previewUrl;
		$('#smb-preview-msg').textContent = msg;
		$('#smb-preview-msg').hidden = !msg;
		pv.hidden = !!msg;

		pv.className = 'smb smb--' + (sval('position') === 'left' ? 'left' : 'right') +
			(c.img ? '' : ' smb--no-img') + (son('hide_image_mobile') ? ' smb--no-img-mobile' : '');
		pv.style.setProperty('--smb-x', num('offset_x', 0, 300) + 'px');
		pv.style.setProperty('--smb-y', num('offset_y', 0, 300) + 'px');
		if (HEX.test(sval('button_bg'))) pv.style.setProperty('--smb-btn-bg', sval('button_bg'));
		if (HEX.test(sval('button_color'))) pv.style.setProperty('--smb-btn-fg', sval('button_color'));

		$('.smb__label', pv).hidden = !c.label;
		$('.smb__label span', pv).textContent = c.label;
		$('.smb__title', pv).textContent = c.title;
		$('.smb__text', pv).hidden = !c.text;
		$('.smb__text', pv).textContent = c.text;
		$('.smb__btn', pv).hidden = !(c.btn && c.hasUrl);
		$('.smb__btn', pv).textContent = c.btn;
		$('.smb__media', pv).hidden = !c.img;
		if (c.img) $('.smb__img', pv).src = c.img;
	}

	$$('[data-device-btn]').forEach((b) => b.addEventListener('click', () => {
		stage.dataset.device = b.dataset.deviceBtn;
		$$('[data-device-btn]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
		renderPreview();
	}));

	/* ---------- Small shared controls ---------- */

	$$('.smb-stepper button').forEach((b) => b.addEventListener('click', () => {
		const input = b.parentNode.querySelector('input');
		const next = (parseFloat(input.value) || 0) + parseFloat(b.dataset.step);
		input.value = Math.max(Number(input.min), Math.min(Number(input.max), next));
		changed();
	}));

	$$('.smb-color').forEach((box) => {
		const text = $('input[type="text"]', box);
		const pick = $('input[type="color"]', box);
		const sync = (raw, from) => {
			let hex = String(raw).trim();
			if (!HEX.test(hex)) return;
			if (hex.length === 4) hex = '#' + hex.slice(1).split('').map((ch) => ch + ch).join('');
			hex = hex.toLowerCase();
			if (from !== 'text') text.value = hex;
			pick.value = hex;
			$$('.smb-swatch', box).forEach((s) => s.setAttribute('aria-pressed', String(s.dataset.color.toLowerCase() === hex)));
		};
		pick.addEventListener('input', () => { sync(pick.value, 'pick'); changed(); });
		text.addEventListener('input', () => { sync(text.value, 'text'); });
		box.addEventListener('click', (e) => {
			const s = e.target.closest('.smb-swatch');
			if (!s) return;
			sync(s.dataset.color, 'swatch');
			changed();
		});
		sync(text.value, 'init');
	});

	function luminance(hex) {
		let h = hex.slice(1);
		if (h.length === 3) h = h.split('').map((ch) => ch + ch).join('');
		const [r, g, b] = [0, 2, 4].map((i) => {
			const c = parseInt(h.substr(i, 2), 16) / 255;
			return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	}
	function renderContrast() {
		const a = sval('button_bg');
		const b = sval('button_color');
		if (!HEX.test(a) || !HEX.test(b)) return;
		const l1 = luminance(a);
		const l2 = luminance(b);
		$('#smb-contrast').hidden = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05) >= 3;
	}

	/* ---------- Saving ---------- */

	const saveState = $('#smb-savestate');
	const saveBtn = $('#smb-save');
	let dirty = false;
	let saving = false;

	function setSaveState(state) {
		saveState.dataset.state = state;
		saveState.textContent = t['save_' + state];
		saveState.title = t['save_' + state];
	}

	const toastEl = $('#smb-toast');
	let toastTimer;
	function toast(msg, isError) {
		toastEl.textContent = msg;
		toastEl.classList.toggle('is-error', !!isError);
		toastEl.classList.add('is-show');
		clearTimeout(toastTimer);
		// Longer messages stay a little longer, so they can be read to the end.
		toastTimer = setTimeout(() => toastEl.classList.remove('is-show'), Math.max(2800, msg.length * 60));
	}

	function renderAll() {
		renderStatus();
		renderTabs();
		langs.forEach((l) => renderPanel(l.code));
		renderPreview();
		renderContrast();
	}

	function changed() {
		dirty = true;
		if (!saving) setSaveState('dirty');
		renderAll();
	}

	const enabledInput = shared('enabled');
	const isOwnInput = (el) => el && el.name && el.name.indexOf('smartbanner_settings[') === 0 && el !== enabledInput;
	form.addEventListener('input', (e) => { if (isOwnInput(e.target)) changed(); });
	form.addEventListener('change', (e) => { if (isOwnInput(e.target)) changed(); });

	function save(message) {
		if (saving) return Promise.resolve(false);
		saving = true;
		saveBtn.disabled = true;
		setSaveState('saving');
		const data = new FormData(form);
		data.set('action', 'smb_save');
		data.set('nonce', boot.nonce);
		const wasDirty = dirty;
		dirty = false;
		return fetch(boot.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
			.then((r) => r.json())
			.then((res) => {
				if (!res || !res.success) throw new Error(res && res.data && res.data.message);
				setSaveState(dirty ? 'dirty' : 'saved');
				toast(message || t.saved);
				return true;
			})
			.catch((err) => {
				dirty = dirty || wasDirty;
				setSaveState('dirty');
				toast((err && err.message) || t.save_error, true);
				return false;
			})
			.finally(() => {
				saving = false;
				saveBtn.disabled = false;
			});
	}

	// The main switch acts immediately, like a light switch.
	enabledInput.addEventListener('change', () => {
		renderAll();
		save(enabledInput.checked ? t.turned_on : t.turned_off);
	});

	form.addEventListener('submit', (e) => { e.preventDefault(); save(); });
	document.addEventListener('keydown', (e) => {
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
			e.preventDefault();
			save();
		}
	});
	window.addEventListener('beforeunload', (e) => {
		if (!dirty) return;
		e.preventDefault();
		e.returnValue = '';
	});

	// Preview shows saved settings, so save pending changes first.
	$('#smb-preview-link').addEventListener('click', (e) => {
		if (!dirty) return;
		e.preventDefault();
		const url = e.currentTarget.href;
		const win = window.open('', '_blank');
		if (win) win.opener = null;
		save().then((ok) => {
			if (!win) return;
			if (ok) win.location = url;
			else win.close();
		});
	});

	/* ---------- "Show it to me again": forget that this browser closed the banner ---------- */

	function forgetClosedHere() {
		// Same cookie names the banner uses (one per language); the admin and the site share the domain.
		const names = langs.map((l) => 'smartbanner_closed_' + l.code).concat('smartbanner_closed');
		const secure = location.protocol === 'https:' ? '; Secure' : '';
		names.forEach((n) => { document.cookie = n + '=; path=/; max-age=0; SameSite=Lax' + secure; });
	}

	$('#smb-show-again').addEventListener('click', () => {
		forgetClosedHere();
		toast(t.shown_again);
	});

	/* ---------- "Show it to everyone again": new banner key for all visitors + clear the page cache ---------- */

	const resetBtn = $('#smb-reset-all');
	resetBtn.addEventListener('click', () => {
		if (resetBtn.disabled || !window.confirm(t.reset_confirm)) return;
		const label = $('span', resetBtn);
		const text = label.textContent;
		resetBtn.disabled = true;
		label.textContent = t.reset_working;
		const data = new FormData();
		data.set('action', 'smb_reset_all');
		data.set('nonce', boot.nonce);
		fetch(boot.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
			.then((r) => r.json())
			.then((res) => {
				if (!res || !res.success) throw new Error('reset');
				forgetClosedHere();
				const caches = Array.isArray(res.data && res.data.caches) ? res.data.caches : [];
				toast(caches.length ? t.reset_done_caches.replace('%s', caches.join(', ')) : t.reset_done_nocache);
			})
			.catch(() => toast(t.save_error, true))
			.finally(() => {
				resetBtn.disabled = false;
				label.textContent = text;
			});
	});

	/* ---------- Screen language ---------- */

	$$('[data-ui-lang]').forEach((b) => b.addEventListener('click', () => {
		if (b.getAttribute('aria-pressed') === 'true') return;
		(dirty ? save() : Promise.resolve(true)).then((ok) => {
			if (!ok) return;
			const data = new FormData();
			data.set('action', 'smb_ui_lang');
			data.set('nonce', boot.nonce);
			data.set('lang', b.dataset.uiLang);
			fetch(boot.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
				.then((r) => r.json())
				.then((res) => {
					if (!res || !res.success) throw new Error();
					dirty = false;
					window.location.reload();
				})
				.catch(() => toast(t.save_error, true));
		});
	}));

	langs.forEach((l) => {
		initDropdown(l.code);
		initImage(l.code);
	});
	renderAll();
	setSaveState('saved');
})();
