/**
 * BLKRM Client Portal — client script (no build step, no dependencies).
 *
 * - Light / dark toggle (saved per browser and to the user's account).
 * - Resumable chunked uploads: files are sliced into chunks (8 MB by default),
 *   each sent with its SHA-256, retried with backoff, two at a time. If the
 *   page closes or the connection drops, re-adding the same file resumes from
 *   the chunks the server already has.
 * - Downloads: asks the server for a 15-minute signed link, then follows it.
 */
(function () {
	'use strict';

	var cfg = window.BLKRM_PORTAL || {};
	var t = cfg.i18n || {};

	function api(method, path, body, headers) {
		var opts = {
			method: method,
			credentials: 'same-origin',
			headers: Object.assign({ 'X-WP-Nonce': cfg.nonce }, headers || {})
		};
		if (body !== undefined) {
			if (body instanceof Blob || body instanceof ArrayBuffer) {
				opts.headers['Content-Type'] = 'application/octet-stream';
				opts.body = body;
			} else {
				opts.headers['Content-Type'] = 'application/json';
				opts.body = JSON.stringify(body);
			}
		}
		return fetch(cfg.restUrl + path, opts).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (data) {
				if (!res.ok) {
					var err = new Error((data && data.message) || ('HTTP ' + res.status));
					err.status = res.status;
					err.data = data;
					throw err;
				}
				return data;
			});
		});
	}

	/* ---------- Theme ---------- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-blkrm-theme-toggle]');
		if (!btn) { return; }
		var root = document.documentElement;
		var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
		root.setAttribute('data-theme', next);
		try { localStorage.setItem('blkrm-theme', next); } catch (x) {}
		if (cfg.restUrl) { api('POST', 'me/preferences', { theme: next }).catch(function () {}); }
	});

	/* ---------- Downloads ---------- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-blkrm-download]');
		if (!btn) { return; }
		btn.disabled = true;
		api('POST', 'files/' + btn.getAttribute('data-blkrm-download') + '/download-link', {})
			.then(function (res) { window.location.assign(res.url); })
			.catch(function (err) { window.alert(err.message); })
			.then(function () { setTimeout(function () { btn.disabled = false; }, 1500); });
	});

	/* ---------- Uploads ---------- */
	function sha256Hex(buffer) {
		if (!window.crypto || !window.crypto.subtle) { return Promise.resolve(''); }
		return window.crypto.subtle.digest('SHA-256', buffer).then(function (hash) {
			return Array.prototype.map.call(new Uint8Array(hash), function (b) {
				return ('0' + b.toString(16)).slice(-2);
			}).join('');
		});
	}

	function fmtBytes(n) {
		var u = ['B', 'KB', 'MB', 'GB'], i = 0;
		while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
		return (i ? n.toFixed(1) : n) + ' ' + u[i];
	}

	function storeKey(projectId, file) {
		return 'blkrm-upload:' + projectId + ':' + file.name + ':' + file.size + ':' + file.lastModified;
	}

	function Upload(file, projectId, listEl) {
		this.file = file;
		this.projectId = projectId;
		this.paused = false;
		this.cancelled = false;
		this.done = {};
		this.inFlight = 0;
		this.render(listEl);
	}

	Upload.prototype.render = function (listEl) {
		var li = document.createElement('li');
		li.className = 'upload';
		var row = document.createElement('div');
		row.className = 'upload__row';
		var name = document.createElement('span');
		name.className = 'upload__name';
		name.textContent = this.file.name;
		this.statusEl = document.createElement('span');
		this.statusEl.className = 'upload__status';
		this.pauseBtn = document.createElement('button');
		this.pauseBtn.type = 'button';
		this.pauseBtn.className = 'btn btn--ghost';
		this.pauseBtn.textContent = t.pause || 'Pause';
		this.cancelBtn = document.createElement('button');
		this.cancelBtn.type = 'button';
		this.cancelBtn.className = 'btn btn--ghost';
		this.cancelBtn.textContent = t.cancel || 'Cancel';
		row.appendChild(name);
		row.appendChild(this.statusEl);
		row.appendChild(this.pauseBtn);
		row.appendChild(this.cancelBtn);
		var bar = document.createElement('div');
		bar.className = 'bar';
		this.fill = document.createElement('div');
		this.fill.className = 'bar__fill';
		bar.appendChild(this.fill);
		li.appendChild(row);
		li.appendChild(bar);
		listEl.appendChild(li);
		this.el = li;

		var self = this;
		this.pauseBtn.addEventListener('click', function () { self.togglePause(); });
		this.cancelBtn.addEventListener('click', function () { self.cancel(); });
	};

	Upload.prototype.setStatus = function (text, cls) {
		this.statusEl.textContent = text;
		this.statusEl.className = 'upload__status' + (cls ? ' ' + cls : '');
	};

	Upload.prototype.progress = function () {
		var doneCount = Object.keys(this.done).length;
		var pct = this.total ? Math.round(doneCount / this.total * 100) : 0;
		var bytes = Math.min(this.file.size, doneCount * this.chunkSize);
		this.fill.style.width = pct + '%';
		this.setStatus((t.uploading || 'Uploading') + ' · ' + fmtBytes(bytes) + ' / ' + fmtBytes(this.file.size) + ' · ' + pct + '%');
	};

	Upload.prototype.start = function () {
		var self = this;
		if (cfg.maxBytes && this.file.size > cfg.maxBytes) {
			this.fail(new Error(t.tooLarge || 'Too large'));
			return;
		}
		var key = storeKey(this.projectId, this.file);
		var saved = null;
		try { saved = localStorage.getItem(key); } catch (x) {}

		var begin = saved
			? api('GET', 'uploads/' + saved).catch(function () { return null; })
			: Promise.resolve(null);

		begin.then(function (status) {
			if (status && status.upload_id) { return status; }
			return api('POST', 'uploads', { project_id: Number(self.projectId), name: self.file.name, size: self.file.size });
		}).then(function (session) {
			self.id = session.upload_id;
			self.chunkSize = session.chunk_size;
			self.total = session.total_chunks;
			(session.received || []).forEach(function (i) { self.done[i] = true; });
			try { localStorage.setItem(key, self.id); } catch (x) {}
			self.storageKey = key;
			self.queue = [];
			for (var i = 0; i < self.total; i++) {
				if (!self.done[i]) { self.queue.push(i); }
			}
			self.progress();
			self.pump();
		}).catch(function (err) { self.fail(err); });
	};

	Upload.prototype.pump = function () {
		var self = this;
		if (this.paused || this.cancelled || this.failed) { return; }
		while (this.inFlight < 2 && this.queue.length) {
			this.sendChunk(this.queue.shift(), 0);
		}
		if (!this.inFlight && !this.queue.length && Object.keys(this.done).length === this.total) {
			this.finish();
		}
	};

	Upload.prototype.sendChunk = function (index, attempt) {
		var self = this;
		this.inFlight++;
		var start = index * this.chunkSize;
		var blob = this.file.slice(start, Math.min(start + this.chunkSize, this.file.size));
		blob.arrayBuffer().then(function (buf) {
			return sha256Hex(buf).then(function (hex) {
				return api('PUT', 'uploads/' + self.id + '/chunks/' + index, buf, hex ? { 'X-Chunk-SHA256': hex } : {});
			});
		}).then(function () {
			self.inFlight--;
			self.done[index] = true;
			self.progress();
			self.pump();
		}).catch(function (err) {
			self.inFlight--;
			var permanent = err.status && err.status >= 400 && err.status < 500 && err.status !== 408 && err.status !== 429 && err.data && err.data.code !== 'blkrm_chunk_checksum' && err.data.code !== 'blkrm_chunk_length';
			if (permanent || attempt >= 4) {
				self.fail(err);
				return;
			}
			// Back off 1s, 2s, 4s, 8s, then try this chunk again.
			setTimeout(function () {
				if (self.cancelled || self.failed) { return; }
				if (self.paused) { self.queue.unshift(index); return; }
				self.sendChunk(index, attempt + 1);
			}, Math.pow(2, attempt) * 1000);
		});
	};

	Upload.prototype.finish = function () {
		var self = this;
		if (this.finishing) { return; }
		this.finishing = true;
		this.pauseBtn.hidden = true;
		this.cancelBtn.hidden = true;
		api('POST', 'uploads/' + this.id + '/complete', {}).then(function () {
			try { localStorage.removeItem(self.storageKey); } catch (x) {}
			self.fill.style.width = '100%';
			self.setStatus((t.done || 'Uploaded') + ' · ' + fmtBytes(self.file.size), 'is-done');
			document.dispatchEvent(new CustomEvent('blkrm:uploaded'));
		}).catch(function (err) {
			self.finishing = false;
			self.fail(err);
		});
	};

	Upload.prototype.togglePause = function () {
		this.paused = !this.paused;
		this.el.classList.toggle('is-paused', this.paused);
		this.pauseBtn.textContent = this.paused ? (t.resume || 'Resume') : (t.pause || 'Pause');
		if (this.paused) {
			this.setStatus(t.paused || 'Paused');
		} else {
			this.progress();
			this.pump();
		}
	};

	Upload.prototype.cancel = function () {
		this.cancelled = true;
		if (this.id) { api('DELETE', 'uploads/' + this.id).catch(function () {}); }
		try { localStorage.removeItem(this.storageKey); } catch (x) {}
		this.el.remove();
	};

	Upload.prototype.fail = function (err) {
		this.failed = true;
		this.pauseBtn.hidden = true;
		this.setStatus((t.failed || 'Upload failed') + ': ' + err.message, 'is-error');
	};

	function initUploader(root) {
		var input = root.querySelector('[data-blkrm-file-input]');
		var list = root.querySelector('[data-blkrm-upload-list]');
		var zone = root.querySelector('.dropzone');
		var projectId = root.getAttribute('data-project-id');
		var pending = 0;

		function add(files) {
			Array.prototype.forEach.call(files, function (f) {
				pending++;
				new Upload(f, projectId, list).start();
			});
		}

		input.addEventListener('change', function () { add(input.files); input.value = ''; });
		['dragenter', 'dragover'].forEach(function (ev) {
			zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
		});
		['dragleave', 'drop'].forEach(function (ev) {
			zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-over'); });
		});
		zone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) { add(e.dataTransfer.files); } });

		// Refresh the file list once every upload in this batch has finished.
		document.addEventListener('blkrm:uploaded', function () {
			pending--;
			if (pending <= 0 && !root.querySelector('.upload__status:not(.is-done)')) {
				setTimeout(function () { window.location.reload(); }, 900);
			}
		});

		window.addEventListener('beforeunload', function (e) {
			if (root.querySelector('.upload__status:not(.is-done):not(.is-error)')) {
				e.preventDefault();
				e.returnValue = '';
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-blkrm-uploader]'), initUploader);
	});
})();
