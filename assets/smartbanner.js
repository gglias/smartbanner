(function () {
	'use strict';

	// The real banner is printed in the footer, so it is the last match. Anything a post author may have
	// pasted into the content with the same id comes earlier and is ignored.
	var all = document.querySelectorAll('aside#smartbanner.smb');
	var el = all[all.length - 1];
	if (!el) return;

	// One cookie per language, so closing the English banner doesn't hide the Greek one.
	// Only values in the exact shape the plugin prints are accepted; anything else and the script does nothing.
	var COOKIE = el.getAttribute('data-cookie') || '';
	var key = el.getAttribute('data-key') || '';
	if (!/^smartbanner_closed_[a-z0-9_-]{1,40}$/.test(COOKIE) || !/^[a-f0-9]{12}$/.test(key)) return;
	var hours = Math.min(8760, Math.max(0, parseInt(el.getAttribute('data-hours'), 10) || 0));
	var delay = Math.min(120, Math.max(0, parseFloat(el.getAttribute('data-delay')) || 0));
	var preview = el.hasAttribute('data-preview');

	// Themes (Ohio and others) often wrap the whole page in a container with a transform, filter or
	// will-change for smooth scrolling and page transitions. Inside such a container position:fixed is
	// fixed to the container, not the screen: the banner scrolls away with the page and can overflow on
	// phones. Moving it to the end of <body> (or <html> if even <body> is affected) takes it out of there.
	function breaksFixed(node) {
		var cs = window.getComputedStyle(node);
		return (cs.transform && cs.transform !== 'none') ||
			(cs.filter && cs.filter !== 'none') ||
			(cs.perspective && cs.perspective !== 'none') ||
			(cs.backdropFilter && cs.backdropFilter !== 'none') ||
			/paint|layout|strict|content/.test(cs.contain || '') ||
			/transform|filter|perspective/.test(cs.willChange || '');
	}
	var host = breaksFixed(document.body) ? document.documentElement : document.body;
	if (el.parentNode !== host) host.appendChild(el);

	function readCookie() {
		var parts = document.cookie ? document.cookie.split(/;\s*/) : [];
		for (var i = 0; i < parts.length; i++) {
			var eq = parts[i].indexOf('=');
			if (eq > 0 && parts[i].slice(0, eq) === COOKIE) {
				try {
					return decodeURIComponent(parts[i].slice(eq + 1));
				} catch (e) {
					return '';
				}
			}
		}
		return '';
	}

	function nowSec() {
		return Math.floor(Date.now() / 1000);
	}

	// The cookie holds "<key>.<unix time it was closed>". The time is what makes a changed setting apply to
	// visitors who already closed the banner: going from 30 days to 1 hour must not leave a 30-day cookie behind.
	function writeCookie(closedAt, maxAge) {
		var c = COOKIE + '=' + key + '.' + closedAt + '; path=/; SameSite=Lax';
		if (maxAge === 0) {
			c = COOKIE + '=; path=/; SameSite=Lax; max-age=0';
		} else if (maxAge > 0) {
			c += '; max-age=' + maxAge; // no max-age = session cookie
		}
		if (location.protocol === 'https:') c += '; Secure';
		document.cookie = c;
	}

	/** True if this browser closed this banner and the current setting says it should stay closed. */
	function stillClosed() {
		var m = /^([a-f0-9]{12})(?:\.(\d{1,11}))?$/.exec(readCookie());
		if (!m || m[1] !== key) return false;
		var now = nowSec();
		// Cookies from 1.3.4 and earlier have no time: count them as closed now, under the current setting.
		var closedAt = m[2] ? parseInt(m[2], 10) : now;
		if (closedAt > now) closedAt = now;
		if (hours === 0) {
			writeCookie(closedAt); // turn a leftover long-lived cookie into a session cookie
			return true;
		}
		var left = closedAt + hours * 3600 - now;
		if (left <= 0) {
			writeCookie(closedAt, 0);
			return false;
		}
		writeCookie(closedAt, left); // expiry follows the setting, whether it got shorter or longer
		return true;
	}

	// Checked in the browser (not in PHP) so it keeps working behind page caching.
	if (!preview && stillClosed()) {
		el.parentNode.removeChild(el);
		return;
	}

	function remember() {
		writeCookie(nowSec(), hours > 0 ? hours * 3600 : undefined);
		if (preview) endPreview();
	}

	// A preview (?smartbanner_preview=1) ignores the cookie so the admin always sees the banner. Once they close
	// it, the address loses the parameter, so a refresh behaves like a normal visit and the banner stays closed.
	function endPreview() {
		preview = false;
		try {
			var url = new URL(location.href);
			if (!url.searchParams.has('smartbanner_preview')) return;
			url.searchParams.delete('smartbanner_preview');
			history.replaceState(history.state, '', url.pathname + url.search + url.hash);
		} catch (e) {}
	}

	function show() {
		el.hidden = false;
		requestAnimationFrame(function () {
			requestAnimationFrame(function () {
				el.classList.add('is-visible');
			});
		});
	}

	function hide() {
		remember();
		el.classList.remove('is-visible');
		var done = false;
		function finish() {
			if (done) return;
			done = true;
			el.hidden = true;
		}
		el.addEventListener('transitionend', finish, { once: true });
		setTimeout(finish, 450);
	}

	var close = el.querySelector('.smb__close');
	if (close) close.addEventListener('click', hide);

	// Clicking through counts as "seen", so it doesn't come back on the next page.
	Array.prototype.forEach.call(el.querySelectorAll('a[href]'), function (a) {
		a.addEventListener('click', remember);
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !el.hidden && el.contains(document.activeElement)) hide();
	});

	setTimeout(show, delay * 1000);
})();
